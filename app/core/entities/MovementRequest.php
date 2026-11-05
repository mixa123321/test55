<?php

namespace app\core\entities;

use app\core\db\Mysql;

class MovementRequest extends Entity
{
    const TYPE_LOCATION_FROM    = 'from';
    const TYPE_LOCATION_TO      = 'to';

    const CANCELLED             = 0;
    const NEW                   = 1;
    const IN_PROGRESS           = 2;
    const DONE                  = 3;

    const ALL_TYPES = [
        self::TYPE_LOCATION_FROM,
        self::TYPE_LOCATION_TO
    ];

    const ALL_STATUSES = [
        self::CANCELLED => 'cancelled',
        self::NEW => 'new',
        self::IN_PROGRESS => 'in progress',
        self::DONE => 'done'
    ];

    private static $status_color = [
        self::NEW => 'primary',
        self::IN_PROGRESS => 'warning',
        self::DONE => 'success',
        self::CANCELLED => 'default',
    ];

    private $id;
    private $SKU_id;
    private $location_id;
    private $type_location;
    private $quantity;
    private $accepted_quantity;
    private $status;

    public function getId()
    {
        return $this->id;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId(int $id)
    {
        $this->SKU_id = $id;
    }

    public function getLocationId()
    {
        return $this->location_id;
    }

    public function setLocationId(int $id)
    {
        $this->location_id = $id;
    }

    public function getTypeLocation()
    {
        return $this->type_location;
    }

    /** @throws */
    public function setTypeLocation(string $type)
    {
        if (!in_array($type, self::ALL_TYPES))
            throw new \Exception('Invalid movement request type');
        $this->type_location = $type;
    }

    public static function isFrom($type_location)
    {
        return $type_location === self::TYPE_LOCATION_FROM;
    }

    public function from()
    {
        return self::isFrom($this->getTypeLocation());
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity(int $qty)
    {
        $this->quantity = $qty;
    }

    public function getAcceptedQuantity()
    {
        return $this->accepted_quantity;
    }

    public function setAcceptedQuantity(int $qty)
    {
        $this->accepted_quantity = $qty;
    }

    public function addAcceptedQuantity(int $qty)
    {
        $this->accepted_quantity += $qty;
    }

    public function getStatus()
    {
        return $this->status;
    }

    /** @throws */
    public static function isStatus(int $status)
    {
        return in_array($status, array_keys(self::ALL_STATUSES));
    }

    /** @throws */
    public static function canCancelled(int $status)
    {
        return in_array($status, [self::NEW, self::IN_PROGRESS]);
    }

    /** @throws */
    public static function getStatusName(int $status)
    {
        if (!self::isStatus($status))
            throw new \Exception('Invalid movement request status');
        return self::ALL_STATUSES[$status];
    }

    public static function getStatusColor($status)
    {
        return self::$status_color[$status];
    }

    public function setStatus(int $status)
    {
        $this->status = $status;
    }

    public static function isInProgress(int $status)
    {
        return $status === self::IN_PROGRESS;
    }

    public function inProgress()
    {
        return self::isInProgress($this->status);
    }

    public function save(array $fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('movement_requests', $data);
        if (empty($this->getId()))
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'SKU_id' => $this->getSKUId(),
            'location_id' => $this->getLocationId(),
            'type_location' => $this->getTypeLocation(),
            'quantity' => $this->getQuantity(),
            'accepted_quantity' => $this->getAcceptedQuantity(),
            'status' => $this->getStatus(),
        ];
    }
}
