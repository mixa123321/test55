<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\BookLocationHistory;

class BookLocationHistories
{
    public static function add(array $histories)
    {
        if (!$histories)
            return false;
        $values = $bind = [];
        /** @var BookLocationHistory $history */
        foreach ($histories as $key => $history) {
            $values[] = "(:supplier_warehouse_id_$key, :type_id_$key, :event_$key, :type_$key, :user_id_$key, :addition_$key)";
            $bind["supplier_warehouse_id_$key"] = $history->getSupplierWarehouseId();
            $bind["type_id_$key"] = $history->getTypeId();
            $bind["event_$key"] = $history->getEvent();
            $bind["type_$key"] = $history->getType();
            $bind["user_id_$key"] = $history->getUserId();
            $bind["addition_$key"] = json_encode($history->getAddition());
        }
        return (new SafeMysql())->execute("
            INSERT INTO book_location_history (supplier_warehouse_id, type_id, event, type, user_id, addition)
            VALUES " . implode(', ', $values), $bind);
    }
}