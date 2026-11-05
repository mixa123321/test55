<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class ChargeOrderPackagings
{
    public static function add(int $ib_charge_id, array $data)
    {
        if (!$ib_charge_id || !$data)
            return;
        $lines = [];
        $params = [];
        foreach ($data as $key => $item) {
            $item['ib_charge_id'] = $ib_charge_id;
            $placeholders = [];
            foreach ($item as $field => $value) {
                $placeholders[] = ":{$field}_{$key}";
                $params["{$field}_{$key}"] = $value;
            }
            $lines[] = '(' . implode(', ', $placeholders) . ')';
        }
        (new SafeMysql())->execute("
            INSERT IGNORE INTO charge_order_packaging (ib_charge_id, order_id, packaging_id, packaging)
            VALUES " . implode(", ", $lines), $params);
    }
}