<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SKUWarehouse extends Entity
{
    private $SKU_id;
    private $warehouse_id;
    private $min_stock_level;


    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($SKU_id): void
    {
        $this->SKU_id = $SKU_id;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($warehouse_id): void
    {
        $this->warehouse_id = $warehouse_id;
    }

    public function getMinStockLevel()
    {
        return $this->min_stock_level;
    }

    public function setMinStockLevel($min_stock_level): void
    {
        $this->min_stock_level = $min_stock_level;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save)) {
            return;
        }
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value) {
            if (!\in_array($data_field, $fields_to_save, true)) {
                unset($data[$data_field]);
            }
        }
        Mysql::saveRow("SKU_warehouses", $data);
    }

    public function toArray()
    {
        return [
            'SKU_id' => $this->getSKUId(),
            'warehouse_id' => $this->getWarehouseId(),
            'min_stock_level' => $this->getMinStockLevel(),
        ];
    }
}
