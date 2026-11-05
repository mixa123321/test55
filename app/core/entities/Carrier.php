<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Carrier extends Entity
{
    private $id;
    private $name;
    private $logo;
    private $code;
    private $url;
    private $is_internal;
    private $is_special;
    private $max_weight;
    private $shipping_class;

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

    public function setName($info)
    {
        $this->name = (string)$info;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setCode($info)
    {
        $this->code = (string)$info;
    }

    public function getLogo()
    {
        return $this->logo;
    }

    public function setLogo($info)
    {
        $this->logo = (string)$info;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setUrl($url)
    {
        $this->url = (string)$url;
    }

    public function getIsInternal()
    {
        return $this->is_internal;
    }

    public function setIsInternal($is_internal)
    {
        $this->is_internal = (bool)$is_internal;
    }

    public function getIsSpecial()
    {
        return $this->is_special;
    }

    public function setIsSpecial($is_special)
    {
        $this->is_special = (bool)$is_special;
    }

    public function getMaxWeight()
    {
        return $this->max_weight;
    }

    public function setMaxWeight($weight)
    {
        $this->max_weight = (float)$weight;
    }

    public function getShippingClass()
    {
        return $this->shipping_class;
    }

    public function setShippingClass($class)
    {
        $this->shipping_class = (string)$class;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('carriers', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'logo' => $this->getLogo(),
            'code' => $this->getCode(),
            'url' => $this->getUrl(),
            'is_internal' => $this->getIsInternal(),
            'is_special' => $this->getIsSpecial(),
            'max_weight' => $this->getMaxWeight(),
            'shipping_class' => $this->getShippingClass(),
        ];
    }
}