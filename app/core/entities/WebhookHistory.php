<?php

namespace app\core\entities;

use app\core\db\Mysql;

class WebhookHistory extends Entity
{
    public const STATUS_SUCCESS = 1;
    public const STATUS_ERROR = 2;

    public const STATUS_NAME = [
        self::STATUS_SUCCESS => 'success',
        self::STATUS_ERROR => 'error',
    ];
    public const STATUS_COLOR = [
        self::STATUS_SUCCESS => 'success',
        self::STATUS_ERROR => 'danger',
    ];
    public const WEBHOOK_OBJECT = [
        'InventoryReceived' => 'ASN',
        'OrderStatusChange' => 'order',
        'ShipConfirm' => 'order',
    ];

    private $id;
    private $name;
    private $external;
    private $number;
    private $json_data;
    private $status;
    private $create_date;
    private $user_id;
    private $response;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($txt)
    {
        $this->name = (string)$txt;
    }

    public function getExternal()
    {
        return $this->external;
    }

    public function setExternal($num)
    {
        $this->external = (int)$num;
    }

    public function getNumber()
    {
        return $this->number;
    }

    public function setNumber($num)
    {
        $this->number = (int)$num;
    }

    public function getJsonData()
    {
        return is_string($this->json_data) ? json_decode($this->json_data, true) : $this->json_data;
    }

    public function setJsonData($txt)
    {
        $this->json_data = is_string($txt) ? json_decode($txt, true) : $txt;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($num)
    {
        $this->status = (int)$num;
    }

    public function getCreateDate()
    {
        return is_null($this->create_date) ? null : $this->create_date;
    }

    public function setCreateDate($create_date)
    {
        $this->create_date = $create_date;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($id)
    {
        $this->user_id = (int)$id;
    }

    public function getResponse()
    {
        return is_string($this->response) ? json_decode($this->response, true) : $this->response;
    }

    public function setResponse($txt)
    {
        $this->response = is_string($txt) ? json_decode($txt, true) : $txt;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        if (isset($data['json_data']) && !is_string($data['json_data']))
            $data['json_data'] = json_encode($data['json_data']);
        if (isset($data['response']) && !is_string($data['response']))
            $data['response'] = json_encode($data['response']);
        $id = Mysql::saveRow('webhook_history', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        $return = [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'external' => $this->getExternal(),
            'number' => $this->getNumber(),
            'json_data' => $this->getJsonData(),
            'status' => $this->getStatus(),
            'create_date' => $this->getCreateDate(),
            'user_id' => $this->getUserId(),
            'response' => $this->getResponse(),
        ];
        return $return;
    }
}