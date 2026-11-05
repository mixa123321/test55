<?php

namespace app\core\helpers\status;

use app\core\entities\Charge;

class ChargeStatus extends Status
{
    public static function getStatusName($status): string
    {
        return Charge::STATUS_NAMES[$status] ?? 'unknown';
    }

    public static function getStatusStyle($status): string
    {
        switch ($status) {
            case Charge::STATUS_ACTIVE:
                return 'success';
            case Charge::STATUS_LOCK:
            default:
                return 'danger';
        }
    }
}