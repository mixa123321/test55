<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ShopInventoryLog extends Entity
{
    private $id;
    private $shop_id;
    private $warehouse_id;
    private $type;
    private $object_id;
    private $user_id;
    private $create_date;
    private $SKU_id;
    private $quantity;
    private $additional;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getShopId()
    {
        return $this->shop_id;
    }

    public function setShopId($id)
    {
        $this->shop_id = (int)$id;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($value)
    {
        $this->type = (string)$value;
    }

    public function getObjectId()
    {
        return $this->object_id;
    }

    public function setObjectId($id)
    {
        $this->object_id = (int)$id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($id)
    {
        $this->user_id = (int)$id;
    }

    public function getCreateDate()
    {
        return $this->create_date;
    }

    public function setCreateDate($date)
    {
        $this->create_date = $date;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($id)
    {
        $this->SKU_id = (int)$id;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($num)
    {
        $this->quantity = (int)$num;
    }

    public function getAdditional()
    {
        $res = $this->additional;
        if (is_string($res))
            $res = json_decode($res, 1);
        return $res;
    }

    public function setAdditional($info)
    {
        $this->additional = $info;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        $data['additional'] = json_encode($data['additional']);
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('shop_inventory_logs', $data);
        if (!$this->getId())
            $this->setId($id);
    }


    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'shop_id' => $this->getShopId(),
            'warehouse_id' => $this->getWarehouseId(),
            'type' => $this->getType(),
            'object_id' => $this->getObjectId(),
            'user_id' => $this->getUserId(),
            'create_date' => $this->getCreateDate(),
            'SKU_id' => $this->getSKUId(),
            'quantity' => $this->getQuantity(),
            'additional' => $this->getAdditional(),
        ];
    }
}