<?php

namespace app\core\models;

use app\core\entities\ChannelSupplier;
use app\core\dbal\SafeMysql;

class ChannelSuppliers
{
    public static function getById($id)
    {
        $id = (int)$id;
        $sql = "SELECT *
                FROM channels_suppliers
                WHERE id = :id";
        $channel_supplier = (new SafeMysql())->fetchOne($sql, ['id' => $id]);
        if (empty($channel_supplier))
            return null;
        return new ChannelSupplier($channel_supplier);
    }

    public static function getChannelSupplier($channel_id, $supplier_id): ?ChannelSupplier
    {
        $channel_id = (int)$channel_id;
        $supplier_id = (int)$supplier_id;
        $sql = "SELECT *
                FROM channels_suppliers
                WHERE channel_id = :channel_id AND supplier_id = :supplier_id
                LIMIT 1";
        $channel_supplier = (new SafeMysql())->fetchOne($sql, ['channel_id' => $channel_id, 'supplier_id' => $supplier_id]);
        if (empty($channel_supplier))
            return null;
        return new ChannelSupplier($channel_supplier);
    }

    public static function getChannelsBySupplier($supplier_id, $warehouse_id)
    {
        $supplier_id = (int)$supplier_id;
        $warehouse_id = (int)$warehouse_id;
        return (new SafeMysql())->fetchArrayOneField("
            SELECT DISTINCT c.name
            FROM shops s 
                JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id AND cs.supplier_id = :supplier_id
                JOIN channels c ON cs.channel_id = c.id
                JOIN shop_warehouses sw ON s.id = sw.shop_id AND sw.warehouse_id = :warehouse_id", ['supplier_id' => $supplier_id, 'warehouse_id' => $warehouse_id]);
    }
}
