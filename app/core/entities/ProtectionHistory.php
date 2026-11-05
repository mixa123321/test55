<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class ProtectionHistory extends Entity
{
   private $id;
   private $attack_date;
   private $host;
   private $params;
   private $ip;
   private $continent;
   private $country_code;
   private $country_name;

          
    public function getId() 
    {
        return $this->id;
    }
                  
    public function setId($id): void 
    {
        $this->id = (int)$id;
    }
      
    public function getAttackDate() 
    {
        return $this->attack_date;
    }
                  
    public function setAttackDate($attack_date): void 
    {
        $this->attack_date = date(WithDate::DATE_SQL_FORMAT, $attack_date);
    }
      
    public function getHost() 
    {
        return $this->host;
    }
                  
    public function setHost($host): void 
    {
        $this->host = (string)$host;
    }
      
    public function getParams() 
    {
        return $this->params;
    }
                  
    public function setParams($params): void 
    {
        $this->params = (string)$params;
    }

    public function getIp()
    {
        return $this->ip;
    }
                  
    public function setIp($ip): void 
    {
        $this->ip = (string)$ip;
    }
      
    public function getContinent() 
    {
        return $this->continent;
    }
                  
    public function setContinent($continent): void 
    {
        $this->continent = (string)$continent;
    }
      
    public function getCountryCode() 
    {
        return $this->country_code;
    }
                  
    public function setCountryCode($country_code): void 
    {
        $this->country_code = (string)$country_code;
    }

    public function getCountryName()
    {
        return $this->country_name;
    }
                  
    public function setCountryName($country_name): void 
    {
        $this->country_name = (string)$country_name;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save)) {
            return;
        }
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value) {
            if (!\in_array($data_field, $fields_to_save, true)) {
                unset($data[$data_field]);
            }
        }
        if (!$this->getId()) {
            $this->setId(Mysql::saveRow("protection_histories", $data));
        } else {
            Mysql::saveRow("protection_histories", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'attack_date' => $this->getAttackDate(),
            'host' => $this->getHost(),
            'params' => $this->getParams(),
            'ip' => $this->getIp(),
            'continent' => $this->getContinent(),
            'country_code' => $this->getCountryCode(),
            'country_name' => $this->getCountryName(),
        ];
    }
}
