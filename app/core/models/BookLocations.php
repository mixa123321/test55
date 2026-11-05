<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Location;

class BookLocations
{
    public static function getLocations(int $warehouse_id, int $supplier_id, string $type_id)
    {
        if (!$warehouse_id || !$supplier_id || !$type_id)
            return [];
        $sql = "
            SELECT l.*
            FROM book_locations bl
                JOIN suppliers_warehouses sw ON bl.supplier_warehouse_id = sw.id AND sw.supplier_id = :supplier_id AND sw.warehouse_id = :warehouse_id
                JOIN locations l ON bl.location_id = l.id AND l.warehouse = :warehouse_id AND l.type_id = :type_id
            ORDER BY l.id
        ";

        $rows = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'supplier_id'  => $supplier_id,
            'type_id'      => $type_id
        ]);

        $locations = [];
        foreach ($rows as $row) {
            $locations[] = new Location($row);
        }
        return $locations;
    }

    public static function getZonesAndColumns(int $warehouse_id, int $supplier_id, string $type_id, array $exclude_ids = [])
    {
        if (!$warehouse_id || !$supplier_id || !$type_id)
            return [];

        $sql = "
            SELECT DISTINCT l.zone, l.column
            FROM book_locations bl
                JOIN suppliers_warehouses sw 
                    ON bl.supplier_warehouse_id = sw.id AND sw.supplier_id = :supplier_id AND sw.warehouse_id = :warehouse_id
                JOIN locations l 
                    ON bl.location_id = l.id AND l.warehouse = :warehouse_id AND l.type_id = :type_id
        ";

        $params = [
            'warehouse_id' => $warehouse_id,
            'supplier_id' => $supplier_id,
            'type_id' => $type_id
        ];
        if (!empty($exclude_ids)) {
            $params['exclude_ids'] = $exclude_ids;
            $sql .= " AND l.id NOT IN (:exclude_ids)";
        }
        $sql .= " ORDER BY l.id";
        $rows = (new SafeMysql())->fetchAll($sql, $params);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['zone']][] = $row['column'];
        }

        return $result;
    }

    /** @throws */
    public static function remove(int $supplier_warehouse_id, array $loc_ids)
    {
        if (!$supplier_warehouse_id || empty($loc_ids))
            return false;
        $params = ['supplier_warehouse_id' => $supplier_warehouse_id];
        $placeholders = [];
        foreach ($loc_ids as $key => $loc_id) {
            $placeholders[] = ":loc_id_$key";
            $params["loc_id_$key"] = (int)$loc_id;
        }
        return (new SafeMysql())->execute("
            DELETE FROM book_locations
            WHERE supplier_warehouse_id = :supplier_warehouse_id AND location_id IN (" . implode(',', $placeholders) . ")", $params);
    }

    /** @throws */
    public static function add(int $supplier_warehouse_id, array $loc_ids)
    {
        if (!$supplier_warehouse_id || empty($loc_ids))
            return false;
        $values = [];
        $params = ['supplier_warehouse_id' => $supplier_warehouse_id];
        foreach ($loc_ids as $key => $location_id) {
            $values[] = "(:supplier_warehouse_id, :location_id_$key)";
            $params["location_id_$key"] = (int)$location_id;
        }
        return (new SafeMysql())->execute("
            INSERT INTO book_locations (supplier_warehouse_id, location_id)
            VALUES " . implode(', ', $values), $params);
    }
}
