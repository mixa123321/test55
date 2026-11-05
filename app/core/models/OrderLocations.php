<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class OrderLocations
{
    public static function inLocation($location_id)
    {
        $location_id = (int)$location_id;
        return (bool)(new SafeMysql())->fetchScalar("SELECT COUNT(*) FROM order_location WHERE location_id = :location_id", ['location_id' => $location_id]);
    }

    public static function getLocation($order_id)
    {
        $sql = 'SELECT location_id FROM order_location WHERE order_id= :order_id';
        return (new SafeMysql())->fetchScalar($sql, ['order_id' => (int)$order_id]);
    }

    public static function cancelOrder($order_id)
    {
        (new SafeMysql())->execute("DELETE FROM order_location WHERE order_id = :order_id", ['order_id' => (int)$order_id]);
    }

    public static function reserveLocation($order_id, $location_id)
    {
        $order_id = (int) $order_id;
        $location_id = (int) $location_id;
        $mysql = new SafeMysql();
        $exist = $mysql->fetchScalar("
            SELECT 1
            FROM order_location
            WHERE order_id = :order_id", ['order_id' => $order_id]);
        if (!$exist)
            $mysql->execute("INSERT INTO order_location (order_id, location_id) VALUES (:order_id, :location_id)", [
                'order_id' => $order_id,
                'location_id' => $location_id,
            ]);
    }
}
