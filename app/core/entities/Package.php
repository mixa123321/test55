<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Package extends Entity
{
    const STATE_ACTIVE = 1;
    const STATE_LOCKED = 0;

    private $id;
    private $name;
    private $instruction;
    private $supplier_id;
    private $state;
    private $barcode;
    private $packaging_id;

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

    public function getInstruction()
    {
        return $this->instruction;
    }

    public function setInstruction($instruction)
    {
        $this->instruction = (string)$instruction;
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($supplier_id)
    {
        $this->supplier_id = (int)$supplier_id;
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state)
    {
        $this->state = (int)$state;
    }

    public function getBarcode()
    {
        return $this->barcode;
    }

    public function setBarcode($txt)
    {
        $this->barcode = (string)$txt;
    }

    public function getPackagingId()
    {
        return $this->packaging_id;
    }

    public function setPackagingId($id)
    {
        $this->packaging_id = (int)$id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('packages', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'instruction' => $this->getInstruction(),
            'supplier_id' => $this->getSupplierId(),
            'state' => $this->getState(),
            'barcode' => $this->getBarcode(),
            'packaging_id' => $this->getPackagingId(),
        ];
    }
}
