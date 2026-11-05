<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SizeHistory extends Entity
{
    private $id;
    private $size_id;
    private $user_id;
    private $event;
    private $addition;
    private $created_at;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getSizeId()
    {
        return $this->size_id;
    }

    public function setSizeId($size_id)
    {
        $this->size_id = (int)$size_id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = (int)$user_id;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function setEvent($event)
    {
        $this->event = (string)$event;
    }

    public function getAddition()
    {
        return $this->addition;
    }

    public function setAddition($addition)
    {
        $this->addition = (string)$addition;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreatedAt($created_at)
    {
        $this->created_at = date(DATE_ATOM, $created_at);
    }


    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('size_history', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'size_id' => $this->getSizeId(),
            'user_id' => $this->getUserId(),
            'event' => $this->getEvent(),
            'addition' => $this->getAddition(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}