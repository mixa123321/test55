<?php

namespace app\core\entities;

class ChargeComplex extends Entity
{
    const TYPE_SIMPLE       = 0;
    const TYPE_PACKAGING    = 1;
    const TYPE_CHARGES      = [
        self::TYPE_SIMPLE => [
            Charge::TYPE_STORAGE_PF,
            Charge::TYPE_STORAGE_ST,
            Charge::TYPE_PICK,
            Charge::TYPE_LOCATION_CULLING,
            Charge::TYPE_BOOKING_IN,
            Charge::TYPE_CULLING_PICK,
            Charge::TYPE_BUNDLING_PICK,
        ],
        self::TYPE_PACKAGING => [
            Charge::TYPE_PACKAGING,
            Charge::TYPE_CULLING_PACKAGING,
            Charge::TYPE_BUNDLING_PACKAGING,
        ],
    ];

    const STATUS_LOCK       = 0;
    const STATUS_ACTIVE     = 1;
    const STATUS_DISABLE    = 2;

    private $id;
    private $charge_id;
    private $supplier_warehouse_id;
    private $type = self::TYPE_SIMPLE;
    private $type_id;
    private $status = self::STATUS_ACTIVE;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): ChargeComplex
    {
        $this->id = $id;
        return $this;
    }

    public function getChargeId(): int
    {
        return $this->charge_id;
    }

    public function setChargeId(int $id): ChargeComplex
    {
        $this->charge_id = $id;
        return $this;
    }

    public function getSupplierWarehouseId(): int
    {
        return $this->supplier_warehouse_id;
    }

    public function setSupplierWarehouseId(int $id): ChargeComplex
    {
        $this->supplier_warehouse_id = $id;
        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $info): ChargeComplex
    {
        $this->type = $info;
        return $this;
    }

    public static function getTypeCharge($charge): ?int
    {
        foreach (self::TYPE_CHARGES as $type_charge => $types)
            if (in_array($charge, $types))
                return $type_charge;
        return null;
    }

    public function getTypeId(): int
    {
        return $this->type_id;
    }

    public function setTypeId(int $id): ChargeComplex
    {
        $this->type_id = $id;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $value): ChargeComplex
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
            'type' => $this->getType(),
            'type_id' => $this->getTypeId(),
            'status' => $this->getStatus(),
        ];
    }
}