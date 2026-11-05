<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SKULocation extends Entity
{
    public const TYPE_PICK      = 1;
    public const TYPE_EXPIRED   = 4;

    private $SKU_id;
    private $location_id;
    private $quantity;
    private $order_id;
    private $type;
    private $reason;
    private $batch_id;

    // type
    // 1 - Pick
    // 21 - Picking (single)
    // 22 - Picking (multi)
    // 23 - Picking (large)
    // 3 - Dropping (partial)
    // 31 - Dropping
    // 4 - Expired
    private static $types = [
        '12' => 'place',
        '12_' => 'reserved',
        '11' => 'order',
        '21' => 'picking_single',
        '22' => 'picking_multi',
        '23' => 'picking_large',
        '3' => 'dropping_partial',
        '31' => 'dropping',
        '4' => 'expired',
    ];

    public static function isMoving($type_id_from, $type_id_to, $type, $reason = '')
    {
        $type_index = $type;
        if ($type == 1)
            $type_index .= substr($reason, 0, 2);
        switch (self::$types[$type_index]) {
            case 'place':
                return Location::isCart($type_id_to) || Location::isStorage($type_id_to) || Location::isNotCounted($type_id_to);
            case 'reserved':
                return (Location::isStorage($type_id_from) || Location::isCart($type_id_from))
                    && (Location::isStorage($type_id_to) || Location::isCart($type_id_to));
            case 'order':
                return Location::isStorage($type_id_from)
                    && Location::isCart($type_id_to);
            case 'picking_single':
                return Location::isCart($type_id_from)
                    && (Location::isCart($type_id_to) || Location::isTP($type_id_to));
            case 'picking_multi':
                return Location::isCart($type_id_from)
                    && (Location::isCart($type_id_to) || Location::isOB($type_id_to));
            case 'picking_large':
                return Location::isCart($type_id_from)
                    && (Location::isCart($type_id_to) || Location::isLP($type_id_to));
            case 'dropping_partial':
            case 'dropping':
                return Location::isCart($type_id_from)
                    && Location::isCart($type_id_to);
            case 'expired':
                return Location::isST($type_id_to) || Location::isCart($type_id_to);
        }
        return false;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($id)
    {
        $this->SKU_id = (int)$id;
    }

    public function getLocationId()
    {
        return $this->location_id;
    }

    public function setLocationId($id)
    {
        $this->location_id = (int)$id;
    }

    public function getOrderId()
    {
        return $this->order_id;
    }

    public function setOrderId($id)
    {
        $this->order_id = (int)$id;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($quan)
    {
        $this->quantity = (int)$quan;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($num)
    {
        $this->type = (int)$num;
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function setReason($num)
    {
        $this->reason = (string)$num;
    }

    public function getBatchId()
    {
        return $this->batch_id;
    }

    public function setBatchId($id)
    {
        $this->batch_id = (int)$id;
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
        Mysql::saveRow("SKU_locations", $data);
    }

    public function toArray()
    {
        return [
            'SKU_id' => $this->getSKUId(),
            'location_id' => $this->getLocationId(),
            'quantity' => $this->getQuantity(),
            'order_id' => $this->getOrderId(),
            'type' => $this->getType(),
            'reason' => $this->getReason(),
            'batch_id' => $this->getBatchId(),
        ];
    }
}
