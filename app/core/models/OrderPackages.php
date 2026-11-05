<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Order;
use app\core\entities\Shipping;
use app\core\models\Packages;

class OrderPackages
{
    /**
     * @param Order[] $order
     * @return array
     * @throws \app\core\db\DBException
     */
    public static function getTrackingInfoByOrder(array $orders): array
    {
        if (!$orders)
            return [];
        $ids = [];
        foreach ($orders as $order)
            $ids[] = (int)$order->getId();
        $sql = "
        SELECT op.id, s.tracking_number, s.url
        FROM orders_packages op
            INNER JOIN order_packages_shipping ops ON ops.order_package_id = op.id
            INNER JOIN shipping s ON s.id = ops.shipping_id AND s.status = :status
        WHERE op.is_cancel = 0 AND op.order_id IN (:order_ids)";
        return (new SafeMysql())->fetchAll($sql, ['status' => Shipping::STATUS_SUCCESS, 'order_ids' => $ids]);
    }

    public static function getSKUsAndQuantity(Order $order): array
    {
        $sql = "
        SELECT op.id, opS.quantity, opS.sku_id
        FROM orders_packages op
            JOIN orders_packages_SKUs opS ON opS.orders_packages_id = op.id
        WHERE op.is_cancel = 0 AND op.order_id = :order_id";
        return (new SafeMysql())->fetchAll($sql, ['order_id' => (int)$order->getId()]);
    }

    public static function deleteByOrderId(int $order_id)
    {
        (new SafeMysql())->execute("DELETE FROM orders_packages WHERE order_id = :order_id", ['order_id' => $order_id]);
    }
}