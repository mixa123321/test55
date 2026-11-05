<?php

namespace app\core\entities;

use app\core\db\Mysql;

class RouteAccessHistory extends Entity
{

    private $id;
    private $route_id;
    private $object_type;
    private $object_id;
    private $access;
    private $addition;
    private $create_date;
    private $create_user_id;


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

    public function getObjectType()
    {
        return $this->object_type;
    }

    public function setObjectType($object_type): void
    {
        $this->object_type = $object_type;
    }

    public function getObjectId()
    {
        return $this->object_id;
    }

    public function setObjectId($object_id): void
    {
        $this->object_id = $object_id;
    }

    public function getAccess()
    {
        return $this->access;
    }

    public function setAccess($access): void
    {
        $this->access = $access;
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

    public function getCreateUserId()
    {
        return $this->create_user_id;
    }

    public function setCreateUserId($create_user_id): void
    {
        $this->create_user_id = $create_user_id;
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
            $this->setId(Mysql::saveRow("route_access_history", $data));
        } else {
            Mysql::saveRow("route_access_history", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'route_id' => $this->getRouteId(),
            'object_type' => $this->getObjectType(),
            'object_id' => $this->getObjectId(),
            'access' => $this->getAccess(),
            'addition' => $this->getAddition(),
            'create_date' => $this->getCreateDate(),
            'create_user_id' => $this->getCreateUserId(),
        ];
    }
}
