<?php

declare(strict_types=1);

namespace app\core\dbal;

use app\core\dbal\exceptions\DbConnectionException;
use app\core\Environment;
use app\core\helpers\Debug;
use mysqli;
use app\core\db\Mysql;

/**
 * MysqlConnectionFactory is responsible only for creating and caching mysqli connections.
 *
 * Responsibilities:
 * - resolve connection parameters from project constants
 * - create mysqli connections
 * - cache connections in an internal pool
 * - reconnect when requested
 * - detect typical "connection lost" conditions
 *
 * This class intentionally does not execute SQL queries.
 * Query execution belongs to SafeMysql.
 */
final class MysqlConnectionFactory
{
    private const DEFAULT_CHARSET = 'utf8mb4';

    public const DB_FF = 'FF';
    public const DB_SFTP = 'SFTP';

    /**
     * @var array<string, mysqli|null>
     */
    private static $dbPool = [
        self::DB_FF => null,
        self::DB_SFTP => null,
    ];

    /**
     * Returns a pooled mysqli connection.
     *
     * @param string $currentDb
     * @param bool $reconnect
     * @return mysqli
     */
    public static function getConnection($currentDb = self::DB_FF, $reconnect = false)
    {
        if (!array_key_exists($currentDb, self::$dbPool)) {
            self::$dbPool[$currentDb] = null;
        }

        if (self::$dbPool[$currentDb] === null || $reconnect) {
            $dbConParams = self::getDbConParams($currentDb);

            if ($dbConParams === []) {
                self::outputConnectionError(
                    $currentDb,
                    sprintf('DB connection params not found for "%s".', $currentDb)
                );
            }

            $waitTime = 250000;

            if (Environment::isRobot()) {
                $maxWaitTime = 300000000; // 5 minutes
            } else {
                $maxWaitTime = 16000000; // 16 seconds
            }

            while (true) {
                $mysqli = new mysqli(
                    $dbConParams['host'],
                    $dbConParams['user'],
                    $dbConParams['pass'],
                    $dbConParams['name']
                );

                if ($mysqli->connect_error || !$mysqli->set_charset(self::DEFAULT_CHARSET)) {
                    if (self::isConnectionLost($mysqli)) {
                        if ($waitTime < $maxWaitTime) {
                            $waitTime *= 2;
                            usleep($waitTime);
                            continue;
                        }

                        self::outputConnectionError(
                            $currentDb,
                            sprintf('%s DB connection failed after reaching max wait time.', $currentDb)
                        );
                    }

                    self::outputConnectionError(
                        $currentDb,
                        sprintf(
                            '%s DB connection failed. %s.',
                            $currentDb,
                            $mysqli->connect_error
                        )
                    );
                }
                $mysqli->query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';");
                self::$dbPool[$currentDb] = $mysqli;
                break;
            }
        }

        return self::$dbPool[$currentDb];
    }

    /**
     * Returns the legacy Mysql class's own connection for the given db key, so that code built
     * on SafeMysql can share a single physical connection - and therefore a single transaction -
     * with legacy `Mysql::` code.
     *
     * This is an intentional, narrow bridge to the legacy connection pool for the ongoing
     * SafeMysql migration - it is not a general-purpose substitute for {@see getConnection()}.
     * Db keys are shared string constants between `Mysql` and `MysqlConnectionFactory` (both
     * define `DB_FF = 'FF'`, `DB_SFTP = 'SFTP'`), so no mapping is needed.
     *
     * Unlike getConnection(), this performs no local pooling - it always asks Mysql for its
     * current connection, so a reconnect performed by Mysql is picked up on the next call.
     *
     * @param string $currentDb
     * @return mysqli
     */
    public static function getSharedConnection($currentDb = self::DB_FF)
    {
        $connection = Mysql::getMysqli($currentDb);

        if (!$connection instanceof mysqli) {
            self::outputConnectionError(
                $currentDb,
                sprintf('Mysql::getMysqli() did not return a valid mysqli instance for "%s".', $currentDb)
            );
        }

        return $connection;
    }

    /**
     * Returns a pooled mysqli server connection without selecting a database.
     *
     * This is useful for server-level operations such as:
     * - CREATE DATABASE
     * - DROP DATABASE
     * - importing schema into a newly created database
     *
     * @param string $currentDb
     * @param bool $reconnect
     * @return mysqli
     */
    public static function getServerConnection($currentDb = self::DB_FF, $reconnect = false)
    {
        $poolKey = $currentDb . '_SERVER';

        if (!array_key_exists($poolKey, self::$dbPool)) {
            self::$dbPool[$poolKey] = null;
        }

        if (self::$dbPool[$poolKey] === null || $reconnect) {
            $dbConParams = self::getDbConParams($currentDb);

            if ($dbConParams === []) {
                self::outputConnectionError(
                    $currentDb,
                    sprintf('DB connection params not found for "%s".', $currentDb)
                );
            }

            $waitTime = 250000;

            if (Environment::isRobot()) {
                $maxWaitTime = 300000000; // 5 minutes
            } else {
                $maxWaitTime = 16000000; // 16 seconds
            }

            while (true) {
                $mysqli = new mysqli(
                    $dbConParams['host'],
                    $dbConParams['user'],
                    $dbConParams['pass']
                );

                if ($mysqli->connect_error || !$mysqli->set_charset(self::DEFAULT_CHARSET)) {
                    if (self::isConnectionLost($mysqli)) {
                        if ($waitTime < $maxWaitTime) {
                            $waitTime *= 2;
                            usleep($waitTime);
                            continue;
                        }

                        self::outputConnectionError(
                            $currentDb,
                            sprintf('%s MySQL server connection failed after reaching max wait time.', $currentDb)
                        );
                    }

                    self::outputConnectionError(
                        $currentDb,
                        sprintf(
                            '%s MySQL server connection failed. %s.',
                            $currentDb,
                            $mysqli->connect_error
                        )
                    );
                }

                self::$dbPool[$poolKey] = $mysqli;
                break;
            }
        }

        $connection = self::$dbPool[$poolKey];
        if (!$connection instanceof mysqli) {
            self::outputConnectionError(
                $currentDb,
                sprintf('%s MySQL server connection is not available.', $currentDb)
            );
            throw new DbConnectionException('MySQL server connection is not available.');
        }

        return $connection;
    }

