<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class MovementRequestHistory extends Entity
{
    const EVENT_CREATE      = 'create';
    const EVENT_CANCEL      = 'cancel';
    const EVENT_CLOSE       = 'close';
    const EVENT_MOVEMENT    = 'movement';

    const ALL_EVENTS = [
        self::EVENT_CREATE,
        self::EVENT_CANCEL,
        self::EVENT_CLOSE,
        self::EVENT_MOVEMENT,
    ];

    private $request_id;
    private $event;
    private $event_date;
    private $user_id;
    private $additional_info;

    public function getRequestId()
    {
        return $this->request_id;
    }

    public function setRequestId(int $id)
    {
        $this->request_id = $id;
    }

    public function getEvent()
    {
        return $this->event;
    }

    /** @throws */
    public function setEvent(string $name)
    {
        if (!in_array($name, self::ALL_EVENTS))
            throw new \Exception('Invalid movement request event');
        $this->event = $name;
    }

    public function getEventDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->event_date);
    }

    public function getEventDateInt()
    {
        return $this->event_date;
    }

    public function setEventDate($event_date)
    {
        $this->event_date = WithDate::dateToInt($event_date);
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId(int $id)
    {
        $this->user_id = $id;
    }

    public function getAdditionalInfo()
    {
        return $this->additional_info;
    }

    public function setAdditionalInfo($info)
    {
        if (is_string($info))
            $info = json_decode($info, true);
        $this->additional_info = (array)$info;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        $data['additional_info'] = json_encode($data['additional_info']);
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        Mysql::saveRow("movement_request_history", $data);
    }

    public function toArray()
    {
        return [
            'request_id' => $this->getRequestId(),
            'event' => $this->getEvent(),
            'event_date' => $this->getEventDate(),
            'user_id' => $this->getUserId(),
            'additional_info' => $this->getAdditionalInfo(),
        ];
    }
}
