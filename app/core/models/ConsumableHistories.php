<?php

namespace app\core\models;

use app\core\entities\Consumable;
use app\core\entities\ConsumableHistory;
use app\core\dbal\SafeMysql;

class ConsumableHistories
{
    private static function saveData($consumable_id, $warehouse_id, $event, $user_id, $addition)
    {
        $data = [
            'consumable_id' => $consumable_id,
            'warehouse_id' => $warehouse_id,
            'event' => $event,
            'user_id' => $user_id,
            'addition' => json_encode($addition),
        ];
        $consumable_history = new ConsumableHistory($data);
        $consumable_history->save(array_keys($data));
    }

    public static function addStock($consumable_id, $warehouse_id, $changes, $user_id)
    {
        self::saveData($consumable_id, $warehouse_id, ConsumableHistory::EVENT_ADD_STOCK, $user_id, $changes);
    }

    public static function saveConsumables($consumable_id, $changes, $user_id, $event)
    {
        self::saveData($consumable_id, 0, $event, $user_id, $changes);
    }

    public static function saveMinStockLevel($consumable_id, $min_stock_levels, $user_id)
    {
        self::saveData($consumable_id, 0, ConsumableHistory::EVENT_MIN_STOCK_LEVEL, $user_id, $min_stock_levels);
    }

    public static function changeStatus($consumable_id, $status, $user_id)
    {
        self::saveData($consumable_id, 0, ConsumableHistory::EVENT_STATUS, $user_id, Consumable::STATUSES[$status]);
    }

    public static function adjustment($consumable_id, $quantity, $warehouse_id, $user_id)
    {
        self::saveData($consumable_id, $warehouse_id, ConsumableHistory::EVENT_ADJUSTMENT, $user_id, ['new_count' => $quantity]);
    }

    public static function decrease($consumable_id, $quantity, $warehouse_id, $order_package_id)
    {
        self::saveData($consumable_id, $warehouse_id, ConsumableHistory::EVENT_PACK, -1, ['new_count' => $quantity, 'package_box' => $order_package_id]);
    }

    public static function increase($consumable_id, $quantity, $warehouse_id, $order_package_id)
    {
        self::saveData($consumable_id, $warehouse_id, ConsumableHistory::EVENT_UNPACK, -1, ['new_count' => $quantity, 'package_box' => $order_package_id]);
    }

    public static function getAdjustment($consumable_id, $warehouse_id, $from_date)
    {
        $consumable_id = (int)$consumable_id;
        $warehouse_id = (int)$warehouse_id;
        $counts = (new SafeMysql())->fetchAll("
            SELECT ch.event_date, u.login, ch.addition
            FROM consumable_history ch
                JOIN users u ON ch.user_id = u.id
            WHERE ch.consumable_id = :consumable_id AND 
                  ch.warehouse_id = :warehouse_id AND
                  DATE_FORMAT(ch.event_date,'%Y-%m-%d') >= :from_date AND
                  ch.`event` = 'adjustment'
            ORDER BY ch.event_date DESC", ['consumable_id' => $consumable_id, 'warehouse_id' => $warehouse_id, 'from_date' => $from_date]);
        if (empty($counts))
            return [];
        foreach ($counts as $key => $item) {
            $val = json_decode($item['addition'], true);
            $counts[$key]['addition'] = $val['new_count'];
        }
        return $counts;
    }
}
