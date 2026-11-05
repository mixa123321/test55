<?php

namespace app\core\entities;

use app\core\db\Mysql;

class PackageConsumable extends Entity
{
    private $package_id;
    private $consumable_id;
    private $quantity = 0;

    public function getPackageId()
    {
        return $this->package_id;
    }

    public function setPackageId($id)
    {
        $this->package_id = (int)$id;
    }

    public function getConsumableId()
    {
        return $this->consumable_id;
    }

    public function setConsumableId($id)
    {
        $this->consumable_id = (int)$id;
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
        if (empty($fields_to_save) || (count($fields_to_save) < 3 && in_array('quantity', $fields_to_save)))
            return;
        $data = $this->toArray();
        Mysql::saveRow('package_consumables', $data);
    }

    public function toArray()
    {
        return [
            'package_id' => $this->getPackageId(),
            'consumable_id' => $this->getConsumableId(),
            'quantity' => $this->getQuantity(),
        ];
    }
}
