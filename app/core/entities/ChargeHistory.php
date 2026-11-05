<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class ChargeHistory extends Entity
{
    const EVENT_IMPORT              = 'uploaded new tariff';
    const EVENT_CHANGE              = 'change tariff';
    const EVENT_ADD                 = 'add tariff';
    const EVENT_DELETE              = 'delete tariff';
    const EVENT_UPDATE              = 'update tariff';
    const EVENT_IMPORT_RECURRING    = 'uploaded recurring';
    const EVENT_CHANGE_RECURRING    = 'change recurring';
    const EVENT_ADD_RECURRING       = 'add recurring';
    const EVENT_DELETE_RECURRING    = 'delete recurring';
    const EVENT_UPDATE_RECURRING    = 'update recurring';

    private $id;
    private $supplier_warehouse_id;
    private $user_id;
    private $event;
    private $event_date;
    private $changes;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): ChargeHistory
    {
        $this->id = $id;
        return $this;
    }

    public function getSupplierWarehouseId(): ?int
    {
        return $this->supplier_warehouse_id;
    }

    public function setSupplierWarehouseId(int $id): ChargeHistory
    {
        $this->supplier_warehouse_id = $id;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(int $id): ChargeHistory
    {
        $this->user_id = $id;
        return $this;
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function setEvent(string $info): ChargeHistory
    {
        $this->event = $info;
        return $this;
    }

    public function getEventDate(): ?string
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->event_date);
    }

    public function setEventDate($date): ChargeHistory
    {
        $this->event_date = WithDate::dateToInt($date);
        return $this;
    }

    public function getChanges(): array
    {
        return json_decode($this->changes ?: '[]', true);
    }

    public function setChanges(array $json): ChargeHistory
    {
        $this->changes = json_encode($json);
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
            elseif ($data_field == 'changes')
                $data[$data_field] = json_encode($data_value);
        $id = Mysql::saveRow('charges_history', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'supplier_warehouse_id' => $this->getSupplierWarehouseId(),
            'user_id' => $this->getUserId(),
            'event' => $this->getEvent(),
            'event_date' => $this->getEventDate(),
            'changes' => $this->getChanges(),
        ];
    }
}