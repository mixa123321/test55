<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\interfaces\AccessPermissionEntity;

class RouteRoleAccess extends Entity implements AccessPermissionEntity
{
    private $id;
    private $role_id;
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

    public function getRoleId()
    {
        return $this->role_id;
    }

    public function setRoleId($role_id): void
    {
        $this->role_id = $role_id;
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
            $this->setId(Mysql::saveRow("route_role_accesses", $data));
        } else {
            Mysql::saveRow("route_role_accesses", $data);
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'role_id' => $this->getRoleId(),
            'route_id' => $this->getRouteId(),
            'access' => $this->getAccess(),
        ];
    }
}
