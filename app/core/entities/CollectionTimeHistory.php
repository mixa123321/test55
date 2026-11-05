<?php

namespace app\core\entities;

use app\core\db\Mysql;

class CollectionTimeHistory extends Entity
{
    private $id;
    private $collection_time_id;
    private $created_at;
    private $event;
    private $user_id;
    private $additional;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = (int)$id;
    }

    public function getCollectionTimeId()
    {
        return $this->collection_time_id;
    }

    public function setCollectionTimeId($id): void
    {
        $this->collection_time_id = $id;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreatedAt($date): void
    {
        $this->created_at = $date;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function setEvent($event): void
    {
        $this->event = (int)$event;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($id): void
    {
        $this->user_id = (int)$id;
    }

    public function getAdditional()
    {
        return json_decode($this->additional, true);
    }

    public function setAdditional(array $array): void
    {
        $this->additional = json_encode($array);
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        if (isset($data['additional']))
            $data['additional'] = json_encode($data['additional']);
        $id = Mysql::saveRow('collection_time_history', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'collection_time_id' => $this->getCollectionTimeId(),
            'created_at' => $this->getCreatedAt(),
            'event' => $this->getEvent(),
            'user_id' => $this->getUserId(),
            'additional' => $this->getAdditional(),
        ];
    }
}