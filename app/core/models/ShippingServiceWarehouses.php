<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class ShippingServiceWarehouses {

    public static function checkShippingServiceServiceAndWarehouseId($shipping_service_id, $warehouse_id)
    {
        return !(new SafeMysql())->fetchScalar(
            "SELECT 1 FROM shipping_service_warehouses WHERE warehouse_id = :warehouse_id AND shipping_service_id = :shipping_service_id",
            ['warehouse_id' => (int)$warehouse_id, 'shipping_service_id' => (int)$shipping_service_id]
        );
    }

}
