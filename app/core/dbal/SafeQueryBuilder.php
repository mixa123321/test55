<?php

declare(strict_types=1);

namespace app\core\dbal;

use InvalidArgumentException;

/**
 * SafeQueryBuilder builds SQL together with a named parameter map
 * for execution through SafeMysql.
 *
 * Supported query types:
 * - SELECT
 * - INSERT
 * - UPDATE
 *
 * Design rules:
 * - user values are never interpolated directly into SQL
 * - SQL text and bound values are always stored separately
 * - the builder generates named placeholders automatically
 *
 * The builder is intentionally small and explicit.
 * It does not execute queries and does not try to hide SQL.
 */
final class SafeQueryBuilder
{
    public const TYPE_SELECT = 'select';
    public const TYPE_INSERT = 'insert';
    public const TYPE_UPDATE = 'update';

    /**
     * @var string
     */
    private $queryType = self::TYPE_SELECT;

    /**
     * @var array<int, string>
     */
    private $select = [];

    /**
     * @var array<int, string>
     */
    private $selectModifiers = [];

    /**
     * @var string|null
     */
    private $from = null;

    /**
     * @var string|null
     */
    private $insertInto = null;

    /**
     * @var string|null
     */
    private $updateTable = null;

    /**
     * @var array<int, string>
     */
    private $joins = [];

    /**
     * @var array<int, string>
     */
    private $where = [];

    /**
     * @var array<int, string>
     */
    private $groupBy = [];

    /**
     * @var array<int, string>
     */
    private $having = [];

    /**
     * @var array<int, string>
     */
    private $orderBy = [];

    /**
     * @var int|null
     */
    private $limit = null;

    /**
     * @var int|null
     */
    private $offset = null;

    /**
     * @var array<string, mixed>
     */
    private $params = [];

    /**
     * @var int
     */
    private $paramCounter = 0;

    /**
     * @var array<string, mixed>
     */
    private $insertValues = [];

    /**
     * @var array<int, string>
     */
    private $updateAssignments = [];

    /**
     * @var array<int, string>
     */
    private $onDuplicateKeyUpdateFields = [];

    /**
     * Sets SELECT fields.
     *
     * @param array<int, string> $fields
     * @return self
     */
    public function select(array $fields)
    {
        $this->assertQueryType(self::TYPE_SELECT);

        if ($fields === []) {
            throw new InvalidArgumentException('SELECT fields must not be empty.');
        }

        foreach ($fields as $field) {
            $this->assertSqlIdentifierExpression($field);
        }

        $this->select = array_values($fields);

        return $this;
    }

    /**
     * Adds a SELECT modifier.
     *
     * Supported modifiers:
     * - DISTINCT
     * - SQL_CALC_FOUND_ROWS
     *
     * @param string $modifier
     * @return self
     */
    public function selectModifier($modifier)
    {
        $this->assertQueryType(self::TYPE_SELECT);

        if (!is_string($modifier) || trim($modifier) === '') {
            throw new InvalidArgumentException('SELECT modifier must be a non-empty string.');
        }

        $modifier = strtoupper(trim($modifier));

        $allowedModifiers = [
            'DISTINCT',
            'SQL_CALC_FOUND_ROWS',
        ];

        if (!in_array($modifier, $allowedModifiers, true)) {
            throw new InvalidArgumentException(
                sprintf('Unsupported SELECT modifier "%s".', $modifier)
            );
        }

        if (!in_array($modifier, $this->selectModifiers, true)) {
            $this->selectModifiers[] = $modifier;
        }

        return $this;
    }

    /**
     * Shortcut for SELECT *.
     *
     * @return self
     */
    public function selectAll()
    {
        $this->assertQueryType(self::TYPE_SELECT);

        $this->select = ['*'];

        return $this;
    }

    /**
     * Sets FROM clause for SELECT queries.
     *
     * @param string $table
     * @return self
     */
    public function from($table)
    {
        $this->assertQueryType(self::TYPE_SELECT);

        $this->assertSqlIdentifierExpression($table);
        $this->from = $table;

        return $this;
    }

