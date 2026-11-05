<?php

namespace app\core\services\billing\AB;

use app\backend\models\ShippingServices;
use app\backend\models\SKUBarcodes;
use app\core\dbal\SafeMysql;
use app\core\entities\Order;

class Orders extends \app\backend\models\Orders
{
    public static function getPicks(int $warehouse_id, $start_date, $last_date, int $is_special = 0)
    {
        [$where_add, $params_add] = ShippingServices::getIsSpecial($warehouse_id, $is_special);
        $sql = "
            SELECT sup.id, sw.sage_id, sup.name sup_name, o.id order_id, os.SKU_id, s.name, (" . SKUBarcodes::getPrimaryBySKUIdSQL('os.SKU_id') . ") barcode, os.quantity
            FROM orders o
                JOIN order_SKUs os ON o.id = os.order_id
                JOIN SKUs s ON os.SKU_id = s.id 
                JOIN suppliers sup ON o.supplier_id = sup.id 
                JOIN suppliers_warehouses sw ON sup.id = sw.supplier_id AND o.warehouse_id = sw.warehouse_id
            WHERE o.warehouse_id = :warehouse_id $where_add AND o.status = :status AND o.despatched_date BETWEEN :start_date AND :end_date
            ORDER BY o.id, s.id";

        $params = [
            'warehouse_id' => $warehouse_id,
            'status' => Order::DESPATCHED_NUM,
            'start_date' => $start_date,
            'end_date' => $last_date,
        ];

        return (new SafeMysql())->fetchAll($sql, $params + $params_add);
    }

    public static function getDistributions(array $order_ids)
    {
        if (empty($order_ids))
            return [];
        return (new SafeMysql())->fetchAll("
            SELECT IFNULL(ss.code, 'Collection by customer') code,
                   d.country_id, COUNT(*) total_packages, ROUND(SUM(op.weight), 3) weight
            FROM orders o
                LEFT JOIN shipping_services ss ON o.shipping_services_id = ss.id
                JOIN deliveries d ON o.delivery_id = d.id
                JOIN orders_packages op ON o.id = op.order_id AND op.is_cancel = 0
            WHERE o.id IN (:order_ids)
            GROUP BY o.shipping_services_id, d.country_id", ['order_ids' => $order_ids]);
    }
}
