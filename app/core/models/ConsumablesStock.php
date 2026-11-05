<?php

namespace app\core\models;

use app\core\entities\ConsumableStock;
use app\core\dbal\SafeMysql;

class ConsumablesStock
{
    public static function addStock($consumable_id, $warehouse_id, $quantity)
    {
        $data = [
            'consumable_id' => $consumable_id,
            'warehouse_id' => $warehouse_id,
            'quantity' => $quantity,
        ];
        $consumable_stock = new ConsumableStock($data);
        $consumable_stock->save(array_keys($data));
        return $consumable_stock->getId();
    }

    public static function getStock($consumable_id, $warehouse_id, $from_date)
    {
        return (new SafeMysql())->fetchAll("
            SELECT DATE_FORMAT(created_at,'%Y-%m-%d') create_date, quantity
            FROM consumable_stock
            WHERE consumable_id = :consumable_id AND warehouse_id = :warehouse_id AND DATE_FORMAT(created_at,'%Y-%m-%d') >= :from_date
            ORDER BY created_at", ['consumable_id' => (int)$consumable_id, 'warehouse_id' => (int)$warehouse_id, 'from_date' => $from_date]);
    }
}
