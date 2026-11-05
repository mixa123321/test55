<?php

namespace app\core\entities;

use app\core\db\Mysql;

class OrderPackageConsumable extends Entity
{
    private $order_package_id;
    private $consumables;

    public function getOrderPackageId()
    {
        return $this->order_package_id;
    }

    public function setOrderPackageId($id)
    {
        $this->order_package_id = (int)$id;
    }

    public function getConsumables()
    {
        return $this->consumables;
    }

    public function setConsumables($txt)
    {
        $this->consumables = (string)$txt;
    }

    public function save()
    {
        $data = $this->toArray();
        if (empty($this->getOrderPackageId()) || empty($this->getConsumables()))
            return;
        Mysql::saveRow('orders_packages_consumables', $data);
    }

    public function toArray()
    {
        return [
            'order_package_id' => $this->getOrderPackageId(),
            'consumables' => $this->getConsumables(),
        ];
    }
}
