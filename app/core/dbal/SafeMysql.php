<?php

declare(strict_types=1);

namespace app\core\dbal;

use app\core\db\Mysql;
use app\core\dbal\exceptions\DbConnectionException;
use app\core\dbal\exceptions\DbQueryException;
use app\core\dbal\exceptions\DbTransactionException;
use BadMethodCallException;
use InvalidArgumentException;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use RuntimeException;
use Throwable;

/**
 * SafeMysql is a thin database helper built for secure query execution.
 *
 * Why this class exists:
 * - The legacy Mysql class is widely used across the project and is risky to refactor directly.
 * - The legacy codebase contains many SQL queries built through string concatenation and manual escaping.
 * - This class provides a separate, safer path based on real prepared statements.
 *
 * Main idea:
 * - SQL structure and user-provided values must always be separated.
 * - SQL queries use named placeholders such as :id, :login, :status.
 * - Placeholder values are passed separately as an associative array.
 * - Internally, named placeholders are converted into positional placeholders (?) because mysqli
 *   supports positional binding only.
 *
 * Migration strategy:
 * - Keep the legacy Mysql class untouched.
 * - Use SafeMysql for all new and security-sensitive queries.
 * - Gradually replace legacy call sites module by module.
 * - By default, SafeMysql shares its connection (and therefore its transaction) with the legacy
 *   Mysql class for the same db key, so a query issued through either class participates in a
 *   transaction opened by either class - see {@see __construct()}.
 *
 * Important limitations:
 * - This class protects query values, not SQL identifiers.
 * - Table names, column names, ORDER BY fields, directions, and SQL keywords cannot be bound.
 * - Any dynamic SQL identifiers must be validated through explicit whitelists before being added to SQL.
 */
final class SafeMysql
{
    /**
     * @var string
     */
    private $dbKey;

    /**
     * @var int
     */
    private $transactionDepth = 0;

    /**
     * @var bool
     */
    private $sharedConnection;

    /**
     * @param string $dbKey Logical database key used by MysqlConnectionFactory.
     * @param bool $sharedConnection When true, use the legacy `Mysql` class's own connection
     *        (via {@see MysqlConnectionFactory::getSharedConnection()}) instead of
     *        {@see MysqlConnectionFactory}'s own pooled connection.
     *
     *        Pass this when the query needs to run on the same connection - and therefore the
     *        same transaction - as surrounding legacy `Mysql::` code. There is no nested/savepoint
     *        transaction handling: {@see beginTransaction()}/{@see commit()}/{@see rollback()}
     *        (and {@see transaction()}, which calls them) simply delegate to
     *        `Mysql::startTransaction()`/`commitTransaction()`/`rollbackTransaction()` for the
     *        same db key, so either class may open the transaction and either class may close it -
     *        both share `Mysql`'s own transaction counter.
     *
     *        Defaults to true: SafeMysql and the legacy Mysql class share one connection per db
     *        key by default, so code does not need to know or care whether it is running inside
     *        a `Mysql::startTransaction()` block. Pass false only when a query must run on its
     *        own, independent connection (e.g. read-only lookups that must not lock behind or be
     *        blocked by an open legacy transaction on the same connection).
     */
    public function __construct(string $dbKey = MysqlConnectionFactory::DB_FF, bool $sharedConnection = true)
    {
        if ($dbKey === '') {
            throw new InvalidArgumentException('DB key must be a non-empty string.');
        }

        $this->dbKey = $dbKey;
        $this->sharedConnection = $sharedConnection;
    }

    /**
     * Returns the current logical database key.
     *
     * @return string
     */
    public function getDbKey(): string
    {
        return $this->dbKey;
    }

