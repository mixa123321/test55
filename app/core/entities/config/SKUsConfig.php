<?php

namespace app\core\entities\config;

class SKUsConfig extends Config
{
    const TABLE_NAME = 'SKUs_config';
    const ID = 'SKU_id';

    private $SKU_id;

    public function getSKUId(): int
    {
        return (int)$this->SKU_id;
    }

    public function setSKUId(int $SKU_id): Config
    {
        $this->SKU_id = $SKU_id;
        return $this;
    }

    public function toArray(): array
    {
        return [
            self::ID => $this->getSKUId(),
            'type' => $this->getType(),
            self::FIELD_VALUE_STRING => $this->getValueString(),
        ];
    }
}
