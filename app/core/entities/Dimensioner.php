<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Dimensioner extends Entity
{
    private $id;
    private $name;
    private $unit_id;
    private $branch_id;
    private $supplier_id;
    private $warehouse_id;
    private $state;

    const LOCKED = 0;
    const ACTIVE = 1;

    const SHOW_STATE = [
        self::LOCKED => ['title' => 'Locked', 'color' => 'danger'],
        self::ACTIVE => ['title' => 'Active', 'color' => 'success'],
    ];

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = (string)$name;
    }

    public function getUnitId()
    {
        return $this->unit_id;
    }

    public function setUnitId($id)
    {
        $this->unit_id = (int)$id;
    }

    public function getBranchId()
    {
        return $this->branch_id;
    }

    public function setBranchId($id)
    {
        $this->branch_id = (int)$id;
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($id)
    {
        $this->supplier_id = (int)$id;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state)
    {
        $this->state = (int)$state;
    }

    public function getStateTitle()
    {
        return self::SHOW_STATE[$this->state]['title'];
    }

    public function getStateColor()
    {
        return self::SHOW_STATE[$this->state]['color'];
    }

    public static function changeState($state)
    {
        return $state == self::LOCKED ? self::ACTIVE : self::LOCKED;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('dimensioners', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'unit_id' => $this->getUnitId(),
            'branch_id' => $this->getBranchId(),
            'supplier_id' => $this->getSupplierId(),
            'warehouse_id' => $this->getWarehouseId(),
            'state' => $this->getState(),
        ];
    }
}
