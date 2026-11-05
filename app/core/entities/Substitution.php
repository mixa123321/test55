<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Substitution extends Entity
{
    private $id;
    private $substitution_id;
    private $SKU_id;
    private $quantity;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getSubstitutionId()
    {
        return $this->substitution_id;
    }

    public function setSubstitutionId($id): void
    {
        $this->substitution_id = $id;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($id): void
    {
        $this->SKU_id = (int)$id;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($qty): void
    {
        $this->quantity = (int)$qty;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('substitutions', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'substitution_id' => $this->getSubstitutionId(),
            'SKU_id' => $this->getSKUId(),
            'quantity' => $this->getQuantity(),
        ];
    }
}
