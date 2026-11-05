<?php

namespace app\core\services\billing\AB;

use app\backend\models\SupplierWarehouses;
use app\core\dbal\SafeMysql;
use app\core\entities\Location;

class SKUStorages extends \app\core\models\SKUStorages
{
    public static function getStorage(int $warehouse_id, $start_date, $end_date)
    {
        $data = (new SafeMysql())->fetchAll("
            SELECT q.supplier_id, l.type_id, SUM(q.qty) units
            FROM (
                    SELECT s.supplier_id, w.location_id, COUNT(*) qty
                    FROM (
                            SELECT sst.SKU_id, sst.location_id, DATEDIFF(:end_date, sst.created_at) DIV 7, COUNT(*)
                            FROM SKU_storage sst
                                JOIN locations l ON sst.location_id = l.id AND l.warehouse = :warehouse_id AND l.type_id IN (:types)
                            WHERE sst.created_at >= :start_date AND sst.created_at <= :end_date
                            GROUP BY sst.SKU_id, sst.location_id, DATEDIFF(:end_date, sst.created_at) DIV 7
                            HAVING COUNT(*) = 7
                        ) w
                        JOIN SKUs s ON w.SKU_id = s.id
                    GROUP BY w.SKU_id, w.location_id
                ) q
                JOIN locations l ON q.location_id = l.id
            GROUP BY q.supplier_id, l.type_id", [
            'warehouse_id' => $warehouse_id,
            'types' => Location::STORAGE_TYPES,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
        if (empty($data))
            return [];
        $supplier_ids = array_unique(array_column($data, 'supplier_id'));
        $suppliers = SupplierWarehouses::getShortData($warehouse_id, $supplier_ids);
        $res = [];
        foreach ($data as $datum) {
            if (empty($res[$datum['type_id']]))
                $res[$datum['type_id']] = [];
            $res[$datum['type_id']][$datum['supplier_id']] = [
                'sage_id' => $suppliers[$datum['supplier_id']]['sage_id'],
                'name' => $suppliers[$datum['supplier_id']]['name'],
                'units' => $datum['units'],
            ];
        }
        return $res;
    }

    public static function getStorageV2(int $warehouse_id, $start_date, $end_date)
    {
        $data = (new SafeMysql())->fetchAll("
            SELECT q.supplier_id, l.type_id, SUM(q.qty) units
            FROM (
                SELECT w.supplier_id, w.location_id, COUNT(*) qty
                FROM (
                    SELECT sup.supplier_id, sup.location_id, DATEDIFF(:end_date, sup.created_at) DIV 7, COUNT(*)
                    FROM (
                        SELECT distinct s.supplier_id, sst.location_id, sst.created_at
                        FROM SKU_storage sst
                            JOIN locations l ON sst.location_id = l.id AND l.warehouse = :warehouse_id AND l.type_id IN (:types)
                            JOIN SKUs s ON sst.SKU_id = s.id
                        WHERE sst.created_at >= :start_date AND sst.created_at <= :end_date
                    ) sup
                    GROUP BY sup.supplier_id, sup.location_id, DATEDIFF(:end_date, sup.created_at) DIV 7
                    HAVING COUNT(*) = 7
                ) w
                GROUP BY w.supplier_id, w.location_id
            ) q
                JOIN locations l ON q.location_id = l.id
            GROUP BY q.supplier_id, l.type_id", [
            'warehouse_id' => $warehouse_id,
            'types' => Location::STORAGE_TYPES,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
        if (empty($data))
            return [];
        $supplier_ids = array_unique(array_column($data, 'supplier_id'));
        $suppliers = SupplierWarehouses::getShortData($warehouse_id, $supplier_ids);
        $res = [];
        foreach ($data as $datum) {
            if (empty($res[$datum['type_id']]))
                $res[$datum['type_id']] = [];
            $res[$datum['type_id']][$datum['supplier_id']] = [
                'sage_id' => $suppliers[$datum['supplier_id']]['sage_id'],
                'name' => $suppliers[$datum['supplier_id']]['name'],
                'units' => $datum['units'],
            ];
        }
        return $res;
    }
}
