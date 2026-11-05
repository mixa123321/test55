<?php

namespace app\core\entities;

use app\core\db\Mysql;

class InvoiceBillingCharge extends Entity
{
    private $id;
    private $invoice_billing_supplier_id;
    private $type;
    private $type_id;
    private $quantity;
    private $value;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): InvoiceBillingCharge
    {
        $this->id = $id;
        return $this;
    }

    public function getInvoiceBillingSupplierId(): ?int
    {
        return $this->invoice_billing_supplier_id;
    }

    public function setInvoiceBillingSupplierId(int $id): InvoiceBillingCharge
    {
        $this->invoice_billing_supplier_id = $id;
        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $num): InvoiceBillingCharge
    {
        $this->type = $num;
        return $this;
    }

    public function getTypeId(): ?int
    {
        return $this->type_id;
    }

    public function setTypeId(int $id): InvoiceBillingCharge
    {
        $this->type_id = $id;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $num): InvoiceBillingCharge
    {
        $this->quantity = $num;
        return $this;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(float $value): InvoiceBillingCharge
    {
        $this->value = $value;
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
        $id = Mysql::saveRow('invoice_billing_charges', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'invoice_billing_supplier_id' => $this->getInvoiceBillingSupplierId(),
            'type' => $this->getType(),
            'type_id' => $this->getTypeId(),
            'quantity' => $this->getQuantity(),
            'value' => $this->getValue(),
        ];
    }
}