<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class InvoiceBillingSupplier extends Entity
{
    private $id;
    private $invoice_billing_id;
    private $supplier_warehouse_id;
    private $user_id;
    private $create_date;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): InvoiceBillingSupplier
    {
        $this->id = $id;
        return $this;
    }

    public function getInvoiceBillingId(): ?int
    {
        return $this->invoice_billing_id;
    }

    public function setInvoiceBillingId(int $id): InvoiceBillingSupplier
    {
        $this->invoice_billing_id = $id;
        return $this;
    }

    public function getSupplierWarehouseId(): ?int
    {
        return $this->supplier_warehouse_id;
    }

    public function setSupplierWarehouseId(int $id): InvoiceBillingSupplier
    {
        $this->supplier_warehouse_id = $id;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(int $id): InvoiceBillingSupplier
    {
        $this->user_id = $id;
        return $this;
    }

    public function getCreateDate(): ?string
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->create_date);
    }

    public function setCreateDate($date): InvoiceBillingSupplier
    {
        $this->create_date = WithDate::dateToInt($date);
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
        $id = Mysql::saveRow('invoice_billing_suppliers', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'invoice_billing_id' => $this->getInvoiceBillingId(),
            'supplier_warehouse_id' => $this->getSupplierWarehouseId(),
            'user_id' => $this->getUserId(),
            'create_date' => $this->getCreateDate(),
        ];
    }
}