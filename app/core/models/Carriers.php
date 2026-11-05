<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Carrier;
use app\core\entities\Warehouse;

class Carriers
{
    public static function getById(int $id)
    {
        static $carriers = [];
        if (!array_key_exists($id, $carriers)) {
            $sql = "SELECT * FROM carriers WHERE id = :id";
            $data = (new SafeMysql())->fetchOne($sql, ['id' => $id]);
            $carriers[$id] = $data ? new Carrier($data) : null;
        }
        return $carriers[$id];
    }

    public static function getByIds($ids)
    {
        if (!$ids)
            return [];
        $sql = "SELECT id, CONCAT(name, ' (', code, ')') title, max_weight, is_special FROM carriers WHERE id IN (:ids)";
        return (new SafeMysql())->fetchAll($sql, ['ids' => array_map('intval', $ids)]);
    }

    public static function getByName($name)
    {
        $data = (new SafeMysql())->fetchOne("SELECT * FROM carriers WHERE name = :name LIMIT 1", ['name' => $name]);
        if (empty($data))
            return null;
        return new Carrier($data);
    }

    public static function getByCode($code)
    {
        $data = (new SafeMysql())->fetchOne('SELECT * FROM carriers WHERE code = :code LIMIT 1', ['code' => $code]);
        if (empty($data))
            return null;
        return new Carrier($data);
    }

    public static function getAll()
    {
        $sql = "
            SELECT c.*, lc.id lc_id, lc.name lc_name, lc.status
            FROM carriers c
                LEFT JOIN logistic_company_carriers lcc ON c.id = lcc.carrier_id
                LEFT JOIN logistic_companies lc ON lcc.lc_id = lc.id
            ORDER BY c.id";
        $carriers = (new SafeMysql())->fetchAll($sql);
        if (empty($carriers))
            return [];
        $res = [];
        foreach ($carriers as $carrier) {
            if (empty($res[$carrier['id']]))
                $res[$carrier['id']] = array_slice($carrier, 0, 5) + ['lc' => []];
            if (!empty($carrier['lc_id']))
                $res[$carrier['id']]['lc'][$carrier['lc_id']] = [$carrier['lc_name'], $carrier['status']];
        }
        return $res;
    }

    public static function addRecord(Warehouse $warehouse, int $carrier_id)
    {
        $lc_carrier_id = LogisticCompanyCarriers::addRecord($warehouse->getLCId(), $carrier_id);
        return (bool)(new SafeMysql())->execute("
            INSERT IGNORE INTO warehouse_carriers (warehouse_id, lc_carrier_id)
            VALUES (:warehouse_id, :lc_carrier_id)", [
            'warehouse_id' => $warehouse->getId(),
            'lc_carrier_id' => $lc_carrier_id,
        ]);
    }

    public static function deleteRecord(Warehouse $warehouse, int $carrier_id)
    {
        $lc_carrier_id = LogisticCompanyCarriers::getId($warehouse->getLCId(), $carrier_id);
        return (new SafeMysql())->execute("
            DELETE FROM warehouse_carriers
            WHERE warehouse_id = :warehouse_id AND lc_carrier_id = :lc_carrier_id", [
            'warehouse_id' => $warehouse->getId(),
            'lc_carrier_id' => $lc_carrier_id,
        ]);
    }

    public static function getByWarehouse(Warehouse $warehouse)
    {
        $sql = "SELECT c.id, c.name, c.logo, c.code, c.url, IF(wc.warehouse_id IS NULL, 0, 1) active, c.is_internal, c.is_special, c.max_weight
                FROM carriers c
                    JOIN logistic_company_carriers lcc ON c.id = lcc.carrier_id AND lcc.lc_id = :lc_id
                    LEFT JOIN warehouse_carriers wc ON lcc.id = wc.lc_carrier_id AND wc.warehouse_id = :warehouse_id";
        return (new SafeMysql())->fetchAll($sql, ['lc_id' => $warehouse->getLCId(), 'warehouse_id' => $warehouse->getId()]);
    }

    public static function getSpecials(int $warehouse_id)
    {
        $sql = "SELECT c.id
                FROM carriers c
                    JOIN logistic_company_carriers lcc ON c.id = lcc.carrier_id
                    JOIN warehouse_carriers wc ON lcc.id = wc.lc_carrier_id AND wc.warehouse_id = :warehouse_id
                WHERE c.is_special";
        return (new SafeMysql())->fetchArrayOneField($sql, ['warehouse_id' => $warehouse_id]);
    }
}
