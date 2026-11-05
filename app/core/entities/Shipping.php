<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class Shipping extends Entity
{
    const COLLECTION_BY_CUSTOMER_ID = 0;
    const COLLECTION_BY_CUSTOMER = 'Collection by customer';

    const STATUS_RESERVED   = 1;
    const STATUS_ACCEPT     = 2;
    const STATUS_WARNING    = 31;
    const STATUS_ERROR      = 310;
    const STATUS_SUCCESS    = 4;

    private $id;
    private $tracking_number;
    private $status = 1;
    private $url;
    private $user_id;
    private $reference;
    private $carrier_tracking_number;
    private $create_date;
    private $carrier_id;

    private static $statusName = [
        ['void', 'danger'],
        ['pending', 'success'],
        ['accepted', 'success'],
        ['error', 'danger'],
        ['confirmed', 'success'],
    ];

    public static function showStatus($status)
    {
        if (empty(self::$statusName[$status]))
            return '';
        return sprintf("<span class='text-%s'>%s</span>", self::$statusName[$status][1], self::$statusName[$status][0]);
    }

    public static function isConfirmed($status)
    {
        if (empty(self::$statusName[$status]))
            return false;
        return self::$statusName[$status][0] === 'confirmed';
    }

    public static function isError($status)
    {
        if (empty(self::$statusName[$status]))
            return false;
        return self::$statusName[$status][0] === 'error';
    }

    public function getReference()
    {
        return $this->reference;
    }

    public function setReference($reference)
    {
        $this->reference = (string)$reference;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($id)
    {
        $this->user_id = (int)$id;
    }

    public function getTrackingNumber()
    {
        return $this->tracking_number;
    }

    public function setTrackingNumber($track)
    {
        $this->tracking_number = (string)$track;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($num)
    {
        $this->status = (int)$num;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setUrl($url)
    {
        $this->url = (string)$url;
    }

    public function getCarrierTrackingNumber()
    {
        return $this->carrier_tracking_number;
    }

    public function setCarrierTrackingNumber($track)
    {
        $this->carrier_tracking_number = (string)$track;
    }

    public function getCreateDate()
    {
        return empty($this->create_date) ? '' : date(WithDate::DATE_SQL_FORMAT, $this->create_date);
    }

    public function setCreateDate($create_date): void
    {
        $this->create_date = WithDate::dateToInt($create_date);
    }

    public function getCarrierId()
    {
        return $this->carrier_id;
    }

    public function setCarrierId($carrier_id)
    {
        $this->carrier_id = $carrier_id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save)) {
            return;
        }
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('shipping', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'tracking_number' => $this->getTrackingNumber(),
            'status' => $this->getStatus(),
            'url' => $this->getUrl(),
            'user_id' => $this->getUserId(),
            'reference' => $this->getReference(),
            'carrier_tracking_number' => $this->getCarrierTrackingNumber(),
            'create_date' => $this->getCreateDate(),
            'carrier_id' => $this->getCarrierId()
        ];
    }
}