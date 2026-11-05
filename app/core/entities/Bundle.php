<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Bundle extends Entity
{
    private $id;
    private $name;
    private $supplier_id;
    private $currency_id;
    private $cost_value;

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
        return $this->supplier_id;
    }

    public function setSupplierId($supplier_id): void
    {
        $this->supplier_id = $supplier_id;
    }

    public function getCurrencyId(): int
    {
        return (int)$this->currency_id;
    }

    public function setCurrencyId(int $id)
    {
        $this->currency_id = $id;
    }

    public function getCostValue(): float
    {
        return round((float)$this->cost_value, 2);
    }

    public function setCostValue(float $cost_value)
    {
        $this->cost_value = round($cost_value, 2);
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
            $this->setId(Mysql::saveRow("bundles", $data));
        } else {
            Mysql::saveRow("bundles", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'supplier_id' => $this->getSupplierId(),
            'currency_id' => $this->getCurrencyId(),
            'cost_value' => $this->getCostValue(),
        ];
    }
}
