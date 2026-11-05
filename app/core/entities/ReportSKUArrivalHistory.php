<?php

namespace app\core\entities;

class ReportSKUArrivalHistory
{
    private $SKU_id;
    private $supplier_id;
    private $name;
    private $pin;
    private $product_code;
    private $in_out;
    private $reason_code;
    private $reason_desc;
    private $qty;
    private $run_date;
    private $event_date;
    private $SKU_history_id;
    private $warehouse_id;

    public function getSKUId(): int
    {
        return (int)$this->SKU_id;
    }

    public function setSKUId(int $SKU_id): ReportSKUArrivalHistory
    {
        $this->SKU_id = $SKU_id;
        return $this;
    }

    public function getSupplierId(): int
    {
        return (int)$this->supplier_id;
    }

    public function setSupplierId(int $supplier_id): ReportSKUArrivalHistory
    {
        $this->supplier_id = $supplier_id;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): ReportSKUArrivalHistory
    {
        $this->name = $name;
        return $this;
    }

    public function getPin(): string
    {
        return $this->pin;
    }

    public function setPin(string $pin): ReportSKUArrivalHistory
    {
        $this->pin = $pin;
        return $this;
    }

    public function getProductCode(): string
    {
        return $this->product_code;
    }

    public function setProductCode(string $product_code): ReportSKUArrivalHistory
    {
        $this->product_code = $product_code;
        return $this;
    }

    public function getInOut(): string
    {
        return $this->in_out;
    }

    public function setInOut(string $in_out): ReportSKUArrivalHistory
    {
        $this->in_out = $in_out;
        return $this;
    }

    public function getReasonCode(): string
    {
        return $this->reason_code;
    }

    public function setReasonCode(string $reason_code): ReportSKUArrivalHistory
    {
        $this->reason_code = $reason_code;
        return $this;
    }

    public function getReasonDesc(): string
    {
        return $this->reason_desc;
    }

    public function setReasonDesc(string $reason_desc): ReportSKUArrivalHistory
    {
        $this->reason_desc = $reason_desc;
        return $this;
    }

    public function getQty(): int
    {
        return $this->qty;
    }

    public function setQty(int $qty): ReportSKUArrivalHistory
    {
        $this->qty = $qty;
        return $this;
    }

    public function getRunDate(): string
    {
        return $this->run_date;
    }

    public function setRunDate(string $run_date): ReportSKUArrivalHistory
    {
        $this->run_date = $run_date;
        return $this;
    }

    public function getEventDate(): string
    {
        return $this->event_date;
    }

    public function setEventDate(string $event_date): ReportSKUArrivalHistory
    {
        $this->event_date = $event_date;
        return $this;
    }

    public function getSKUHistoryId(): int
    {
        return $this->SKU_history_id;
    }

    public function setSKUHistoryId(int $SKU_history_id): ReportSKUArrivalHistory
    {
        $this->SKU_history_id = $SKU_history_id;
        return $this;
    }

    public function getWarehouseId(): int
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId(int $warehouse_id): ReportSKUArrivalHistory
    {
        $this->warehouse_id = $warehouse_id;
        return $this;
    }

    public function toArray()
    {
        return [
            'SKU_id' => $this->getSKUId(),
            'supplier_id,' => $this->getSupplierId(),
            'name' => $this->getName(),
            'pin' => $this->getPin(),
            'product_code' => $this->getProductCode(),
            'in_out' => $this->getInOut(),
            'reason_code' => $this->getReasonCode(),
            'reason_desc' => $this->getReasonDesc(),
            'qty' => $this->getQty(),
            'run_date' => $this->getRunDate(),
            'event_date' => $this->getEventDate(),
            'SKU_history_id' => $this->getSKUHistoryId(),
            'warehouse_id' =>  $this->getWarehouseId(),
        ];
    }
}