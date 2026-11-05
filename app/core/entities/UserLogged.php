<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class UserLogged extends Entity
{

    private $id;
    private $user_id;
    private $last_access;
    private $IPv4;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = (int)$user_id;
    }

    public function getLastAccess()
    {
        return $this->last_access;
    }

    public function setLastAccess($last_access)
    {
        $this->last_access = date(DATE_ATOM, $last_access);
    }

    public function getIPv4()
    {
        return $this->IPv4;
    }

    public function setIPv4($IPv4)
    {
        $this->IPv4 = (string)$IPv4;
    }

    public function getCountryId()
    {
        return $this->country_id;
    }

    public function setCountryId($id)
    {
        $this->country_id = (string)$id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('user_logged', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'user_id' => $this->getUserId(),
            'last_access' => $this->getLastAccess(),
            'IPv4' => $this->getIPv4(),
            'country_id' => $this->getCountryId(),
        ];
    }
}
