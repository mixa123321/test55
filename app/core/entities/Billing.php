<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Billing extends Entity
{
    const ERROR     = -1;
    const NEW       = 1;
    const DONE      = 2;

    const STATUS_NAME = [
        self::ERROR => 'error',
        self::NEW => 'new',
        self::DONE => 'done',
    ];

    private $id;
    private $created_at;
    private $updated_at;
    private $warehouse_id;
    private $invoice_billing_id;
    private $filename;
    private $status;
    private $response;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreatedAt($date)
    {
        $this->created_at = $date;
    }

    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    public function setUpdatedAt($date)
    {
        $this->updated_at = $date;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getInvoiceBillingId()
    {
        return $this->invoice_billing_id;
    }

    public function setInvoiceBillingId($id)
    {
        $this->invoice_billing_id = (int)$id;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function setFilename($name)
    {
        $this->filename = (string)$name;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = (int)$status;
    }

    public static function isError($status)
    {
        return (int)$status === self::ERROR;
    }

    public function new()
    {
        return $this->getStatus() === self::NEW;
    }

    public function getResponse()
    {
        if (empty($this->response))
            return [];
        $res = json_decode($this->response, true);
        return empty($res) ? [] : $res;
    }

    public function setResponse($response)
    {
        if (is_array($response))
            $this->response = json_encode($response);
        elseif (is_string($response))
            $this->response = empty(json_decode($response, true)) ? '[]' : $response;
        else
            $this->response = '[]';
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        if (isset($data['response']))
            $data['response'] = json_encode($data['response']);
        $id = Mysql::saveRow('billing', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            'warehouse_id' => $this->getWarehouseId(),
            'invoice_billing_id' => $this->getInvoiceBillingId(),
            'filename' => $this->getFilename(),
            'status' => $this->getStatus(),
            'response' => $this->getResponse()
        ];
    }
}