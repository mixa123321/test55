<?php

namespace app\core\db;

use app\core\helpers\Debug;

class QueryBuilder
{
    public $query_string;

    /**
     * This field has a priority over $select_fields and $from when building a query
     * @var string for simple SELECTS like "select * from table_name..."
     */
    private $select_from;

    //$select_fields and $from MUST be used if $select_from was not specified
    private $select_fields;
    private $from;

    private $where;
    private $join;
    private $group_by;
    private $order_by;
    private $limit;
    private $having;

    public function __construct()
    {
        $this->select_fields = [];
        $this->where = [];
        $this->join = [];
        $this->having = [];
    }

    public function addWhere($string)
    {
        $this->where[] = $string;
        return $this;
    }

    /**
     * @param string $column
     * @param string $operator
     * @param int|string|array $value
     * @return $this
     */
    private const ALLOWED_OPERATORS = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE'];

    public function where($column, $value, $operator = "=")
    {
        if(is_null($value)) return $this;
        $mysqli = Mysql::getMysqli();
        if(is_array($value)) {
            $operator = 'IN';
            $escaped = array_map(array($mysqli, 'real_escape_string'), $value);
            $escapedValue = "('" . implode("','", $escaped) . "')";
        } else {
            if (!in_array($operator, self::ALLOWED_OPERATORS, true))
                throw new \InvalidArgumentException("Unsupported operator: $operator");
            $escapedValue = "'{$mysqli->real_escape_string($value)}'";
        }
        $string = "{$mysqli->real_escape_string($column)} {$operator} {$escapedValue}";
        return $this->addWhere($string);
    }

    public function addJoin($string)
    {
        $this->join[] = $string;
        return $this;
    }

    public function setSelectFrom($string)
    {
        $this->select_from = $string;
        return $this;
    }

    public function addSelectFields($fields)
    {
        $this->select_fields = array_merge($this->select_fields, $fields);
        return $this;
    }

    public function setFrom($table_name)
    {
        $this->from = $table_name;
        return $this;
    }

    public function setGroupBy($group_by)
    {
        $this->group_by = $group_by;
        return $this;
    }

    public function setOrderBy($order_by)
    {
        $this->order_by = $order_by;
        return $this;
    }

    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function addHaving(string $havingPart): self
    {
        $this->having[] = $havingPart;
        return $this;
    }


    /**
     * Build SQL query
     */
    public function build()
    {
        $this->query_string = '';
        if (!empty($this->select_from)) {
            $this->query_string .= $this->select_from;
        } elseif (!empty($this->from)) {
            if (empty($this->select_fields)) {
                $selectFields = '*';
            } elseif ($this->select_fields === ['*']) {
                $selectFields = '*';
            } else {
                $selectFields = implode(', ', $this->select_fields);
            }

            $this->query_string .= "SELECT {$selectFields} FROM {$this->from}";
        } else {
            Debug::sendDebugInfo("Query builder error in SELECT statement", var_export($this, 1));
            return null;
        }

        if (!empty($this->join)) {
            $this->join = array_unique($this->join);
            $this->query_string .= " " . implode(" ", $this->join);
        }

        if (!empty($this->where)) {
            $whereConditions = implode(") AND (", $this->where);
            $this->query_string .= " WHERE ({$whereConditions})";
        }

        foreach (['GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT'] as $statementType) {
            $statementVar = preg_replace('/\s+/', '_', mb_strtolower($statementType));

            if (isset($this->$statementVar)) {
                if ($statementVar === 'having') {
                    if (!empty($this->order_by) && !empty($this->having)) {
                        $this->having = array_unique($this->having);
                        $havingConditions = implode(") AND (", $this->having);
                        $this->query_string .= " HAVING ({$havingConditions})";
                        continue;
                    }
                } else {
                    $this->query_string .= " {$statementType} {$this->$statementVar}";
                }
            }
        }

        return $this->query_string;
    }
}
