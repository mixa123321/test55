<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class SKUHistory extends Entity
{
    private $id;
    private $SKU_id;
    private $event_date;
    private $adjustment;
    private $event;
    private $additional_info;
    private $warehouse_id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($id)
    {
        $this->SKU_id = (int)$id;
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

    public function getAdjustment()
    {
        return $this->adjustment;
    }

    public function setAdjustment($adj)
    {
        $this->adjustment = (int)$adj;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function setEvent($name)
    {
        $this->event = (string)$name;
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

    public function getWarehouseId(): int
    {
        return (int)$this->warehouse_id;
    }

    public function setWarehouseId($warehouse_id): void
    {
        $this->warehouse_id = (int)$warehouse_id;
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
        Mysql::saveRow("SKU_history", $data);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'SKU_id' => $this->getSKUId(),
            'event_date' => $this->getEventDate(),
            'adjustment' => $this->getAdjustment(),
            'event' => $this->getEvent(),
            'additional_info' => $this->getAdditionalInfo(),
            'warehouse_id' => $this->getWarehouseId(),
        ];
    }
}
