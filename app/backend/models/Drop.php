<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\entities\Order;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\models\OrderLocations;
use app\core\models\OrderLocationTemporaries;

class Drop
{
    public const LIMIT_PER_PAGE = 20;

    public static function getDroppingUrlByMode($mode)
    {
        return '/drop/dropping_' . substr($mode, 0, 1);
    }

    public static function dropSKUs(Warehouse $warehouse, $mode, Location $location, User $user, int $table_id, int $order_id = null)
    {
        list(, $orders) = SKULocations::getDroppingByWorker($mode, $location->getId());
        $status = '2' . Pick::$mode_val[$mode][0];
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        foreach ($orders as $id => $order) {
            list(, $SKU_id, $batch_id) = explode('_', $id);
            if ($batch_id == '.')
                $batch_id = '';
            foreach ($order as $_order_id => $quantity) {
                if ($order_id && $order_id != $_order_id)
                    continue;
                OrderLocationTemporaries::addSKU($SKU_id, $quantity, $_order_id, $table_id);
                OrderLocations::reserveLocation($_order_id, $table_id);
                $move_id = Movements_::setDropping($table_id, $_order_id, $SKU_id, $batch_id, $quantity, $user->getId());
                SKUHistories::addDroppingTable($location->getId(), $_order_id, $SKU_id, $batch_id, $quantity, $move_id, $user->getId(), $warehouse->getId());
                Pick::addNearTable($SKU_id, $batch_id, $quantity, $status, $_order_id, $location->getId(), $user->getId());
                if ($mode === 'single')
                    Orders::changeStatus($_order_id, Order::AWAITING_PACK, $user->getId());
                if ($mode !== 'single')
                    $ords[$_order_id] = 1;
            }
        }
        foreach ($ords as $_order_id => $tmp)
            if (OrderSKUs::isCompleted($_order_id) && (!$order_id || $order_id == $_order_id))
                Orders::changeStatus($_order_id, Order::AWAITING_PACK, $user->getId());
        $mysql->commit();
    }
}