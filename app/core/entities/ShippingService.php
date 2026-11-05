<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ShippingService extends Entity
{
    private $id;
    private $name;
    private $code;
    private $carrier_id;
    private $is_untracked;
    private $max_weight;
    private $next_day;
    private $special;
    private $logo;
    private $indicia_image;

    const NEXT_DAY_SERVICES = [
        'UK24',
        'RM24',
        'DPE1',
        'DPE5',
        'DPND'
    ];

    const SPECIAL_CULLING = 1;
    const SPECIAL_BUNDLING = 2;
    const SPECIAL_NAMES = [
        0 => 'None',
        self::SPECIAL_CULLING => 'Culling',
        self::SPECIAL_BUNDLING => 'Bundling',
    ];

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = (int)$id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setCode($code): void
    {
        $this->code = $code;
    }

    public function getCarrierId()
    {
        return $this->carrier_id;
    }

    public function setCarrierId($carrier_id): void
    {
        $this->carrier_id = $carrier_id;
    }

    public function getIsUntracked()
    {
        return (int)$this->is_untracked;
    }

    public function setIsUntracked($is_untracked): void
    {
        $this->is_untracked = (int)$is_untracked;
    }

    public function getMaxWeight()
    {
        return (double)$this->max_weight;
    }

    public function setMaxWeight($max_weight): void
    {
        if (empty($max_weight))
            $this->max_weight = null;
        else
            $this->max_weight = (double)$max_weight > 0 ? (double)$max_weight : null;
    }

    public function getNextDay(): int
    {
        return (int)$this->next_day;
    }

    public function setNextDay($next_day): void
    {
        $this->next_day = (int)$next_day;
    }

    public function getSpecial(): int
    {
        return (int)$this->special;
    }

    public function getSpecialName(): string
    {
        return self::SPECIAL_NAMES[(int)$this->special];
    }

    public function setSpecial($special): void
    {
        if (!in_array((int)$special, array_keys(self::SPECIAL_NAMES)))
            throw new \Exception('Invalid special value');
        $this->special = $special;
    }

    public function getLogo()
    {
        return $this->logo;
    }

    public function setLogo($info)
    {
        $this->logo = (string)$info;
    }

    public function getIndiciaImage()
    {
        return $this->indicia_image;
    }

    public function setIndiciaImage($image)
    {
        $this->indicia_image = $image;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        if (!empty($data['indicia_image']))
            $data['indicia_image'] = base64_encode($data['indicia_image']);
        $id = Mysql::saveRow('shipping_services', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'code' => $this->getCode(),
            'carrier_id' => $this->getCarrierId(),
            'is_untracked' => $this->getIsUntracked(),
            'max_weight' => $this->getMaxWeight(),
            'next_day' => $this->getNextDay(),
            'special' => $this->getSpecial(),
            'logo' => $this->getLogo(),
            'indicia_image' => $this->getIndiciaImage(),
        ];
    }
}