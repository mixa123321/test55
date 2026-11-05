<?php

namespace app\core\entities;

use app\core\db\Mysql;

class RouteHistory extends Entity
{

    const EVENT_CREATE = 'create';
    const EVENT_UPDATE = 'update';
    const EVENT_ADD_CHILD = 'add_child';
    const EVENT_REMOVE_CHILD = 'remove_child';
    const EVENT_LOCK = 'lock';
    const EVENT_UNLOCK = 'unlock';
    const EVENT_DELETE = 'delete';

    private $id;
    private $route_id;
    private $event_type;
    private $addition;
    private $create_date;
    private $user_id;


    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getRouteId()
    {
        return $this->route_id;
    }

    public function setRouteId($route_id): void
    {
        $this->route_id = $route_id;
    }

    public function getEventType()
    {
        return $this->event_type;
    }

    public function setEventType($event_type): void
    {
        $this->event_type = $event_type;
    }

    public function getAddition()
    {
        return $this->addition;
    }

    public function setAddition($addition): void
    {
        $this->addition = $addition;
    }

    public function getCreateDate()
    {
        return $this->create_date;
    }

    public function setCreateDate($create_date): void
    {
        $this->create_date = $create_date;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id): void
    {
        $this->user_id = $user_id;
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
            $this->setId(Mysql::saveRow("route_history", $data));
        } else {
            Mysql::saveRow("route_history", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'route_id' => $this->getRouteId(),
            'event_type' => $this->getEventType(),
            'addition' => $this->getAddition(),
            'create_date' => $this->getCreateDate(),
            'user_id' => $this->getUserId()
        ];
    }
}
