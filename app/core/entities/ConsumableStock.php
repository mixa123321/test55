<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ConsumableStock extends Entity
{
    private $id;
    private $consumable_id;
    private $warehouse_id;
    private $created_at;
    private $quantity;
    private $comment;

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

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreateAt($date)
    {
        $this->created_at = $date;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($num)
    {
        $this->quantity = (int)$num;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function setComment($txt)
    {
        $this->comment = $txt;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('consumable_stock', $data);
        if (empty($this->getId()))
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'consumable_id' => $this->getConsumableId(),
            'warehouse_id' => $this->getWarehouseId(),
            'created_at' => $this->getCreatedAt(),
            'quantity' => $this->getQuantity(),
            'comment' => $this->getComment(),
        ];
    }
}