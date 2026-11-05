<?php


namespace app\core\channels;

use app\core\helpers\Str;

class BaseCredentials
{
    public $shop_url;

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

    public function getShopUrl()
    {
        return $this->shop_url;
    }

    public function setShopUrl($shop_url)
    {
        $this->shop_url = (string)$shop_url;
    }

    public function toArray(){
        $result = [];
        foreach (get_object_vars($this) as $var => $val)
            if($val)
                $result[$var] = $val;
        return $result;
    }

    public function toJson(){
        return json_encode($this->toArray());
    }
}