<?php

namespace app\core\flat;

use app\core\helpers\Str;

class BaseFlatCredentials
{
    public $host;

    public function __construct($data)
    {
        $this->set($data);
    }

    public function set($data)
    {
        if (empty($data))
            return;
        if (!is_array($data))
            $data = json_decode($data, true);
        foreach ($data as $name => $value){
            $methodName = "set" . Str::toCamelCase($name);
            if(!empty($value) && method_exists($this, $methodName))
                $this->$methodName($value);
        }
    }

    public function getHost()
    {
        return $this->host;
    }

    public function setHost($host)
    {
        $this->host = (string)$host;
    }

    public function toArray()
    {
        $result = [];
        foreach (get_object_vars($this) as $var => $val)
            if($val)
                $result[$var] = $val;
        return $result;
    }

    public function toJson()
    {
        return json_encode($this->toArray());
    }
}