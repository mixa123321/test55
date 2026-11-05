<?php

namespace app\core\entities;

use app\core\helpers\Str;

abstract class Entity
{
    public function __construct($data = [], $prefix = "")
    {
        if (!empty($prefix))
            $data = $this->filterFieldsByPrefix($data, $prefix);
        $this->setData($data);
    }

    abstract public function toArray();

    public function setData($data)
    {
        foreach ($data as $key => $val) {
            $setter = Str::toCamelCase("set_$key");
            if (method_exists($this, $setter))
                $this->$setter($val);
        }
    }

    public function setObject($data, $class_name)
    {
        if (is_array($data))
            return new $class_name($data);
        if (is_object($data) && is_a($data, $class_name))
            return $data;
        return new $class_name();
    }

    protected function filterFieldsByPrefix($data, $prefix)
    {
        $tmp_data = [];
        foreach ($data as $field_name => $field_value)
            if (stripos($field_name, $prefix) === 0) {
                $field_name_no_prefix = substr($field_name, strlen($prefix));
                $tmp_data[$field_name_no_prefix] = $field_value;
            }
        return $tmp_data;
    }
}