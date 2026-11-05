<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class OrdersPackagesSKUs
{
    public static function getSKUsFromPackage($id)
    {
        $id = (int)$id;
        if (empty($id))
            return [];
        $sql = "SELECT SKU_id, quantity
                FROM orders_packages_SKUs
                WHERE orders_packages_id = $id";
        return (new SafeMysql())->fetchAll("SELECT SKU_id, quantity FROM orders_packages_SKUs WHERE orders_packages_id = :id", ['id' => $id]);
    }

    public static function deleteByOrderId(int $order_id)
    {
        $sql = "
        DELETE FROM orders_packages_SKUs
        WHERE orders_packages_id IN (SELECT op.id 
                                     FROM orders_packages op 
                                     WHERE op.order_id = :order_id)";
        (new SafeMysql())->execute($sql, ['order_id' => $order_id]);
    }

    public static function getSKUsFromPackageByIds(array $ids)
    {
        if (!$ids)
            return [];
        $items = (new SafeMysql())->fetchAll("SELECT SKU_id, quantity, orders_packages_id FROM orders_packages_SKUs WHERE orders_packages_id IN (:ids)", ['ids' => array_map('intval', $ids)]);
        $result = [];
        foreach ($items as $item) {
            if (isset($result[(int)$item['orders_packages_id']][(int)$item['SKU_id']]))
                throw new \Exception('Duplicated SKU in package. orders_packages_id ' . $item['orders_packages_id'] . ' SKU_id ' . $item['SKU_id']);
            $result[(int)$item['orders_packages_id']][(int)$item['SKU_id']] = (int)$item['quantity'];
        }
        return $result;
    }
}
