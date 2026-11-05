<?php

namespace app\core\models;

use app\core\entities\ReturnCarrier;
use app\core\entities\Warehouse;
use app\core\entities\Returns as ReturnsEntity;
use app\core\helpers\Debug;

class ReturnSAPI
{
    public static function sendCancelReturnRequest($tracking_number, $is_test)
    {
        Debug::sendDebugInfo('Run Return SAPI (sendCancelReturnRequest)', json_encode($tracking_number));
        return ['errors' => 'Return is under construction'];
    }

    public static function createReturns($order_info, $sender, ReturnCarrier $return_carrier, Warehouse $warehouse, $package, $user_id): ?ReturnsEntity
    {
        Debug::sendDebugInfo('Run Return SAPI (createReturns)', json_encode($order_info));
        return null;
    }

    public static function sendReturnTrackingEvents($tracking_number, $is_test) : array
    {
        Debug::sendDebugInfo('Run Return SAPI (sendReturnTrackingEvents)', json_encode($tracking_number));
        return ['errors' => 'Return is under construction'];
    }
}
