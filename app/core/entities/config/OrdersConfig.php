<?php

namespace app\core\entities\config;

class OrdersConfig extends Config
{
    const TABLE_NAME = 'orders_config';
    const ID = 'order_id';

    private $order_id;

    public function getOrderId(): int
    {
        return (int)$this->order_id;
    }

    public function setOrderId(int $order_id): Config
    {
        $this->order_id = $order_id;
        return $this;
    }

    public function toArray(): array
    {
        return [
            self::ID => $this->getOrderId(),
            'type' => $this->getType(),
            self::FIELD_VALUE_INT => $this->getValueInt(),
            self::FIELD_VALUE_STRING => $this->getValueString(),
        ];
    }
}