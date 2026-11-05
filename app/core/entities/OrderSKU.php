<?php

namespace app\core\entities;

use app\core\db\Mysql;

class OrderSKU extends Entity
{
    private $order_id;
    private $SKU_id;
    private $quantity;
    private $price;

    public function getOrderId()
    {
        return $this->order_id;
    }

    public function setOrderId($id)
    {
        $this->order_id = (int)$id;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($SKU_id)
    {
        $this->SKU_id = (int)$SKU_id;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($quantity)
    {
        $this->quantity = (int)$quantity;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function setPrice($price)
    {
        $this->price = (int)$price;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save) || (count($fields_to_save) != 3)) {
            return;
        }
        $data = $this->toArray();
        Mysql::saveRow('order_SKUs', $data);
    }

    public function toArray()
    {
        return [
            'order_id' => $this->getOrderId(),
            'SKU_id' => $this->getSKUId(),
            'quantity' => $this->getQuantity(),
            'price' => $this->getPrice(),
        ];
    }
}
