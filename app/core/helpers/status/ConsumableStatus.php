<?php

namespace app\core\helpers\status;

use app\core\entities\Consumable;

class ConsumableStatus extends Status
{
    public static function getStatusName($status): string
    {
        return Consumable::STATUSES[$status] ?? 'unknown';
    }

    public static function getStatusStyle($status): string
    {
        switch ($status) {
            case Consumable::STATUS_ACTIVE:
                return 'success';
            default:
                return 'danger';
        }
    }
}