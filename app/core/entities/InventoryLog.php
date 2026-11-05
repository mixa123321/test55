<?php

namespace app\core\entities;

use app\core\db\Mysql;

class InventoryLog extends Entity
{
    private $id;
    private $warehouse_id;
    private $SKU_id;
    private $create_date;
    private $user_id;
    private $request;
    private $start;
    private $response;
    private $status;

    const STATUS_ERROR      = 0;
    const STATUS_SUCCESS    = 1;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($id)
    {
        $this->SKU_id = (int)$id;
    }

    public function getCreateDate()
    {
        return $this->create_date;
    }

    public function setCreateDate($date)
    {
        $this->create_date = $date;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($id)
    {
        $this->user_id = (int)$id;
    }

    public function getRequest()
    {
        $res = $this->request;
        if (is_string($res))
            $res = json_decode($res, 1);
        return $res;
    }

    public function setRequest($info)
    {
        $this->request = $info;
    }

    public function getStart()
    {
        $res = $this->start;
        if (is_string($res))
            $res = json_decode($res, 1);
        return $res;
    }

    public function setStart($info)
    {
        $this->start = $info;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setResponse($info)
    {
        $this->response = $info;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = (int)$status;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        $data['request'] = json_encode($data['request']);
        $data['start'] = json_encode($data['start']);
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('inventory_log', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'warehouse_id' => $this->getWarehouseId(),
            'SKU_id' => $this->getSKUId(),
            'create_date' => $this->getCreateDate(),
            'user_id' => $this->getUserId(),
            'request' => $this->getRequest(),
            'start' => $this->getStart(),
            'response' => $this->getResponse(),
            'status' => $this->getStatus(),
        ];
    }
}