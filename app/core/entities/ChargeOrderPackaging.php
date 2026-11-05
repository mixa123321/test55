<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ChargeOrderPackaging extends Entity
{
    private $ib_charge_id;
    private $order_id;
    private $packaging_id;
    private $packaging;

    public function getIBChargeId(): ?int
    {
        return $this->ib_charge_id;
    }

    public function setIBChargeId(int $id): ChargeOrderPackaging
    {
        $this->ib_charge_id = $id;
        return $this;
    }

    public function getOrderId(): ?int
    {
        return $this->order_id;
    }

    public function setOrderId(int $id): ChargeOrderPackaging
    {
        $this->order_id = $id;
        return $this;
    }

    public function getPackagingId(): ?int
    {
        return $this->packaging_id;
    }

    public function setPackagingId(int $id): ChargeOrderPackaging
    {
        $this->packaging_id = $id;
        return $this;
    }

    public function getPackaging(): ?float
    {
        return $this->packaging_id;
    }

    public function setPackaging(float $price): ChargeOrderPackaging
    {
        $this->packaging = $price;
        return $this;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        Mysql::saveRow('charge_order_packaging', $data);
    }

    public function toArray(): array
    {
        return [
            'ib_charge_id' => $this->getIBChargeId(),
            'order_id' => $this->getOrderId(),
            'packaging_id' => $this->getPackagingId(),
            'packaging' => $this->getPackaging(),
        ];
    }
}