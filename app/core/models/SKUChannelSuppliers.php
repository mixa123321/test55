<?php

namespace app\core\models;

use app\core\channels\magento\MagentoConnector;
use app\core\channels\shopify\ShopifyConnector;
use app\core\channels\woocommerce\WooCommerceConnector;
use app\core\dbal\SafeMysql;
use app\core\entities\Order;

class SKUChannelSuppliers
{
    public static function getSqlExternalId($channel, $product_id, $variant_id, $inventory_id = 0)
    {
        switch ($channel) {
            case ShopifyConnector::INTEGRATION_NAME:
                return (!empty($variant_id) ? ("$variant_id-" . ($inventory_id ?: '%') . '-') : '') . $product_id;
            case WooCommerceConnector::INTEGRATION_NAME:
                return $product_id . (empty($variant_id) ? '' : "_$variant_id");
            case MagentoConnector::INTEGRATION_NAME:
                return empty($variant_id) ? $product_id : $variant_id;
            default:
                return null;
        }
    }

    public static function changeExternalId($SKU_channel_id, $shop_id, $SKU_id, $external_id)
    {
        (new SafeMysql())->execute("
            UPDATE SKU_channel_suppliers
            SET external_id = :external_id
            WHERE id = :id AND shop_id = :shop_id AND SKU_id = :SKU_id", [
            'external_id' => $external_id,
            'id' => $SKU_channel_id,
            'shop_id' => $shop_id,
            'SKU_id' => $SKU_id,
        ]);
    }

    public static function getConnection($shop_id, $SKU_id, $external_id)
    {
        return (new SafeMysql())->fetchOne("
            SELECT *
            FROM SKU_channel_suppliers
            WHERE shop_id = :shop_id AND SKU_id = :SKU_id AND external_id LIKE :external_id
            LIMIT 1", [
            'shop_id' => (int)$shop_id,
            'SKU_id' => (int)$SKU_id,
            'external_id' => $external_id,
        ]);
    }

    public static function getByExternalShopId($external_id, $shop_id)
    {
        return (new SafeMysql())->fetchScalar("
            SELECT SKU_id
            FROM SKU_channel_suppliers
            WHERE shop_id = :shop_id AND external_id = :external_id
            LIMIT 1", [
            'shop_id' => (int)$shop_id,
            // getSqlExternalId() returns null for an unknown channel; legacy compared with ''
            'external_id' => (string)$external_id,
        ]);
    }

    public static function getIdByPrefixExternalId($variant_id, $product_id, $shop_id)
    {
        $SKU_id = (new SafeMysql())->fetchArrayOneField("
            SELECT SKU_id
            FROM SKU_channel_suppliers
            WHERE shop_id = :shop_id AND
                  external_id LIKE :pattern
            LIMIT 1", [
            'shop_id' => (int)$shop_id,
            'pattern' => $variant_id . '-%-' . $product_id,
        ]);
        if (!empty($SKU_id))
            return $SKU_id[0];
        return null;
    }

    public static function getMaxExternalId($shop_id)
    {
        return (new SafeMysql())->fetchArrayOneField("
            SELECT external_id as max
            FROM SKU_channel_suppliers
            WHERE shop_id = :shop_id
            ORDER BY id DESC
            LIMIT 1", [
            'shop_id' => (int)$shop_id,
        ]);
    }

    public static function getBySKU($SKU_id, $channel_name, $warehouse_id)
    {
        if (empty($channel_name))
            return [];
        return (new SafeMysql())->fetchAll("
            SELECT scs.*
            FROM SKU_channel_suppliers scs
                JOIN shops s ON scs.shop_id = s.id AND s.is_active = 1
                JOIN shop_warehouses sw ON s.id = sw.shop_id AND sw.warehouse_id = :warehouse_id AND sw.is_active = 1
                JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id
                JOIN channels c ON cs.channel_id = c.id AND c.name = :channel_name
            WHERE SKU_id = :SKU_id", [
            'SKU_id' => (int)$SKU_id,
            'warehouse_id' => (int)$warehouse_id,
            'channel_name' => $channel_name,
        ]);
    }

    public static function addConnectionSKU($SKU_id, $shop_id, $external_id, $user_id = -1, $warehouse_id = 0)
    {
        (new SafeMysql())->execute("
            INSERT INTO SKU_channel_suppliers
            SET SKU_id = :SKU_id,
                shop_id = :shop_id,
                external_id = :external_id", [
            'SKU_id' => (int)$SKU_id,
            'shop_id' => (int)$shop_id,
            'external_id' => (string)$external_id,
        ]);
        SKUHistories::addChannelProduct($SKU_id, $shop_id, $external_id, $user_id, $warehouse_id);
    }

    public static function getShopifyVariants($SKU_id, $shop_id)
    {
        $external_ids = (new SafeMysql())->fetchArrayOneField("
            SELECT external_id
            FROM SKU_channel_suppliers
            WHERE SKU_id = :SKU_id AND shop_id = :shop_id", [
            'SKU_id' => (int)$SKU_id,
            'shop_id' => (int)$shop_id,
        ]);
        if (empty($external_ids))
            return [];
        $res = [];
        foreach ($external_ids as $external_id) {
            [, $inventory_item_id, ] = explode('-', $external_id);
            $res[] = (int)$inventory_item_id;
        }
        return $res;
    }

    public static function getShopifyExternals($SKU_id, $shop_id)
    {
        $external_ids = (new SafeMysql())->fetchAll("
            SELECT id, external_id
            FROM SKU_channel_suppliers
            WHERE SKU_id = :SKU_id AND shop_id = :shop_id", [
            'SKU_id' => (int)$SKU_id,
            'shop_id' => (int)$shop_id,
        ]);
        if (empty($external_ids))
            return [];
        $res = [];
        foreach ($external_ids as $external)
            $res[$external['id']] = explode('-', $external['external_id']);
        return $res;
    }

    public static function existByExternalId($SKU_id, $variant_id, $product_id, $shop_id)
    {
        return (new SafeMysql())->fetchScalar("
            SELECT 1
            FROM SKU_channel_suppliers
            WHERE SKU_id = :SKU_id AND
                  shop_id = :shop_id AND
                  external_id LIKE :pattern
            LIMIT 1", [
            'SKU_id' => (int)$SKU_id,
            'shop_id' => (int)$shop_id,
            'pattern' => $variant_id . '-%-' . $product_id,
        ]);
    }

    public static function changeSKUId($SKU_channel_id, $SKU_id)
    {
        (new SafeMysql())->execute("
            UPDATE SKU_channel_suppliers 
            SET SKU_id = :SKU_id 
            WHERE id = :id", [
            'SKU_id' => (int)$SKU_id,
            'id' => (int)$SKU_channel_id,
        ]);
    }

    public static function getVariantIds(Order $order, array $SKUs, array $variant_ids): ?array
    {
        if (empty($SKUs) || empty($variant_ids))
            return [];
        $external_ids = self::getVariantIdsForRequest($variant_ids);
        $sql = "
        SELECT Scs.SKU_id, Scs.external_id
        FROM SKU_channel_suppliers Scs
        WHERE Scs.shop_id = :shop_id AND Scs.SKU_id IN (:SKU_ids) AND Scs.external_id REGEXP '$external_ids'";
        $SKU_variant_id = [];
        foreach ((new SafeMysql())->fetchAll($sql, ['shop_id' => (int)$order->getShopId(), 'SKU_ids' => $SKUs]) as $item) {
            $external_id = explode('-', $item['external_id']);
            if (empty($external_id[0]))
                return null;
            [$variant_id, $inventory_item_id, $product_id] = $external_id;
            $SKU_variant_id[$item['SKU_id']] = $variant_id;
        }
        return $SKU_variant_id;
    }

    public static function getVariantIdsByProductId(Order $order, array $variant_ids): ?array
    {
        if (empty($variant_ids))
            return [];
        $external_ids = self::getVariantIdsForRequest($variant_ids);
        $sql = "
        SELECT s.name, Scs.SKU_id, Scs.external_id
        FROM SKUs s
            JOIN SKU_channel_suppliers Scs ON Scs.SKU_id = s.id AND Scs.shop_id = :shop_id AND Scs.external_id REGEXP '$external_ids'";
        $product_id_to_variant_id = [];
        foreach ((new SafeMysql())->fetchAll($sql, ['shop_id' => (int)$order->getShopId()]) as $item) {
            $external_id = explode('-', $item['external_id']);
            if (empty($external_id[0]))
                return null;
            [$variant_id, $inventory_item_id, $product_id] = $external_id;
            $product_id_to_variant_id[$product_id] = $variant_id;
        }
        return $product_id_to_variant_id;
    }

    private static function getVariantIdsForRequest(array $variant_ids): string
    {
        return "^" . implode('|', array_map(function ($v) {
            return ((int)$v) . '\-.+';
        }, $variant_ids)) . "$";
    }

    public static function getShops($SKU_id, $warehouse_id)
    {
        return (new SafeMysql())->fetchAll("
            SELECT sw.id, scs.external_id
            FROM SKU_channel_suppliers scs
                JOIN shops s ON scs.shop_id = s.id AND s.is_active = 1
                JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id
                JOIN channels c ON cs.channel_id = c.id
                JOIN shop_warehouses sw ON s.id = sw.shop_id AND sw.warehouse_id = :warehouse_id AND sw.is_active = 1
            WHERE scs.SKU_id = :SKU_id", [
            'SKU_id' => (int)$SKU_id,
            'warehouse_id' => (int)$warehouse_id,
        ]);
    }

    public static function getItemId($channel_name, $external_id)
    {
        switch ($channel_name) {
            case ShopifyConnector::INTEGRATION_NAME:
                [, $inventory_item_id, ] = explode('-', $external_id);
                return $inventory_item_id;
            case WooCommerceConnector::INTEGRATION_NAME:
                $external_info = explode("_", $external_id);
                return $external_info[0];
            case MagentoConnector::INTEGRATION_NAME:
                return $external_id;
            default:
                return null;
        }
    }
}