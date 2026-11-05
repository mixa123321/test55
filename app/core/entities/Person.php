<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\interfaces\AccessObjectEntity;

class Person extends Entity implements AccessObjectEntity
{
    private $id;
    private $email;
    private $name;
    private $phone;
    private $user_id;
    private $lc_id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = (string)$email;
    }

    public function getName(): string
    {
        return (string)$this->name;
    }

    public function setName($name)
    {
        $this->name = (string)$name;
    }

    public function getPhone()
    {
        return $this->phone;
    }

    public function setPhone($phone)
    {
        $this->phone = (string)$phone;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = (string)$user_id;
    }

    public function getLCId()
    {
        return $this->lc_id;
    }

    public function setLCId($id)
    {
        $this->lc_id = (string)$id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        if ($this->getId() == 0)
            unset($data['id']);
        $id = Mysql::saveRow('persons', $data);
        if (empty($this->getId()))
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'phone' => $this->getPhone(),
            'email' => $this->getEmail(),
            'user_id' => $this->getUserId(),
            'lc_id' => $this->getLCId(),
        ];
    }

    public function isActive(): bool
    {
        return true;
    }

    public function isBlocked(): bool
    {
        return false;
    }
}
