<?php

namespace app\core\entities;

use app\core\db\Mysql;

class UserGroupRole extends Entity
{

    private $id;
    private $user_group_id;
    private $role_id;


    public function getId(): int
    {
        return (int)$this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getUserGroupId(): int
    {
        return (int)$this->user_group_id;
    }

    public function setUserGroupId($user_group_id): void
    {
        $this->user_group_id = (int)$user_group_id;
    }

    public function getRoleId(): int
    {
        return (int)$this->role_id;
    }

    public function setRoleId($role_id): void
    {
        $this->role_id = (int)$role_id;
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
            $this->setId(Mysql::saveRow("user_group_roles", $data));
        } else {
            Mysql::saveRow("user_group_roles", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'user_group_id' => $this->getUserGroupId(),
            'role_id' => $this->getRoleId(),
        ];
    }
}
