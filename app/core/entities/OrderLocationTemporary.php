<?php

namespace app\core\entities;

use app\core\db\Mysql;

class OrderLocationTemporary extends Entity
{
    private $order_id;
    private $location_id;
    private $SKU_id;
    private $quantity;

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

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($id)
    {
        $this->SKU_id = (int)$id;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($num)
    {
        $this->quantity = (int)$num;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save) || (count($fields_to_save) != 4)) {
            return;
        }
        $data = $this->toArray();
        Mysql::saveRow("order_location_temporary", $data);
    }

    public function toArray()
    {
        return [
            'order_id' => $this->getOrderId(),
            'location_id' => $this->getLocationId(),
            'SKU_id' => $this->getSKUId(),
            'quantity' => $this->getQuantity(),
        ];
    }
}
