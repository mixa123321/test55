<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SupplierNotification extends Entity
{

    private $type_id;
    private $supplier_email_id;

    public function getTypeId()
    {
        return $this->type_id;
    }

    public function setTypeId($type_id): void
    {
        $this->type_id = $type_id;
    }

    public function getSupplierEmailId()
    {
        return $this->supplier_email_id;
    }

    public function setSupplierEmailId($supplier_email_id): void
    {
        $this->supplier_email_id = $supplier_email_id;
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
        Mysql::saveRow("supplier_notifications", $data);
    }

    public function toArray()
    {
        return [
            'type_id' => $this->getTypeId(),
            'supplier_email_id' => $this->getSupplierEmailId(),
        ];
    }
}
