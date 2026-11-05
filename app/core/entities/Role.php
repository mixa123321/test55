<?php

namespace app\core\entities;

use app\backend\models\Roles;
use app\core\db\Mysql;
use app\core\interfaces\AccessObjectEntity;

class Role extends Entity implements AccessObjectEntity
{
    private $id;
    private $name;
    private $constant;
    private $side;
    private $multiple_warehouses;
    private $sub_role;
    private $create_date;
    private $is_active;
    private $api_key;
    private $user_groups;
    private $person;
    private $warehouse;

    public function getId(): int
    {
        return (int)$this->id;
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

    public function getConstant()
    {
        return $this->constant;
    }

    public function setConstant($constant): void
    {
        $this->constant = $constant;
    }

    public function getSide()
    {
        return $this->side;
    }

    public function setSide($side): void
    {
        $this->side = $side;
    }

    public function getMultipleWarehouses(): int
    {
        return (int)$this->multiple_warehouses;
    }

    public function setMultipleWarehouses($multiple_warehouses): void
    {
        $this->multiple_warehouses = $multiple_warehouses;
    }

    public function getSubRole()
    {
        return $this->sub_role;
    }

    public function setSubRole($sub_role): void
    {
        $this->sub_role = $sub_role;
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

    public function getApiKey()
    {
        return $this->api_key;
    }

    public function setApiKey($api_key): void
    {
        $this->api_key = $api_key;
    }

    public function getUserGroups()
    {
        return $this->user_groups;
    }

    /**
     * @param mixed $user_groups
     */
    public function setUserGroups($user_groups): void
    {
        $this->user_groups = $user_groups;
    }

    /**
     * @return mixed
     */
    public function getPerson()
    {
        return $this->person;
    }

    public function setPerson($person): void
    {
        $this->person = $person;
    }

    public function getWarehouse()
    {
        return $this->warehouse;
    }

    public function setWarehouse($warehouse): void
    {
        $this->warehouse = $warehouse;
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
            $this->setId(Mysql::saveRow("roles", $data));
        } else {
            Mysql::saveRow("roles", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'constant' => $this->getConstant(),
            'side' => $this->getSide(),
            'multiple_warehouses' => $this->getMultipleWarehouses(),
            'sub_role' => $this->getSubRole(),
            'create_date' => $this->getCreateDate(),
            'is_active' => $this->getIsActive(),
            'api_key' => $this->getApiKey(),
            'user_groups' => $this->getUserGroups(),
            'person' => $this->getPerson(),
            'warehouse' => $this->getWarehouse(),
        ];
    }

    public function isActive(): bool
    {
        return !empty($this->getIsActive());
    }

    public function isBlocked(): bool
    {
        return empty($this->getIsActive());
    }

    public function isWarehouseSide(): bool
    {
        return $this->getSide() === Roles::SIDE_WAREHOUSE;
    }

    public function isClientSide(): bool
    {
        return $this->getSide() === Roles::SIDE_CLIENT;
    }

    public function isMultipleWarehouses(): bool
    {
        return !empty($this->multiple_warehouses);
    }

    public function hasApiKey(): bool
    {
        return !empty($this->api_key);
    }

    public function hasUserGroups(): bool
    {
        return !empty($this->user_groups);
    }

    public function hasPerson(): bool
    {
        return !empty($this->person);
    }

    public function hasSelectedWarehouse(): bool
    {
        return !empty($this->warehouse);
    }

    public function isSA(): bool
    {
        return $this->getId() === Roles::SA;
    }

    public function isManager(): bool
    {
        return $this->getId() === Roles::MANAGER;
    }

    public function isWorker(): bool
    {
        return $this->getId() === Roles::WORKER;
    }

    public function isSupplierAdmin(): bool
    {
        return $this->getId() === Roles::SUPPLIER_ADMIN;
    }

    public function isSupplierUser(): bool
    {
        return $this->getId() === Roles::SUPPLIER_USER;
    }

    public function isRole($role_id): bool
    {
        return $this->getId() === (int)$role_id;
    }
}
