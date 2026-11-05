<?php

namespace app\core\services\simpleCache\db;

use Psr\SimpleCache\CacheInterface;
use app\core\dbal\SafeMysql;

class SimpleDBCache implements CacheInterface
{
    const PREFIX_SHOPIFY_OAUTH = 'shopify_oauth_';
    private $mysql;

    public function __construct(?SafeMysql $mysql = null) {
        $this->mysql = $mysql ?? new SafeMysql();
    }

    public function get($key, $default = null)
    {
        $this->validateKey($key);
        $sql = "SELECT value FROM simple_cache WHERE `key` = :key AND (expiration = 0 OR expiration >= UNIX_TIMESTAMP())";
        $result = $this->mysql->fetchScalar($sql, ['key' => $key]);
        if ($result === null || $result === false) {
            return $default;
        }
        return json_decode($result, true);
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->validateKey($key);
        if (is_int($ttl) && $ttl < 0)
            throw new SimpleDBCacheInvalidArgumentException('Wrong ttl value ' . $ttl);
        $expiration = 0;
        if ($ttl instanceof \DateInterval)
            $expiration = (new \DateTime())->add($ttl)->getTimestamp();
        elseif (is_int($ttl))
            $expiration = (new \DateTime())->add(new \DateInterval("PT{$ttl}S"))->getTimestamp();
        elseif ($ttl !== null)
            throw new SimpleDBCacheInvalidArgumentException('Wrong ttl ' . $ttl);
        $encoded_value = json_encode($value);
        if ($encoded_value === false)
            throw new SimpleDBCacheInvalidArgumentException('Wrong value ' . $value);
        $sql = '
        INSERT INTO `simple_cache` (`key`, `value`, `expiration`) VALUES (:key, :value, :expiration)
        ON DUPLICATE KEY UPDATE `value` = :value, `expiration` = :expiration';
        $this->mysql->execute($sql, ['key' => $key, 'value' => $encoded_value, 'expiration' => $expiration]);
        return true;
    }

    public function delete($key): bool
    {
        throw new SimpleDBCacheException('Method is not implemented yet.');
    }

    public function clear(): bool
    {
        $this->mysql->execute('DELETE FROM simple_cache');
        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        throw new SimpleDBCacheException('Method is not implemented yet.');
    }

    public function setMultiple($values, $ttl = null): bool
    {
        throw new SimpleDBCacheException('Method is not implemented yet.');
    }

    public function deleteMultiple($keys): bool
    {
        throw new SimpleDBCacheException('Method is not implemented yet.');
    }

    public function has($key): bool
    {
        $sql = "SELECT 1 FROM simple_cache WHERE `key` = :key AND (expiration = 0 OR expiration >= UNIX_TIMESTAMP()) LIMIT 1;";
        return (bool)$this->mysql->fetchScalar($sql, ['key' => $key]);
    }

    private function validateKey(string $key): void
    {
        if (!$key) {
            throw new SimpleDBCacheInvalidArgumentException('Cache key must not be empty.');
        }
        if (!preg_match('/^[a-zA-Z0-9_\.]{1,128}$/', $key)) {
            throw new SimpleDBCacheInvalidArgumentException("Invalid cache key $key");
        }
    }
}