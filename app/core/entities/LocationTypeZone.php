<?php

namespace app\core\entities;

class LocationTypeZone extends Entity
{

    private $type_id = '';
    private $warehouse_id = 0;
    private $zone = '';

    public function getTypeId(): string
    {
        return $this->type_id;
    }

    public function setTypeId(string $type_id)
    {
        $this->type_id = $type_id;
    }

    public function getWarehouseId(): int
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId(int $warehouse_id)
    {
        $this->warehouse_id = $warehouse_id;
    }

    public function getZone(): string
    {
        return $this->zone;
    }

    public function setZone(string $zone)
    {
        $this->zone = $zone;
    }

    public function toArray(): array
    {
        return [
            'type_id' => $this->type_id,
            'warehouse_id' => $this->warehouse_id,
            'zone' => $this->zone
        ];
    }
}