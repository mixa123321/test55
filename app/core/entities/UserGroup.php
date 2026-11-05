<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\interfaces\AccessObjectEntity;

class UserGroup extends Entity implements AccessObjectEntity
{
    private $id;
    private $name;
    private $create_date;
    private $is_active;
    private $access_scope;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return (string)$this->name;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function getCreateDate()
    {
        return $this->create_date;
    }

    public function setCreateDate($create_date): void
    {
        $this->create_date = $create_date;
    }

    public function getIsActive(): int
    {
        return (int)$this->is_active;
    }

    public function setIsActive($is_active): void
    {
        $this->is_active = $is_active;
    }

    public function getAccessScope()
    {
        return is_array($this->access_scope) ? $this->access_scope : [];
    }

    public function setAccessScope($access_scope)
    {
        if (is_string($access_scope))
            $access_scope = json_decode($access_scope, true);
        $this->access_scope = (array)$access_scope;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save)) {
            return;
        }
        $data = $this->toArray();
        $data['access_scope'] = json_encode($data['access_scope']);
        foreach ($data as $data_field => $data_value) {
            if (!\in_array($data_field, $fields_to_save, true)) {
                unset($data[$data_field]);
            }
        }
        if (!$this->getId()) {
            $this->setId(Mysql::saveRow("user_groups", $data));
        } else {
            Mysql::saveRow("user_groups", $data);
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'create_date' => $this->getCreateDate(),
            'is_active' => $this->getIsActive(),
            'access_scope' => $this->getAccessScope(),
        ];
    }

    public function isActive(): bool
    {
        return !empty($this->is_active);
    }

    /**
     * @return bool
     */
    public function isBlocked(): bool
    {
        return empty($this->is_active);
    }
}