    /**
     * Returns a lightweight mysqli connection with a short timeout.
     *
     * This is the extracted version of the legacy simpleGetMysqli() logic.
     *
     * @param string $currentDb
     * @param int $connectionTimeout
     * @return mysqli|null
     */
    public static function getSimpleConnection($currentDb = self::DB_FF, $connectionTimeout = 1)
    {
        if (!array_key_exists($currentDb, self::$dbPool)) {
            self::$dbPool[$currentDb] = null;
        }

        if (self::$dbPool[$currentDb] === null) {
            $dbConParams = self::getDbConParams($currentDb);

            if ($dbConParams === []) {
                self::logWarning(
                    sprintf('DB %s connection params not found.', $currentDb)
                );
                return null;
            }

            $mysqli = mysqli_init();
            if (!$mysqli) {
                self::logWarning(
                    sprintf('Failed to initialize mysqli for DB %s.', $currentDb)
                );
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
                self::logWarning(
                    sprintf(
                        '%s DB connection failed. %s.',
                        $currentDb,
                        $mysqli->connect_error
                    )
                );
                return null;
            }

            if (!$mysqli->set_charset(self::DEFAULT_CHARSET)) {
                self::logWarning(
                    sprintf(
                        '%s DB connection established but failed to set charset to %s.',
                        $currentDb,
                        self::DEFAULT_CHARSET
                    )
                );
                return null;
            }

            self::$dbPool[$currentDb] = $mysqli;
        }

        return self::$dbPool[$currentDb];
    }

    /**
     * Drops one connection from the pool.
     *
     * @param string $currentDb
     * @return void
     */
    public static function resetConnection($currentDb = self::DB_FF)
    {
        if (isset(self::$dbPool[$currentDb]) && self::$dbPool[$currentDb] instanceof mysqli) {
            self::$dbPool[$currentDb]->close();
        }

        self::$dbPool[$currentDb] = null;
    }

    /**
     * Drops all pooled connections.
     *
     * @return void
     */
    public static function resetAllConnections()
    {
        foreach (self::$dbPool as $dbKey => $connection) {
            if ($connection instanceof mysqli) {
                $connection->close();
            }

            self::$dbPool[$dbKey] = null;
        }
    }

    /**
     * Returns connection parameters from project constants.
     *
     * Expected constants:
     * - {DB}_DB_HOST
     * - {DB}_DB_USER
     * - {DB}_DB_PASS
     * - {DB}_DB_NAME
     *
     * @param string $db
     * @return array<string, string>
     */
    public static function getDbConParams($db)
    {
        foreach (['HOST', 'USER', 'PASS', 'NAME'] as $varName) {
            if (!defined($db . '_DB_' . $varName)) {
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

    /**
     * Detects common mysqli connection-loss errors.
     *
     * @param mysqli $mysqli
     * @return bool
     */
    public static function isConnectionLost(mysqli $mysqli)
    {
        static $mysqliErrors = [
            2001, // Can't create UNIX socket
            2002, // Can't connect to local MySQL server through socket
            2003, // Can't connect to MySQL server
            2004, // Can't create TCP/IP socket
            2005, // Unknown MySQL server host
            2006, // MySQL server has gone away
            2008, // MySQL client ran out of memory
            2012, // Error in server handshake
            2013, // Lost connection to MySQL server during query
            2014, // Commands out of sync
            2024, // Error connecting to slave
            2025, // Error connecting to master
            2026, // SSL connection error
            2045, // Can't open shared memory
            2048, // Invalid connection handle
            2055, // Lost connection to MySQL server
            1053, // Server shutdown in progress
        ];

        return
            (!empty($mysqli->errno) && in_array($mysqli->errno, $mysqliErrors, true))
            || (!empty($mysqli->connect_errno) && in_array($mysqli->connect_errno, $mysqliErrors, true));
    }

    /**
     * Logs a non-fatal warning.
     *
     * @param string $message
     * @return void
     */
    private static function logWarning($message)
    {
        error_log('[MysqlConnectionFactory] ' . $message);

        if (class_exists(Debug::class)) {
            Debug::sendDebugInfo('MysqlConnectionFactory warning', $message);
        }
    }

    /**
     * Handles fatal connection errors.
     *
     * @param string $db
     * @param string $message
     * @return void
     * @throws DbConnectionException
     */
    private static function outputConnectionError($db, $message)
    {
        $fullMessage = sprintf('[%s] %s', $db, $message);

        error_log('[MysqlConnectionFactory] ' . $fullMessage);

        if (class_exists(Debug::class)) {
            Debug::sendDebugInfo('DB connection error', $fullMessage);
        }

        throw new DbConnectionException($fullMessage);
    }
}
