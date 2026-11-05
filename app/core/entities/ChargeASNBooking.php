<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ChargeASNBooking extends Entity
{
    private $ib_charge_id;
    private $ASN_id;
    private $unique_SKUs;
    private $additional_SKU;
    private $total_SKUs;
    private $booking_1_SKU;

    public function getIBChargeId(): ?int
    {
        return $this->ib_charge_id;
    }

    public function setIBChargeId(int $id): ChargeASNBooking
    {
        $this->ib_charge_id = $id;
        return $this;
    }

    public function getASNId(): ?int
    {
        return $this->ASN_id;
    }

    public function setASNId(int $id): ChargeASNBooking
    {
        $this->ASN_id = $id;
        return $this;
    }

    public function getUniqueSKUs(): ?int
    {
        return $this->unique_SKUs;
    }

    public function setUniqueSKUs(int $num): ChargeASNBooking
    {
        $this->unique_SKUs = $num;
        return $this;
    }

    public function getAdditionalSKU(): ?float
    {
        return $this->additional_SKU;
    }

    public function setAdditionalSKU(float $price): ChargeASNBooking
    {
        $this->additional_SKU = $price;
        return $this;
    }

    public function getTotalSKUs(): ?int
    {
        return $this->total_SKUs;
    }

    public function setTotalSKUs(int $num): ChargeASNBooking
    {
        $this->total_SKUs = $num;
        return $this;
    }

    public function getBooking1SKU(): ?float
    {
        return $this->booking_1_SKU;
    }

    public function setBooking1SKU(float $price): ChargeASNBooking
    {
        $this->booking_1_SKU = $price;
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
        Mysql::saveRow('charge_ASN_booking', $data);
    }

    public function toArray(): array
    {
        return [
            'ib_charge_id' => $this->getIBChargeId(),
            'ASN_id' => $this->getASNId(),
            'unique_SKUs' => $this->getUniqueSKUs(),
            'additional_SKU' => $this->getAdditionalSKU(),
            'total_SKUs' => $this->getTotalSKUs(),
            'booking_1_SKU' => $this->getBooking1SKU(),
        ];
    }
}