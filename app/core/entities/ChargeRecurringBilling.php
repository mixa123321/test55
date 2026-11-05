<?php

namespace app\core\entities;

use app\core\statical\WithDate;

class ChargeRecurringBilling extends Entity
{
    private $id;
    private $charge_recurring_id;
    private $quantity;
    private $value;
    private $valid_from;
    private $valid_to;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): ChargeRecurringBilling
    {
        $this->id = $id;
        return $this;
    }

    public function getChargeRecurringId(): int
    {
        return $this->charge_recurring_id;
    }

    public function setChargeRecurringId(int $id): ChargeRecurringBilling
    {
        $this->charge_recurring_id = $id;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $num): ChargeRecurringBilling
    {
        $this->quantity = $num;
        return $this;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): ChargeRecurringBilling
    {
        $this->value = $value;
        return $this;
    }

    public function getValidFrom(): string
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->valid_from);
    }

    public function setValidFrom($date): ChargeRecurringBilling
    {
        $this->valid_from = WithDate::dateToInt($date);
        return $this;
    }

    public function getValidTo(): string
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->valid_to);
    }

    public function setValidTo($date): ChargeRecurringBilling
    {
        $this->valid_to = WithDate::dateToInt($date);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'charge_recurring_id' => $this->getChargeRecurringId(),
            'quantity' => $this->getQuantity(),
            'value' => $this->getValue(),
            'valid_from' => $this->getValidFrom(),
            'valid_to' => $this->getValidTo(),
        ];
    }
}