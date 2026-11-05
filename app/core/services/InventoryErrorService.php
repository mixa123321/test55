<?php

namespace app\core\services;

use app\backend\models\Warehouses;
use app\core\models\Channels;
use app\core\models\InventoryErrors;
use app\core\models\Shops;

class InventoryErrorService
{
    public static function processErrors($date)
    {
        $res = InventoryErrors::viewErrors($date);
        if (empty($res))
            return;
        $ids = [];
        $result = [];
        foreach ($res as $error) {
            $ids[] = $error['id'];
            if (empty($result[$error['channel_id']][$error['warehouse_id']]["{$error['code']}: {$error['message']}"][$error['shop_id']]))
                $result[$error['channel_id']][$error['warehouse_id']]["{$error['code']}: {$error['message']}"][$error['shop_id']] = [];
            $result[$error['channel_id']][$error['warehouse_id']]["{$error['code']}: {$error['message']}"][$error['shop_id']][] = $error['item_id'];
        }
        foreach ($result as $channel_id => $channel_values) {
            $channel = Channels::getById($channel_id);
            foreach ($channel_values as $warehouse_id => $warehouse_values) {
                $warehouse = Warehouses::getById($warehouse_id);
                foreach ($warehouse_values as $error_message => $code_values) {
                    $subj = "{$channel['name']} ({$warehouse->getName()})\n<i>$error_message</i>";
                    $message = '';
                    foreach ($code_values as $shop_id => $item_values) {
                        $shop = Shops::getById($shop_id);
                        $item = implode("\n", array_unique($item_values));
                        $message .= "\n<b>{$shop->getCredentials()['shop_url']}</b>\n$item";
                    }
                    Channels::sendMessageToTelegram($subj, $message);
                }
            }
        }
        InventoryErrors::deleteErrors($ids);
    }
}