    /**
     * Starts an INSERT query.
     *
     * @param string $table
     * @return self
     */
    public function insertInto($table)
    {
        $this->setQueryType(self::TYPE_INSERT);
        $this->assertSqlIdentifierExpression($table);

        $this->insertInto = $table;

        return $this;
    }

    /**
     * Sets INSERT values.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public function values(array $data)
    {
        $this->assertQueryType(self::TYPE_INSERT);

        if ($data === []) {
            throw new InvalidArgumentException('INSERT values must not be empty.');
        }

        $this->assertWriteDataKeys($data);

        $this->insertValues = [];
        $this->onDuplicateKeyUpdateFields = [];

        foreach ($data as $field => $value) {
            $this->insertValues[$field] = $value;
        }

        return $this;
    }

    /**
     * Adds ON DUPLICATE KEY UPDATE clause for INSERT queries.
     *
     * The provided fields must already exist in the INSERT values.
     *
     * @param array<int, string> $fields
     * @return self
     */
    public function onDuplicateKeyUpdate(array $fields)
    {
        $this->assertQueryType(self::TYPE_INSERT);

        if ($fields === []) {
            throw new InvalidArgumentException('ON DUPLICATE KEY UPDATE fields must not be empty.');
        }

        foreach ($fields as $field) {
            if (!is_string($field) || $field === '') {
                throw new InvalidArgumentException('ON DUPLICATE KEY UPDATE field names must be non-empty strings.');
            }

            $this->assertSqlIdentifierName($field);

            if (!array_key_exists($field, $this->insertValues)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Field "%s" cannot be used in ON DUPLICATE KEY UPDATE because it is not present in INSERT values.',
                        $field
                    )
                );
            }
        }

        $this->onDuplicateKeyUpdateFields = array_values(array_unique($fields));

        return $this;
    }

    /**
     * Starts an UPDATE query.
     *
     * @param string $table
     * @return self
     */
    public function update($table)
    {
        $this->setQueryType(self::TYPE_UPDATE);
        $this->assertSqlIdentifierExpression($table);

        $this->updateTable = $table;

        return $this;
    }

    /**
     * Adds SET assignments for UPDATE queries.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public function set(array $data)
    {
        $this->assertQueryType(self::TYPE_UPDATE);

        if ($data === []) {
            throw new InvalidArgumentException('UPDATE data must not be empty.');
        }

        $this->assertWriteDataKeys($data);

        foreach ($data as $field => $value) {
            $paramName = $this->nextParamName($field);
            $this->updateAssignments[] = sprintf('%s = :%s', $field, $paramName);
            $this->params[$paramName] = $value;
        }

        return $this;
    }

    /**
     * Adds a raw SET assignment for UPDATE queries.
     *
     * Example:
     * ->setRaw('updated_at', 'NOW()')
     *
     * @param string $field
     * @param string $expression
     * @param array<string, mixed> $params
     * @return self
     */
    public function setRaw($field, $expression, array $params = [])
    {
        $this->assertQueryType(self::TYPE_UPDATE);

        $this->assertSqlIdentifierName($field);
        $this->assertRawSqlExpression($expression);
        $this->mergeParams($params);

        $this->updateAssignments[] = sprintf('%s = %s', $field, $expression);

        return $this;
    }

    /**
     * Adds INNER JOIN.
     *
     * @param string $table
     * @param string $onExpression
     * @return self
     */
    public function innerJoin($table, $onExpression)
    {
        $this->assertSqlIdentifierExpression($table);
        $this->assertRawSqlExpression($onExpression);

        $this->joins[] = sprintf('INNER JOIN %s ON %s', $table, $onExpression);

        return $this;
    }

    /**
     * Adds LEFT JOIN.
     *
     * @param string $table
     * @param string $onExpression
     * @return self
     */
    public function leftJoin($table, $onExpression)
    {
        $this->assertSqlIdentifierExpression($table);
        $this->assertRawSqlExpression($onExpression);

        $this->joins[] = sprintf('LEFT JOIN %s ON %s', $table, $onExpression);

        return $this;
    }

    /**
     * Adds a WHERE condition joined with AND.
     *
     * Supported operators:
     * =, !=, <>, >, >=, <, <=, LIKE
     *
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return self
     */
    public function where($field, $operator, $value)
    {
        return $this->addWhereCondition('AND', $field, $operator, $value);
    }

    /**
     * Adds a WHERE condition joined with OR.
     *
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return self
     */
    public function orWhere($field, $operator, $value)
    {
        return $this->addWhereCondition('OR', $field, $operator, $value);
    }

    /**
     * Adds a WHERE IN (...) condition joined with AND.
     *
     * @param string $field
     * @param array<int, mixed> $values
     * @return self
     */
    public function whereIn($field, array $values)
    {
        return $this->addInCondition('AND', $field, $values, false);
    }

    /**
     * Adds a WHERE IN (...) condition joined with OR.
     *
     * @param string $field
     * @param array<int, mixed> $values
     * @return self
     */
    public function orWhereIn($field, array $values)
    {
        return $this->addInCondition('OR', $field, $values, false);
    }

    /**
     * Adds a WHERE NOT IN (...) condition joined with AND.
     *
     * @param string $field
     * @param array<int, mixed> $values
     * @return self
     */
    public function whereNotIn($field, array $values)
    {
        return $this->addInCondition('AND', $field, $values, true);
    }

    /**
     * Adds a WHERE IS NULL condition.
     *
     * @param string $field
     * @return self
     */
    public function whereNull($field)
    {
        $this->assertSqlIdentifierExpression($field);

        $condition = sprintf('%s IS NULL', $field);
        $this->appendCondition($this->where, $condition, 'AND');

        return $this;
    }

    /**
     * Adds a WHERE IS NOT NULL condition.
     *
     * @param string $field
     * @return self
     */
    public function whereNotNull($field)
    {
        $this->assertSqlIdentifierExpression($field);

        $condition = sprintf('%s IS NOT NULL', $field);
        $this->appendCondition($this->where, $condition, 'AND');

        return $this;
    }

    /**
     * Adds a raw WHERE fragment.
     *
     * Raw expressions are for trusted internal SQL fragments only.
     * Dynamic values must still be passed through params.
     *
     * @param string $expression
     * @param array<string, mixed> $params
     * @param string $glue
     * @return self
     */
    public function whereRaw($expression, array $params = [], $glue = 'AND')
    {
        $this->assertRawSqlExpression($expression);
        $this->appendCondition($this->where, $expression, $glue);
        $this->mergeParams($params);

        return $this;
    }

    /**
     * Adds GROUP BY fields.
     *
     * @param array<int, string> $fields
     * @return self
     */
    public function groupBy(array $fields)
    {
        $this->assertQueryType(self::TYPE_SELECT);

        foreach ($fields as $field) {
            $this->assertSqlIdentifierExpression($field);
        }

        foreach ($fields as $field) {
            $this->groupBy[] = $field;
        }

        return $this;
    }

    /**
     * Adds a HAVING condition joined with AND.
     *
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return self
     */
    public function having($field, $operator, $value)
    {
        $this->assertQueryType(self::TYPE_SELECT);

        return $this->addHavingCondition('AND', $field, $operator, $value);
    }

    /**
     * Adds ORDER BY.
     *
     * Direction is restricted to ASC or DESC.
     *
     * @param string $field
     * @param string $direction
     * @return self
     */
    public function orderBy($field, $direction = 'ASC')
    {
        $this->assertSqlIdentifierExpression($field);

        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('ORDER BY direction must be ASC or DESC.');
        }

        $this->orderBy[] = sprintf('%s %s', $field, $direction);

        return $this;
    }

    /**
     * Sets LIMIT.
     *
     * @param int $limit
     * @return self
     */
    public function limit($limit)
    {
        if (!is_int($limit) || $limit < 0) {
            throw new InvalidArgumentException('LIMIT must be greater than or equal to 0.');
        }

        $this->limit = $limit;

        return $this;
    }

    /**
     * Sets OFFSET.
     *
     * @param int $offset
     * @return self
     */
    public function offset($offset)
    {
        if (!is_int($offset) || $offset < 0) {
            throw new InvalidArgumentException('OFFSET must be greater than or equal to 0.');
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * Builds the final SQL query and wraps it into BuiltQuery.
     *
     * @return BuiltQuery
     */
    public function build()
    {
        switch ($this->queryType) {
            case self::TYPE_SELECT:
                return $this->buildSelectQuery();

            case self::TYPE_INSERT:
                return $this->buildInsertQuery();

            case self::TYPE_UPDATE:
                return $this->buildUpdateQuery();
        }

        throw new InvalidArgumentException(
            sprintf('Unsupported query type "%s".', $this->queryType)
        );
    }

    /**
     * Returns only the compiled SQL text.
     *
     * @return string
     */
    public function getSql()
    {
        return $this->build()->getSql();
    }

    /**
     * Returns only the compiled params.
     *
     * @return array<string, mixed>
     */
    public function getParams()
    {
        return $this->build()->getParams();
    }

    /**
     * @return BuiltQuery
     */
    private function buildSelectQuery()
    {
        if ($this->from === null) {
            throw new InvalidArgumentException('FROM clause is required for SELECT queries.');
        }

        $select = $this->select !== [] ? implode(', ', $this->select) : '*';
        $selectModifiers = $this->selectModifiers !== [] ? implode(' ', $this->selectModifiers) . ' ' : '';

        $sqlParts = [];
        $sqlParts[] = sprintf('SELECT %s%s', $selectModifiers, $select);
        $sqlParts[] = sprintf('FROM %s', $this->from);

        if ($this->joins !== []) {
            $sqlParts[] = implode(' ', $this->joins);
        }

        if ($this->where !== []) {
            $sqlParts[] = 'WHERE ' . $this->compileConditions($this->where);
        }

        if ($this->groupBy !== []) {
            $sqlParts[] = 'GROUP BY ' . implode(', ', $this->groupBy);
        }

        if ($this->having !== []) {
            $sqlParts[] = 'HAVING ' . $this->compileConditions($this->having);
        }

        if ($this->orderBy !== []) {
            $sqlParts[] = 'ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sqlParts[] = 'LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            if ($this->limit === null) {
                throw new InvalidArgumentException('OFFSET cannot be used without LIMIT.');
            }

            $sqlParts[] = 'OFFSET ' . $this->offset;
        }

        return new BuiltQuery(
            implode(' ', $sqlParts),
            $this->params
        );
    }

    /**
     * @return BuiltQuery
     */
    private function buildInsertQuery()
    {
        if ($this->insertInto === null) {
            throw new InvalidArgumentException('INSERT INTO clause is required for INSERT queries.');
        }

        if ($this->insertValues === []) {
            throw new InvalidArgumentException('INSERT values are required for INSERT queries.');
        }

        $fields = [];
        $placeholders = [];
        $params = $this->params;

        foreach ($this->insertValues as $field => $value) {
            $fields[] = $field;
            $params[$field] = $value;
            $placeholders[] = ':' . $field;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->insertInto,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        if ($this->onDuplicateKeyUpdateFields !== []) {
            $assignments = [];

            foreach ($this->onDuplicateKeyUpdateFields as $field) {
                $assignments[] = sprintf('%s = VALUES(%s)', $field, $field);
            }

            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $assignments);
        }

        return new BuiltQuery($sql, $params);
    }

    /**
     * @return BuiltQuery
     */
    private function buildUpdateQuery()
    {
        if ($this->updateTable === null) {
            throw new InvalidArgumentException('UPDATE table is required for UPDATE queries.');
        }

        if ($this->updateAssignments === []) {
            throw new InvalidArgumentException('UPDATE assignments are required for UPDATE queries.');
        }

        $sqlParts = [];
        $sqlParts[] = sprintf('UPDATE %s', $this->updateTable);

        if ($this->joins !== []) {
            $sqlParts[] = implode(' ', $this->joins);
        }

        $sqlParts[] = 'SET ' . implode(', ', $this->updateAssignments);

        if ($this->where !== []) {
            $sqlParts[] = 'WHERE ' . $this->compileConditions($this->where);
        }

        if ($this->orderBy !== []) {
            $sqlParts[] = 'ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sqlParts[] = 'LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            throw new InvalidArgumentException('OFFSET is not supported for UPDATE queries.');
        }

        return new BuiltQuery(
            implode(' ', $sqlParts),
            $this->params
        );
    }

    /**
     * @param string $type
     * @return void
     */
    private function setQueryType($type)
    {
        if (!in_array($type, [self::TYPE_SELECT, self::TYPE_INSERT, self::TYPE_UPDATE], true)) {
            throw new InvalidArgumentException(
                sprintf('Unsupported query type "%s".', $type)
            );
        }

        if ($this->queryType !== self::TYPE_SELECT && $this->queryType !== $type) {
            throw new InvalidArgumentException(
                sprintf(
                    'Query type is already set to "%s" and cannot be changed to "%s".',
                    $this->queryType,
                    $type
                )
            );
        }

        $this->queryType = $type;
    }

    /**
     * @param string $type
     * @return void
     */
    private function assertQueryType($type)
    {
        if ($this->queryType !== $type) {
            throw new InvalidArgumentException(
                sprintf(
                    'Method cannot be used for query type "%s". Expected "%s".',
                    $this->queryType,
                    $type
                )
            );
        }
    }

    /**
     * @param string $glue
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return self
     */
    private function addWhereCondition($glue, $field, $operator, $value)
    {
        $this->assertSqlIdentifierExpression($field);
        $operator = $this->normalizeOperator($operator);

        if ($value === null) {
            if ($operator === '=') {
                $condition = sprintf('%s IS NULL', $field);
                $this->appendCondition($this->where, $condition, $glue);

                return $this;
            }

            if (in_array($operator, ['!=', '<>'], true)) {
                $condition = sprintf('%s IS NOT NULL', $field);
                $this->appendCondition($this->where, $condition, $glue);

                return $this;
            }

            throw new InvalidArgumentException('NULL can only be used with =, != or <> operators.');
        }

        $paramName = $this->nextParamName($field);
        $condition = sprintf('%s %s :%s', $field, $operator, $paramName);

        $this->appendCondition($this->where, $condition, $glue);
        $this->params[$paramName] = $value;

        return $this;
    }

    /**
     * @param string $glue
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return self
     */
    private function addHavingCondition($glue, $field, $operator, $value)
    {
        $this->assertSqlIdentifierExpression($field);
        $operator = $this->normalizeOperator($operator);

        $paramName = $this->nextParamName($field);
        $condition = sprintf('%s %s :%s', $field, $operator, $paramName);

        $this->appendCondition($this->having, $condition, $glue);
        $this->params[$paramName] = $value;

        return $this;
    }

    /**
     * @param string $glue
     * @param string $field
     * @param array<int, mixed> $values
     * @param bool $not
     * @return self
     */
    private function addInCondition($glue, $field, array $values, $not)
    {
        $this->assertSqlIdentifierExpression($field);

        if ($values === []) {
            throw new InvalidArgumentException('IN condition values must not be empty.');
        }

        $placeholders = [];

        foreach ($values as $value) {
            $paramName = $this->nextParamName($field);
            $placeholders[] = ':' . $paramName;
            $this->params[$paramName] = $value;
        }

        $operator = $not ? 'NOT IN' : 'IN';
        $condition = sprintf('%s %s (%s)', $field, $operator, implode(', ', $placeholders));

        $this->appendCondition($this->where, $condition, $glue);

        return $this;
    }

    /**
     * @param array<int, string> $conditions
     * @param string $condition
     * @param string $glue
     * @return void
     */
    private function appendCondition(array &$conditions, $condition, $glue)
    {
        $glue = strtoupper($glue);

        if (!in_array($glue, ['AND', 'OR'], true)) {
            throw new InvalidArgumentException('Condition glue must be AND or OR.');
        }

        if ($conditions === []) {
            $conditions[] = $condition;
            return;
        }

        $conditions[] = $glue . ' ' . $condition;
    }

    /**
     * @param array<int, string> $conditions
     * @return string
     */
    private function compileConditions(array $conditions)
    {
        return implode(' ', $conditions);
    }

    /**
     * @param string $operator
     * @return string
     */
    private function normalizeOperator($operator)
    {
        $operator = strtoupper(trim($operator));

        $allowedOperators = [
            '=',
            '!=',
            '<>',
            '>',
            '>=',
            '<',
            '<=',
            'LIKE',
        ];

        if (!in_array($operator, $allowedOperators, true)) {
            throw new InvalidArgumentException(
                sprintf('Unsupported SQL operator "%s".', $operator)
            );
        }

        return $operator;
    }

    /**
     * @param string $field
     * @return string
     */
    private function nextParamName($field)
    {
        $base = preg_replace('/[^a-zA-Z0-9_]+/', '_', $field);
        $base = trim((string) $base, '_');

        if ($base === '') {
            $base = 'param';
        }

        $this->paramCounter++;

        return strtolower($base) . '_' . $this->paramCounter;
    }

    /**
     * @param array<string, mixed> $params
     * @return void
     */
    private function mergeParams(array $params)
    {
        foreach ($params as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new InvalidArgumentException('Raw SQL params must use non-empty string keys.');
            }

            if (strpos($key, ':') === 0) {
                throw new InvalidArgumentException('Raw SQL params must not include a leading colon.');
            }

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid raw SQL parameter name "%s".', $key)
                );
            }

            if (array_key_exists($key, $this->params)) {
                throw new InvalidArgumentException(
                    sprintf('Duplicate SQL parameter name "%s".', $key)
                );
            }

            $this->params[$key] = $value;
        }
    }

    /**
     * Validates field names used in INSERT and UPDATE data arrays.
     *
     * Only plain column names are allowed here.
     *
     * @param array<string, mixed> $data
     * @return void
     */
    private function assertWriteDataKeys(array $data)
    {
        foreach ($data as $field => $_value) {
            if (!is_string($field) || $field === '') {
                throw new InvalidArgumentException('Write data keys must be non-empty strings.');
            }

            $this->assertSqlIdentifierName($field);
        }
    }

    /**
     * Validates a plain SQL identifier name.
     *
     * Allowed examples:
     * - id
     * - login
     * - created_at
     *
     * @param string $value
     * @return void
     */
    private function assertSqlIdentifierName($value)
    {
        if (!is_string($value) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
            throw new InvalidArgumentException(
                sprintf('Unsafe or unsupported SQL identifier name "%s".', $value)
            );
        }
    }

    /**
     * Performs a conservative validation for identifiers
     * and simple identifier expressions.
     *
     * Allowed examples:
     * - users
     * - users u
     * - users.*
     * - users.id
     * - COUNT(*)
     * - COUNT(users.id)
     * - users AS u
     * - s.user_id AS id
     */
    private function assertSqlIdentifierExpression($value)
    {
        $value = trim($value);

        if ($value === '*') {
            return;
        }

        $patterns = [
            '/^[a-zA-Z_][a-zA-Z0-9_]*$/',
            '/^[a-zA-Z_][a-zA-Z0-9_]*\.\*$/',
            '/^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*$/',
            '/^[a-zA-Z_][a-zA-Z0-9_]*\s+[a-zA-Z_][a-zA-Z0-9_]*$/',
            '/^[a-zA-Z_][a-zA-Z0-9_]*\s+AS\s+[a-zA-Z_][a-zA-Z0-9_]*$/i',
            '/^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*\s+AS\s+[a-zA-Z_][a-zA-Z0-9_]*$/i',
            '/^[A-Z_][A-Z0-9_]*\(\*\)$/i',
            '/^[A-Z_][A-Z0-9_]*\([a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*\)$/i',
            '/^[A-Z_][A-Z0-9_]*\([a-zA-Z_][a-zA-Z0-9_]*\)$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return;
            }
        }

        throw new InvalidArgumentException(
            sprintf('Unsafe or unsupported SQL identifier expression "%s".', $value)
        );
    }

    /**
     * Performs a light validation for raw SQL fragments.
     *
     * Raw expressions are only for trusted internal SQL snippets.
     *
     * @param string $value
     * @return void
     */
    private function assertRawSqlExpression($value)
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Raw SQL expression must not be empty.');
        }

        if (strpos($value, ';') !== false) {
            throw new InvalidArgumentException('Raw SQL expression must not contain semicolons.');
        }
    }
}
