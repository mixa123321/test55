<?php

namespace app\core\db;

use app\core\Environment;
use app\core\helpers\Debug;
use app\core\helpers\Request;
use app\core\helpers\View;

class Mysql
{
    const MAX_ALLOWED_PACKET = 33554432; //32*1024*1024;
    const DB_FF = 'FF';
    const DB_SFTP = 'SFTP';

    const LEVEL_REPEATABLE_READ = 'REPEATABLE READ';
    const LEVEL_READ_COMMITTED = 'READ COMMITTED';
    const LEVEL_READ_UNCOMMITTED = 'READ UNCOMMITTED';
    const LEVEL_SERIALIZABLE = 'SERIALIZABLE';
    const ISOLATION_LEVELS = [
        self::LEVEL_REPEATABLE_READ,
        self::LEVEL_READ_COMMITTED,
        self::LEVEL_READ_UNCOMMITTED,
        self::LEVEL_SERIALIZABLE
    ];

    const SCOPE_GLOBAL = 'GLOBAL';
    const SCOPE_SESSION = 'SESSION';
    const ISOLATION_SCOPES = [
        self::SCOPE_GLOBAL,
        self::SCOPE_SESSION
    ];

    private static $query_time = 0;

    private static $transaction_pool = [
        self::DB_FF => null,
        self::DB_SFTP => null,
    ];
    private static $db_pool = [
        self::DB_FF => null,
        self::DB_SFTP => null,
    ];
    private static $requests_log;

    private static $transaction_counter = [];

    public static function setQueryTime($query_time): void
    {
        self::$query_time = $query_time;
    }

    public static function getLastQueryTime(): float
    {
        return self::$query_time;
    }

    /**
     * @param string $current_db
     * @param bool $reconnect
     * @return \mysqli
     */
    public static function getMysqli($current_db = self::DB_FF, $reconnect = false)
    {
        if (empty(self::$db_pool[$current_db]) || $reconnect) {
            $dbConParams = self::getDbConParams($current_db);
            if (empty($dbConParams))
                self::outputDbError(['db' => $current_db, 'msg' => "DB connection params not found"]);
            $wait_time = 25 * pow(10, 4);
            if (Environment::isRobot())
                $max_wait_time = 300 * pow(10, 6); //5 minutes
            else
                $max_wait_time = 16 * pow(10, 6); //16 seconds
            while (1) {
                $mysqli = new \mysqli(
                    $dbConParams['host'],
                    $dbConParams['user'],
                    $dbConParams['pass'],
                    $dbConParams['name']
                );
                if ($mysqli->connect_error || !$mysqli->set_charset('utf8mb4')) {
                    if (self::isMysqliConnectionLost($mysqli)) {
                        if ($wait_time < $max_wait_time) {
                            $wait_time *= 2;
                            usleep($wait_time);
                            continue;
                        }
                        $msg = "$current_db DB Connection failed after reaching max wait time";
                        self::outputDbError(['db' => $current_db, 'msg' => $msg]);
                    } else {
                        $msg = "$current_db DB Connection failed. {$mysqli->connect_error}.\n" . var_export($mysqli, 1);
                        self::outputDbError(['db' => $current_db, 'msg' => $msg]);
                    }
                }
                $mysqli->query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';");
                self::$db_pool[$current_db] = $mysqli;
                break;
            }
        }
        return self::$db_pool[$current_db];
    }

