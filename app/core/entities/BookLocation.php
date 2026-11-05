<?php

namespace app\core\entities;

class BookLocation extends Entity
{
    private $supplier_warehouse_id;
    private $location_id;

    public function getSupplierWarehouseId(): int
    {
        return $this->supplier_warehouse_id;
    }

    public function setSupplierWarehouseId($id): BookLocation
    {
        $this->supplier_warehouse_id = (int)$id;
        return $this;
    }

    public function getLocationId(): int
    {
        return $this->location_id;
    }

    public function setLocationId($id): BookLocation
    {
        $this->location_id = (int)$id;
        return $this;
    }

    public function toArray()
    {
        return [
            'supplier_warehouse_id' => $this->getSupplierWarehouseId(),
            'location_id' => $this->getLocationId(),
        ];
    }
}