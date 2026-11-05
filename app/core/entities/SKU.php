<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\models\Sizes;
use app\core\Storage;
use app\core\helpers\FileWrapper;

class SKU extends Entity
{
    private $id;
    private $name;
    private $gs1;
    private $serial_number;
    private $cost_value;
    private $cost_currency;
    private $market_value;
    private $market_currency;
    private $width;
    private $height;
    private $depth;
    private $weight;
    private $hs_code;
    private $season;
    private $customer_ref;
    private $customer_ref2;
    private $max_capacity;
    private $supplier_id;
    private $state;
    private $photo;
    private $photo_src;
    private $unique_customer_ref;
    private $country_id = 'UK';
    private $ignore_WBE = 0;
    private $DDS;
    private $exemption_TARIC;
    private static $photo_exist_multiton = [];
    private static $photo_multiton = [];

    const LOCKED_NUM        = 0;
    const ACTIVE_NUM        = 1;
    const UNEXPECTED_NUM    = 2;
    const NOT_MAPPED_NUM    = 3;
    const BUNDLE_NUM        = 4;

    const LOCKED        = 'Locked';
    const ACTIVE        = 'Active';
    const UNEXPECTED    = 'Unexpected';
    const NOT_MAPPED    = 'Not mapped';
    const BUNDLE        = 'Bundle';

    private static $stateName = [
        self::LOCKED_NUM => self::LOCKED,
        self::ACTIVE_NUM => self::ACTIVE,
        self::UNEXPECTED_NUM => self::UNEXPECTED,
        self::NOT_MAPPED_NUM => self::NOT_MAPPED,
        self::BUNDLE_NUM => self::BUNDLE,
    ];

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

    public function getGs1()
    {
        return $this->gs1;
    }

    public function setGs1($gs1)
    {
        $this->gs1 = (int)$gs1;
    }

    public function getSerialNumber(): int
    {
        return (int)$this->serial_number;
    }

    public function setSerialNumber($serial_number)
    {
        $this->serial_number = (int)$serial_number;
    }

    public function getCostValue()
    {
        return $this->cost_value;
    }

    public function setCostValue($cost_value)
    {
        $this->cost_value = (float)$cost_value;
    }

    public function getCostCurrency()
    {
        return $this->cost_currency;
    }

    public function setCostCurrency($currency)
    {
        $this->cost_currency = (string)$currency;
    }

    public function getMarketValue()
    {
        return $this->market_value;
    }

    public function setMarketValue($market_value)
    {
        $this->market_value = (float)$market_value;
    }

    public function getMarketCurrency()
    {
        return $this->market_currency;
    }

