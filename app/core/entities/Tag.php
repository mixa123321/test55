<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Tag extends Entity
{
    private $id;
    private $tag;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int) $id;
    }

    public function getTag()
    {
        return $this->tag;
    }

    public function setTag($name)
    {
        $this->tag = (string) $name;
    }

    public function save()
    {
        $data = $this->toArray();
        if (empty($data['tag']))
            return;
        if (empty($data['id']))
            unset($data['id']);
        $id = Mysql::saveRow('tags', $data);
        if (empty($data['id']))
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'tag' => $this->getTag(),
        ];
    }
}