    /**
     * Returns the first row from the result set or null if no rows were found.
     *
     * @param string $sql SQL with named placeholders, e.g. :id, :login.
     * @param array<string, mixed> $params Parameters indexed by placeholder name without leading colon.
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $rows = $this->fetchAll($sql, $params);

        return isset($rows[0]) ? $rows[0] : null;
    }

    /**
     * Returns all rows as associative arrays.
     *
     * @param string $sql SQL with named placeholders.
     * @param array<string, mixed> $params Parameters indexed by placeholder name without leading colon.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->prepareAndExecute($sql, $params);

        try {
            $result = $stmt->get_result();

            if (!$result instanceof mysqli_result) {
                throw new DbQueryException('Failed to fetch query result.');
            }

            return $result->fetch_all(MYSQLI_ASSOC);
        } finally {
            $stmt->close();
        }
    }

    /**
     * Returns all rows as numeric arrays (with integer keys).
     *
     * This is an analog to the legacy Mysql::fetchRow() method.
     *
     * @param string $sql SQL with named placeholders.
     * @param array<string, mixed> $params Parameters indexed by placeholder name without leading colon.
     *
     * @return array<int, array<int, mixed>>
     */
    public function fetchRow(string $sql, array $params = []): array
    {
        $stmt = $this->prepareAndExecute($sql, $params);

        try {
            $result = $stmt->get_result();

            if (!$result instanceof mysqli_result) {
                throw new DbQueryException('Failed to fetch query result.');
            }

            return $result->fetch_all(MYSQLI_NUM);
        } finally {
            $stmt->close();
        }
    }

    /**
     * Returns the first column of the first row, or null if the query returned no rows.
     *
     * @param string $sql SQL with named placeholders.
     * @param array<string, mixed> $params Parameters indexed by placeholder name without leading colon.
     *
     * @return mixed
     */
    public function fetchScalar(string $sql, array $params = [])
    {
        $row = $this->fetchOne($sql, $params);

        if ($row === null) {
            return null;
        }

        return reset($row);
    }

    /**
     * Executes a query and returns rows indexed by a selected field.
     *
     * Example result:
     * [
     *     10 => ['id' => 10, 'login' => 'admin'],
     *     15 => ['id' => 15, 'login' => 'editor'],
     * ]
     *
     * @param string $sql SQL with named placeholders.
     * @param array<string, mixed> $params Parameters indexed by placeholder name without leading colon.
     * @param string $keyField Row field used as array key.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function fetchAssocWithKey(string $sql, array $params, string $keyField): array
    {
        if ($keyField === '') {
            throw new InvalidArgumentException('Key field must be a non-empty string.');
        }

        $rows = $this->fetchAll($sql, $params);
        $result = [];

        foreach ($rows as $row) {
            if (!array_key_exists($keyField, $row)) {
                throw new RuntimeException(
                    sprintf('Key field "%s" was not found in query result row.', $keyField)
                );
            }

            $key = $row[$keyField];

            if (!is_int($key) && !is_string($key)) {
                throw new RuntimeException(
                    sprintf(
                        'Key field "%s" must contain only int or string values, %s given.',
                        $keyField,
                        is_object($key) ? get_class($key) : gettype($key)
                    )
                );
            }

            $result[$key] = $row;
        }

        return $result;
    }

    /**
     * Executes a query and returns a flat array of values from one field.
     *
     * If $field is null, the first column of each row is used.
     * If $field is provided, that field must exist in every row.
     *
     * Example result:
     * [10, 15, 20]
     *
     * @param string $sql SQL with named placeholders.
     * @param array<string, mixed> $params Parameters indexed by placeholder name.
     * @param string|null $field Row field to extract. If null, the first column is used.
     *
     * @return array<int, mixed>
     */
    public function fetchArrayOneField(string $sql, array $params = [], ?string $field = null): array
    {
        if ($field === '') {
            throw new InvalidArgumentException('Field must be null or a non-empty string.');
        }

        $rows = $this->fetchAll($sql, $params);
        $result = [];

        foreach ($rows as $row) {
            if ($field !== null) {
                if (!array_key_exists($field, $row)) {
                    throw new RuntimeException(
                        sprintf('Field "%s" was not found in query result row.', $field)
                    );
                }

                $result[] = $row[$field];
                continue;
            }

            if ($row === []) {
                throw new RuntimeException('Query result row is empty.');
            }

            $result[] = reset($row);
        }

        return $result;
    }

