<?php

namespace app\core\entities;

class BookRangeLocation extends Entity
{
    private $supplier_warehouse_id;
    private $type_id;
    private $zone;
    private $column;

    public function getSupplierWarehouseId(): int
    {
        return $this->supplier_warehouse_id;
    }

    public function setSupplierWarehouseId($id): BookRangeLocation
    {
        $this->supplier_warehouse_id = (int)$id;
        return $this;
    }

    public function getTypeId(): string
    {
        return $this->type_id;
    }

    public function setTypeId($id): BookRangeLocation
    {
        $this->type_id = (string)$id;
        return $this;
    }

    public function getZone(): string
    {
        return $this->zone;
    }

    public function setZone($zone): BookRangeLocation
    {
        $this->zone = (string)$zone;
        return $this;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function setColumn($column): BookRangeLocation
    {
        $this->column = (string)$column;
        return $this;
    }

    public function toArray()
    {
        return [
            'supplier_warehouse_id' => $this->getSupplierWarehouseId(),
            'type_id' => $this->getTypeId(),
            'zone' => $this->getZone(),
            'column' => $this->getColumn(),
        ];
    }
}