    public function setMarketCurrency($currency)
    {
        $this->market_currency = (string)$currency;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function setWidth($width)
    {
        $this->width = (float)$width;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function setHeight($height)
    {
        $this->height = (float)$height;
    }

    public function getDepth()
    {
        return $this->depth;
    }

    public function setDepth($depth)
    {
        $this->depth = (float)$depth;
    }

    public function getWeight()
    {
        return $this->weight;
    }

    public function setWeight($weight)
    {
        $this->weight = (float)$weight;
    }

    public function getHsCode()
    {
        return $this->hs_code;
    }

    public function setHsCode($hs_code)
    {
        $this->hs_code = (string)$hs_code;
    }

    public function getSeason()
    {
        return $this->season;
    }

    public function setSeason($season)
    {
        $this->season = (string)$season;
    }

    public function getCustomerRef()
    {
        return $this->customer_ref;
    }

    public function setCustomerRef($ref)
    {
        $this->customer_ref = (string)$ref;
    }

    public function getCustomerRef2()
    {
        return $this->customer_ref2;
    }

    public function setCustomerRef2($ref)
    {
        $this->customer_ref2 = (string)$ref;
    }

    public function getMaxCapacity()
    {
        return is_array($this->max_capacity) ? $this->max_capacity : [];
    }

    public function setMaxCapacity($max_capacity)
    {
        if (is_string($max_capacity))
            $max_capacity = json_decode($max_capacity, true);
        if (!empty($max_capacity) && is_array($max_capacity)) {
            $capacity_list = Sizes::getAllSizesName();
            foreach ($max_capacity as $key => $item)
                if (!isset($capacity_list[$key][0]))
                    unset($max_capacity[$key]);
        }
        $this->max_capacity = (array)$max_capacity;
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($supplier_id)
    {
        $this->supplier_id = (int)$supplier_id;
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state)
    {
        $this->state = (int)$state;
    }

    public static function getStateNames($from = 0)
    {
        $names = [];
        $i = $from;
        foreach (self::$stateName as $name)
            $names[$i++] = $name;
        return $names;
    }

    public static function getStateName($state)
    {
        if ($state < 0 || $state > self::BUNDLE_NUM)
            $state = self::UNEXPECTED_NUM;
        return self::$stateName[$state];
    }

    public function locked()
    {
        return $this->getState() === self::LOCKED_NUM;
    }

    public static function isActive($status)
    {
        return (int)$status === self::ACTIVE_NUM;
    }

    public function active()
    {
        return self::isActive($this->getState());
    }

    public static function isUnexpected($status)
    {
        return (int)$status === self::UNEXPECTED_NUM;
    }

    public static function canBeMapped($status)
    {
        return (int)$status === self::NOT_MAPPED_NUM;
    }

    public function getPhoto()
    {
        return $this->photo;
    }

    public function setPhoto($url)
    {
        $this->photo = (string)$url;
    }

    //Здесь 2 проблемы. Первое, такого не должно быть в энтити, второе, нужно проследить чтоб не было file system injections
    //Как минимум надо убирать "/" из названий файлов и проследить чтоб в базу писалось только название файла, без путей (поэтому я слеш не убираю, вдруг ты пишешь путь)
    public function showPhoto()
    {
        $filename = Storage::getFile(SKUS_STORAGE, $this->getPhoto());
        if (empty($filename))
            return '';
        $file_wrapper = new FileWrapper(FileWrapper::getContainerByPath($filename));
        $image_mime_type = $file_wrapper->getMimeType($filename);
        $image = stream_get_contents($file_wrapper->getStream($filename));
        $image = base64_encode($image);
        return "data:$image_mime_type;base64,$image";
    }

    public static function existPhotoSKU($file_name): bool
    {
        if (isset(self::$photo_exist_multiton[$file_name]))
            return self::$photo_exist_multiton[$file_name];
        self::$photo_exist_multiton[$file_name] = !empty(Storage::getFile(SKUS_STORAGE, $file_name));
        return self::$photo_exist_multiton[$file_name];
    }

    public static function showPhotoSKU($file_name): string
    {
        return "/image/public?path=" . $file_name;

//        if (isset(self::$photo_multiton[$file_name]))
//            return self::$photo_multiton[$file_name];
//        $filename = Storage::getFile(SKUS_STORAGE, $file_name);
//        if (empty($filename)) {
//            self::$photo_multiton[$file_name] = '';
//            return self::$photo_multiton[$file_name];
//        }
//        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($filename)));
//        $stream = $file_wrapper->getStream($filename);
//        $image = stream_get_contents($stream);
//        $image_mime_type = $file_wrapper->getMimeType($filename);
//        $image = base64_encode($image);
//        self::$photo_multiton[$file_name] = "data:$image_mime_type;base64,$image";
//        return self::$photo_multiton[$file_name];
    }

    public function getPhotoSrc()
    {
        return $this->photo_src;
    }

    public function setPhotoSrc($url)
    {
        $this->photo_src = (string)$url;
    }

    public function getUniqueCustomerRef()
    {
        return $this->unique_customer_ref;
    }

    public function setUniqueCustomerRef($unique_customer_ref)
    {
        $this->unique_customer_ref = $unique_customer_ref;
    }

    public function getCountryId()
    {
        return $this->country_id;
    }

    public function setCountryId($country_id)
    {
        $this->country_id = (string)$country_id;
    }

    public function getIgnoreWBE()
    {
        return $this->ignore_WBE;
    }

    public function setIgnoreWBE($ignore_WBE)
    {
        $this->ignore_WBE = (string)$ignore_WBE;
    }

    public function getDDS()
    {
        return $this->DDS;
    }

    public function setDDS($num)
    {
        $this->DDS = (string)$num;
    }

    public function getExemptionTARIC()
    {
        return $this->exemption_TARIC;
    }

    public function setExemptionTARIC($num)
    {
        $this->exemption_TARIC = (string)$num;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        $data['max_capacity'] = json_encode($data['max_capacity']);
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('SKUs', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'gs1' => $this->getGs1(),
            'serial_number' => $this->getSerialNumber(),
            'cost_value' => $this->getCostValue(),
            'cost_currency' => $this->getCostCurrency(),
            'market_value' => $this->getMarketValue(),
            'market_currency' => $this->getMarketCurrency(),
            'width' => $this->getWidth(),
            'height' => $this->getHeight(),
            'depth' => $this->getDepth(),
            'weight' => $this->getWeight(),
            'hs_code' => $this->getHsCode(),
            'season' => $this->getSeason(),
            'customer_ref' => $this->getCustomerRef(),
            'customer_ref2' => $this->getCustomerRef2(),
            'max_capacity' => $this->getMaxCapacity(),
            'supplier_id' => $this->getSupplierId(),
            'state' => $this->getState(),
            'photo' => $this->getPhoto(),
            'photo_src' => $this->getPhotoSrc(),
            'unique_customer_ref' => $this->getUniqueCustomerRef(),
            'country_id' => $this->getCountryId(),
            'ignore_WBE' => $this->getIgnoreWBE(),
            'DDS' => $this->getDDS(),
            'exemption_TARIC' => $this->getExemptionTARIC(),
        ];
    }

    public function toArrayView()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'gs1' => $this->getGs1(),
            'cost_value' => $this->getCostValue(),
            'cost_currency' => $this->getCostCurrency(),
            'market_value' => $this->getMarketValue(),
            'market_currency' => $this->getMarketCurrency(),
            'width' => $this->getWidth(),
            'height' => $this->getHeight(),
            'depth' => $this->getDepth(),
            'weight' => $this->getWeight(),
            'hs_code' => $this->getHsCode(),
            'season' => $this->getSeason(),
            'customer_ref' => $this->getCustomerRef(),
            'customer_ref2' => $this->getCustomerRef2(),
        ];
    }
}
