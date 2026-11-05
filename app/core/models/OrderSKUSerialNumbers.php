<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Warehouse;
use app\core\entities\OrderSKUSerialNumber;
use app\core\statical\Timezones;

class OrderSKUSerialNumbers
{
    public static function delByOrderId(int $order_id)
    {
        (new SafeMysql())->execute("DELETE FROM order_SKU_serial_numbers WHERE order_id = :order_id", ['order_id' => $order_id]);
    }

    /**
     * @param int $order_id
     * @param int $warehouse_id
     * @param OrderSKUSerialNumber[] $objects
     */
    public static function bulkAdd(int $order_id, int $warehouse_id, array $objects)
    {
        $sql = "INSERT IGNORE INTO order_SKU_serial_numbers (order_id, SKU_id, serial_number, orders_packages_id) VALUES ";
        $values = $binds = [];
        foreach ($objects as $key => $object) {
            $values[] = "(:order_id_$key, :SKU_id_$key, :serial_number_$key, :orders_packages_id_$key)";
            $binds["order_id_$key"] = $order_id;
            $binds["SKU_id_$key"] = $object->getSKUId();
            $binds["serial_number_$key"] = $object->getSerialNumber();
            $binds["orders_packages_id_$key"] = $object->getOrdersPackagesId();
            //Оставил запись в цикле чтобы не ломать общую концепцию истории
            SKUHistories::addOrderSKUSerialNumbers($order_id, $object->getSKUId(), $object->getSerialNumber(), $object->getOrdersPackagesId(), $warehouse_id);
        }
        (new SafeMysql())->execute($sql . implode(", ", $values), $binds);
    }

    public static function getAllByOrderIdGroupSKUId(int $order_id, Warehouse $warehouse): array
    {
        $data = [];
        $sql = "SELECT SKU_id, serial_number, orders_packages_id, created_at FROM order_SKU_serial_numbers WHERE order_id = :order_id";
        foreach ((new SafeMysql())->fetchAll($sql, ['order_id' => $order_id]) as $item)
            $data[(int)$item['SKU_id']][(int)$item['orders_packages_id']][$item['serial_number']] = Timezones::getDiff($warehouse->getTimezone(), $item['created_at']);
        return $data;
    }

    public static function getBySKUId(int $SKU_id, Warehouse $warehouse, int $start = 0, int $limit = 10): array
    {
        $sql = "
        SELECT DISTINCT oSsn.order_id
        FROM order_SKU_serial_numbers oSsn
            JOIN orders o ON o.id = oSsn.order_id AND o.warehouse_id = :warehouse_id
        WHERE oSsn.SKU_id = :SKU_id 
        ORDER BY oSsn.order_id DESC
        LIMIT $start, $limit";
        $items = (new SafeMysql())->fetchAll($sql, ['SKU_id' => $SKU_id, 'warehouse_id' => $warehouse->getId()]);
        if (!$items)
            return [];
        $ids = [];
        foreach ($items as $item) {
            $ids[] = $item['order_id'];
        }
        $sql = "
        SELECT oSsn.order_id, oSsn.serial_number
        FROM order_SKU_serial_numbers oSsn
        WHERE oSsn.order_id IN (:order_id)
        ORDER BY oSsn.order_id DESC";
        $items = (new SafeMysql())->fetchAll($sql, ['order_id' => $ids]);
        $data = [];
        foreach ($items as $item)
            $data[$item['order_id']][] = $item['serial_number'];
        return $data;
    }

    public static function getCountBySKUId(int $SKU_id, Warehouse $warehouse): int
    {
        $sql = "
        SELECT count(DISTINCT oSsn.order_id)
        FROM order_SKU_serial_numbers oSsn
            JOIN orders o ON o.id = oSsn.order_id AND o.warehouse_id = :warehouse_id
        WHERE oSsn.SKU_id = :SKU_id";
        return (int)(new SafeMysql())->fetchScalar($sql, ['SKU_id' => $SKU_id, 'warehouse_id' => $warehouse->getId()]);
    }

    public static function getByOrderIds(array $ids): array
    {
        if (!$ids)
            return [];
        $items = (new SafeMysql())->fetchAll("SELECT order_id, SKU_id, serial_number FROM order_SKU_serial_numbers WHERE order_id IN (:order_id)", ['order_id' => $ids]);
        $data = [];
        foreach ($items as $item)
            $data[(int)$item['order_id']][(int)$item['SKU_id']][] = $item['serial_number'];
        return $data;
    }

    public static function getByOrderIdsWithOrdersPackagesId(array $ids): array
    {
        if (!$ids)
            return [];
        $items = (new SafeMysql())->fetchAll("SELECT order_id, SKU_id, serial_number, orders_packages_id FROM order_SKU_serial_numbers WHERE order_id IN (:order_id)", ['order_id' => $ids]);
        $data = [];
        foreach ($items as $item)
            $data[(int)$item['order_id']][(int)$item['SKU_id']][(int)$item['orders_packages_id']][] = $item['serial_number'];
        return $data;
    }

    public static function getSKUsBySKUs(array $ids, int $warehouse_id, bool $last_month = true): array
    {
        if (!$ids)
            return [];
        $where = $last_month ? "AND oSsn.created_at >= (NOW() - INTERVAL 1 MONTH)" : "";
        $sql = "
        SELECT oSsn.SKU_id
        FROM order_SKU_serial_numbers oSsn
            JOIN orders o ON o.id = oSsn.order_id AND o.warehouse_id = :warehouse_id
        WHERE oSsn.SKU_id IN (:SKU_id) $where";
        $items = (new SafeMysql())->fetchAll($sql, ['SKU_id' => $ids, 'warehouse_id' => $warehouse_id]);
        $data = [];
        foreach ($items as $item)
            $data[(int)$item['SKU_id']] = (int)$item['SKU_id'];
        return $data;
    }
}