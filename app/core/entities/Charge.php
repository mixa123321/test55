<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Charge extends Entity
{
    const TYPE_RECURRING            = 0;
    const TYPE_STORAGE_PF           = 1;
    const TYPE_STORAGE_ST           = 2;
    const TYPE_PICK                 = 3;
    const TYPE_PACKAGING            = 4;
    const TYPE_LOCATION_CULLING     = 5;
    const TYPE_BOOKING_IN           = 6;
    const TYPE_CULLING_PACKAGING    = 7;
    const TYPE_BUNDLING_PACKAGING   = 8;
    const TYPE_CULLING_PICK         = 9;
    const TYPE_BUNDLING_PICK        = 10;

    const TYPE_NAMES = [
        self::TYPE_STORAGE_PF => 'ChargeStoragePF',
        self::TYPE_STORAGE_ST => 'ChargeStorageST',
        self::TYPE_PICK => 'Picks',
        self::TYPE_PACKAGING => 'Packagings',
        self::TYPE_LOCATION_CULLING => 'LocationCulling',
        self::TYPE_BOOKING_IN => 'Booking',
        self::TYPE_CULLING_PACKAGING => 'CullingPackagings',
        self::TYPE_BUNDLING_PACKAGING => 'BundlingPackagings',
        self::TYPE_CULLING_PICK => 'CullingPicks',
        self::TYPE_BUNDLING_PICK => 'BundlingPicks',
    ];
    const TYPE_FULL_NAMES = [
        self::TYPE_STORAGE_PF => 'Pick Bin Storage Charge (Per Bin/Per Week)',
        self::TYPE_STORAGE_ST => 'Pallet Storage Charge (Per Pallet/Per Week)',
        self::TYPE_PICK => 'Charge For Picks Service',
        self::TYPE_PACKAGING => 'Packaging',
        self::TYPE_LOCATION_CULLING => 'Location Culling',
        self::TYPE_BOOKING_IN => 'Charge For Booking In Service',
        self::TYPE_CULLING_PACKAGING => 'Cost for Recycling',
        self::TYPE_BUNDLING_PACKAGING => 'Packaging',
        self::TYPE_CULLING_PICK => 'Charge For Culling Picks Service',
        self::TYPE_BUNDLING_PICK => 'Charge For Bundling Picks Service',
    ];

    const TYPE_EQUAL = [
        self::TYPE_BUNDLING_PACKAGING => self::TYPE_PACKAGING,
        self::TYPE_CULLING_PICK => self::TYPE_PICK,
        self::TYPE_BUNDLING_PICK => self::TYPE_PICK,
    ];

    const STATUS_LOCK   = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_NAMES = [
        self::STATUS_LOCK => 'Lock',
        self::STATUS_ACTIVE => 'Active',
    ];

    private $id;
    private $name;
    private $code;
    private $type;
    private $status = self::STATUS_ACTIVE;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): Charge
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $info): Charge
    {
        $this->name = $info;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $info): Charge
    {
        $this->code = $info;
        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $info): Charge
    {
        $this->type = $info;
        return $this;
    }

    public static function isStandard(int $type): ?bool
    {
        return in_array($type, array_keys(self::TYPE_NAMES));
    }

    public function standard(): ?bool
    {
        return self::isStandard($this->getType());
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $info): Charge
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
        $id = Mysql::saveRow('charges', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'code' => $this->getCode(),
            'type' => $this->getType(),
            'status' => $this->getStatus(),
        ];
    }
}