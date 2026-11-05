<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ReturnCarrier extends Entity
{
    private $id;
    private $service_id;
    private $name;
    private $credentials_key;
    private $is_test = 1;
    private $countries;
    private $state = 1;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getServiceId()
    {
        return $this->service_id;
    }

    public function setServiceId($id)
    {
        $this->service_id = (int)$id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = (string)$name;
    }

    public function getCredentialsKey()
    {
        return $this->credentials_key;
    }

    public function setCredentialsKey($key)
    {
        $this->credentials_key = (string)$key;
    }

    public function getIsTest()
    {
        return $this->is_test;
    }

    public function setIsTest($is_test)
    {
        $this->is_test = (int)$is_test;
    }

    public function getCountries()
    {
        return $this->countries ?: [];
    }

    public function setCountries($countries)
    {
        $this->countries = (is_string($countries) ? json_decode($countries, true) : $countries);
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state)
    {
        $this->state = (int)$state;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        $data['countries'] = json_encode($data['countries']);
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('return_carriers', $data);
        if (empty($this->getId()))
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'service_id' => $this->getServiceId(),
            'name' => $this->getName(),
            'credentials_key' => $this->getCredentialsKey(),
            'is_test' => $this->getIsTest(),
            'countries' => $this->getCountries(),
            'state' => $this->getState()
        ];
    }
}
