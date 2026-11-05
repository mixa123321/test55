<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SupplierUser extends Entity
{
    private $supplier_id;
    private $user_id;

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($supplier_id)
    {
        $this->supplier_id = (int)$supplier_id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = (int)$user_id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save) || (count($fields_to_save) != 2)) {
            return;
        }
        $data = $this->toArray();
        Mysql::saveRow("supplier_users", $data);
    }

    public function toArray()
    {
        return [
            'supplier_id' => $this->getSupplierId(),
            'user_id' => $this->getUserId(),
        ];
    }
}
