<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ASNTemporary extends Entity
{
    private $ASN_id;
    private $SKU_id;
    private $quantity;

    public function getASNId()
    {
        return $this->ASN_id;
    }

    public function setASNId($ASN_id)
    {
        $this->ASN_id = (int)$ASN_id;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($SKU_id)
    {
        $this->SKU_id = (int)$SKU_id;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($quantity)
    {
        $this->quantity = (int)$quantity;
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
        Mysql::saveRow("ASN_temporary", $data);
    }

    public function toArray()
    {
        return [
            'ASN_id' => $this->getASNId(),
            'SKU_id' => $this->getSKUId(),
            'quantity' => $this->getQuantity(),
        ];
    }
}
