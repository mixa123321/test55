<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\InventoryError;

class InventoryErrors
{
    public static function addError($channel_id, $shop_warehouse_id, $item_id, $code, $message)
    {
        $error = new InventoryError([
            'channel_id' => $channel_id,
            'shop_warehouse_id' => $shop_warehouse_id,
            'item_id' => $item_id,
            'code' => $code,
            'message' => $message,
        ]);
        $error->save(['channel_id', 'shop_warehouse_id', 'item_id', 'code', 'message']);
        return $error->getId();
    }

    public static function viewErrors($time)
    {
        $time_str = date('Y-m-d H:00:00', $time);
        $sql = "SELECT ie.id, ie.channel_id, sw.shop_id, sw.warehouse_id, ie.item_id, ie.code, ie.message 
                from inventory_errors ie
                join shop_warehouses sw on ie.shop_warehouse_id = sw.id
                where ie.created_at < :time_str";
        return (new SafeMysql())->fetchAll($sql, ['time_str' => $time_str]);
    }

    public static function deleteErrors($ids)
    {
        if (empty($ids) || !is_array($ids))
            return [];
        return (new SafeMysql())->execute("Delete from inventory_errors where id in (:ids)", ['ids' => $ids]);
    }
}
