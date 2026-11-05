<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ShopWarehouse;

class ShopWarehouses
{
    public static function getByShopWarehouse($shop_id, $warehouse_id)
    {
        $shop_warehouse = (new SafeMysql())->fetchOne(
            "SELECT * FROM shop_warehouses WHERE shop_id = :shop_id AND warehouse_id = :warehouse_id LIMIT 1",
            ['shop_id' => (int)$shop_id, 'warehouse_id' => (int)$warehouse_id]
        );
        if (empty($shop_warehouse))
            return null;
        return new ShopWarehouse($shop_warehouse);
    }

    public static function getAllShopWarehouse($shop_id)
    {
        $shop_warehouses = (new SafeMysql())->fetchAll(
            "SELECT * FROM shop_warehouses WHERE shop_id = :shop_id",
            ['shop_id' => (int)$shop_id]
        );
        if (empty($shop_warehouses))
            return null;
        $res = [];
        foreach ($shop_warehouses as $shop_warehouse)
            $res[$shop_warehouse['warehouse_id']] = new ShopWarehouse($shop_warehouse);
        return $res;
    }
}
