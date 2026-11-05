<?php

namespace app\core\virtual;

use app\core\helpers\Str;

class BaseVirtualCredentials
{
    protected $host;
    protected $user_name;
    protected $pass;
    protected $supplier_id;

    public function __construct($data)
    {
        if (is_object($data))
            $data = $data->toArray();
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

    public function getUserName()
    {
        return $this->user_name;
    }

    public function setUserName($name)
    {
        $this->user_name = (string)$name;
    }

    public function getPass()
    {
        return $this->pass;
    }

    public function setPass($pass)
    {
        $this->pass = (string)$pass;
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($id)
    {
        $this->supplier_id = (int)$id;
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

    public static function getClassName($class)
    {
        return "\\app\\core\\virtual\\" . strtolower($class) . "\\{$class}Credentials";
    }
}
