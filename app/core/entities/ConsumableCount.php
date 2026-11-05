<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ConsumableCount extends Entity
{
    private $consumable_id;
    private $warehouse_id;
    private $quantity;
    private $min_stock_level;

    public function getConsumableId()
    {
        return $this->consumable_id;
    }

    public function setConsumableId($id)
    {
        $this->consumable_id = (int)$id;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($num)
    {
        $this->quantity = (int)$num;
    }

    public function getMinStockLevel()
    {
        return $this->min_stock_level;
    }

    public function setMinStockLevel($num)
    {
        $this->min_stock_level = (int)$num;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        Mysql::saveRow('consumable_count', $data);
    }

    public function toArray()
    {
        return [
            'consumable_id' => $this->getConsumableId(),
            'warehouse_id' => $this->getWarehouseId(),
            'quantity' => $this->getQuantity(),
            'min_stock_level' => $this->getMinStockLevel(),
        ];
    }
}