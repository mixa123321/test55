<?php

namespace app\core\entities;

use app\core\db\Mysql;

class OrderPackageReturn extends Entity
{
    private $order_package_id;
    private $return_id;

    public function getOrderPackageId()
    {
        return $this->order_package_id;
    }

    public function setOrderPackageId($id)
    {
        $this->order_package_id = (int)$id;
    }

    public function getReturnId()
    {
        return $this->return_id;
    }

    public function setReturnId($id)
    {
        $this->return_id = (int)$id;
    }

    public function save()
    {
        $data = $this->toArray();
        if (empty($this->getOrderPackageId()) || empty($this->getReturnId()))
            return;
        Mysql::saveRow('order_packages_returns', $data);
    }

    public function toArray()
    {
        return [
            'order_package_id' => $this->getOrderPackageId(),
            'return_id' => $this->getReturnId(),
        ];
    }
}
