<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\helpers\Arr;

class BookLocations extends \app\core\models\BookLocations
{
    public static function getLocationsFromRange(int $warehouse_id, string $type_id, string $zone, string $column)
    {
        if (!$warehouse_id || !$type_id || !$zone)
            return [];
        $params = [
            'warehouse_id' => $warehouse_id,
            'type_id' => $type_id,
            'zone' => $zone,
        ];
        $where_column = '';
        if ($column) {
            $where_column = "AND l.`column` = :column";
            $params['column'] = $column;
        }
        $sql = "
            SELECT sw.supplier_id, sup.name,
                   bl.location_id, CONCAT(IF(w.label IS NULL OR w.label = '', l.warehouse, w.label), '-', l.type_id, '-', l.zone, '-', l.`column`, '-', l.`row`) location_name
            FROM book_locations bl
                JOIN suppliers_warehouses sw ON bl.supplier_warehouse_id = sw.id AND sw.warehouse_id = :warehouse_id
                JOIN suppliers sup ON sw.supplier_id = sup.id
                JOIN locations l ON bl.location_id = l.id AND l.warehouse = :warehouse_id AND l.type_id = :type_id AND l.zone = :zone $where_column
                JOIN warehouses w ON l.warehouse = w.id";
        $data = (new SafeMysql())->fetchAll($sql, $params);
        if (empty($data))
            return [];
        $res = [];
        foreach ($data as $datum) {
            if (empty($res[$datum['supplier_id']]))
                $res[$datum['supplier_id']] = [
                    'name' => $datum['name'],
                    'locations' => [],
                ];
            $res[$datum['supplier_id']]['locations'][$datum['location_id']] = $datum['location_name'];
        }
        return $res;
    }

    public static function getUsedLocations(int $warehouse_id, string $type_id)
    {
        if (!$warehouse_id || !$type_id)
            return [];
        $data = (new SafeMysql())->fetchAll("
            SELECT sw.supplier_id, sup.name, bl.location_id
            FROM book_locations bl
                JOIN suppliers_warehouses sw ON bl.supplier_warehouse_id = sw.id AND sw.warehouse_id = :warehouse_id
                JOIN suppliers sup ON sw.supplier_id = sup.id
                JOIN locations l ON bl.location_id = l.id AND l.warehouse = :warehouse_id AND l.type_id = :type_id",
            [
                'warehouse_id' => $warehouse_id,
                'type_id' => $type_id
            ]
        );
        if (empty($data))
            return [];
        return Arr::maps($data, 'location_id', ['supplier_id', 'name']);
    }

    /** @throws */
    public static function removeByRanges(int $supplier_warehouse_id, string $type_id, array $ranges)
    {
        if (!$supplier_warehouse_id || !$type_id || empty($ranges))
            return false;
        $range_conditions = [];
        $params = [
            'supplier_warehouse_id' => $supplier_warehouse_id,
            'type_id' => $type_id,
        ];
        foreach ($ranges as $i => $range) {
            $range_conditions[] = "(l.zone = :zone_$i AND l.`column` = :column_$i)";
            $params["zone_$i"] = $range[0];
            $params["column_$i"] = $range[1];
        }
        $sql = "DELETE FROM book_locations
            WHERE location_id IN (
                SELECT location_id FROM (
                    SELECT bl.location_id
                    FROM book_locations bl
                        JOIN locations l ON bl.location_id = l.id AND l.type_id = :type_id AND (" . implode(' OR ', $range_conditions) . ")
                    ) c
                ) AND supplier_warehouse_id = :supplier_warehouse_id";
        return (new SafeMysql())->execute($sql, $params);
    }
}