<?php

namespace app\core\helpers\status;

use app\core\entities\Charge;

class ChargeType extends Status
{
    public static function getStatusName($status): string
    {
        return Charge::TYPE_NAMES[$status] ?? 'unknown';
    }

    public static function getStatusStyle($status): string
    {
        switch ($status) {
            case Charge::TYPE_STORAGE_PF:
            case Charge::TYPE_STORAGE_ST:
                return 'primary';
            case Charge::TYPE_PICK:
            case Charge::TYPE_PACKAGING:
            case Charge::TYPE_CULLING_PACKAGING:
            case Charge::TYPE_BUNDLING_PACKAGING:
            case Charge::TYPE_CULLING_PICK:
            case Charge::TYPE_BUNDLING_PICK:
                return 'success';
            case Charge::TYPE_LOCATION_CULLING:
            case Charge::TYPE_BOOKING_IN:
                return 'info';
            default:
                return 'danger';
        }
    }
}