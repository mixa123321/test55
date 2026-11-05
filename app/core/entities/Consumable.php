<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Consumable extends Entity
{
    const STATUS_LOCKED     = 0;
    const STATUS_ACTIVE     = 1;
    const STATUSES = [
        self::STATUS_LOCKED => 'Locked',
        self::STATUS_ACTIVE => 'Active',
    ];

    const TYPE_CONSUMABLE   = 1;
    const TYPE_PACKAGING    = 2;
    const TYPES = [
        self::TYPE_CONSUMABLE => 'Consumable',
        self::TYPE_PACKAGING => 'Packaging',
    ];

    private $id;
    private $name;
    private $type = self::TYPE_CONSUMABLE;
    private $supplier_id;
    private $width;
    private $length;
    private $height;
    private $weight;
    private $max_weight;
    private $customer_ref;
    private $status = self::STATUS_ACTIVE;
    private $reuse = 1;

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

    public function setName($txt)
    {
        $this->name = (string)$txt;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($num)
    {
        $this->type = (int)$num;
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($id)
    {
        $this->supplier_id = (int)$id;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function setWidth($val)
    {
        $this->width = (float)$val;
    }

    public function getLength()
    {
        return $this->length;
    }

    public function setLength($val)
    {
        $this->length = (float)$val;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function setHeight($val)
    {
        $this->height = (float)$val;
    }

    public function getWeight()
    {
        return $this->weight;
    }

    public function setWeight($val)
    {
        $this->weight = (float)$val;
    }

    public function getMaxWeight()
    {
        return $this->max_weight;
    }

    public function setMaxWeight($val)
    {
        $this->max_weight = (float)$val;
    }

    public function getCustomerRef()
    {
        return $this->customer_ref;
    }

    public function setCustomerRef($txt)
    {
        $this->customer_ref = (string)$txt;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = (int)$status;
    }

    public function getReuse()
    {
        return $this->reuse;
    }

    public function setReuse($reuse)
    {
        $this->reuse = (int)$reuse;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('consumables', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'type' => $this->getType(),
            'supplier_id' => $this->getSupplierId(),
            'width' => $this->getWidth(),
            'length' => $this->getLength(),
            'height' => $this->getHeight(),
            'weight' => $this->getWeight(),
            'max_weight' => $this->getMaxWeight(),
            'customer_ref' => $this->getCustomerRef(),
            'status' => $this->getStatus(),
            'reuse' => $this->getReuse(),
        ];
    }
}