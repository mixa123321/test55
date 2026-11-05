<?php

namespace app\core\entities;

class BookLocationHistory extends Entity
{
    const TYPE_ID_ST = Location::ST;
    const TYPE_ID_PF = Location::PF;

    const EVENT_ADD = 'add';
    const EVENT_REMOVE = 'remove';

    const TYPE_RANGE = 'range';
    const TYPE_RANGES = 'ranges';
    const TYPE_LOCATION = 'location';
    const TYPE_LOCATIONS = 'locations';

    private $supplier_warehouse_id;
    private $type_id;
    private $event;
    private $type;
    private $created_at;
    private $user_id;
    private $addition;

    public function getSupplierWarehouseId(): int
    {
        return $this->supplier_warehouse_id;
    }

    public function setSupplierWarehouseId(int $id): BookLocationHistory
    {
        $this->supplier_warehouse_id = $id;
        return $this;
    }

    public function getTypeId(): string
    {
        return $this->type_id;
    }

    public function setTypeId(string $id): BookLocationHistory
    {
        $this->type_id = $id;
        return $this;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function setEvent(string $id): BookLocationHistory
    {
        $this->event = $id;
        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType(string $type): BookLocationHistory
    {
        $this->type = $type;
        return $this;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreatedAt($created_at): BookLocationHistory
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): BookLocationHistory
    {
        $this->user_id = $user_id;
        return $this;
    }

    public function getAddition(): array
    {
        return $this->addition;
    }

    public function setAddition($addition): BookLocationHistory
    {
        if (is_string($addition))
            $addition = json_decode($addition, true);
        $this->addition = (array)$addition;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'supplier_warehouse_id' => $this->getSupplierWarehouseId(),
            'type_id' => $this->getTypeId(),
            'event' => $this->getEvent(),
            'type' => $this->getType(),
            'created_at' => $this->getCreatedAt(),
            'user_id' => $this->getUserId(),
            'addition' => $this->getAddition(),
        ];
    }
}
