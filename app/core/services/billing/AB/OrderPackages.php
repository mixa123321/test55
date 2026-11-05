<?php

namespace app\core\services\billing\AB;

use app\backend\models\ShippingServices;
use app\core\dbal\SafeMysql;

class OrderPackages extends \app\backend\models\OrderPackages
{
    public static function getPackagings(int $warehouse_id, $start_date, $last_date, int $is_special = 0)
    {
        [$where_add, $params_add] = ShippingServices::getIsSpecial($warehouse_id, $is_special);
        $sql = "
            SELECT o.supplier_id, sw.sage_id, sup.name sup_name, o.id order_id, c.id, c.name
            FROM orders_packages op
                JOIN packages p ON op.package_type_id = p.id
                JOIN consumables c ON p.packaging_id = c.id AND NOT (c.reuse AND op.is_cancel)
                JOIN orders o ON op.order_id = o.id AND o.warehouse_id = :warehouse_id $where_add
                JOIN suppliers sup ON o.supplier_id = sup.id
                JOIN suppliers_warehouses sw ON sup.id = sw.supplier_id AND sw.warehouse_id = o.warehouse_id
            WHERE op.packed_date >= :start_date AND op.packed_date <= :end_date";

        $params = [
            'warehouse_id' => $warehouse_id,
            'start_date' => $start_date,
            'end_date' => $last_date,
        ];

        return (new SafeMysql())->fetchAll($sql, $params + $params_add);
    }
}
