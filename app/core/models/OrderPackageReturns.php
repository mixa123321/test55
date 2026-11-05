<?php

namespace app\core\models;

use app\core\helpers\Arr;
use app\core\dbal\SafeMysql;

class OrderPackageReturns
{
    public static function getReturns($order_package_ids, $all = false)
    {
        if (empty($order_package_ids) || !is_array($order_package_ids))
            return [];
        $where_status = !$all ? 'AND status = 1' : '';
        $sql = "SELECT order_package_id, return_id
                FROM order_packages_returns
                    JOIN `returns` ON return_id = id $where_status
                WHERE order_package_id IN (:order_package_ids)
                ORDER BY order_package_id, create_date DESC";
        $returns = (new SafeMysql())->fetchAll($sql, ['order_package_ids' => $order_package_ids]);
        return !$all ? Arr::map($returns, 'order_package_id', 'return_id') : $returns;
    }

    public static function getByOrderId($order_id): array
    {
        return (new SafeMysql())->fetchAll('SELECT op.id as order_package_id, r.* FROM `returns` r
                                            JOIN order_packages_returns opr on r.id = opr.return_id
                                            JOIN orders_packages op ON op.id = opr.order_package_id AND op.order_id = :order_id',
                                                ['order_id' => (int)$order_id]);
    }
}
