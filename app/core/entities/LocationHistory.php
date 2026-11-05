<?php

namespace app\core\entities;

use app\core\db\Mysql;

class LocationHistory extends Entity
{
    private $id;
    private $location_id;
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

    public function getLocationId()
    {
        return $this->location_id;
    }

    public function setLocationId($location_id)
    {
        $this->location_id = (int)$location_id;
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
        $id = Mysql::saveRow('location_history', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'location_id' => $this->getLocationId(),
            'user_id' => $this->getUserId(),
            'event' => $this->getEvent(),
            'addition' => $this->getAddition(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}