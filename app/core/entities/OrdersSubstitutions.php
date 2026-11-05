<?php

namespace app\core\entities;

use app\core\db\Mysql;

class OrdersSubstitutions extends Entity
{
    private $id;
    private $order_id;
    private $substitution_id;
    private $quantity;
    private $history_id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = (int)$id;
    }

    public function getOrderId()
    {
        return $this->order_id;
    }

    public function setOrderId($id): void
    {
        $this->order_id = (int)$id;
    }

    public function getSubstitutionId()
    {
        return $this->substitution_id;
    }

    public function setSubstitutionId($id): void
    {
        $this->substitution_id = (int)$id;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($qty): void
    {
        $this->quantity = (int)$qty;
    }

    public function getHistoryId()
    {
        return $this->history_id;
    }

    public function setHistoryId($id): void
    {
        $this->history_id = (int)$id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('orders_substitutions', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'order_id' => $this->getOrderId(),
            'substitution_id' => $this->getSubstitutionId(),
            'quantity' => $this->getQuantity(),
            'history_id' => $this->getHistoryId(),
        ];
    }
}
