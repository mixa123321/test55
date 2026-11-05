<?php

namespace app\backend\models;

use app\core\channels\BaseChannelConnector;
use app\core\channels\BaseChannelProcessor;
use app\core\channels\magento\MagentoConnector;
use app\core\channels\shopify\ShopifyConnector;
use app\core\channels\woocommerce\WooCommerceConnector;
use app\core\dbal\SafeMysql;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\exceptions\ChannelException;
use app\core\helpers\Arr;
use app\core\models\Bundles;
use app\core\models\Channels;
use app\core\models\Shops;
use app\core\helpers\Debug;

class InventorySync extends \app\core\models\InventorySync
{
    const HISTORY_MIN_DAYS = 3;

    const SYNC_ORDER = 'order';
    const SYNC_ASN = 'ASN';
    const SYNC_PUTAWAY = 'putaway';
    const SYNC_MANUAL = 'manual';
    const SYNC_SHOP = 'shop';
    const SYNC_DAILY = 'daily';
    const SYNC_SKU = 'SKU';
    const SYNC_ORDER_RESERVED = 'reserved';

    public static $types_sync = [
        self::SYNC_ORDER => 'Order',
        self::SYNC_ASN => 'ASN',
        self::SYNC_PUTAWAY => 'Putaway',
        self::SYNC_MANUAL => 'Manual (SKU)',
        self::SYNC_SHOP => 'Shop (all SKUs)',
        self::SYNC_DAILY => 'Daily',
        self::SYNC_SKU => 'SKU',
        self::SYNC_ORDER_RESERVED => 'Reserved',
    ];

    public static function showType($type, $object_id)
    {
        $object_id = (int)$object_id;
        switch($type) {
            case self::SYNC_ASN:
//                return "ASN #$object_id";
                return "ASN" . (empty($object_id) ? '' : " #$object_id");
            case self::SYNC_ORDER:
            case self::SYNC_ORDER_RESERVED:
                return "Order #$object_id";
            case self::SYNC_MANUAL:
            case self::SYNC_SHOP:
            case self::SYNC_DAILY:
            case self::SYNC_PUTAWAY:
            case self::SYNC_SKU:
                return $type;
            default:
                return 'unknown';
        }
    }

    public static function showQuantity($quantity, $channel_name)
    {
        $quantity = (int)$quantity;
        switch($channel_name) {
            case ShopifyConnector::INTEGRATION_NAME:
                return "<span class='font-weight-bold text-" . ($quantity < 0 ? 'danger' : 'success') . "'>" . abs($quantity) . "</span>";
            case WooCommerceConnector::INTEGRATION_NAME:
                return $quantity;
            default:
                return $quantity;
        }
    }

    public static function updateSync($shops, $warehouse_id, $daily_arr)
    {
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $num = 0;
        foreach ($shops as $shop_pred => $value) {
            [$shop_id, $daily] = explode('_', $shop_pred);
            $new_daily = !empty($daily_arr[$shop_id]) ? '1' : '0';
            if ($daily != $new_daily) {
                $mysql->execute("UPDATE shop_warehouses SET is_daily = :new_daily WHERE shop_id = :shop_id AND warehouse_id = :warehouse_id", [
                    'new_daily' => $new_daily,
                    'shop_id' => $shop_id,
                    'warehouse_id' => $warehouse_id,
                ]);
                $num++;
            }
        }
        $mysql->commit();
        return $num;
    }

    public static function clearHistory($clear_date, $warehouse_id)
    {
        $mysql = new SafeMysql();
        $mysql->execute("DELETE FROM shop_inventory_logs WHERE create_date < :clear_date AND warehouse_id = :warehouse_id", [
            'clear_date' => $clear_date,
            'warehouse_id' => $warehouse_id,
        ]);
        return $mysql->fetchScalar("SELECT ROW_COUNT()");
    }

    public static function updateInventoryManual(Shops $shop, $params, User $user)
    {
        if (empty($shop))
            return ['error' => "Shop isn't found"];
        $channel = Channels::getById($shop->getChannelSupplier()->getChannelId());
        if (!in_array($channel['name'], Channels::$allowed_channels))
            return ['error' => 'Coming soon'];
        try {
            $connector_class = Channels::getConnectorName($channel);
            $processor_class = Channels::getProcessorName($channel);
            /** @var BaseChannelProcessor $processor */
            $processor = new $processor_class();
            $processor->setUserChannel($shop);
            $processor->setConnector(new $connector_class($shop->getShop()->getCredentials()));
            $processor->setSupplier(Suppliers::getById($shop->getChannelSupplier()->getSupplierId()));
            $processor->setInventoryManual($params + ['user_id' => $user->getId()]);
            $response = ['success' => 'Success!'];
        } catch (\Exception $e) {
            $response = ['error' => "Failed with message: {$e->getMessage()}"];
        }
        return $response;
    }

