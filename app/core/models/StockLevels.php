<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class StockLevels
{
    public static function getMinDate()
    {
        return (new SafeMysql())->fetchScalar("SELECT MIN(created_at) FROM stock_level");
    }

    public static function getByDate($date)
    {
        if (empty($date))
            return [];
        $SKUs = (new SafeMysql())->fetchAll("SELECT * FROM stock_level WHERE DATE_FORMAT(created_at, '%Y-%m-%d') = :date", ['date' => $date]);
        if (empty($SKUs))
            return [];
        $res = [];
        foreach ($SKUs as $SKU)
            $res[$SKU['SKU_id']][$SKU['warehouse_id'] . (!empty($SKU['comment']) ? ", {$SKU['comment']}" : '')] = [$SKU['quantity'], $SKU['created_at']];
        return $res;
    }
}
