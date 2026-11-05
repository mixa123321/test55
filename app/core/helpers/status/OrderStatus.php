<?php

namespace app\core\helpers\status;

use app\core\entities\Order;

class OrderStatus extends Status
{
    public static function getStatusName($status): string
    {
        return Order::getStateName($status);
    }

    public static function getStatusStyle($status): string
    {
        return Order::colorStatus($status);
    }
}