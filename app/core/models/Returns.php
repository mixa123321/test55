<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Returns as ReturnsEntity;

class Returns
{
    const EXPIRE = 6; // month

    public static function getById($id)
    {
        $data = (new SafeMysql())->fetchOne('SELECT * FROM `returns` WHERE id = :id', ['id' => (int)$id]);
        return empty($data) ? null : new ReturnsEntity($data);
    }

    public static function getByOrderId($order_id, $errors = false)
    {
        return (new SafeMysql())->fetchAll("
            SELECT r.*, rc.is_test FROM `returns` r
                JOIN order_packages_returns opr ON r.id = opr.return_id
                JOIN orders_packages op ON opr.order_package_id = op.id AND op.is_cancel = 0
                JOIN return_carriers rc ON r.return_carrier_id = rc.id
            WHERE op.order_id = :order_id" . ($errors ? '' : ' AND r.status = 1'),
            ['order_id' => (int)$order_id]);
    }

    public static function getReturnDataByOrderId($order_id)
    {
        return (new SafeMysql())->fetchAll("
            SELECT r.*
            FROM `returns` r
                JOIN order_packages_returns opr ON r.id = opr.return_id
                JOIN orders_packages op ON op.id = opr.order_package_id AND op.order_id = :order_id
            ORDER BY r.create_date DESC",
            ['order_id' => (int)$order_id]);
    }

    public static function getNew()
    {
        return (new SafeMysql())->fetchAll("SELECT r.id, r.tracking_number, rc.is_test
                FROM `returns` r
                    JOIN return_carriers rc ON r.return_carrier_id = rc.id
                WHERE r.`status` = :status AND r.create_date >= DATE_ADD(NOW(), INTERVAL - " . self::EXPIRE . " MONTH)",
            ['status' => ReturnsEntity::NEW]);
    }

    public static function getActive()
    {
        return (new SafeMysql())->fetchAll("SELECT * FROM `returns` WHERE `status` = :status",
            ['status' => ReturnsEntity::ACTIVE]);
    }

    public static function getOrders4BillingReturns($warehouse_id)
    {
        $returns = (new SafeMysql())->fetchAll("SELECT op.id, o.id order_id, w.address, r.tracking_number, r.update_date, op.weight, op.length, op.width, op.height
                FROM `returns` r
                    JOIN order_packages_returns opr ON r.id = opr.return_id
                    JOIN orders_packages op ON opr.order_package_id = op.id
                    JOIN orders o ON op.order_id = o.id AND o.warehouse_id = :warehouse_id
                    JOIN warehouses w ON o.warehouse_id = w.id
                WHERE r.status = :status",
            ['warehouse_id' => (int)$warehouse_id, 'status' => ReturnsEntity::ACTIVE]);
        if (empty($returns))
            return [];
        $res = [];
        foreach ($returns as $return) {
            $res[$return['order_id']]['address'] = $return['address'];
            $res[$return['order_id']]['returns'][$return['id']] = [
                'tracking_number' => $return['tracking_number'],
                'update_date' => $return['update_date'],
                'weight' => $return['weight'],
                'length' => $return['length'],
                'width' => $return['width'],
                'height' => $return['height'],
            ];
        }
        return $res;
    }
}
