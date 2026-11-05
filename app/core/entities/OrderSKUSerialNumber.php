<?php

namespace app\core\entities;

class OrderSKUSerialNumber extends Entity
{
    private $order_id;
    private $SKU_id;
    private $serial_number;
    private $orders_packages_id;
    private $created_at;

    public function getOrderId()
    {
        return (int)$this->order_id;
    }

    public function setOrderId(int $order_id): OrderSKUSerialNumber
    {
        $this->order_id = $order_id;
        return $this;
    }

    public function getSKUId()
    {
        return (int)$this->SKU_id;
    }

    public function setSKUId(int $SKU_id): OrderSKUSerialNumber
    {
        $this->SKU_id = $SKU_id;
        return $this;
    }

    public function getSerialNumber(): string
    {
        return (string)$this->serial_number;
    }

    public function setSerialNumber(string $serial_number): OrderSKUSerialNumber
    {
        $this->serial_number = $serial_number;
        return $this;
    }

    public function getOrdersPackagesId()
    {
        return (int)$this->orders_packages_id;
    }

    public function setOrdersPackagesId(int $orders_packages_id): OrderSKUSerialNumber
    {
        $this->orders_packages_id = $orders_packages_id;
        return $this;
    }

    public function getCreatedAt(): string
    {
        return (string)$this->created_at;
    }

    public function setCreatedAt(string $created_at): OrderSKUSerialNumber
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function toArray()
    {
        return [
            'order_id' => $this->getOrderId(),
            'SKU_id' => $this->getSKUId(),
            'serial_number' => $this->getSerialNumber(),
            'orders_packages_id' => $this->getOrdersPackagesId(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}