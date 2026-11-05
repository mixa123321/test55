<?php

namespace app\core\entities;

use app\core\db\Mysql;

class MovementHistory extends Entity
{
    const EVENT_CREATE  = 'create';
    const EVENT_UPDATE  = 'update';
    const EVENT_DELETE  = 'delete';
    const EVENT_MERGE   = 'merge';
    const EVENT_RESTORE = 'restore';

    private $id;
    private $event;
    private $user_id;
    private $movement_id;
    private $create_date;
    private $addition;
    private $SKU_id;
    private $reason;
    private $warehouse_id;


    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function setEvent($event): void
    {
        $this->event = $event;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getMovementId()
    {
        return $this->movement_id;
    }

    public function setMovementId($movement_id): void
    {
        $this->movement_id = $movement_id;
    }

    public function getCreateDate()
    {
        return $this->create_date;
    }

    public function setCreateDate($create_date): void
    {
        $this->create_date = $create_date;
    }

    public function getAddition()
    {
        return $this->addition;
    }

    public function setAddition($addition): void
    {
        $this->addition = $addition;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($SKU_id): void
    {
        $this->SKU_id = $SKU_id;
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function setReason($reason): void
    {
        $this->reason = $reason;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($warehouse_id): void
    {
        $this->warehouse_id = $warehouse_id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('movement_history', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'event' => $this->getEvent(),
            'user_id' => $this->getUserId(),
            'movement_id' => $this->getMovementId(),
            'create_date' => $this->getCreateDate(),
            'addition' => $this->getAddition(),
            'SKU_id' => $this->getSKUId(),
            'reason' => $this->getReason(),
            'warehouse_id' => $this->getWarehouseId(),
        ];
    }

}
