<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\models\SubstitutionConditions;

class SubstitutionCondition extends Entity
{
    private $substitution_id;
    private $is_quantity;
    private $quantity_from;
    private $quantity_to;
    private $status = 1;

    const LOCKED = 0;
    const ACTIVE = 1;
    const NAME_STATUSES = [
        self::LOCKED => 'locked',
        self::ACTIVE => 'active'
    ];
    const COLOR_STATUSES = [
        self::LOCKED => 'danger',
        self::ACTIVE => 'success'
    ];

    public function getSubstitutionId()
    {
        return $this->substitution_id;
    }

    public function setSubstitutionId($id): void
    {
        $this->substitution_id = $id;
    }

    public function getIsQuantity()
    {
        return $this->is_quantity;
    }

    public function setIsQuantity($is_quantity): void
    {
        $this->is_quantity = $is_quantity;
    }

    public function isQuantity()
    {
        return (bool)$this->is_quantity;
    }

    public function getQuantityFrom()
    {
        return $this->quantity_from;
    }

    public function setQuantityFrom($quantity): void
    {
        $this->quantity_from = (int)$quantity;
    }

    public function getQuantityTo()
    {
        return $this->quantity_to;
    }

    public function setQuantityTo($quantity): void
    {
        $this->quantity_to = (int)$quantity;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status): void
    {
        $this->status = (int)$status;
    }

    public static function getNotStatus($status)
    {
        return 1 - $status;
    }

    public function save($fields_to_save = null)
    {
        $data = $this->toArray();
        if (!empty($fields_to_save))
            foreach ($data as $data_field => $data_value)
                if (!\in_array($data_field, $fields_to_save, true))
                    unset($data[$data_field]);
        if (count($data) < 2 || empty($data['substitution_id']))
            return false;
        if (empty(SubstitutionConditions::getById($this->getSubstitutionId())))
            Mysql::saveRow('substitution_conditions', $data);
        else
            Mysql::saveRow('substitution_conditions', $data, Mysql::DB_FF, 'substitution_id');
        return true;
    }

    public function toArray()
    {
        return [
            'substitution_id' => $this->getSubstitutionId(),
            'is_quantity' => $this->getIsQuantity(),
            'quantity_from' => $this->getQuantityFrom(),
            'quantity_to' => $this->getQuantityTo(),
            'status' => $this->getStatus()
        ];
    }
}
