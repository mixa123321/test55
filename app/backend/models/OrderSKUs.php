<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;

class OrderSKUs extends \app\core\models\OrderSKUs
{
    public static function isCompleted($order_id)
    {
        $order_id = (int) $order_id;
        $res1 = (new SafeMysql())->fetchAssocWithKey("SELECT SKU_id, SUM(quantity) num
                                         FROM order_SKUs
                                         WHERE order_id = :order_id
                                         GROUP BY SKU_id", ['order_id' => $order_id], 'SKU_id');
        $res2 = (new SafeMysql())->fetchAssocWithKey("SELECT SKU_id, SUM(quantity) num
                                         FROM order_location_temporary
                                         WHERE order_id = :order_id
                                         GROUP BY SKU_id", ['order_id' => $order_id], 'SKU_id');
        foreach ($res1 as $SKU_id => $num) {
            if (empty($res2[$SKU_id]))
                return false;
            if ($num['num'] != $res2[$SKU_id]['num'])
                return false;
            unset($res2[$SKU_id]);
        }
        return empty($res2);
    }

    public static function getCompleted($locs)
    {
        $loc_id = [];
        foreach ($locs as $loc)
            list($loc_id[], ) = explode('|', $loc);
        $sql = "SELECT o.id, (SELECT SUM(os.quantity) -
                                     (SELECT IFNULL(SUM(quantity),0) FROM order_location_temporary WHERE order_id=os.order_id)
                              FROM order_SKUs os
                              WHERE os.order_id=ol.order_id) as num
                FROM orders o, (SELECT order_id FROM order_location WHERE location_id in (:loc_ids)) ol
                WHERE o.id = ol.order_id";
        $res = (new SafeMysql())->fetchAll($sql, ['loc_ids' => $loc_id]);
        foreach ($res as $key => $line)
            if ($line['num'])
                unset($res[$key]);
            else
                $res[$key] = $line['id'];
        return array_values($res);
    }

    public static function getSKUsQuantitiesByOrder($order_id)
    {
        $order_skus = self::getSKUsByOrder($order_id);
        $qties = [];
        if (!empty($order_skus))
            foreach ($order_skus as $order_sku)
                $qties[$order_sku['SKU_id']] = $order_sku['quantity'];
        return $qties;
    }
}
