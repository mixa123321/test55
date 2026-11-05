<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class Returns extends Entity
{
    const ERROR = -2;
    const CANCEL = -1;
    const NEW = 1;
    const ACTIVE = 2;
    const DONE = 3;

    private $id;
    private $tracking_number;
    private $barcode_number;
    private $secondary_barcode;
    private $status = self::NEW;
    private $user_id;
    private $create_date;
    private $update_date;
    private $return_carrier_id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getTrackingNumber()
    {
        return $this->tracking_number;
    }

    public function setTrackingNumber($track)
    {
        $this->tracking_number = (string)$track;
    }

//    public function getBarcodeNumber()
//    {
//        return $this->barcode_number;
//    }
//
//    public function setBarcodeNumber($number)
//    {
//        $this->barcode_number = (string)$number;
//    }

    public function getSecondaryBarcode()
    {
        return $this->secondary_barcode;
    }

    public function setSecondaryBarcode($barcode)
    {
        $this->secondary_barcode = (string)$barcode;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($num)
    {
        $this->status = (int)$num;
    }

    public function isNew()
    {
        return $this->status == self::NEW;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($id)
    {
        $this->user_id = (int)$id;
    }

    public function getCreateDate()
    {
        return empty($this->create_date) ? '' : date(WithDate::DATE_SQL_FORMAT, $this->create_date);
    }

    public function setCreateDate($create_date): void
    {
        $this->create_date = WithDate::dateToInt($create_date);
    }

    public function getUpdateDate()
    {
        return empty($this->update_date) ? '' : date(WithDate::DATE_SQL_FORMAT, $this->update_date);
    }

    public function setUpdateDate($update_date)
    {
        $this->update_date = WithDate::dateToInt($update_date);
    }

    public function getReturnCarrierId()
    {
        return $this->return_carrier_id;
    }

    public function setReturnCarrierId($id)
    {
        $this->return_carrier_id = (int)$id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('returns', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'tracking_number' => $this->getTrackingNumber(),
//            'barcode_number' => $this->getBarcodeNumber(),
            'secondary_barcode' => $this->getSecondaryBarcode(),
            'status' => $this->getStatus(),
            'user_id' => $this->getUserId(),
            'create_date' => $this->getCreateDate(),
            'update_date' => $this->getUpdateDate(),
            'return_carrier_id' => $this->getReturnCarrierId()
        ];
    }
}