    /**
     * Returns the currently running MySQL queries (excluding this SHOW processlist query itself
     * and idle connections).
     *
     * This is an analog to the legacy Mysql::getProcessList() method.
     *
     * @return array<int, array{query: string, state: ?string, time: string}>
     */
    public function getProcessList(): array
    {
        $query = 'SHOW processlist';
        $rows = $this->fetchAll($query);
        $list = [];

        foreach ($rows as $row) {
            if (!empty($row['Info']) && $row['Info'] !== $query)
                $list[] = [
                    'query' => $row['Info'],
                    'state' => $row['State'],
                    'time' => $row['Time'],
                ];
        }

        return $list;
    }

    /**
     * Checks whether a row exists in $table matching all of the given field => value pairs.
     *
     * This is an analog to the legacy Mysql::checkUniqueField()/checkUniqueFields() methods.
     *
     * @param string $table
     * @param array<string, mixed> $fields Field name => value pairs to match, combined with AND.
     * @param string $exception Optional raw SQL condition appended with AND (e.g. "id <> 5").
     *                          Not escaped - must never contain unescaped user input.
     *
     * @return bool
     */
    public function checkUniqueFields(string $table, array $fields, string $exception = ''): bool
    {
        if (empty($fields)) {
            return false;
        }

        $where = [];
        $params = [];
        $i = 0;

        foreach ($fields as $field => $value) {
            $placeholder = "field_$i";
            $where[] = "`$field` = :$placeholder";
            $params[$placeholder] = $value;
            $i++;
        }

        $sql = "SELECT 1 FROM $table WHERE " . implode(' AND ', $where);
        if ($exception !== '') {
            $sql .= " AND $exception";
        }
        $sql .= " LIMIT 1";

        return $this->fetchScalar($sql, $params) !== null;
    }

    /**
     * Executes a non-SELECT query and returns the number of affected rows.
     *
     * @param string $sql SQL with named placeholders.
     * @param array<string, mixed> $params Parameters indexed by placeholder name without leading colon.
     *
     * @return int
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->prepareAndExecute($sql, $params);

        try {
            return (int) $stmt->affected_rows;
        } finally {
            $stmt->close();
        }
    }

    /**
     * Executes a server-level query without selecting a database.
     *
     * Use this method for operations such as:
     * - CREATE DATABASE
     * - DROP DATABASE
     * - CREATE TABLE target.table LIKE source.table
     *
     * Important:
     * This method does not support bound parameters.
     * SQL identifiers such as database names and table names must be validated before being added to SQL.
     *
     * @param string $sql
     * @return int
     */
    public function executeServer(string $sql): int
    {
        $connection = MysqlConnectionFactory::getServerConnection($this->dbKey);

        $result = $connection->query($sql);

        if ($result === false) {
            throw new DbQueryException('Failed to execute server SQL query: ' . $connection->error);
        }

        if ($result instanceof mysqli_result) {
            $result->free();
        }

        return (int) $connection->affected_rows;
    }

    public function executeServerMulti(string $sql): void
    {
        $connection = MysqlConnectionFactory::getServerConnection($this->dbKey);

        if (!$connection->multi_query($sql)) {
            throw new DbQueryException('Failed to execute server SQL query: ' . $connection->error);
        }

        do {
            $result = $connection->store_result();

            if ($result instanceof mysqli_result) {
                $result->free();
            }

            if (!$connection->more_results()) {
                break;
            }
        } while ($connection->next_result());

        if ($connection->errno) {
            throw new DbQueryException('Failed to execute server SQL query: ' . $connection->error);
        }
    }

    /**
     * Executes an INSERT query and returns the last insert id.
     *
     * @param string $sql SQL with named placeholders.
     * @param array<string, mixed> $params Parameters indexed by placeholder name without leading colon.
     *
     * @return int
     */
    public function insert(string $sql, array $params = []): int
    {
        $stmt = $this->prepareAndExecute($sql, $params);

        try {
            $connection = $this->getConnection();

            return (int) $connection->insert_id;
        } finally {
            $stmt->close();
        }
    }

