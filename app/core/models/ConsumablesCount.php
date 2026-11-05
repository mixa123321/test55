<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ConsumableCount;

class ConsumablesCount
{
    public static function getMinStockLevels($lc_id, $consumable_id, $suppliers): array
    {
        $warehouses = Warehouses::getAll($lc_id);
        $warehouses = array_filter($warehouses, function($item) {
            return !$item['is_virtual'];
        });
        $consumable_id = (int)$consumable_id;
        $stocks = (new SafeMysql())->fetchAssocWithKey("SELECT warehouse_id, min_stock_level FROM consumable_count WHERE consumable_id = :consumable_id", ['consumable_id' => $consumable_id], 'warehouse_id');
        $min_stock_level_data = [];
        foreach ($suppliers as $supplier) {
            $supplier_id = $supplier['id'];
            if ($supplier_id < 0) {
                foreach ($warehouses as $warehouse)
                    $min_stock_level_data[$supplier_id][] = [
                        'warehouse_id' => $warehouse['id'],
                        'warehouse_name' => $warehouse['name'],
                        'value' => $stocks[$warehouse['id']]['min_stock_level'] ?? 0,
                    ];
            } else {
                $supplier_warehouses = is_string($supplier['warehouses']) ? json_decode($supplier['warehouses']) : $supplier['warehouses'];
                if (empty($supplier_warehouses))
                    continue;
                foreach ($warehouses as $warehouse)
                    if (in_array($warehouse['id'], $supplier_warehouses))
                        $min_stock_level_data[$supplier_id][] = [
                            'warehouse_id' => $warehouse['id'],
                            'warehouse_name' => $warehouse['name'],
                            'value' => $stocks[$warehouse['id']]['min_stock_level'] ?? 0,
                        ];
            }
        }
        return $min_stock_level_data;
    }

    public static function getMinStockLevel($consumable_id, $warehouse_id): int
    {
        $consumable_id = (int)$consumable_id;
        $warehouse_id = (int)$warehouse_id;
        return (int)(new SafeMysql())->fetchScalar("SELECT min_stock_level FROM consumable_count WHERE consumable_id = :consumable_id AND warehouse_id = :warehouse_id", ['consumable_id' => $consumable_id, 'warehouse_id' => $warehouse_id]);
    }

    public static function setMinStockLevel($min_stock_level, $consumable_id, $warehouse_id)
    {
        $rec = (new SafeMysql())->fetchScalar("SELECT 1 FROM consumable_count WHERE consumable_id = :consumable_id AND warehouse_id = :warehouse_id", ['consumable_id' => (int)$consumable_id, 'warehouse_id' => (int)$warehouse_id]);
        if ($rec)
            (new SafeMysql())->execute("UPDATE consumable_count SET min_stock_level = :min_stock_level WHERE consumable_id = :consumable_id AND warehouse_id = :warehouse_id", [
                'min_stock_level' => $min_stock_level,
                'consumable_id' => $consumable_id,
                'warehouse_id' => $warehouse_id,
            ]);
        else {
            $data = [
                'consumable_id' => $consumable_id,
                'warehouse_id' => $warehouse_id,
                'min_stock_level' => $min_stock_level,
            ];
            $consumable_count = new ConsumableCount($data);
            $consumable_count->save(array_keys($data));
        }
    }

    public static function getQuantity($consumable_id, $warehouse_id)
    {
        $consumable_id = (int)$consumable_id;
        $warehouse_id = (int)$warehouse_id;
        return (int)(new SafeMysql())->fetchScalar("SELECT quantity FROM consumable_count WHERE consumable_id = :consumable_id AND warehouse_id = :warehouse_id", ['consumable_id' => $consumable_id, 'warehouse_id' => $warehouse_id]);
    }

    public static function getAllStock($consumable_lst)
    {
        if (empty($consumable_lst))
            return [];
        $sql = "SELECT con.id, con.name, cc.quantity, cc.warehouse_id, con.supplier_id
                FROM consumables con
                    JOIN consumable_count cc ON con.id = cc.consumable_id 
                WHERE con.id IN (:consumable_lst)";
        $consumables_stock_res = (new SafeMysql())->fetchAll($sql, ['consumable_lst' => $consumable_lst]);
        if (empty($consumables_stock_res))
            return [];
        $consumables_stock = [];
        foreach ($consumables_stock_res as $consumable) {
            if (empty($consumables_stock[$consumable['id']]))
                $consumables_stock[$consumable['id']] = ['name' => $consumable['name'], 'supplier_id' => $consumable['supplier_id']];
            $consumables_stock[$consumable['id']]['warehouse_id'][$consumable['warehouse_id']] = $consumable['quantity'];
        }
        return $consumables_stock;
    }

    public static function setQuantity($quantity, $consumable_id, $warehouse_id)
    {
        $rec = (new SafeMysql())->fetchScalar("SELECT 1 FROM consumable_count WHERE consumable_id = :consumable_id AND warehouse_id = :warehouse_id", ['consumable_id' => (int)$consumable_id, 'warehouse_id' => (int)$warehouse_id]);
        if ($rec)
            (new SafeMysql())->execute("UPDATE consumable_count SET quantity = :quantity WHERE consumable_id = :consumable_id AND warehouse_id = :warehouse_id", [
                'quantity' => $quantity,
                'consumable_id' => $consumable_id,
                'warehouse_id' => $warehouse_id,
            ]);
        else {
            $data = [
                'consumable_id' => $consumable_id,
                'warehouse_id' => $warehouse_id,
                'quantity' => $quantity,
            ];
            $consumable_count = new ConsumableCount($data);
            $consumable_count->save(array_keys($data));
        }
    }

    public static function getConsumablesMinimum($consumable_lst)
    {
        if (empty($consumable_lst))
            return [];
        $sql = "SELECT consumable_id, min_stock_level, warehouse_id
                FROM consumable_count
                WHERE consumable_id IN (:consumable_lst)";
        $consumables_minimum_res = (new SafeMysql())->fetchAll($sql, ['consumable_lst' => $consumable_lst]);
        if (empty($consumables_minimum_res))
            return [];
        $consumables_minimum = [];
        foreach ($consumables_minimum_res as $SKU)
            $consumables_minimum[$SKU['consumable_id']][$SKU['warehouse_id']] = $SKU['min_stock_level'];
        return $consumables_minimum;
    }
}
