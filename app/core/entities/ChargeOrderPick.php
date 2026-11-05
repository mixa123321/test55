<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ChargeOrderPick extends Entity
{
    private $ib_charge_id;
    private $order_id;
    private $packing_1_order;
    private $unique_SKUs;
    private $additional_SKU;
    private $total_SKUs;
    private $pick_1_SKU;

    public function getIBChargeId(): ?int
    {
        return $this->ib_charge_id;
    }

    public function setIBChargeId(int $id): ChargeOrderPick
    {
        $this->ib_charge_id = $id;
        return $this;
    }

    public function getOrderId(): ?int
    {
        return $this->order_id;
    }

    public function setOrderId(int $id): ChargeOrderPick
    {
        $this->order_id = $id;
        return $this;
    }

    public function getPacking1Order(): ?float
    {
        return $this->packing_1_order;
    }

    public function setPacking1Order(float $price): ChargeOrderPick
    {
        $this->packing_1_order = $price;
        return $this;
    }

    public function getUniqueSKUs(): ?int
    {
        return $this->unique_SKUs;
    }

    public function setUniqueSKUs(int $num): ChargeOrderPick
    {
        $this->unique_SKUs = $num;
        return $this;
    }

    public function getAdditionalSKU(): ?float
    {
        return $this->additional_SKU;
    }

    public function setAdditionalSKU(float $price): ChargeOrderPick
    {
        $this->additional_SKU = $price;
        return $this;
    }

    public function getTotalSKUs(): ?int
    {
        return $this->total_SKUs;
    }

    public function setTotalSKUs(int $num): ChargeOrderPick
    {
        $this->total_SKUs = $num;
        return $this;
    }

    public function getPick1SKU(): ?float
    {
        return $this->pick_1_SKU;
    }

    public function setPick1SKU(float $price): ChargeOrderPick
    {
        $this->pick_1_SKU = $price;
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
        Mysql::saveRow('charge_order_pick', $data);
    }

    public function toArray(): array
    {
        return [
            'ib_charge_id' => $this->getIBChargeId(),
            'order_id' => $this->getOrderId(),
            'packing_1_order' => $this->getPacking1Order(),
            'unique_SKUs' => $this->getUniqueSKUs(),
            'additional_SKU' => $this->getAdditionalSKU(),
            'total_SKUs' => $this->getTotalSKUs(),
            'pick_1_SKU' => $this->getPick1SKU(),
        ];
    }
}