    private static function simpleGetMysqli($currentDb = self::DB_FF, $connectionTimeout = 1)
    {
        if (empty(self::$db_pool[$currentDb])) {
            $dbConParams = self::getDbConParams($currentDb);
            if (empty($dbConParams)) {
                Debug::sendDebugInfo("DB $currentDb connection params not found", '');
                return null;
            }
            $mysqli = mysqli_init();
            if (!$mysqli) {
                return null;
            }
            $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, $connectionTimeout);
            $mysqli->real_connect(
                $dbConParams['host'],
                $dbConParams['user'],
                $dbConParams['pass'],
                $dbConParams['name']
            );
            if ($mysqli->errno || $mysqli->connect_errno) {
                $msg = "$currentDb DB Connection failed. {$mysqli->connect_error}.\n" . var_export($mysqli, 1);
                Debug::sendDebugInfo("$currentDb DB Connection failed", $msg);
                return null;
            }
            self::$db_pool[$currentDb] = $mysqli;
        }
        return self::$db_pool[$currentDb];
    }

    private static function getDbConParams($db)
    {
        foreach (['HOST', 'USER', 'PASS', 'NAME'] as $var_name) {
            if (!defined($db . '_DB_' . $var_name)) {
                return [];
            }
        }
        return [
            'host' => constant($db . '_DB_HOST'),
            'user' => constant($db . '_DB_USER'),
            'pass' => constant($db . '_DB_PASS'),
            'name' => constant($db . '_DB_NAME'),
        ];
    }

    public static function insertRowAsync($tableName, $data, $currentDb = self::DB_FF)
    {
        $mysqli = self::simpleGetMysqli($currentDb);
        if ($mysqli === null) {
            return false;
        }
        $keys = implode(', ', array_keys($data));
        $values = array_values($data);
        foreach ($values as &$value) {
            $value = $mysqli->escape_string($value);
            $value = "'{$value}'";
        }
        unset($value);
        $values = implode(', ', $values);
        return $mysqli->query("INSERT INTO {$tableName} ({$keys}) VALUES ({$values})", MYSQLI_ASYNC);
    }

    private static function isMysqliConnectionLost($mysqli)
    {
        static $mysqli_errors = [
            2001, //Can't create UNIX socket (%d)
            2002, //Can't connect to local MySQL server through socket '%s' (%d)
            2003, //Can't connect to MySQL server on '%s' (%d)
            2004, //Can't create TCP/IP socket (%d)
            2005, //Unknown MySQL server host '%s' (%d)
            2006, //MySQL server has gone away
            2008, //MySQL client ran out of memory
            2012, //Error in server handshake
            2013, //Lost connection to MySQL server during query
            2014, //Commands out of sync; you can't run this command now
            2024, //Error connecting to slave:
            2025, //Error connecting to master:
            2026, //SSL connection error: %s
            2045, //Can't open shared memory; no answer from server (%lu)
            2048, //Invalid connection handle
            2055, //Lost connection to MySQL server at '%s', system error: %d
            1053, //Server shutdown in progress
        ];
        return
            !empty($mysqli->errno) && in_array($mysqli->errno, $mysqli_errors) ||
            !empty($mysqli->connect_errno) && in_array($mysqli->connect_errno, $mysqli_errors);
    }

    public static function fetchScalarWithBind($query, array $binds = [], $current_db = self::DB_FF)
    {
        $query = self::bind($query, $current_db, $binds);
        return self::fetchScalar($query, $current_db);
    }

    public static function fetchAssocWithBind($query, array $binds = [], $current_db = self::DB_FF)
    {
        $query = self::bind($query, $current_db, $binds);
        return self::fetchAssoc($query, $current_db);
    }

    public static function runQueryWithBind($query, array $binds = [], $affected_rows = false, $current_db = self::DB_FF)
    {
        $query = self::bind($query, $current_db, $binds);
        return self::runQuery($query, $affected_rows, $current_db);
    }

    public static function fetchOneAssocWithBind($query, array $binds = [], $current_db = self::DB_FF)
    {
        $query = self::bind($query, $current_db, $binds);
        return self::fetchOneAssoc($query, $current_db);
    }

    private static function bind(string $query, $current_db, array $binds = []): string
    {
        foreach ($binds as $key => $bind) {
            if (!mb_ereg_match('^\:[a-zA-Z_0-9]+$', $key))
                self::outputDbError(['db' => $current_db, 'msg' => "Wrong bind key " . $key]);
            if (is_array($bind)) {
                if (!$bind)
                    self::outputDbError(['db' => $current_db, 'msg' => "Wrong type bind parameter for $key"]);
                $first = current($bind);
                $where = [];
                foreach ($bind as $item) {
                    if (gettype($item) !== gettype($first)) {
                        self::outputDbError(['db' => $current_db, 'msg' => "Wrong type bind parameter for $key"]);
                    }
                    if (is_int($item)) {
                        $where[] = $item;
                    } elseif (is_float($item)) {
                        self::outputDbError(['db' => $current_db, 'msg' => "Not implemented yet"]);
                    } elseif (is_string($item)) {
                        $where[] = "'" . addslashes($item) . "'";
                    } else
                        self::outputDbError(['db' => $current_db, 'msg' => "Wrong type bind parameter for $key"]);
                }
                $query = mb_ereg_replace(preg_quote($key) . "\b", implode(",", $where), $query);
            } elseif (is_int($bind)) {
                $query = mb_ereg_replace(preg_quote($key) . "\b", $bind, $query);
            } elseif (is_float($bind)) {
                self::outputDbError(['db' => $current_db, 'msg' => "Not implemented yet"]);
            } elseif (is_bool($bind)) {
                $query = mb_ereg_replace(preg_quote($key) . "\b", (int)$bind, $query);
            } elseif (is_string($bind)) {
                $query = mb_ereg_replace(preg_quote($key) . "\b", "'" . addslashes($bind) . "'", $query);
            } elseif (is_null($bind)) {
                $query = mb_ereg_replace(preg_quote($key) . "\b", 'NULL', $query);
            } else
                self::outputDbError(['db' => $current_db, 'msg' => "Wrong type bind parameter for $key"]);
            if (!is_string($query))
                self::outputDbError(['db' => $current_db, 'msg' => "Wrong type bind parameter for $key"]);
        }
        if (mb_ereg_match('^.*(\s|\()\:[a-zA-Z_0-9]+\b.*$', $query))
            self::outputDbError(['db' => $current_db, 'msg' => "Bind error " . $query]);
        return $query;
    }

    public static function runQuery($query, $affected_rows = false, $current_db = self::DB_FF)
    {
        $mysqli = self::getMysqli($current_db);
        $start = microtime(true);
        $result = $mysqli->query($query);
        $query_time = microtime(true) - $start;
        if ($result == false && !self::$transaction_pool[$current_db] && self::isMysqliConnectionLost($mysqli)) {
            $mysqli = self::getMysqli($current_db, true);
            $start = microtime(true);
            $result = $mysqli->query($query);
            $query_time = microtime(true) - $start;
        }
        self::setQueryTime($query_time);
        if ($result == false) {
            if (Environment::isStage())
                throw new DBException($mysqli->error);
            self::outputDbError(
                ['db' => $current_db, 'msg' => 'Query: ' . $query . ' | Error message: ' . $mysqli->error . ' | Error code: ' . $mysqli->errno]
            );
        }

        if (Environment::isProduction() && !Environment::isRobot())
            if ($query_time > 30) {
                $process_list = Mysql::getProcessList();
                Debug::sendDebugInfo("Query time > 30 seconds", $query . " | Time: " . $query_time);
                Debug::sendDebugInfo("Process List", 'list: ' . var_export($process_list, 1), Debug::ROBOT);
            }
        if (Environment::isDev()) {
            self::$requests_log[] = [
                'query' => $query,
                'time' => $query_time,
                'db' => $current_db,
            ];
            if (Environment::isDbConsoleReporting() && !Request::isAjax())
                View::addToDbLogging($query, $current_db, $affected_rows, $query_time, $mysqli->error);
            if (Environment::isFileReporting())
                Debug::putToWatchingFile($query . PHP_EOL . PHP_EOL);
        }
        if ($affected_rows)
            return $mysqli->affected_rows;
        return $result;
    }

    public static function getRequestsLog()
    {
        return self::$requests_log;
    }

    public static function simpleRunQuery($query, $current_db = self::DB_FF)
    {
        $mysqli = self::getMysqli($current_db);
        $result = $mysqli->query($query);
        if (!$result) {
            return $mysqli->error;
        }
        return true;
    }

    public static function multiInsert($table_name, $params, $ignore = false)
    {
        $ignore = $ignore ? ' IGNORE ' : '';
        if (!is_array($params))
            return false;
        $fields = implode(',', array_keys($params[0]));
        $sql = [];
        foreach ($params as $row) {
            $row = array_map('addslashes', $row);
            $sql[] = "('" . implode("','", $row) . "')";
        }
        return self::runQuery("INSERT {$ignore} INTO {$table_name} ({$fields}) VALUES " . implode(',', $sql), true);
    }

    public static function insert($table_name, $params, $onDuplicateKeyUpdate = false)
    {
        if (!is_array($params))
            return false;
        $fields = implode(',', array_keys($params));
        $values = array_map('addslashes', array_values($params));
        $sql = "INSERT INTO {$table_name} ({$fields}) VALUES ('" . implode("','", $values) . "')";
        if ($onDuplicateKeyUpdate) {
            $updates = implode( ',', array_map( function( $value ) { return "$value = VALUES($value)"; } , array_keys($params)));
            $sql .= " ON DUPLICATE KEY UPDATE " . $updates;
        }
        return self::runQuery($sql);
    }

    public static function fetchAssoc($query, $current_db = self::DB_FF)
    {
        $result = self::runQuery($query, false, $current_db);
        $data = [];
        while ($row = $result->fetch_assoc())
            $data[] = $row;
        return $data;
    }

    public static function getProcessList(): array
    {
        $query = 'SHOW processlist';
        $result = self::runQuery($query);
        $list = [];
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['Info']) && $row['Info'] !== $query)
                $list[] = [
                    'query' => $row['Info'],
                    'state' => $row['State'],
                    'time' => $row['Time']
                ];
        }
        return $list;
    }

    public static function fetchObject($query, $class_name = null, $current_db = self::DB_FF)
    {
        $result = self::runQuery($query, false, $current_db);
        $data = [];
        while ($row = $result->fetch_object($class_name))
            $data[] = $row;
        return $data;
    }

    public static function fetchRow($query, $current_db = self::DB_FF)
    {
        $result = self::runQuery($query, false, $current_db);
        $data = [];
        while ($row = $result->fetch_row()) {
            $data[] = $row;
        }
        return $data;
    }

    public static function fetchAssocWithKey($query, $key, $current_db = self::DB_FF)
    {
        $result = self::runQuery($query, false, $current_db);
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[$row[$key]] = $row;
        }
        return $data;
    }

    public static function fetchAssocWithKeyAndBind($query, $key, array $binds = [], $current_db = self::DB_FF)
    {
        $query = self::bind($query, $current_db, $binds);
        return self::fetchAssocWithKey($query, $key, $current_db);
    }

    public static function fetchArrayOneField($query, $current_db = self::DB_FF)
    {
        $result = self::runQuery($query, false, $current_db);
        $data = [];
        while ($row = $result->fetch_row())
            $data[] = $row[0];
        return $data;
    }

    public static function fetchArrayOneFieldWithBind($query, array $binds = [], $current_db = self::DB_FF)
    {
        $query = self::bind($query, $current_db, $binds);
        return self::fetchArrayOneField($query, $current_db);
    }

    public static function fetchOneAssoc($query, $current_db = self::DB_FF)
    {
        $result = self::runQuery($query, false, $current_db);
        $data = $result->fetch_assoc();
        if (Environment::isDev())
            if ($result->num_rows > 1)
                trigger_error("In fetch_one_assoc(query) result has more then one row:\n$query\n");
        return $data;
    }

    public static function fetchScalar($query, $current_db = self::DB_FF)
    {
        $result = self::runQuery($query, false, $current_db);
        $data = $result->fetch_row();
        if (!$data)
            return false;
        return $data[0];
    }

    public static function lastInsertId($current_db = self::DB_FF)
    {
        return self::getMysqli($current_db)->insert_id;
    }

    public static function getTableFields($table, $current_db = self::DB_FF)
    {
        static $fields_names = [];
        if (!empty($fields_names[$table]))
            return $fields_names[$table];
        $sql = "SHOW COLUMNS FROM $table";
        $fields = self::fetchAssoc($sql, $current_db);
        foreach ($fields as $field)
            $fields_names[$table][$field['Field']] = ['type' => $field['Type'], 'null' => $field['Null'] === 'YES'];
        return $fields_names[$table];
    }

    public static function saveRow($table_name, $params, $current_db = self::DB_FF, $id_field = 'id')
    {
        if (empty($table_name) || empty($params))
            return false;
        $order_fields = self::getTableFields($table_name, $current_db);
        $sql_set_string = '';
        foreach ($params as $key => $val) {
            if (!isset($order_fields[$key]) || $key === $id_field)
                continue;
            if (!isset($val) && !$order_fields[$key]['null'])
                continue;
            if (isset($order_fields[$key]))
                $sql_set_string .= "`" . addslashes($key) .
                    (!is_null($val) ? ("`='" . addslashes($val) . "',") : '`=NULL,');
        }
        $sql_set_string = rtrim($sql_set_string, ',');
        //in case of update
        if (isset($params[$id_field])) {
            $id = (int)$params[$id_field];
            $sql = "UPDATE " . addslashes($table_name) . " SET {$sql_set_string} WHERE $id_field = {$id}";
            return self::runQuery($sql, false, $current_db);
        }
        //in case of insert
        $sql = "INSERT INTO " . addslashes($table_name) . " SET {$sql_set_string}";
        if (!self::runQuery($sql, false, $current_db))
            return false;
        return self::lastInsertId($current_db);
    }

    public static function setTransactionIsolation($level = self::LEVEL_REPEATABLE_READ,
                                                   $scope = self::SCOPE_SESSION,
                                                   $current_db = self::DB_FF)
    {
        if (empty($level) || !in_array($level, self::ISOLATION_LEVELS)) {
            Debug::sendDebugInfo('setTransactionIsolation', "Level of isolation is not found (DB $current_db)");
            return false;
        }
        if (empty($scope) || !in_array($scope, self::ISOLATION_SCOPES)) {
            Debug::sendDebugInfo('setTransactionIsolation', "Scope of isolation is not found (DB $current_db)");
            return false;
        }
        return self::runQuery("SET $scope TRANSACTION ISOLATION LEVEL $level", false, $current_db);
    }

    public static function startTransaction($level = '',
                                            $scope = self::SCOPE_SESSION,
                                            $current_db = self::DB_FF)
    {
        self::$transaction_counter[$current_db] = (self::$transaction_counter[$current_db] ?? 0) + 1;
        if (self::$transaction_counter[$current_db] > 1)
            return true;
        if (!empty($level))
            if (!self::setTransactionIsolation($level, $scope, $current_db))
                return false;
        if (self::$transaction_pool[$current_db])
            return true;
        if (self::runQuery('START TRANSACTION', false, $current_db))
            self::$transaction_pool[$current_db] = $level ? $scope : true;
        return isset(self::$transaction_pool[$current_db]) ? self::$transaction_pool[$current_db] : false;
    }

    public static function commitTransaction($current_db = self::DB_FF)
    {
        if (!isset(self::$transaction_counter[$current_db]) || self::$transaction_counter[$current_db] <= 0) {
            Debug::sendDebugInfo('commitTransaction() used but transaction not started', 'commitTransaction() used but transaction not started on DB ' . $current_db);
            return;
        }
        self::$transaction_counter[$current_db]--;
        if (self::$transaction_counter[$current_db] > 0)
            return;
        if (!self::$transaction_pool[$current_db]) {
            Debug::sendDebugInfo(
                'commitTransaction() used but transaction not started',
                'commitTransaction() used but transaction not started on DB ' . $current_db
            );
            return;
        }
        $scope = self::$transaction_pool[$current_db];
        self::$transaction_pool[$current_db] = false;
        self::runQuery('COMMIT', false, $current_db);
        if (is_string($scope))
            self::setTransactionIsolation(self::LEVEL_REPEATABLE_READ, $scope, $current_db);
    }

    public static function rollbackTransaction($current_db = self::DB_FF, ?\Exception $e = null)
    {
        if (!isset(self::$transaction_counter[$current_db]) || self::$transaction_counter[$current_db] <= 0) {
            Debug::sendDebugInfo('rollbackTransaction() used but transaction not started', 'rollbackTransaction() used but transaction not started on DB ' . $current_db);
            return;
        }
        if (self::$transaction_counter[$current_db] > 1) {
            self::$transaction_counter[$current_db]--;
            if ($e === null)
                self::outputDbError(['db' => $current_db, 'msg' => "Rollback internal transaction"]);
            throw $e;
        }
        self::$transaction_counter[$current_db] = 0;
        if (!self::$transaction_pool[$current_db]) {
            Debug::sendDebugInfo(
                'rollbackTransaction() used but transaction not started',
                'rollbackTransaction() used but transaction not started on DB ' . $current_db
            );
            return;
        }
        $scope = self::$transaction_pool[$current_db];
        self::$transaction_pool[$current_db] = false;
        self::runQuery('ROLLBACK', false, $current_db);
        if (is_string($scope))
            self::setTransactionIsolation(self::LEVEL_REPEATABLE_READ, $scope, $current_db);
    }

    /**
     * @param string $current_db
     * @return bool True if a transaction started via startTransaction() is currently open on $current_db.
     */
    public static function isOpenTransaction($current_db = self::DB_FF): bool
    {
        return isset(self::$transaction_counter[$current_db]) && self::$transaction_counter[$current_db] > 0;
    }

    /**
     * @return no-return
     * @throws DBException
     */
    private static function outputDbError($data)
    {
        throw new DBException($data['db'] . " - " . $data['msg']);
    }

    public static function escapeLike($str)
    {
        return addslashes(addcslashes($str, "_%"));
    }

    public static function escapeMatch($str)
    {
        $res = preg_replace("/[\/\\\!\@\#\$\%\^\&\*\(\)\[\]\{\}\;\:\+\=\.\,\?\|\'\"\<\>]+/", "", " $str ");
        return trim(preg_replace("/ +/", " ", str_ireplace([' the ', ' a ', ' an ', '-'], [' ', ' ', ' ', ''], $res)));
    }

    public static function escapeArrayOfStrings($array)
    {
        if (!is_array($array))
            $array = [$array];
        return '\'' . implode('\', \'', array_map('addslashes', $array)) . '\'';
    }

    public static function getUUIDShort()
    {
        return self::fetchScalar("SELECT UUID_SHORT()");
    }
}
