<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SupplierEmail extends Entity
{
    private $id;
    private $name;
    private $supplier_id;
    private $is_active;


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

    public function getSupplierId()
    {
        return (int)$this->supplier_id;
    }

    public function setSupplierId($supplier_id): void
    {
        $this->supplier_id = $supplier_id;
    }

    public function getIsActive()
    {
        return (boolean)$this->is_active;
    }

    public function setIsActive($is_active): void
    {
        $this->is_active = $is_active;
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
            $this->setId(Mysql::saveRow("supplier_emails", $data));
        } else {
            Mysql::saveRow("supplier_emails", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'supplier_id' => $this->getSupplierId(),
            'is_active' => $this->getIsActive(),
        ];
    }
}
