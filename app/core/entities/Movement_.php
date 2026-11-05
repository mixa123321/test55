<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Movement_ extends Entity
{
    private $id;
    private $location_id;
    private $SKU_id;
    private $quantity;
    private $order_id;
    private $type;
    private $reason;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
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

    public function setQuantity($qty)
    {
        $this->quantity = (int)$qty;
    }

    public function getOrderId()
    {
        return $this->order_id;
    }

    public function setOrderId($id)
    {
        $this->order_id = (int)$id;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($name)
    {
        $this->type = (string)$name;
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function setReason($name)
    {
        $this->reason = (string)$name;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save)) {
            return;
        }
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value) {
            if (!\in_array($data_field, $fields_to_save, true)) {
                unset($data[$data_field]);
            }
        }
        if (!$this->getId()) {
            $this->setId(Mysql::saveRow("movement", $data));
        } else {
            Mysql::saveRow("movement", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'location_id' => $this->getLocationId(),
            'SKU_id' => $this->getSKUId(),
            'quantity' => $this->getQuantity(),
            'order_id' => $this->getOrderId(),
            'type' => $this->getType(),
            'reason' => $this->getReason(),
        ];
    }
}
