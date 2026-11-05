<?php

namespace app\core\entities;

use app\core\db\Mysql;

class StockLevel extends Entity
{
    private $id;
    private $SKU_id;
    private $created_at;
    private $quantity;
    private $warehouse_id;
    private $comment;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($id)
    {
        $this->SKU_id = (int)$id;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreatedAt($time)
    {
        $this->created_at = $time;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($num)
    {
        $this->quantity = (int)$num;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function setComment($comment)
    {
        $this->comment = (string)$comment;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('stock_level', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'SKU_id' => $this->getSKUId(),
            'created_at' => $this->getCreatedAt(),
            'quantity' => $this->getQuantity(),
            'warehouse_id' => $this->getWarehouseId(),
            'comment' => $this->getComment(),
        ];
    }
}
