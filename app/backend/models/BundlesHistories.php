<?php

namespace app\backend\models;

use app\core\entities\BundlesHistory;
use app\core\helpers\Arr;

class BundlesHistories extends \app\core\models\BundlesHistories
{

    private static $fields = ['bundle_id', 'event', 'addition', 'supplier_id', 'user_id'];

    private static function init($bundle_id, $user_id): BundlesHistory
    {
        $history = new BundlesHistory();
        $history->setBundleId($bundle_id);
        $history->setUserId($user_id);
        return $history;
    }

    public static function add($bundle_id, $user_id, $supplier_id, $name, $SKUs, int $currency_id = 0, float $cost_value = 0)
    {
        $history = self::init($bundle_id, $user_id);
        $history->setEvent(BundlesHistory::EVENT_ADD);
        $history->setAddition(json_encode([
            'name' => $name,
            'supplier_id' => $supplier_id,
            'currency_id' => $currency_id,
            'cost_value' => round($cost_value, 2),
            'SKUs' => $SKUs
        ]));
        $history->save(self::$fields);
    }

    public static function edit($bundle_id, $user_id, $old_name, $new_name, $old_SKUs, $new_SKUs, int $old_currency_id = 0, int $new_currency_id = 0, float $old_cost_value = 0, float $new_cost_value = 0)
    {
        $history = self::init($bundle_id, $user_id);
        $history->setEvent(BundlesHistory::EVENT_EDIT);
        $data = [
            'SKUs' => ['old' => $old_SKUs, 'new' => $new_SKUs]
        ];
        if ($old_name !== $new_name)
            $data['name'] = ['old' => $old_name, 'new' => $new_name];
        if ($old_currency_id !== $new_currency_id)
            $data['currency_id'] = ['old' => $old_currency_id, 'new' => $new_currency_id];
        if ($old_cost_value !== $new_cost_value)
            $data['cost_value'] = ['old' => round($old_cost_value, 2), 'new' => round($new_cost_value, 2)];
        $history->setAddition(json_encode($data));
        $history->save(self::$fields);
    }

    public static function delete($bundle_id, $user_id, $supplier_id, $name, $SKUs, int $currency_id = 0, float $cost_value = 0)
    {
        $history = self::init($bundle_id, $user_id);
        $history->setEvent(BundlesHistory::EVENT_DELETE);
        $history->setAddition(json_encode([
            'name' => $name,
            'supplier_id' => $supplier_id,
            'currency_id' => $currency_id,
            'cost_value' => round($cost_value, 2),
            'SKUs' => $SKUs
        ]));
        $history->save(self::$fields);
    }


    public static function addToOrder($bundle_id, $order_id, $quantity, $user_id): int
    {
        $history = self::init($bundle_id, $user_id);
        $history->setEvent(BundlesHistory::EVENT_ADD_TO_ORDER);
        $history->setAddition(json_encode([
            'order_id' => $order_id,
            'quantity' => $quantity,
            'SKUs' => Arr::map(Bundles::getBundleSKUs($bundle_id), 'id', 'quantity')
        ]));
        $history->save(self::$fields);
        return (int)$history->getId();
    }
}
