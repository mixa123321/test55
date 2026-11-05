<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ConsumableHistory extends Entity
{
    const EVENT_CREATE          = 'create';
    const EVENT_UPDATE          = 'update';
    const EVENT_MIN_STOCK_LEVEL = 'min_stock_level';
    const EVENT_STATUS          = 'change_status';
    const EVENT_ADJUSTMENT      = 'adjustment';
    const EVENT_PACK            = 'pack';
    const EVENT_UNPACK          = 'unpack';
    const EVENT_ADD_STOCK       = 'stock';

    private $id;
    private $consumable_id;
    private $warehouse_id;
    private $event_date;
    private $event;
    private $user_id;
    private $addition;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getConsumableId()
    {
        return $this->consumable_id;
    }

    public function setConsumableId($id)
    {
        $this->consumable_id = (int)$id;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getEventDate()
    {
        return $this->event_date;
    }

    public function setEventDate($date)
    {
        $this->event_date = $date;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function setEvent($txt)
    {
        $this->event = (string)$txt;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($id)
    {
        $this->user_id = (int)$id;
    }

    public function getAddition()
    {
        return $this->addition;
    }

    public function setAddition($add)
    {
        $this->addition = (string)$add;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('consumable_history', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'consumable_id' => $this->getConsumableId(),
            'warehouse_id' => $this->getWarehouseId(),
            'event_date' => $this->getEventDate(),
            'event' => $this->getEvent(),
            'user_id' => $this->getUserId(),
            'addition' => $this->getAddition(),
        ];
    }
}