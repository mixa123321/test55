<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\interfaces\AccessPermissionEntity;

class RouteUserGroupAccess extends Entity implements AccessPermissionEntity
{
    private $id;
    private $user_group_id;
    private $route_id;
    private $access;


    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getUserGroupId()
    {
        return $this->user_group_id;
    }

    public function setUserGroupId($user_group_id): void
    {
        $this->user_group_id = $user_group_id;
    }

    public function getRouteId()
    {
        return $this->route_id;
    }

    public function setRouteId($route_id): void
    {
        $this->route_id = $route_id;
    }

    public function getAccess(): int
    {
        return (int)$this->access;
    }

    public function setAccess($access): void
    {
        $this->access = $access;
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
            $this->setId(Mysql::saveRow("route_user_group_accesses", $data));
        } else {
            Mysql::saveRow("route_user_group_accesses", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'user_group_id' => $this->getUserGroupId(),
            'route_id' => $this->getRouteId(),
            'access' => $this->getAccess(),
        ];
    }
}
