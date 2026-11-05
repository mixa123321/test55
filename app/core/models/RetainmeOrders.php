<?php

namespace app\core\models;

use app\backend\models\SupplierWarehouses;
use app\core\dbal\SafeMysql;
use app\core\entities\Order;

class RetainmeOrders
{
    public static function addOrder(Order $order)
    {
        if (empty($order))
            return false;
        $packing_slip_type = SupplierWarehouses::getPackingSlipType($order->getWarehouseId(), $order->getSupplierId(), $order->getShopId());
        $is_need = (int) ($packing_slip_type === PackingSlips::TYPE_RETAIN_ME);
        (new SafeMysql())->execute("INSERT IGNORE INTO retain_me_orders (id, order_id, is_need) VALUES (NULL, :order_id, :is_need)", [
            'order_id' => $order->getId(),
            'is_need' => $is_need,
        ]);
        return true;
    }

    public static function getOrders()
    {
        return (new SafeMysql())->fetchArrayOneField("
            SELECT order_id
            FROM retain_me_orders
            WHERE is_need = 1
            ORDER BY order_id");
    }

    public static function updateOrders($orders)
    {
        if (empty($orders))
            return;
        (new SafeMysql())->execute("
            UPDATE retain_me_orders
            SET is_need = 2
            WHERE order_id IN (:order_ids)", ['order_ids' => $orders]);
    }
}