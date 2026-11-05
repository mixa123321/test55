<?php

namespace app\core\entities;

use app\backend\models\Locations;
use app\backend\models\Users;
use app\core\db\Mysql;

class Location extends Entity
{
    public const LOCATION_NAME_REGEXP = '#\S{1,10}\-\S{1,2}\-\S{1,10}\-\S{1,10}\-\S{1,10}#';

    const PF = 'PF';
    const ST = 'ST';
    const PA = 'PA';
    const PR = 'PR';
    const CA = 'CA';
    const DH = 'DH';
    const OB = 'OB';
    const TP = 'TP';
    const LP = 'LP';
    const US = 'US';

    const LOCKED = 0;
    const ACTIVE = 1;
    const DETACHED = 2;
    const ALL_STATUSES = [
        self::LOCKED, self::ACTIVE, self::DETACHED
    ];

    const ALL_TYPES = [
        self::PF,
        self::ST,
        self::PA,
        self::PR,
        self::CA,
        self::DH,
        self::OB,
        self::TP,
        self::LP,
        self::US,
    ];

    const UNIQUE_SKU_TYPES = [
        self::PF,
    ];

    const STORAGE_TYPES = [
        self::PF,
        self::ST,
    ];

    const ALLOWED_TYPES = [
        self::PF,
        self::ST,
        self::PA,
        self::CA,
        self::DH,
        self::OB,
        self::TP,
        self::LP,
    ];

    const CART_TYPES = [
        self::CA,
        self::US,
    ];

    const PACKING_TABLE_TYPES = [
        self::TP,
        self::LP,
    ];

    const PICK_AND_PACK_TYPES = [
        self::OB,
        self::TP,
        self::LP,
        self::CA,
        self::US,
    ];

    const NOT_COUNTED_TYPES = [
        self::PA,
        self::PR,
        self::DH,
    ];

    const SPECIAL_LOCATION__VIRTUAL = [self::ST, 'VIR', 'TUAL'];
    const SPECIAL_LOCATION__CART = [self::CA, 'CA', 'CA'];
    const SPECIAL_LOCATION__VIRTUAL_CART = [self::US, 'ER', 'TR'];
    const SPECIAL_LOCATION__ORDER_BIN = [self::OB, 'OB', 'OB'];
    const SPECIAL_LOCATION__TABLE_PACK = [self::TP, 'TP', 'TP'];
    const SPECIAL_LOCATION__LARGE_TABLE_PACK = [self::LP, 'LP', 'LP'];
    const SPECIAL_LOCATION__CANCEL = [self::ST, 'ORD', 'CAN', 'CEL'];
    const SPECIAL_LOCATION__EXPIRED = [self::ST, 'EX', 'PI', 'RED'];
    const SPECIAL_LOCATION__DH_ST = [self::DH, 'ST', 'ST', 'ST'];
    const SPECIAL_LOCATION__DH_M = [self::DH, 'CNT', 'CNT', 'M'];
    const SPECIAL_LOCATION__DH_P = [self::DH, 'CNT', 'CNT', 'P'];
    const SPECIAL_LOCATION__PA = [self::PA, 'PA', 'PA', 'PA'];
    const SPECIAL_LOCATION__NOVERIFY = [self::PA, 'NO', 'VER', 'IFY'];

    private $id;
    private $warehouse;
    private $type_id;
    private $zone;
    private $column;
    private $row;
    private $alias;
    private $size_id;
    private $mezzanine;
    private $state;
    private $is_non_picking;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getWarehouse()
    {
        return $this->warehouse;
    }

    public function setWarehouse($warehouse)
    {
        $this->warehouse = (int)$warehouse;
    }

    public function getTypeId()
    {
        return $this->type_id;
    }

    public function setTypeId($type_id)
    {
        $this->type_id = (string)$type_id;
    }

    public function getZone()
    {
        return $this->zone;
    }

    public function setZone($zone)
    {
        $this->zone = (string)$zone;
    }

    public function getColumn()
    {
        return $this->column;
    }

    public function setColumn($column)
    {
        $this->column = (string)$column;
    }

    public function getRow()
    {
        return $this->row;
    }

