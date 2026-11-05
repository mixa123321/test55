<?php

namespace app\core\entities\config;

class WarehousesConfig extends Config
{
    const TABLE_NAME = 'warehouses_config';
    const ID = 'warehouse_id';

    private $warehouse_id;

    public function getWarehouseId(): int
    {
        return (int)$this->warehouse_id;
    }

    public function setWarehouseId(int $warehouse_id): Config
    {
        $this->warehouse_id = $warehouse_id;
        return $this;
    }

    public function toArray(): array
    {
        return [
            self::ID => $this->getWarehouseId(),
            'type' => $this->getType(),
            self::FIELD_VALUE_BOOL => $this->getValueBool(),
            self::FIELD_VALUE_INT => $this->getValueInt(),
        ];
    }
}