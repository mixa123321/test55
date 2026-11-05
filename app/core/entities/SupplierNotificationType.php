<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SupplierNotificationType extends Entity
{
    private $id;
    private $name;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name): void
    {
        $this->name = $name;
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
        if (!$this->getId()) {
            $this->setId(Mysql::saveRow('supplier_notification_types', $data));
        } else {
            Mysql::saveRow('supplier_notification_types', $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
        ];
    }
}
