<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class OrderLocationTemporaries
{
    public static function getSKUs($order_id)
    {
        $order_id = (int) $order_id;
        $sql = "SELECT s.name, olt.SKU_id, SUM(olt.quantity) qty, s.photo, s.serial_number, s.weight
                FROM order_location_temporary olt
                    JOIN SKUs s ON olt.SKU_id = s.id
                WHERE olt.order_id = :order_id
                GROUP BY olt.SKU_id";
        $items = (new SafeMysql())->fetchAll($sql, ['order_id' => $order_id]);
        SKUBarcodes::addBarcodesToArray($items, 'SKU_id');
        return $items;
    }

    public static function cancelOrder($order_id)
    {
        (new SafeMysql())->execute("DELETE FROM order_location_temporary WHERE order_id = :order_id", ['order_id' => (int)$order_id]);
    }

    public static function getQuantity($order_id, $location_id, $SKU_id)
    {
        $order_id = (int) $order_id;
        $location_id = (int) $location_id;
        $SKU_id = (int) $SKU_id;
        return (new SafeMysql())->fetchScalar("SELECT SUM(quantity)
                                  FROM order_location_temporary
                                  WHERE order_id = :order_id AND location_id = :location_id AND SKU_id = :SKU_id",
                                    [
                                        'order_id' => $order_id,
                                        'location_id' => $location_id,
                                        'SKU_id' => $SKU_id,
                                    ]);
    }

    public static function addSKU($SKU_id, $quantity, $order_id, $location_id)
    {
        $SKU_id = (int) $SKU_id;
        $quantity = (int) $quantity;
        $order_id = (int) $order_id;
        $location_id = (int) $location_id;
        $q_os = OrderSKUs::getQuantity($order_id, $SKU_id);
        $q_olt = self::getQuantity($order_id, $location_id, $SKU_id);
        $left = $q_os - $q_olt;
        if ($left <= 0 || $left < $quantity)
            return false;
        $mysql = new SafeMysql();
        if ($q_olt)
            return $mysql->execute("UPDATE order_location_temporary
                    SET quantity = quantity + :quantity
                    WHERE order_id = :order_id AND location_id = :location_id AND SKU_id = :SKU_id", [
                'quantity' => $quantity,
                'order_id' => $order_id,
                'location_id' => $location_id,
                'SKU_id' => $SKU_id,
            ]);
        else
            return $mysql->execute("INSERT INTO order_location_temporary (order_id, location_id, SKU_id, quantity)
                    VALUES (:order_id, :location_id, :SKU_id, :quantity)", [
                'order_id' => $order_id,
                'location_id' => $location_id,
                'SKU_id' => $SKU_id,
                'quantity' => $quantity,
            ]);
    }
}
