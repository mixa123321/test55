<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;

class LogisticCompanyCarriers extends \app\core\models\LogisticCompanyCarriers
{
    public static function addRecords(array $lc_ids, int $carrier_id, &$errors)
    {
        if (!is_array($lc_ids))
            $lc_ids = [$lc_ids];
        $old_lc_ids = self::getLCsByCarrier($carrier_id);
        $insert = array_diff($lc_ids, $old_lc_ids);
        $delete = array_diff($old_lc_ids, $lc_ids);
        if (empty($insert) && empty($delete))
            return false;
        if (!empty($delete))
            if (!empty(self::getActives($delete, $carrier_id))) {
                $errors[] = ['message' => 'cannot delete active carriers'];
                return false;
            }
        foreach ($insert as $lc_id)
            self::addRecord($lc_id, $carrier_id);
        foreach ($delete as $lc_id)
            self::deleteRecord($lc_id, $carrier_id);
        return true;
    }

    public static function getActives(array $lc_ids, int $carrier_id)
    {
        if (!is_array($lc_ids))
            $lc_ids = [$lc_ids];
        return (new SafeMysql())->fetchArrayOneField("
            SELECT lcc.id
            FROM logistic_company_carriers lcc
                JOIN warehouse_carriers wc ON lcc.id = wc.lc_carrier_id
            WHERE lc_id IN (:lc_ids) AND carrier_id = :carrier_id", [
            'lc_ids' => $lc_ids,
            'carrier_id' => $carrier_id,
        ]);
    }
}
