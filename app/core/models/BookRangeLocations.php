<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class BookRangeLocations
{
    public static function getRanges(int $warehouse_id, int $supplier_id, string $type_id)
    {
        if (!$warehouse_id || !$supplier_id || !$type_id)
            return [];
        $sql = "
            SELECT brl.zone, brl.`column`
            FROM book_range_locations brl
                JOIN suppliers_warehouses sw ON brl.supplier_warehouse_id = sw.id AND sw.supplier_id = :supplier_id AND sw.warehouse_id = :warehouse_id
            WHERE brl.type_id = :type_id
            ORDER BY brl.zone, brl.`column`";
        $rows = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'supplier_id'  => $supplier_id,
            'type_id'      => $type_id,
        ]);

        return $rows;
    }

    /** @throws */
    public static function remove(int $supplier_warehouse_id, string $type_id, string $zone, array $ranges)
    {
        if (!$supplier_warehouse_id || !$type_id || !$zone || empty($ranges))
            return false;
        $range_values = [];
        $params = [
            'supplier_warehouse_id' => $supplier_warehouse_id,
            'type_id' => $type_id,
            'zone' => $zone,
        ];
        foreach ($ranges as $key => $range) {
            if ($range) {
                $range_values[] = "(zone = :zone AND `column` = :range_$key)";
                $params["range_$key"] = $range;
            } else {
                $range_values[] = "(zone = :zone AND `column` IS NULL)";
            }
        }
        return (new SafeMysql())->execute("
            DELETE FROM book_range_locations
            WHERE supplier_warehouse_id = :supplier_warehouse_id AND type_id = :type_id AND (" . implode(' OR ', $range_values) . ')', $params);
    }

    /** @throws */
    public static function add(int $supplier_warehouse_id, string $type_id, array $ranges)
    {
        if (!$supplier_warehouse_id || !$type_id || empty($ranges))
            return false;
        $values = [];
        $params = ['supplier_warehouse_id' => $supplier_warehouse_id, 'type_id' => $type_id];
        foreach ($ranges as $key => $range) {
            $values[] = "(:supplier_warehouse_id, :type_id, :zone_$key, " . ($range[1] ? ":range_$key" : 'NULL') . ')';
            $params["zone_$key"] = $range[0];
            if ($range[1]) {
                $params["range_$key"] = $range[1];
            }
        }
        return (new SafeMysql())->execute("
            INSERT IGNORE INTO book_range_locations (supplier_warehouse_id, type_id, zone, `column`)
            VALUES " . implode(', ', $values), $params);
    }
}
