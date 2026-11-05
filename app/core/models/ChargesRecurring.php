<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ChargeRecurring;

class ChargesRecurring
{
    public static function insertBySupplier(int $sw_id, array $charge_ids)
    {
        if (empty($sw_id) || empty($charge_ids))
            return;
        $values = [];
        $params = ['sw_id' => $sw_id, 'status' => ChargeRecurring::STATUS_ACTIVE];
        foreach ($charge_ids as $key => $charge_id) {
            $values[] = "(:charge_id_$key, :sw_id, :status)";
            $params["charge_id_$key"] = $charge_id;
        }
        (new SafeMysql())->execute("INSERT INTO charges_recurring (charge_id, supplier_warehouse_id, status) VALUES " . implode(', ', $values) . " ON DUPLICATE KEY UPDATE status = :status", $params);
    }

    public static function deleteBySupplier(int $sw_id, array $charge_ids = [])
    {
        if (empty($sw_id))
            return;
        $where_charges = '';
        $params = [
            'sw_id' => $sw_id,
            'status' => ChargeRecurring::STATUS_LOCK
        ];
        if (!empty($charge_ids)) {
            $placeholders = [];
            foreach ($charge_ids as $key => $charge_id) {
                $placeholders[] = ":charge_id_$key";
                $params["charge_id_$key"] = $charge_id;
            }
            $where_charges = 'AND charge_id IN (' . implode(',', $placeholders) . ')';
        }
        (new SafeMysql())->execute("UPDATE charges_recurring SET status = :status WHERE supplier_warehouse_id = :sw_id $where_charges", $params);
    }
}