    public function setRow($row)
    {
        $this->row = (string)$row;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getAlternativeAlias()
    {
        return (string)($this->getZone() . $this->getColumn() . $this->getRow());
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    public function getSizeId()
    {
        return $this->size_id;
    }

    public function setSizeId($size_id)
    {
        $this->size_id = (int)$size_id;
    }

    public function getMezzanine()
    {
        return $this->mezzanine;
    }

    public function setMezzanine($mezzanine)
    {
        $this->mezzanine = (int)$mezzanine;
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state)
    {
        $this->state = (int)$state;
    }

    public function getIsNonPicking()
    {
        return $this->is_non_picking;
    }

    public function setIsNonPicking($nonPicking)
    {
        $this->is_non_picking = (int)$nonPicking;
    }

    public function isMoveable(): bool
    {
        return $this->ST();
    }

    public function getLocation($label)
    {
        if ($this->US()) {
            $user = Users::getById($this->getRow());
            if (empty($user))
                return '';
            return sprintf("Virtual (%s)", $user->getLogin());
        } else
            return $this->getLocationReal($label);
    }

    public function getLocationReal($label)
    {
        return self::getLocationName(
            $label,
            $this->getTypeId(),
            $this->getZone(),
            $this->getColumn(),
            $this->getRow()
        );
    }

    public static function getLocationName($warehouse, $type_id, $zone, $column, $row)
    {
        return sprintf("%s-%s-%s-%s-%s", $warehouse, $type_id, $zone, $column, $row);
    }

    public static function isLocationName($name): bool
    {
        $name = trim($name);
        if (empty($name) || !is_string($name))
            return false;
        return (bool)preg_match(self::LOCATION_NAME_REGEXP, $name);
    }

    public static function isPF($type_id): bool
    {
        return $type_id === self::PF;
    }

    public function PF(): bool
    {
        return self::isPF($this->getTypeId());
    }

    public static function isST($type_id): bool
    {
        return $type_id === self::ST;
    }

    public function ST(): bool
    {
        return self::isST($this->getTypeId());
    }

    public static function isOB($type_id): bool
    {
        return $type_id === 'OB';
    }

    public function OB(): bool
    {
        return self::isOB($this->getTypeId());
    }

    public static function isDH($type_id): bool
    {
        return $type_id === self::DH;
    }

    public function DH(): bool
    {
        return self::isDH($this->getTypeId());
    }

    public static function isTP($type_id): bool
    {
        return $type_id === self::TP;
    }

    public static function isLP($type_id): bool
    {
        return $type_id === self::LP;
    }

    public static function isPA($type_id): bool
    {
        return $type_id === self::PA;
    }

    public function PA(): bool
    {
        return self::isPA($this->getTypeId());
    }

    public static function isPR($type_id): bool
    {
        return $type_id === self::PR;
    }

    public function PR(): bool
    {
        return self::isPR($this->getTypeId());
    }

    public static function isCA($type_id): bool
    {
        return $type_id === self::CA;
    }

    public function CA(): bool
    {
        return self::isCA($this->getTypeId());
    }

    public static function isUS($type_id): bool
    {
        return $type_id === self::US;
    }

    public function US(): bool
    {
        return self::isUS($this->getTypeId());
    }

    public function isActive(): bool
    {
        return $this->getState() == self::ACTIVE;
    }

    public function isLocked(): bool
    {
        return $this->getState() == self::LOCKED;
    }

    public function showNonPicking(): bool
    {
        return $this->storage() && !Locations::isExpiredLocation($this) && !Locations::isCancelLocation($this);
    }

    public function isDetached(): bool
    {
        return $this->getState() == self::DETACHED;
    }

    public static function isAllowed($type_id): bool
    {
        return in_array($type_id, self::ALLOWED_TYPES, true);
    }

    public function allowed(): bool
    {
        return self::isAllowed($this->getTypeId());
    }

    public static function isStorage($type_id): bool
    {
        return in_array($type_id, self::STORAGE_TYPES, true);
    }

    public function storage(): bool
    {
        return self::isStorage($this->getTypeId());
    }

    public static function isCart($type_id): bool
    {
        return in_array($type_id, self::CART_TYPES, true);
    }

    public function cart(): bool
    {
        return self::isCart($this->getTypeId());
    }

    public function getTypeTitle(): string
    {
        if ($this->cart())
            return 'trolley';
        if ($this->ST() && $this->isMoveable())
            return 'pallet';
        return 'unexpected location';
    }

    public static function isNotCounted($type_id): bool
    {
        return in_array($type_id, self::NOT_COUNTED_TYPES, true);
    }

    public function notCounted(): bool
    {
        return self::isNotCounted($this->getTypeId());
    }

    public static function isPackingTable($type_id): bool
    {
        return in_array($type_id, self::PACKING_TABLE_TYPES, true);
    }

    public static function isPickAndPack($type_id): bool
    {
        return in_array($type_id, self::PICK_AND_PACK_TYPES, true);
    }

    public static function isForReservation(Location $origin, Location $destination, bool $phaseB = false): bool
    {
        return ($origin->ST() || $origin->cart())
            && ($destination->PF() || $destination->cart() || ($phaseB && $destination->ST()));
    }

    public static function hasUniqueSKU($type_id): bool
    {
        return in_array($type_id, self::UNIQUE_SKU_TYPES, true);
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow("locations", $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'warehouse' => $this->getWarehouse(),
            'type_id' => $this->getTypeId(),
            'zone' => $this->getZone(),
            'column' => $this->getColumn(),
            'row' => $this->getRow(),
            'alias' => $this->getAlias(),
            'size_id' => $this->getSizeId(),
            'mezzanine' => $this->getMezzanine(),
            'state' => $this->getState(),
            'is_non_picking' => $this->getIsNonPicking(),
            'is_moveable' => $this->isMoveable(),
        ];
    }
}
