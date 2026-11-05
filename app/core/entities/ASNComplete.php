<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ASNComplete extends Entity
{
    private $id;
    private $ASN_id;
    private $barcode;
    private $quantity;
    private $location_id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getASNId()
    {
        return $this->ASN_id;
    }

    public function setASNId($ASN_id)
    {
        $this->ASN_id = (int)$ASN_id;
    }

    public function getBarcode()
    {
        return $this->barcode;
    }

    public function setBarcode($barcode)
    {
        $this->barcode = (string)$barcode;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($quantity)
    {
        $this->quantity = (int)$quantity;
    }

    public function getLocationId()
    {
        return $this->location_id;
    }

    public function setLocationId($location_id)
    {
        $this->location_id = (int)$location_id;
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
            $this->setId(Mysql::saveRow('ASN_complete', $data));
        } else {
            Mysql::saveRow('ASN_complete', $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'ASN_id' => $this->getASNId(),
            'barcode' => $this->getBarcode(),
            'quantity' => $this->getQuantity(),
            'location_id' => $this->getLocationId(),
        ];
    }
}
