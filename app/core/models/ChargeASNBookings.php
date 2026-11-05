<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class ChargeASNBookings
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
            INSERT INTO charge_ASN_booking (ib_charge_id, ASN_id, unique_SKUs, additional_SKU, total_SKUs, booking_1_SKU)
            VALUES " . implode(", ", $lines), $params);
    }
}