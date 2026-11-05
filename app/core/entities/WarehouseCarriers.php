<?php

namespace app\core\entities;

use app\core\dbal\SafeMysql;

class WarehouseCarriers extends Entity
{
    private $warehouse_id;
    private $lc_carrier_id;

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getLCCarrierId()
    {
        return $this->lc_carrier_id;
    }

    public function setLCCarrierId($id)
    {
        $this->lc_carrier_id = (int)$id;
    }

    public function toArray()
    {
        $return = [
            'warehouse_id' => $this->getWarehouseId(),
            'lc_carrier_id' => $this->getLCCarrierId(),
        ];
        return $return;
    }

    public function save()
    {
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (empty($data_value))
                return;
        $placeholders = [];
        foreach ($data as $field => $value)
            $placeholders[] = ":$field";
        (new SafeMysql())->execute("
            INSERT IGNORE INTO warehouse_carriers (" . implode(',', array_keys($data)) . ")
            VALUES (" . implode(',', $placeholders) . ")", $data);
    }
}