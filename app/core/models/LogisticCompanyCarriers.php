<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class LogisticCompanyCarriers
{
    public static function getId(int $lc_id, int $carrier_id)
    {
        return (new SafeMysql())->fetchScalar("SELECT id FROM logistic_company_carriers WHERE lc_id = :lc_id AND carrier_id = :carrier_id",  ['lc_id' => $lc_id, 'carrier_id' => $carrier_id,]);
    }

    public static function getLCsByCarrier(int $carrier_id)
    {
        return (new SafeMysql())->fetchArrayOneField("SELECT lc_id FROM logistic_company_carriers WHERE carrier_id = :carrier_id", ['carrier_id' => $carrier_id,]);
    }

    public static function addRecord(int $lc_id, int $carrier_id)
    {
        $id = (new SafeMysql())->insert("INSERT IGNORE INTO logistic_company_carriers (id, lc_id, carrier_id) VALUES (NULL, :lc_id, :carrier_id)", [
            'lc_id' => $lc_id,
            'carrier_id' => $carrier_id,
        ]);
        if (!$id)
            $id = self::getId($lc_id, $carrier_id);
        return $id;
    }

    public static function deleteRecord(int $lc_id, int $carrier_id)
    {
        (new SafeMysql())->execute("DELETE FROM logistic_company_carriers WHERE lc_id = :lc_id AND carrier_id = :carrier_id", [
            'lc_id' => $lc_id,
            'carrier_id' => $carrier_id,
        ]);
    }
}