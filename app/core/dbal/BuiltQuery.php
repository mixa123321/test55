<?php

declare(strict_types=1);

namespace app\core\dbal;

use InvalidArgumentException;

/**
 * BuiltQuery is an immutable DTO that contains compiled SQL text
 * together with a named parameter map for SafeMysql.
 */
final class BuiltQuery
{
    /**
     * @var string
     */
    private $sql;

    /**
     * @var array<string, mixed>
     */
    private $params;

    /**
     * @param string $sql Compiled SQL text with named placeholders.
     * @param array<string, mixed> $params Parameter values indexed by placeholder name.
     */
    public function __construct($sql, array $params = array())
    {
        $this->assertSql($sql);
        $this->assertParams($params);

        $this->sql = $sql;
        $this->params = $params;
    }

    /**
     * Returns compiled SQL text.
     *
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * Returns named parameters.
     *
     * @return array<string, mixed>
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Returns whether the query has any parameters.
     *
     * @return bool
     */
    public function hasParams()
    {
        return $this->params !== array();
    }

    /**
     * Returns a new DTO with merged parameters.
     *
     * Existing parameter names cannot be overwritten.
     *
     * @param array<string, mixed> $params
     * @return self
     */
    public function withParams(array $params)
    {
        $this->assertParams($params);

        $merged = $this->params;

        foreach ($params as $key => $value) {
            if (array_key_exists($key, $merged)) {
                throw new InvalidArgumentException(
                    sprintf('Duplicate SQL parameter name "%s".', $key)
                );
            }

            $merged[$key] = $value;
        }

        return new self($this->sql, $merged);
    }

    /**
     * Returns a new DTO with a different SQL string and the same params.
     *
     * @param string $sql
     * @return self
     */
    public function withSql($sql)
    {
        return new self($sql, $this->params);
    }

    /**
     * @param string $sql
     */
    private function assertSql($sql)
    {
        if (!is_string($sql) || trim($sql) === '') {
            throw new InvalidArgumentException('Built SQL must not be empty.');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function assertParams(array $params)
    {
        foreach ($params as $key => $_value) {
            if (!is_string($key) || $key === '') {
                throw new InvalidArgumentException(
                    'SQL parameter keys must be non-empty strings.'
                );
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
}
