<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;

class BookRangeLocations extends \app\core\models\BookRangeLocations
{
    public static function getSelectedRange(int $warehouse_id, string $type_id, string $zone)
    {
        if (!$warehouse_id || !$type_id || !$zone)
            return [];
        $data = (new SafeMysql())->fetchAll("
            SELECT DISTINCT sw.supplier_id, sup.name, l.`column`
            FROM book_range_locations brl
                JOIN suppliers_warehouses sw ON brl.supplier_warehouse_id = sw.id AND sw.warehouse_id = :warehouse_id
                JOIN suppliers sup ON sw.supplier_id = sup.id
                JOIN locations l ON brl.type_id = l.type_id AND l.warehouse = :warehouse_id AND brl.zone = l.zone AND (brl.`column` IS NULL OR brl.`column` = l.`column`) 
            WHERE brl.type_id = :type_id AND brl.zone = :zone", [
                'type_id' => $type_id,
                'zone' => $zone,
                'warehouse_id' => $warehouse_id
        ]);
        if (empty($data))
            return [];
        $res = [];
        foreach ($data as $datum) {
            if (empty($res[$datum['supplier_id']]))
                $res[$datum['supplier_id']] = [
                    'name' => $datum['name'],
                    'ranges' => [],
                ];
            $res[$datum['supplier_id']]['ranges'][] = $datum['column'];
        }
        return $res;
    }

    public static function getUsedRanges(int $warehouse_id, string $type_id)
    {
        if (!$warehouse_id || !$type_id)
            return [];
        $data = (new SafeMysql())->fetchAll("
            SELECT sw.supplier_id, sup.name, brl.zone, brl.`column`
            FROM book_range_locations brl
                JOIN suppliers_warehouses sw ON brl.supplier_warehouse_id = sw.id AND sw.warehouse_id = :warehouse_id
                JOIN suppliers sup ON sw.supplier_id = sup.id
            WHERE brl.type_id = :type_id", [
            'type_id' => $type_id,
            'warehouse_id' => $warehouse_id
        ]);
        if (empty($data))
            return [];
        $res = [];
        foreach ($data as $datum) {
            if (empty($res[$datum['zone']]))
                $res[$datum['zone']] = [];
            if (empty($res[$datum['zone']][$datum['supplier_id']]))
                $res[$datum['zone']][$datum['supplier_id']] = [
                    'name' => $datum['name'],
                    'columns' => []
                ];
            if ($datum['column'])
                $res[$datum['zone']][$datum['supplier_id']]['columns'][] = $datum['column'];
        }
        return $res;
    }

    public static function removeByRanges(int $supplier_warehouse_id, string $type_id, array $ranges)
    {
        if (!$supplier_warehouse_id || !$type_id || empty($ranges))
            return 0;
        $num = 0;
        foreach ($ranges as $range)
            $num += self::remove($supplier_warehouse_id, $type_id, $range[0], [$range[1]]);
        return $num;
    }
}