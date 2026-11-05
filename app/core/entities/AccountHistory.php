<?php

namespace app\core\entities;

use app\core\db\Mysql;

class AccountHistory extends Entity
{

    const EVENT_CREATE = 'create';
    const EVENT_UPDATE = 'update';
    const EVENT_SET = 'set';
    const EVENT_ADD = 'add';
    const EVENT_DELETE = 'delete';

    private $id;
    private $object_id;
    private $object_type;
    private $event;
    private $additional;
    private $user_id;
    private $create_date;


    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getObjectId()
    {
        return $this->object_id;
    }

    public function setObjectId($object_id): void
    {
        $this->object_id = $object_id;
    }

    public function getObjectType()
    {
        return $this->object_type;
    }

    public function setObjectType($object_type): void
    {
        $this->object_type = $object_type;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function setEvent($event): void
    {
        $this->event = $event;
    }

    public function getAdditional()
    {
        return $this->additional;
    }

    public function setAdditional($additional): void
    {
        $this->additional = $additional;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getCreateDate()
    {
        return $this->create_date;
    }

    public function setCreateDate($create_date): void
    {
        $this->create_date = $create_date;
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
            $this->setId(Mysql::saveRow("account_history", $data));
        } else {
            Mysql::saveRow("account_history", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'object_id' => $this->getObjectId(),
            'object_type' => $this->getObjectType(),
            'event' => $this->getEvent(),
            'additional' => $this->getAdditional(),
            'user_id' => $this->getUserId(),
            'create_date' => $this->getCreateDate(),
        ];
    }
}
