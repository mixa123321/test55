<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SKUStorage extends Entity
{
    private $SKU_id;
    private $location_id;
    private $quantity;
    private $created_at;

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($id)
    {
        $this->SKU_id = (int)$id;
    }

    public function getLocationId()
    {
        return $this->location_id;
    }

    public function setLocationId($id)
    {
        $this->location_id = (int)$id;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($quan)
    {
        $this->quantity = (int)$quan;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreatedAt($time)
    {
        $this->created_at = $time;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        Mysql::saveRow('SKU_storage', $data);
    }

    public function toArray()
    {
        return [
            'SKU_id' => $this->getSKUId(),
            'location_id' => $this->getLocationId(),
            'quantity' => $this->getQuantity(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
