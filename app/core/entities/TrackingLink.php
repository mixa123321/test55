<?php

namespace app\core\entities;

class TrackingLink extends Entity
{
    private $hash;
    private $order_id;
    private $created_at;

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setId(string $hash): TrackingLink
    {
        $this->hash = $hash;
        return $this;
    }

    public function getOrderId(): int
    {
        return (int)$this->order_id;
    }

    public function setOrderId(int $order_id): TrackingLink
    {
        $this->order_id = $order_id;
        return $this;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    public function setCreatedAt(string $created_at): TrackingLink
    {
        $this->created_at = $created_at;
        return $this;
    }


    public function toArray()
    {
        return [
            'hash' => $this->getHash(),
            'order_id' => $this->getOrderId(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}