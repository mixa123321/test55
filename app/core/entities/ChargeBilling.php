<?php

namespace app\core\entities;

use app\core\statical\WithDate;

class ChargeBilling extends Entity
{
    private $id;
    private $charge_complex_id;
    private $value;
    private $valid_from;
    private $valid_to;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): ChargeBilling
    {
        $this->id = $id;
        return $this;
    }

    public function getChargeComplexId(): int
    {
        return $this->charge_complex_id;
    }

    public function setChargeComplexId(int $id): ChargeBilling
    {
        $this->charge_complex_id = $id;
        return $this;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): ChargeBilling
    {
        $this->value = $value;
        return $this;
    }

    public function getValidFrom(): string
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->valid_from);
    }

    public function setValidFrom($date): ChargeBilling
    {
        $this->valid_from = WithDate::dateToInt($date);
        return $this;
    }

    public function getValidTo(): string
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->valid_to);
    }

    public function setValidTo($date): ChargeBilling
    {
        $this->valid_to = WithDate::dateToInt($date);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'charge_complex_id' => $this->getChargeComplexId(),
            'value' => $this->getValue(),
            'valid_from' => $this->getValidFrom(),
            'valid_to' => $this->getValidTo(),
        ];
    }
}