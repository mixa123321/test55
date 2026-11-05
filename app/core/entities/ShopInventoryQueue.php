<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ShopInventoryQueue extends Entity
{
    const ERROR         = 0;
    const NEW           = 1;
    const PROCESSING    = 2;

    private $id;
    private $shop_warehouse_id;
    private $item_id;
    private $available;
    private $status = self::NEW;
    private $created_at;
    private $update_at;
    private $source;
    private $object_id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getShopWarehouseId()
    {
        return $this->shop_warehouse_id;
    }

    public function setShopWarehouseId($id)
    {
        $this->shop_warehouse_id = (int)$id;
    }

    public function getItemId()
    {
        return $this->item_id;
    }

    public function setItemId($id)
    {
        $this->item_id = (string)$id;
    }

    public function getAvailable()
    {
        return $this->available;
    }

    public function setAvailable($value)
    {
        $this->available = (int)$value;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($value)
    {
        $this->status = (int)$value;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreatedAt($date)
    {
        $this->created_at = $date;
    }

    public function getUpdatedAt()
    {
        return $this->update_at;
    }

    public function setUpdatedAt($date)
    {
        $this->update_at = $date;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function setSource($source)
    {
        $this->source = (string)$source;
    }

    public function getObjectId()
    {
        return $this->object_id;
    }

    public function setObjectId($object_id)
    {
        $this->object_id = (string)$object_id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save) || !$this->getId())
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        Mysql::saveRow('shop_inventory_queue', $data);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'shop_warehouse_id' => $this->getShopWarehouseId(),
            'item_id' => $this->getItemId(),
            'available' => $this->getAvailable(),
            'status' => $this->getStatus(),
            'created_at' => $this->getCreatedAt(),
            'update_at' => $this->getUpdatedAt(),
            'source' => $this->getSource(),
            'object_id' => $this->getObjectId()
        ];
    }
}