<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class ShippingServiceWarehouseHistory extends Entity
{

    private $id;
    private $shipping_service_id;
    private $warehouse_id;
    private $user_id;
    private $create_date;
    private $type;

    const TYPE_ADD = 'add';
    const TYPE_UNSET = 'unset';
    const TYPE_ERROR = 'error';
    const TYPE_DEFAULT = 'default';

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getShippingServiceId()
    {
        return $this->shipping_service_id;
    }

    public function setShippingServiceId($shipping_service_id): void
    {
        $this->shipping_service_id = $shipping_service_id;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($warehouse_id)
    {
        $this->warehouse_id = $warehouse_id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getCreateDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->create_date);
    }

    public function setCreateDate($create_date)
    {
        $this->create_date = WithDate::dateToInt($create_date);
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type): void
    {
        $this->type = $type;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('shipping_service_warehouses_history', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'shipping_service_id' => $this->getShippingServiceId(),
            'warehouse_id' => $this->getWarehouseId(),
            'user_id' => $this->getUserId(),
            'create_date' => $this->getCreateDate(),
            'type' => $this->getType()
        ];
    }
}

