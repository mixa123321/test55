<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ReturnSupplierWarehouse extends Entity
{
    private $supplier_warehouse_id;
    private $return_carrier_id;

    public function getSupplierWarehouseId()
    {
        return (int)$this->supplier_warehouse_id;
    }

    public function setSupplierWarehouseId($id)
    {
        $this->supplier_warehouse_id = (int)$id;
    }

    public function getReturnCarrierId()
    {
        return $this->return_carrier_id;
    }

    public function setReturnCarrierId($id)
    {
        $this->return_carrier_id = (int)$id;
    }

    public function save()
    {
        $data = $this->toArray();
        if (empty($this->getSupplierWarehouseId()) || empty($this->getReturnCarrierId()))
            return;
        Mysql::saveRow('return_supplier_warehouses', $data);
    }

    public function toArray()
    {
        return [
            'supplier_warehouse_id' => $this->getSupplierWarehouseId(),
            'return_carrier_id' => $this->getReturnCarrierId(),
        ];
    }
}
