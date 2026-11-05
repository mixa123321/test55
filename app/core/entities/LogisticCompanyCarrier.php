<?php

namespace app\core\entities;

use app\core\db\Mysql;

class LogisticCompanyCarrier extends Entity
{
    protected $id;
    protected $lc_id;
    protected $carrier_id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getLCId()
    {
        return $this->lc_id;
    }

    public function setLCId($id)
    {
        $this->lc_id = (int)$id;
    }

    public function getCarrierId()
    {
        return $this->carrier_id;
    }

    public function setCarrierId($id)
    {
        $this->carrier_id = (int)$id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('logistic_company_carriers', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'lc_id' => $this->getLCId(),
            'carrier_id' => $this->getCarrierId(),
        ];
    }
}