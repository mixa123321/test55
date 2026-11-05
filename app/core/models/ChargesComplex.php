<?php

namespace app\core\models;

use app\backend\models\SupplierWarehouses;
use app\core\dbal\SafeMysql;
use app\core\entities\ChargeComplex;
use app\core\helpers\Arr;

class ChargesComplex
{
    public static function getAll(int $sw_id)
    {
        if (empty($sw_id))
            return [];
        return (new SafeMysql())->fetchAll("SELECT * FROM charges_complex WHERE supplier_warehouse_id = :sw_id", ['sw_id' => $sw_id]);
    }

    public static function deleteBySupplier(int $sw_id, array $charge_ids = [])
    {
        if (empty($sw_id))
            return;
        $params = [
            'sw_id' => $sw_id,
            'status' => ChargeComplex::STATUS_LOCK,
        ];
        $sql = "UPDATE charges_complex SET status = :status WHERE supplier_warehouse_id = :sw_id";
        if (!empty($charge_ids)) {
            $placeholders = [];
            foreach ($charge_ids as $key => $charge_id) {
                $placeholders[] = ":charge_id_$key";
                $params["charge_id_$key"] = $charge_id;
            }
            $sql .= " AND charge_id IN (" . implode(',', $placeholders) . ")";
        }
        (new SafeMysql())->execute($sql, $params);
    }

    public static function insertBySupplierSimple(int $sw_id, array $charge_ids)
    {
        if (empty($sw_id) || empty($charge_ids))
            return;
        $sql = "INSERT INTO charges_complex (id, charge_id, supplier_warehouse_id, type, type_id, status) VALUES ";
        $values = [];
        $params = ['sw_id' => $sw_id];
        foreach ($charge_ids as $key => $charge_id) {
            $values[] = "(NULL, :charge_id_$key, :sw_id, 0, 0, " . ChargeComplex::STATUS_ACTIVE . ")";
            $params["charge_id_$key"] = $charge_id;
        }
        (new SafeMysql())->execute($sql . implode(', ', $values) . " ON DUPLICATE KEY UPDATE status = " . ChargeComplex::STATUS_ACTIVE, $params);
    }

    public static function insertBySupplierPackaging(int $sw_id, array $charge_ids)
    {
        if (empty($sw_id) || empty($charge_ids))
            return;
        $packaging_ids = array_column(SupplierWarehouses::getPackaging($sw_id), 'packaging_id');
        if (empty($packaging_ids))
            $packaging_ids = [0];
        else
            self::deleteEmptyPackaging($sw_id, $charge_ids);
        $sql = "INSERT INTO charges_complex (charge_id, supplier_warehouse_id, type, type_id, status) VALUES ";
        $values = [];
        $params = ['sw_id' => $sw_id];
        $i = 0;
        foreach ($charge_ids as $charge_id)
            foreach ($packaging_ids as $packaging_id) {
                $values[] = "(:charge_id_$i, :sw_id, 1, :packaging_id_$i, " . ChargeComplex::STATUS_ACTIVE . ")";
                $params["charge_id_$i"] = $charge_id;
                $params["packaging_id_$i"] = $packaging_id;
                $i++;
            }
        (new SafeMysql())->execute($sql . implode(', ', $values) . " ON DUPLICATE KEY UPDATE status = " . ChargeComplex::STATUS_ACTIVE, $params);
    }

    public static function deleteEmptyPackaging(int $sw_id, array $charge_ids)
    {
        if (empty($sw_id) || empty($charge_ids))
            return;
        $params = ['sw_id' => $sw_id, 'type' => 1, 'type_id' => 0];
        $placeholders = [];
        foreach ($charge_ids as $key => $charge_id) {
            $placeholders[] = ":charge_id_$key";
            $params["charge_id_$key"] = $charge_id;
        }
        (new SafeMysql())->execute("
            DELETE FROM charges_complex
            WHERE charge_id IN (" . implode(',', $placeholders) . ") AND supplier_warehouse_id = :sw_id AND type = :type AND type_id = :type_id", $params);
    }

    public static function getPackaging(int $sw_id): array
    {
        $data = (new SafeMysql())->fetchAll("
            SELECT charge_id, GROUP_CONCAT(type_id) type_ids, GROUP_CONCAT(DISTINCT status) statuses
            FROM charges_complex
            WHERE supplier_warehouse_id = :sw_id AND type = :type
            GROUP BY charge_id",['sw_id' => $sw_id,'type'  => ChargeComplex::TYPE_PACKAGING]);
        if (empty($data))
            return [];
        return Arr::maps($data, 'charge_id', ['type_ids', 'statuses']);
    }

    public static function insertAbsentPackaging(int $sw_id, array $inserts)
    {
        $sql = "INSERT IGNORE INTO charges_complex (charge_id, supplier_warehouse_id, type, type_id, status) VALUES ";
        $values = [];
        $params = ['sw_id' => $sw_id];
        $i = 0;
        foreach ($inserts as $charge_id => $inserts_data)
            foreach ($inserts_data['type_ids'] as $type_id) {
                $values[] = "(:charge_id_$i, :sw_id, " . ChargeComplex::TYPE_PACKAGING . ", :type_id_$i, :status_$i)";
                $params["charge_id_$i"] = $charge_id;
                $params["type_id_$i"] = $type_id;
                $params["status_$i"] = $inserts_data['status'];
                $i++;
            }
        (new SafeMysql())->execute($sql . implode(', ', $values), $params);
    }
}
