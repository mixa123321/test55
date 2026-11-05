<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class DimensionerHistory extends Entity
{
    private $id;
    private $dimensioner_id;
    private $event;
    private $event_date;
    private $user_id;
    private $dimensioner_params;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getDimensionerId()
    {
        return $this->dimensioner_id;
    }

    public function setDimensionerId($id)
    {
        $this->dimensioner_id = (int)$id;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function setEvent($name)
    {
        $this->event = (string)$name;
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

    public function getUserId(): int
    {
        return (int)$this->user_id;
    }

    public function setUserId($id): void
    {
        $this->user_id = (int)$id;
    }

    public function getDimensionerParams()
    {
        return $this->dimensioner_params;
    }

    public function setDimensionerParams($params)
    {
        if (is_string($params))
            $info = json_decode($params, true);
        $this->dimensioner_params = (array)$params;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        $data['dimensioner_params'] = json_encode($data['dimensioner_params']);
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        Mysql::saveRow('dimensioner_history', $data);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'dimensioner_id' => $this->getDimensionerId(),
            'event' => $this->getEvent(),
            'event_date' => $this->getEventDate(),
            'user_id' => $this->getUserId(),
            'dimensioner_params' => $this->getDimensionerParams(),
        ];
    }
}
