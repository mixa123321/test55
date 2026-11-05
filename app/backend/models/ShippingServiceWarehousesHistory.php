<?php

namespace app\backend\models;

use app\core\entities\ShippingServiceWarehouse;
use app\core\entities\ShippingServiceWarehouseHistory;

/**
 * Class ShippingServiceWarehousesHistory
 * @package app\backend\models
 */
class ShippingServiceWarehousesHistory extends \app\core\models\ShippingServiceWarehousesHistory
{

    /**
     * @param ShippingServiceWarehouse $shipping_service_warehouse
     * @param $user_id
     * @param $type
     */
    public static function setHistory(ShippingServiceWarehouse $shipping_service_warehouse, $user_id, $type)
    {
        $shipping_service_warehouse_history = array_merge($shipping_service_warehouse->toArray(), [
            'user_id' => $user_id,
            'create_date' => time(),
            'type' => $type
        ]);
        (new ShippingServiceWarehouseHistory($shipping_service_warehouse_history))->save(array_keys($shipping_service_warehouse_history));
    }

}
