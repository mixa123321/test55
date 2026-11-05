<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SKUChannelSupplier extends Entity
{
    private $id;
    private $SKU_id;
    private $external_id;
    private $shop_id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($id)
    {
        $this->SKU_id = (int)$id;
    }

    public function getShopId()
    {
        return $this->shop_id;
    }

    public function setShopId($id)
    {
        $this->shop_id = (int)$id;
    }

    public function getExternalId()
    {
        return $this->external_id;
    }

    public function setExternalId($id)
    {
        $this->external_id = (string)$id;
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
            $this->setId(Mysql::saveRow("SKU_channel_suppliers", $data));
        } else {
            Mysql::saveRow("SKU_channel_suppliers", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'SKU_id' => $this->getSKUId(),
            'external_id' => $this->getExternalId(),
            'shop_id' => $this->getShopId(),
        ];
    }
}
