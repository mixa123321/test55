<?php

namespace app\backend\models;

use app\core\models\ConsumableHistories;

class ConsumablesCount extends \app\core\models\ConsumablesCount
{
    public static function decreaseQuantity($consumables, $warehouse_id, $order_package_id)
    {
        foreach ($consumables as $consumable_id => $quantity) {
            $q = ConsumablesCount::getQuantity($consumable_id, $warehouse_id);
            ConsumablesCount::setQuantity($q - $quantity, $consumable_id, $warehouse_id);
            ConsumableHistories::decrease($consumable_id, $q - $quantity, $warehouse_id, $order_package_id);
        }
    }

    public static function increaseQuantity($consumables, $warehouse_id, $order_package_id)
    {
        foreach ($consumables as $consumable_id => $quantity) {
            $q = ConsumablesCount::getQuantity($consumable_id, $warehouse_id);
            ConsumablesCount::setQuantity($q + $quantity, $consumable_id, $warehouse_id);
            ConsumableHistories::increase($consumable_id, $q + $quantity, $warehouse_id, $order_package_id);
        }
    }
}