    /**
     * Inserts multiple rows in a single INSERT statement and returns the number of affected rows.
     *
     * All rows must have the same set of columns.
     *
     * @param string $table
     * @param array<int, array<string, mixed>> $rows
     * @param bool $ignore When true, uses INSERT IGNORE.
     *
     * @return int
     */
    public function multiInsert(string $table, array $rows, bool $ignore = false): int
    {
        $columns = array_keys($rows[0]);
        foreach ($rows as $row) {
            if (array_keys($row) !== $columns) {
                throw new InvalidArgumentException('All rows must have the same structure');
            }
        }

        $ignoreSql = $ignore ? 'IGNORE' : '';
        $values = [];
        $placeholders = [];

        foreach ($rows as $i => $row) {
            $rowPlaceholders = [];

            foreach ($columns as $col) {
                $param = "{$col}_{$i}";
                $rowPlaceholders[] = ":{$param}";
                $values[$param] = $row[$col];
            }

            $placeholders[] = '(' . implode(',', $rowPlaceholders) . ')';
        }

        $columnsSql = implode(',', array_map(fn($col) => "`$col`", $columns));
        $valuesSql = implode(',', $placeholders);

        $sql = "INSERT {$ignoreSql} INTO `{$table}` ({$columnsSql}) VALUES {$valuesSql}";

        return $this->execute($sql, $values);
    }

    /**
     * Executes a BuiltQuery and returns the first row.
     *
     * @param BuiltQuery $query
     * @return array<string, mixed>|null
     */
    public function fetchOneQuery(BuiltQuery $query): ?array
    {
        return $this->fetchOne($query->getSql(), $query->getParams());
    }

    /**
     * Executes a BuiltQuery and returns all rows.
     *
     * @param BuiltQuery $query
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllQuery(BuiltQuery $query): array
    {
        return $this->fetchAll($query->getSql(), $query->getParams());
    }

    /**
     * Executes a BuiltQuery and returns rows indexed by a selected field.
     *
     * @param BuiltQuery $query
     * @param string $keyField Row field used as array key.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function fetchAssocWithKeyQuery(BuiltQuery $query, string $keyField): array
    {
        return $this->fetchAssocWithKey(
            $query->getSql(),
            $query->getParams(),
            $keyField
        );
    }

    /**
     * Executes a BuiltQuery and returns the first scalar value.
     *
     * @param BuiltQuery $query
     * @return mixed
     */
    public function fetchScalarQuery(BuiltQuery $query)
    {
        return $this->fetchScalar($query->getSql(), $query->getParams());
    }

    /**
     * Executes a BuiltQuery and returns the number of affected rows.
     *
     * @param BuiltQuery $query
     * @return int
     */
    public function executeQuery(BuiltQuery $query): int
    {
        return $this->execute($query->getSql(), $query->getParams());
    }

    /**
     * Executes an INSERT BuiltQuery and returns the last insert id.
     *
     * @param BuiltQuery $query
     * @return int
     */
    public function insertQuery(BuiltQuery $query): int
    {
        return $this->insert($query->getSql(), $query->getParams());
    }

