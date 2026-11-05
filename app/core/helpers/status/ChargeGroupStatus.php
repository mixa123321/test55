<?php

namespace app\core\helpers\status;

use app\core\entities\ChargeGroup;

class ChargeGroupStatus extends Status
{
    public static function getStatusName($status): string
    {
        return ChargeGroup::STATUS_NAMES[$status] ?? 'unknown';
    }

    public static function getStatusStyle($status): string
    {
        switch ($status) {
            case ChargeGroup::STATUS_ACTIVE:
                return 'success';
            case ChargeGroup::STATUS_LOCK:
            default:
                return 'danger';
        }
    }
}