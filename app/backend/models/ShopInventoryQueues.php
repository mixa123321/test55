<?php

namespace app\backend\models;

use app\core\models\Channels;
use app\core\entities\ShopInventoryQueue;
use app\core\models\SKUChannelSuppliers;

class ShopInventoryQueues extends \app\core\models\ShopInventoryQueues
{
    public static function addASN($ASN_id)
    {
        $ASN = ASNs::getById($ASN_id);
        if (empty($ASN)) {
            Channels::sendMessageToTelegram('Inventory integration', "ASN #$ASN_id is not found");
            return;
        }
        $SKUs = ASNSKUs::getAcceptedSKUsByAsn($ASN_id);
        if (empty($SKUs)) {
            Channels::sendMessageToTelegram('Inventory integration', "ASN #$ASN_id: nothing was accepted");
            return;
        }
        foreach ($SKUs as $SKU_data)
            self::addSKU((int)$SKU_data['SKU_id'], $ASN->getWarehouseId(), InventorySync::SYNC_ASN, $ASN_id);
    }

    public static function addOrder($order_id)
    {
        $order = Orders::getById($order_id);
        if (empty($order)) {
            Channels::sendMessageToTelegram('Inventory integration', "Order #$order_id is not found");
            return;
        }
        $SKUs = OrderSKUs::getSKUsByOrder($order_id);
        if (empty($SKUs)) {
            Channels::sendMessageToTelegram('Inventory integration', "Order #$order_id: nothing was ordered");
            return;
        }
        foreach ($SKUs as $SKU_data)
            self::addSKU((int)$SKU_data['SKU_id'], $order->getWarehouseId(), InventorySync::SYNC_ORDER, $order_id);
    }

    public static function addSKU($SKU_id, $warehouse_id, $source, $object_id = null)
    {
        $quantity_available = SKULocations::getUnreservedCount($SKU_id, $warehouse_id);
        $shops_inventory = SKUChannelSuppliers::getShops($SKU_id, $warehouse_id);
        if (empty($shops_inventory))
            return;
        $messages = [];
        foreach ($shops_inventory as $shop_inventory)
            self::add(new ShopInventoryQueue([
                'shop_warehouse_id' => $shop_inventory['id'],
                'item_id' => $shop_inventory['external_id'],
                'available' => $quantity_available,
                'source' => $source,
                'object_id' => $object_id
            ]));
        if (!empty($messages))
            Channels::sendMessageToTelegram('Inventory integration', "SKU #$SKU_id: " . implode('; ', $messages));
    }
}
