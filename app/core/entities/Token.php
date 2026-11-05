<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Token extends Entity
{
    private $id;
    private $token;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int) $id;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($name)
    {
        $this->token = (string) $name;
    }

    public function save()
    {
        $data = $this->toArray();
        if (empty($data['token']))
            return;
        if (empty($data['id']))
            unset($data['id']);
        $id = Mysql::saveRow('tokens', $data);
        if (empty($data['id']))
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'token' => $this->getToken(),
        ];
    }
}