<?php

namespace app\core\entities;

use app\core\db\Mysql;

class CollectionTime extends Entity
{
    private $id;
    private $shipping_service_id;
    private $warehouse_id;
    private $collection;
    private $is_priority;
    private $priority_level;

    public function getId()
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getShippingServiceId()
    {
        return (int)$this->shipping_service_id;
    }

    public function setShippingServiceId(int $id): void
    {
        $this->shipping_service_id = $id;
    }

    public function getWarehouseId()
    {
        return (int)$this->warehouse_id;
    }

    public function setWarehouseId(int $id): void
    {
        $this->warehouse_id = $id;
    }

    public function getCollection()
    {
        return $this->collection;
    }

    public function setCollection($time): void
    {
        $this->collection = $time;
    }

    public function getIsPriority()
    {
        return (int)$this->is_priority;
    }

    public function setIsPriority(bool $is_priority): void
    {
        $this->is_priority = $is_priority;
    }

    public function isPriority()
    {
        return $this->getIsPriority();
    }

    public function getPriorityLevel()
    {
        return (int)$this->priority_level;
    }

    public function setPriorityLevel(int $num): void
    {
        $this->priority_level = $num;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('collection_time', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'shipping_service_id' => $this->getShippingServiceId(),
            'warehouse_id' => $this->getWarehouseId(),
            'collection' => $this->getCollection(),
            'is_priority' => $this->getIsPriority(),
            'priority_level' => $this->getPriorityLevel(),
        ];
    }
}