    /**
     * Starts a database transaction on the selected connection.
     *
     * Use this method when several queries must be executed atomically.
     * If any later operation fails, the transaction should be rolled back.
     *
     * When this instance was constructed with $sharedConnection = true, this delegates to
     * {@see \app\core\db\Mysql::startTransaction()} on the same db key instead of issuing its
     * own START TRANSACTION - see the constructor docblock.
     *
     * @param string $level Optional isolation level (one of {@see \app\core\db\Mysql::ISOLATION_LEVELS},
     *        e.g. {@see \app\core\db\Mysql::LEVEL_SERIALIZABLE}). Applied as
     *        "SET SESSION TRANSACTION ISOLATION LEVEL ..." before starting the transaction.
     *        Ignored on a nested call (only the outermost beginTransaction() of this instance
     *        actually starts a transaction). Empty string leaves the connection's current
     *        isolation level unchanged.
     * @return void
     * @throws DbTransactionException
     * @throws InvalidArgumentException If $level is non-empty and not a recognized isolation level.
     */
    public function beginTransaction(string $level = ''): void
    {
        if ($this->sharedConnection) {
            if (!Mysql::startTransaction($level, Mysql::SCOPE_SESSION, $this->dbKey)) {
                throw new DbTransactionException('Failed to begin shared transaction on db "' . $this->dbKey . '".');
            }

            return;
        }

        $connection = $this->getConnection();

        if ($this->transactionDepth === 0) {
            if ($level !== '') {
                if (!in_array($level, Mysql::ISOLATION_LEVELS, true)) {
                    throw new InvalidArgumentException('Unknown transaction isolation level "' . $level . '".');
                }

                if (!$connection->query('SET SESSION TRANSACTION ISOLATION LEVEL ' . $level)) {
                    throw new DbTransactionException('Failed to set transaction isolation level: ' . $connection->error);
                }
            }

            if (!$connection->begin_transaction()) {
                throw new DbTransactionException('Failed to begin transaction: ' . $connection->error);
            }
        }

        $this->transactionDepth++;
    }

