<?php

namespace app\core\entities;

use app\core\db\Mysql;

class LogisticCompany extends Entity
{
    const ACTIVE = 1;
    const LOCKED = 0;
    const STATUSES = [
        self::LOCKED => 'Locked',
        self::ACTIVE => 'Active',
    ];

    protected $id;
    protected $name;
    protected $logo;
    protected $css;
    protected $shipping_class;
    protected $carrier_id;
    protected $billing_class;
    protected $status = self::ACTIVE;
    protected $lang_id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = (string)$name;
    }

    public function getLogo()
    {
        return $this->logo;
    }

    public function setLogo($name)
    {
        $this->logo = (string)$name;
    }

    public function getCss()
    {
        return $this->css;
    }

    public function setCss($name)
    {
        $this->css = (string)$name;
    }

    public function getShippingClass()
    {
        return $this->shipping_class;
    }

    public function setShippingClass($name)
    {
        $this->shipping_class = (string)$name;
    }

    public function getCarrierId()
    {
        return $this->carrier_id;
    }

    public function setCarrierId($id)
    {
        $this->carrier_id = (int)$id;
    }

    public function getBillingClass()
    {
        return $this->billing_class;
    }

    public function setBillingClass($name)
    {
        $this->billing_class = (string)$name;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = (int)$status;
    }

    public function isActive()
    {
        return (int)($this->getStatus()) === self::ACTIVE;
    }

    public function getLangId()
    {
        return $this->lang_id;
    }

    public function setLangId($id)
    {
        $this->lang_id = (int)$id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('logistic_companies', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'logo' => $this->getLogo(),
            'css' => $this->getCss(),
            'shipping_class' => $this->getShippingClass(),
            'carrier_id' => $this->getCarrierId(),
            'billing_class' => $this->getBillingClass(),
            'status' => $this->getStatus(),
            'lang_id' => $this->getLangId(),
       ];
    }
}