    /** @throws ChannelException */
    public static function getInventoryManual($channel, Shops $shop, $inventories)
    {
        $connector_class = Channels::getConnectorName($channel);
        /** @var BaseChannelConnector $connector */
        $connector = new $connector_class($shop->getShop()->getCredentials());
        $location_id = $shop->getShopWarehouse()->getLocationId();
        if ($connector instanceof ShopifyConnector && empty($location_id))
            Debug::sendDebugInfo('InventorySync', implode(',', $inventories), Debug::TEST);
        return $connector->getInventory($inventories, $location_id);
    }

    public static function changeInventory(Shops $shop, $channel_name, &$inventory)
    {
        foreach ($inventory as $key => &$SKU)
            if (Bundles::isBundleExists($shop->getChannelSupplier()->getSupplierId(), $SKU['customer_ref']))
                $SKU['state'] = SKU::BUNDLE_NUM;
        array_walk($inventory, function (&$el) use ($channel_name) {
            switch ($channel_name) {
                case ShopifyConnector::INTEGRATION_NAME:
                    $el['inventory'] = explode('-', $el['external_id'])[1];
                    break;
                case WooCommerceConnector::INTEGRATION_NAME:
//                    $ids = explode('_', $el['external_id']);
                    $el['inventory'] = $el['external_id'];
                    break;
                case MagentoConnector::INTEGRATION_NAME:
                    $el['inventory'] = $el['external_id'];
                    break;
                default:
                    $el['inventory'] = $el['external_id'];
                    break;
            }
            $el['available'] = (int)$el['available'];
        });
    }

    public static function getExternalName($channel_name)
    {
        switch ($channel_name) {
            case ShopifyConnector::INTEGRATION_NAME:
                return "Variant-Inventory-Product IDs";
            case WooCommerceConnector::INTEGRATION_NAME:
                return "Product(_Variation) ID(s)";
            case MagentoConnector::INTEGRATION_NAME:
                return "Product ID";
            default:
                return false;
        }
    }

    public static function getExternalFieldName($channel_name)
    {
        switch ($channel_name) {
            case ShopifyConnector::INTEGRATION_NAME:
                return "inventory_item_id";
            case WooCommerceConnector::INTEGRATION_NAME:
            case MagentoConnector::INTEGRATION_NAME:
                return "external_id";
            default:
                return false;
        }
    }

    public static function updateInventoryDaily(Shops $shop, $type = InventorySync::SYNC_DAILY)
    {
        if (empty($shop))
            return ['error' => "Shop isn't found"];
        $channel = Channels::getById($shop->getChannelSupplier()->getChannelId());
        if (!in_array($channel['name'], Channels::$allowed_channels))
            return ['error' => 'Coming soon'];
        $wms_stock_raw = self::getInventory($shop->getShopWarehouse(), 0, true);
        self::changeInventory($shop, $channel['name'], $wms_stock_raw);
        $connector_class = Channels::getConnectorName($channel);
        $processor_class = Channels::getProcessorName($channel);
        /** @var BaseChannelProcessor $processor */
        $connector = new $connector_class($shop->getShop()->getCredentials());
        $processor = new $processor_class();
        $processor->setUserChannel($shop);
        $processor->setConnector($connector);
        $processor->setSupplier(Suppliers::getById($shop->getChannelSupplier()->getSupplierId()));
        try {
            $inventories = Arr::column($wms_stock_raw, 'inventory');
            $location_id = $shop->getShopWarehouse()->getLocationId();
            if ($connector instanceof ShopifyConnector && empty($location_id))
                Debug::sendDebugInfo('InventorySync', implode(',', $inventories), Debug::TEST);
            $stock = $processor->getConnector()->getInventory($inventories, $location_id);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
        if (empty($stock))
            return ['error' => 'Cannot get inventory data'];
        $err_msg = $processor->updateInventoryDaily($type, $wms_stock_raw, $stock);
        if (!empty($err_msg))
            return ['error' => implode('; ', $err_msg)];
        return ['success' => 'Success!'];
    }
}
