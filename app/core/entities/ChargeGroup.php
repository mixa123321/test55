<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ChargeGroup extends Entity
{
    const STATUS_LOCK   = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_NAMES = [
        self::STATUS_LOCK => 'Lock',
        self::STATUS_ACTIVE => 'Active',
    ];

    private $id;
    private $warehouse_id;
    private $name;
    private $code;
    private $status = self::STATUS_ACTIVE;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): ChargeGroup
    {
        $this->id = $id;
        return $this;
    }

    public function getWarehouseId(): ?int
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId(int $id): ChargeGroup
    {
        $this->warehouse_id = $id;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $info): ChargeGroup
    {
        $this->name = $info;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $info): ChargeGroup
    {
        $this->code = $info;
        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $info): ChargeGroup
    {
        $this->status = $info;
        return $this;
    }

    public static function isActive(int $status): ?bool
    {
        return $status == self::STATUS_ACTIVE;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('charge_groups', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'warehouse_id' => $this->getWarehouseId(),
            'name' => $this->getName(),
            'code' => $this->getCode(),
            'status' => $this->getStatus(),
        ];
    }
}