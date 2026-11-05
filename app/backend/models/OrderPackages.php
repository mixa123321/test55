<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;

class OrderPackages
{
    public static function getBoxes($order_id)
    {
        $sql = "SELECT op.id, sh.tracking_number, op.weight, op.length, op.width, op.height, sh.reference, op.order_inserts
                FROM orders_packages op
                    LEFT JOIN order_packages_shipping ops ON ops.order_package_id = op.id
                    JOIN shipping sh ON ops.shipping_id = sh.id AND sh.status = 4
                WHERE op.is_cancel = 0 AND op.order_id = :order_id";
        return (new SafeMysql())->fetchAll($sql, ['order_id' => (int)$order_id]);
    }

    public static function getBoxesNotCancelled($order_id)
    {
        $sql = "SELECT id, package_type_id
                FROM orders_packages
                WHERE is_cancel = 0 AND order_id = :order_id";
        return (new SafeMysql())->fetchAll($sql, ['order_id' => (int)$order_id]);
    }
}
