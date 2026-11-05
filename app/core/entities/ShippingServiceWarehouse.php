<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ShippingServiceWarehouse extends Entity
{

    private $shipping_service_id;
    private $warehouse_id;

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

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        Mysql::saveRow('shipping_service_warehouses', $data);
    }

    public function toArray()
    {
        return [
            'shipping_service_id' => $this->getShippingServiceId(),
            'warehouse_id' => $this->getWarehouseId(),
        ];
    }
}

