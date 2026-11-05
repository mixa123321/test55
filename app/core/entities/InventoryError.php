<?php

namespace app\core\entities;

use app\core\db\Mysql;

class InventoryError extends Entity
{

    private $id;
    private $channel_id;
    private $shop_warehouse_id;
    private $item_id;
    private $code;
    private $message;
    private $created_at;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getChannelId()
    {
        return $this->channel_id;
    }

    public function setChannelId($channel_id)
    {
        $this->channel_id = (int)$channel_id;
    }

    public function getShopWarehouseId()
    {
        return $this->shop_warehouse_id;
    }

    public function setShopWarehouseId($shop_warehouse_id)
    {
        $this->shop_warehouse_id = (int)$shop_warehouse_id;
    }

    public function getItemId()
    {
        return $this->item_id;
    }

    public function setItemId($item_id)
    {
        $this->item_id = (string)$item_id;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setCode($code)
    {
        $this->code = (int)$code;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message)
    {
        $this->message = (string)$message;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreatedAt($created_at)
    {
        $this->created_at = date(DATE_ATOM, $created_at);
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('inventory_errors', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'channel_id' => $this->getChannelId(),
            'shop_warehouse_id' => $this->getShopWarehouseId(),
            'item_id' => $this->getItemId(),
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
