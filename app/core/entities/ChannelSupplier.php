<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ChannelSupplier extends Entity
{
    private $id;
    private $channel_id;
    private $supplier_id;
    private $user_id;
    private $primary_shop_id;

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

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($supplier_id)
    {
        $this->supplier_id = (int)$supplier_id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = (int)$user_id;
    }

    public function getPrimaryShopId()
    {
        return $this->primary_shop_id;
    }

    public function setPrimaryShopId($id)
    {
        $this->primary_shop_id = (int)$id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('channels_suppliers', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        $return = [
            'id' => $this->getId(),
            'channel_id' => $this->getChannelId(),
            'supplier_id' => $this->getSupplierId(),
            'user_id' => $this->getUserId(),
            'primary_shop_id' => $this->getPrimaryShopId(),
        ];
        return $return;
    }
}