<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\services\crypto\StoredSecret;

class Warehouse extends Entity
{
    /**
     * For a virtual warehouse picking_history holds its connection settings; these keys are encrypted at rest by
     * StoredSecret (ADR-002 Phase 2). The entity holds the plaintext: setPickingHistory() decrypts, save() encrypts.
     */
    public const SECRET_CONNECTION_FIELDS = ['api_key'];

    private $id;
    private $name;
    private $CA;
    private $TP;
    private $OB;
    private $path;
    private $billing;
    private $currency_id;
    private $label;
    private $start_warehouse;
    private $is_virtual;
    private $mezzanine;
    private $picking_history;
    private $default_shipping_service_id;
    private $country_id;
    private $address;
    private $collection_by_customer;
    private $timezone;
    private $lc_id;
    private $lang_id;
    private $is_multi_dropping;

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

    public function getCA()
    {
        $res = $this->CA;
        if (is_string($res))
            $res = json_decode($res, true);
        return $res;
    }

    public function setCA($loc)
    {
        $this->CA = $loc;
    }

    public function getTP()
    {
        $res = $this->TP;
        if (is_string($res))
            $res = json_decode($res, true);
        return $res;
    }

    public function setTP($loc)
    {
        $this->TP = $loc;
    }

    public function getOB()
    {
        $res = $this->OB;
        if (is_string($res))
            $res = json_decode($res, true);
        return $res;
    }

    public function setOB($loc)
    {
        $this->OB = $loc;
    }

    public function getPath()
    {
        $res = $this->path;
        if (is_string($res))
            $res = json_decode($res, true);
        return $res;
    }

    public function setPath($str)
    {
        $this->path = $str;
    }

    public function getBilling()
    {
        return $this->billing;
    }

    public function setBilling($name)
    {
        $this->billing = (string)$name;
    }

    public function getCurrencyId()
    {
        return $this->currency_id;
    }

    public function setCurrencyId($id)
    {
        $this->currency_id = (int)$id;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function setLabel($name)
    {
        $this->label = (string)$name;
    }

    public function getStartWarehouse()
    {
        return $this->start_warehouse;
    }

    public function setStartWarehouse($date)
    {
        $this->start_warehouse = (string)$date;
    }

    public function getIsVirtual()
    {
        return $this->is_virtual;
    }

    public function setIsVirtual($virtual)
    {
        $this->is_virtual = (bool)$virtual;
    }

    public function isVirtual(): bool
    {
        return !empty($this->is_virtual);
    }

    public function getMezzanine()
    {
        return $this->mezzanine;
    }

    public function getMultiDropping()
    {
        return $this->is_multi_dropping;
    }

    public function setIsMultiDropping($is_multi_dropping)
    {
        $this->is_multi_dropping = (int)$is_multi_dropping;
    }

    public function setMezzanine($is_mezzanine)
    {
        $this->mezzanine = (int)$is_mezzanine;
    }

    public function getPickingHistory()
    {
        return $this->picking_history;
    }

    public function setPickingHistory($name)
    {
        if (is_string($name))
            $name = json_decode($name, true);
        if (is_array($name))
            $name = StoredSecret::decryptFields($name, self::SECRET_CONNECTION_FIELDS);
        $this->picking_history = $name;
    }

    public function getPickingZones($type_id, $mezzanine = null)
    {
        $mezzanine = (int)$mezzanine;
        if (!empty($this->picking_history[$mezzanine][$type_id]))
            return $this->picking_history[$mezzanine][$type_id];
        return [];
    }

    public function getPickingZone($type_id, $zone, $mezzanine = null)
    {
        $mezzanine = (int)$mezzanine;
        if (!empty($this->picking_history[$mezzanine][$type_id][$zone]))
            return (int)$this->picking_history[$mezzanine][$type_id][$zone];
        return 0;
    }

    public function setPickingZone($time, $type_id, $zone, $mezzanine = null)
    {
        $mezzanine = (int)$mezzanine;
        $this->picking_history[$mezzanine][$type_id][$zone] = (int)$time;
    }

    public function getDefaultShippingServiceId()
    {
        return $this->default_shipping_service_id;
    }

    public function setDefaultShippingServiceId($id)
    {
        $this->default_shipping_service_id = (int)$id;
    }

    public function getCountryId()
    {
        return $this->country_id;
    }

    public function setCountryId($id)
    {
        $this->country_id = $id;
    }

    public function getAddress()
    {
        $res = $this->address;
        if (is_string($res))
            $res = json_decode($res, true);
        return $res;
    }

    public function setAddress($str)
    {
        if (empty($str))
            $str = [];
        if (is_array($str))
            $str = json_encode($str);
        elseif (is_string($str)) {
            $tmp = json_decode($str, true);
            if (!is_array($tmp))
                return;
        } else
            return;
        $this->address = $str;
    }

    public function getCollectionByCustomer()
    {
        return $this->collection_by_customer;
    }

    public function setCollectionByCustomer($collection_by_customer)
    {
        $this->collection_by_customer = (bool)$collection_by_customer;
    }

    public function getTimezone()
    {
        return $this->timezone;
    }

    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
    }

    public function getLCId()
    {
        return $this->lc_id;
    }

    public function setLCId($id)
    {
        $this->lc_id = (int)$id;
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
        $data['CA'] = json_encode($data['CA']);
        $data['TP'] = json_encode($data['TP']);
        $data['OB'] = json_encode($data['OB']);
        $data['path'] = json_encode($data['path']);
        $data['picking_history'] = json_encode(is_array($data['picking_history'])
            ? StoredSecret::encryptFields($data['picking_history'], self::SECRET_CONNECTION_FIELDS)
            : $data['picking_history']);
        $data['address'] = json_encode($data['address']);
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('warehouses', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'CA' => $this->getCA(),
            'TP' => $this->getTP(),
            'OB' => $this->getOB(),
            'path' => $this->getPath(),
            'billing' => $this->getBilling(),
            'currency_id' => $this->getCurrencyId(),
            'label' => $this->getLabel(),
            'start_warehouse' => $this->getStartWarehouse(),
            'is_virtual' => $this->getIsVirtual(),
            'mezzanine' => $this->getMezzanine(),
            'picking_history' => $this->getPickingHistory(),
            'default_shipping_service_id' => $this->getDefaultShippingServiceId(),
            'country_id' => $this->getCountryId(),
            'address' => $this->getAddress(),
            'collection_by_customer' => $this->getCollectionByCustomer(),
            'timezone' => $this->getTimezone(),
            'lc_id' => $this->getLCId(),
            'lang_id' => $this->getLangId(),
            'is_multi_dropping' => $this->getMultiDropping(),
        ];
    }
}
