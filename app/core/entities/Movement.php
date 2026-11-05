<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class Movement extends Entity
{
    const ERROR_NOT_LOC_IN_SKU = 'Not found location in SKU inventory';

    const NON_PRIORITY              =   0;
    const PF_REPLEN_PRIORITY        =  10;
    const MOVEMENT_IN_CART_PRIORITY =  20;
    const CANCEL_ORDER_PRIORITY     = 100;
    const ORDER_REPLEN_PRIORITY     = 110;
    const EXPIRED_PRIORITY          = 120;

    const NEW_MOVEMENT              = 'new_movement';
    const IN_PROGRESS               = 'in_progress';
    const NON_COMPLIANT             = 'non_compliant';
    const PARTIAL                   = 'partial';
    const EXPIRED_BATCH             = 'expired_batch';
    const IMPOSSIBLE                = 'impossible';

    const REPLENISH                 = 'Replenish';
    const ORDER                     = 'Order';
    const CANCEL_ORDER              = 'Cancel order';

    const ALL_STATUSES = [
        self::NEW_MOVEMENT,
        self::IN_PROGRESS,
        self::NON_COMPLIANT,
        self::PARTIAL,
        self::EXPIRED_BATCH,
//        self::IMPOSSIBLE,
    ];

    const WORKER_STATUSES = [
        self::NEW_MOVEMENT,
        self::EXPIRED_BATCH,
    ];

    const SKIP_STATUSES = [
        self::EXPIRED_BATCH,
        self::IMPOSSIBLE,
    ];

    const COMMENT_FILTERS = [
        1 => self::REPLENISH,
        2 => self::ORDER,
        3 => self::CANCEL_ORDER,
    ];

    private static $status_name = [
        self::NEW_MOVEMENT => 'New movement',
        self::IN_PROGRESS => 'In progress',
        self::NON_COMPLIANT => 'Non-compliant',
        self::PARTIAL => 'Partial',
        self::EXPIRED_BATCH => 'Expired batch',
        self::IMPOSSIBLE => 'Impossible',
    ];

    private static $status_color = [
        self::NEW_MOVEMENT => 'success',
        self::IN_PROGRESS => 'warning',
        self::NON_COMPLIANT => 'danger',
        self::PARTIAL => 'danger',
        self::EXPIRED_BATCH => 'warning',
        self::IMPOSSIBLE => 'danger',
    ];

    const TYPE_UNRESTRICTED     = 0;
    const TYPE_STANDARD         = 1;
    const TYPE_CULLING          = 2;
    const TYPE_STOCK_MANAGEMENT = 3;
    const TYPE_CLIENT_REQUEST   = 4;
    const TYPE_UPLOAD           = 5;

    public static $type_name = [
        self::TYPE_STANDARD => 'System',
        self::TYPE_CULLING => 'Culling',
        self::TYPE_STOCK_MANAGEMENT => 'Stock Management',
        self::TYPE_CLIENT_REQUEST => 'Client Request',
        self::TYPE_UPLOAD => 'Upload',
        self::TYPE_UNRESTRICTED => 'Processing',
    ];

    private static $type_color = [
        self::TYPE_UNRESTRICTED => 'success',
        self::TYPE_STANDARD => 'success',
        self::TYPE_CULLING => 'warning',
        self::TYPE_STOCK_MANAGEMENT => 'success',
        self::TYPE_CLIENT_REQUEST => 'success',
        self::TYPE_UPLOAD => 'warning',
    ];

    private $id;
    private $SKU_id;
    private $quantity;
    private $loc_from_id;
    private $loc_to_id;
    private $create_date;
    private $create_user_id;
    private $user_id;
    private $status;
    private $comment;
    private $priority;
    private $reason;
    private $type = self::TYPE_STANDARD;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($id)
    {
        $this->SKU_id = (int)$id;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($qty)
    {
        $this->quantity = (int)$qty;
    }

    public function getLocFromId()
    {
        return $this->loc_from_id;
    }

    public function setLocFromId($id)
    {
        $this->loc_from_id = (int)$id;
    }

    public function getLocToId()
    {
        return $this->loc_to_id;
    }

    public function setLocToId($id)
    {
        $this->loc_to_id = (int)$id;
    }

    public function getCreateDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->create_date);
    }

    public function getCreateDateInt()
    {
        return $this->create_date;
    }

    public function setCreateDate($create_date)
    {
        $this->create_date = WithDate::dateToInt($create_date);
    }

    public function getCreateUserId()
    {
        return $this->create_user_id;
    }

    public function setCreateUserId($id)
    {
        $this->create_user_id = (int)$id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($id)
    {
        $this->user_id = (int)$id;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public static function getStatusName($status)
    {
        return self::$status_name[$status];
    }

    public static function getStatusColor($status)
    {
        return self::$status_color[$status];
    }

    public function setStatus($txt)
    {
        $this->status = (string)$txt;
    }

    public static function getStatusAll()
    {
        return self::$status_name;
    }

    public static function getTypeAll()
    {
        return self::$type_name;
    }

    public static function getTypeName($type)
    {
        return self::$type_name[$type];
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function setComment($txt)
    {
        if (empty($txt))
            return;
        if (!is_array($txt)) {
            $tmp = json_decode($txt, true);
            if (!is_array($tmp))
                $tmp = [$txt];
            $txt = $tmp;
        }
        $this->comment = json_encode($txt);
    }

    public function addComment($comment)
    {
        $tmp = json_decode($this->getComment(), true) ?? [];
        if (empty($comment) || in_array($comment, $tmp))
            return;
        $tmp[] = $comment;
        $this->setComment($tmp);
    }

    public function addComments($comments)
    {
        if (!is_array($comments))
            $comments = [$comments];
        foreach ($comments as $comment)
            $this->addComment($comment);
    }

    public function getPriority()
    {
        return (int) $this->priority;
    }

    public function setPriority($priority)
    {
        $this->priority = (int) $priority;
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function setReason($txt)
    {
        $this->reason = (string)$txt;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = (int)$type;
    }

    public static function getTypeColor($type)
    {
        return self::$type_color[$type];
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('movements', $data);
        if (empty($this->getId()))
            $this->setId($id);
    }

    public static function isNewMovement($status)
    {
        return $status === self::NEW_MOVEMENT;
    }

    public static function isInProgress($status)
    {
        return $status === self::IN_PROGRESS;
    }

    public static function isNonCompliant($status)
    {
        return $status === self::NON_COMPLIANT;
    }

    public static function isPartial($status): bool
    {
        return $status === self::PARTIAL;
    }

    public static function isExpired($status)
    {
        return $status === self::EXPIRED_BATCH;
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'SKU_id' => $this->getSKUId(),
            'quantity' => $this->getQuantity(),
            'loc_from_id' => $this->getLocFromId(),
            'loc_to_id' => $this->getLocToId(),
            'create_date' => $this->getCreateDate(),
            'create_user_id' => $this->getCreateUserId(),
            'user_id' => $this->getUserId(),
            'status' => $this->getStatus(),
            'comment' => $this->getComment(),
            'priority' => $this->getPriority(),
            'reason' => $this->getReason(),
            'type' => $this->getType(),
        ];
    }
}
