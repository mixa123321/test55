<?php

namespace app\core\entities;

class ChargeRecurring extends Entity
{
    const STATUS_LOCK       = 0;
    const STATUS_ACTIVE     = 1;

    private $id;
    private $charge_id;
    private $supplier_warehouse_id;
    private $status = self::STATUS_ACTIVE;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): ChargeRecurring
    {
        $this->id = $id;
        return $this;
    }

    public function getChargeId(): int
    {
        return $this->charge_id;
    }

    public function setChargeId(int $id): ChargeRecurring
    {
        $this->charge_id = $id;
        return $this;
    }

    public function getSupplierWarehouseId(): int
    {
        return $this->supplier_warehouse_id;
    }

    public function setSupplierWarehouseId(int $id): ChargeRecurring
    {
        $this->supplier_warehouse_id = $id;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $value): ChargeRecurring
    {
        $this->status = $value;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'charge_id' => $this->getChargeId(),
            'supplier_warehouse_id' => $this->getSupplierWarehouseId(),
            'status' => $this->getStatus(),
        ];
    }
}