<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Lang extends Entity
{
    private $id;
    private $name;
    private $code;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int) $id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = (string) $name;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setCode($name)
    {
        $this->code = (string) $name;
    }

    public function save()
    {
        $data = $this->toArray();
        if (empty($data['name']) || empty($data['code']))
            return;
        if (empty($data['id']))
            unset($data['id']);
        $id = Mysql::saveRow('langs', $data);
        if (empty($data['id']))
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'code' => $this->getCode(),
        ];
    }
}