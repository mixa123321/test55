<?php

namespace app\core\entities;

use app\core\db\Mysql;

class OrderLocation extends Entity
{
    private $order_id;
    private $location_id;

    public function getOrderId()
    {
        return $this->order_id;
    }

    public function setOrderId($id)
    {
        $this->order_id = (int)$id;
    }

    public function getLocationId()
    {
        return $this->location_id;
    }

    public function setLocationId($id)
    {
        $this->location_id = (int)$id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save) || (count($fields_to_save) != 2)) {
            return;
        }
        $data = $this->toArray();
        Mysql::saveRow("order_location", $data);
    }

    public function toArray()
    {
        return [
            'order_id' => $this->getOrderId(),
            'location_id' => $this->getLocationId(),
        ];
    }
}