    /**
     * Commits the active transaction on the selected connection.
     *
     * After commit, all changes made within the transaction become permanent.
     *
     * When this instance was constructed with $sharedConnection = true, this delegates to
     * {@see \app\core\db\Mysql::commitTransaction()} on the same db key - see the constructor
     * docblock.
     *
     * @return void
     * @throws DbTransactionException
     */
    public function commit(): void
    {
        if ($this->sharedConnection) {
            if (!Mysql::isOpenTransaction($this->dbKey)) {
                throw new DbTransactionException('Cannot commit transaction: no active transaction.');
            }

            Mysql::commitTransaction($this->dbKey);

            return;
        }

        if ($this->transactionDepth <= 0) {
            throw new DbTransactionException('Cannot commit transaction: no active transaction.');
        }

        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            $connection = $this->getConnection();

            if (!$connection->commit()) {
                throw new DbTransactionException('Failed to commit transaction: ' . $connection->error);
            }
        }
    }

    /**
     * Rolls back the active transaction on the selected connection.
     *
     * Use rollback when a query inside the transaction fails
     * and all previous changes made in the same transaction must be undone.
     *
     * When this instance was constructed with $sharedConnection = true, this delegates to
     * {@see \app\core\db\Mysql::rollbackTransaction()} on the same db key - see the constructor
     * docblock.
     *
     * @return void
     * @throws DbTransactionException
     */
    public function rollback(): void
    {
        if ($this->sharedConnection) {
            if (!Mysql::isOpenTransaction($this->dbKey)) {
                throw new DbTransactionException('Cannot rollback transaction: no active transaction.');
            }

            Mysql::rollbackTransaction($this->dbKey);

            return;
        }

        if ($this->transactionDepth <= 0) {
            throw new DbTransactionException('Cannot rollback transaction: no active transaction.');
        }

        $connection = $this->getConnection();

        if (!$connection->rollback()) {
            throw new DbTransactionException('Failed to rollback transaction: ' . $connection->error);
        }

        $this->transactionDepth = 0;
    }

    /**
     * Executes a callback inside a transaction.
     *
     * Flow:
     * - begin transaction
     * - execute callback
     * - commit on success
     * - rollback on failure
     *
     * The callback result is returned unchanged.
     *
     * Example:
     * <code>
     * $db = new SafeMysql();
     *
     * $result = $db->transaction(function (SafeMysql $db) use ($data) {
     *     $db->execute('UPDATE users SET status = :status WHERE id = :id', [
     *         'status' => 1,
     *         'id' => 10,
     *     ]);
     *
     *     return $db->insert('INSERT INTO logs (message) VALUES (:message)', [
     *         'message' => 'User updated',
     *     ]);
     * });
     * </code>
     *
     * @param callable $callback
     * @return mixed
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);

            $this->commit();

            return $result;
        } catch (Throwable $e) {
            try {
                if ($this->transactionDepth > 0) {
                    $this->rollback();
                }
            } catch (Throwable $rollbackException) {
                error_log(
                    '[SafeMysql] Transaction rollback failed after original exception: '
                    . $rollbackException->getMessage()
                );
            }

            throw $e;
        }
    }

    /**
     * Normalizes a free-text search term for use in a MySQL FULLTEXT MATCH ... AGAINST clause.
     *
     * Strips FULLTEXT boolean-mode operators and other punctuation, and drops common English
     * stop words, so the term can be safely bound as a query parameter. This is a text
     * transformation only - it does not perform any SQL escaping, so the result must still be
     * passed as a bound parameter, never concatenated into the SQL text.
     *
     * @param string $str
     * @return string
     */
    public static function escapeMatch(string $str): string
    {
        $res = preg_replace("/[\/\\\!\@\#\$\%\^\&\*\(\)\[\]\{\}\;\:\+\=\.\,\?\|\'\"\<\>]+/", "", " $str ");
        return trim(preg_replace("/ +/", " ", str_ireplace([' the ', ' a ', ' an ', '-'], [' ', ' ', ' ', ''], $res)));
    }

    /**
     * Prepares and executes a SQL statement using named placeholders.
     *
     * @param string $sql
     * @param array<string, mixed> $params
     *
     * @return mysqli_stmt
     */
    private function prepareAndExecute(string $sql, array $params): mysqli_stmt
    {
        list($parsedSql, $orderedValues) = self::compileNamedQuery($sql, $params);

        $connection = $this->getConnection();
        $stmt = $connection->prepare($parsedSql);

        if (!$stmt instanceof mysqli_stmt) {
            throw new DbQueryException('Failed to prepare SQL statement: ' . $connection->error, $connection->errno);
        }

        try {
            if ($orderedValues !== []) {
                self::bindValues($stmt, $orderedValues);
            }

            if (!$stmt->execute()) {
                throw new DbQueryException('Failed to execute SQL statement: ' . $stmt->error, $stmt->errno);
            }

            return $stmt;
        } catch (Throwable $e) {
            $stmt->close();
            throw $e;
        }
    }

    /**
     * Converts SQL with named placeholders into SQL with positional placeholders.
     *
     * Supports both scalar and array parameters.
     *
     * Scalar example:
     *   SELECT * FROM users WHERE id = :id
     *   ['id' => 10]
     *
     * Becomes:
     *   SELECT * FROM users WHERE id = ?
     *   [10]
     *
     * Array example:
     *   SELECT * FROM users WHERE id IN (:ids)
     *   ['ids' => [10, 15, 20]]
     *
     * Becomes:
     *   SELECT * FROM users WHERE id IN (?, ?, ?)
     *   [10, 15, 20]
     *
     * @param string $sql
     * @param array<string, mixed> $params
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private static function compileNamedQuery(string $sql, array $params): array
    {
        self::assertParameterKeys($params);
        SafeMysqlDiagnostic::reportMixedInParameterTypes($sql, $params);

        $usedParamNames = [];
        $orderedValues = [];

        $parsedSql = preg_replace_callback(
            '/:([a-zA-Z_][a-zA-Z0-9_]*)/',
            function (array $matches) use ($params, &$usedParamNames, &$orderedValues): string {
                $name = $matches[1];

                if (!array_key_exists($name, $params)) {
                    throw new InvalidArgumentException(
                        sprintf('Missing value for SQL placeholder :%s', $name)
                    );
                }

                $usedParamNames[] = $name;
                $value = $params[$name];

                if (is_array($value)) {
                    if ($value === []) {
                        throw new InvalidArgumentException(
                            sprintf('Array parameter :%s must not be empty.', $name)
                        );
                    }

                    $placeholders = [];

                    foreach (array_values($value) as $item) {
                        if (is_array($item) || is_object($item)) {
                            throw new InvalidArgumentException(
                                sprintf('Array parameter :%s must contain only scalar or null values.', $name)
                            );
                        }

                        $placeholders[] = '?';
                        $orderedValues[] = $item;
                    }

                    return implode(', ', $placeholders);
                }

                $orderedValues[] = $value;

                return '?';
            },
            $sql
        );

        if (!is_string($parsedSql) || $parsedSql === '') {
            throw new RuntimeException('Failed to parse SQL with named placeholders.');
        }

        $usedParamNames = array_values(array_unique($usedParamNames));
        $providedParamNames = array_keys($params);
        // A null value is the "filter not set" convention for optional placeholders, so only non-null leftovers are logged
        $unusedParamNames = array_filter(
            array_diff($providedParamNames, $usedParamNames),
            static function ($name) use ($params): bool {
                return $params[$name] !== null;
            }
        );

        if ($unusedParamNames !== []) {
            error_log(
                sprintf(
                    '[SafeMysql] Unused SQL parameters provided: %s; SQL: %s',
                    implode(', ', $unusedParamNames),
                    $sql
                )
            );
        }

        return [$parsedSql, $orderedValues];
    }

    /**
     * Ensures parameter keys are valid placeholder names.
     *
     * @param array<string, mixed> $params
     */
    private static function assertParameterKeys(array $params): void
    {
        foreach ($params as $key => $_value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('SQL parameter keys must be strings.');
            }

            if (strpos($key, ':') === 0) {
                throw new InvalidArgumentException(
                    sprintf('SQL parameter key "%s" must not include a leading colon.', $key)
                );
            }

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid SQL parameter name "%s".', $key)
                );
            }
        }
    }

    /**
     * Binds ordered values to a prepared mysqli statement.
     *
     * @param mysqli_stmt $stmt
     * @param array<int, mixed> $values
     */
    private static function bindValues(mysqli_stmt $stmt, array $values): void
    {
        $types = '';
        $normalizedValues = array();

        foreach ($values as $value) {
            list($type, $normalizedValue) = self::normalizeValue($value);
            $types .= $type;
            $normalizedValues[] = $normalizedValue;
        }

        $refs = array();
        foreach ($normalizedValues as $index => $value) {
            $normalizedValues[$index] = $value;
            $refs[$index] = &$normalizedValues[$index];
        }

        array_unshift($refs, $types);

        if (!call_user_func_array(array($stmt, 'bind_param'), $refs)) {
            throw new DbQueryException('Failed to bind SQL statement parameters: ' . $stmt->error);
        }
    }

    /**
     * Converts a PHP value into a mysqli bind type and a normalized scalar value.
     *
     * @param mixed $value
     * @return array{0: string, 1: int|float|string|null}
     */
    private static function normalizeValue($value): array
    {
        if (is_int($value)) {
            return array('i', $value);
        }

        if (is_float($value)) {
            return array('d', $value);
        }

        if (is_string($value)) {
            return array('s', $value);
        }

        if (is_bool($value)) {
            return array('i', $value ? 1 : 0);
        }

        if ($value === null) {
            return array('s', null);
        }

        throw new InvalidArgumentException(
            sprintf(
                'Unsupported SQL parameter type: %s',
                is_object($value) ? get_class($value) : gettype($value)
            )
        );
    }

    /**
     * Returns the connection this instance operates on: the legacy Mysql class's own connection
     * when constructed with $sharedConnection = true, otherwise a pooled connection from
     * MysqlConnectionFactory.
     *
     * @return mysqli
     */
    private function getConnection(): mysqli
    {
        $connection = $this->sharedConnection
            ? MysqlConnectionFactory::getSharedConnection($this->dbKey)
            : MysqlConnectionFactory::getConnection($this->dbKey);

        if (!$connection instanceof mysqli) {
            throw new DbConnectionException(
                'MysqlConnectionFactory did not return a valid mysqli instance.'
            );
        }

        return $connection;
    }
}
