<?php

namespace app\core\models;

use app\core\channels\magento\MagentoConnector;
use app\core\channels\shopify\ShopifyConnector;
use app\core\channels\shopify\ShopifyCredentials;
use app\core\channels\woocommerce\WooCommerceConnector;
use app\core\db\QueryBuilder;
use app\core\helpers\notifiers\Notifier;
use app\core\helpers\notifiers\TelegramNotifier;
use app\core\dbal\SafeMysql;
use app\core\services\crypto\StoredSecret;

class Channels
{
    const FLAT_CHANNEL  = -1;
    const SFTP_CHANNEL  = -2;

    public static $allowed_channels = [
        ShopifyConnector::INTEGRATION_NAME => ShopifyConnector::INTEGRATION_NAME,
        WooCommerceConnector::INTEGRATION_NAME => WooCommerceConnector::INTEGRATION_NAME,
        MagentoConnector::INTEGRATION_NAME => MagentoConnector::INTEGRATION_NAME,
    ];

    public static function sendMessageToTelegram($subj, $message)
    {
//        $message = "<b>$subj</b>\n<i>$message</i>";
//        (new TelegramNotifier(Notifier::GROUP_INTEGRATION, $message, TelegramNotifier::PARSE_HTML))->send();

        Messages::addDebugInfo($subj, $message, Messages::GROUP_INTEGRATION, Messages::TEST_PARAMETERS);
    }

    public static function isShopify($channel_name)
    {
        return strtolower($channel_name) === strtolower(ShopifyConnector::INTEGRATION_NAME);
    }

    public static function isWooCommerce($channel_name)
    {
        return strtolower($channel_name) === strtolower(WooCommerceConnector::INTEGRATION_NAME);
    }

    public static function getFullShopUrl($channel_name, $shop_url)
    {
        return $shop_url . (self::isShopify($channel_name) ? ShopifyCredentials::SUFFIX : '');
    }

    public static function getAll()
    {
        return (new SafeMysql())->fetchAssocWithKey("SELECT * FROM channels", [], 'id');
    }

    public static function getAllWithSuppliers(array $filters = [])
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("SELECT DISTINCT cs.id, cs.channel_id, cs.supplier_id, c.name
                               FROM channels_suppliers cs");
        $query->addJoin("JOIN channels c ON cs.channel_id = c.id");
        $query->addJoin("JOIN shops s ON cs.id = s.channel_supplier_id");
        if(!empty($filters['supplier_id']))
            $query->addWhere("cs.supplier_id = " . (int) $filters['supplier_id']);
        if(!empty($filters['channel_id']))
            $query->addWhere("cs.channel_id = " . (int) $filters['channel_id']);
        if(!isset($filters['is_active']))
            $query->addWhere("s.is_active = 1");
        elseif($filters['is_active'] !== 'any')
            $query->addWhere("s.is_active = " . (int) $filters['is_active']);
        $tmp = (new SafeMysql())->fetchAll($query->build());
        $result = [];
        foreach ($tmp as $value) {
            if(empty($result[$value['name']]))
                $result[$value['name']] = [];
            $result[$value['name']][$value['supplier_id']] = $value['id'];
        }
        return $result;
    }

    public static function getById($id)
    {
        return (new SafeMysql())->fetchOne("SELECT * FROM channels WHERE id = :id", ['id' => (int) $id]);
    }

    public static function getByName($name)
    {
        return (new SafeMysql())->fetchOne("SELECT * FROM channels WHERE lower(name) = :name", ['name' => strtolower($name)]);
    }

    public static function getUserChannel(array $params)
    {
        $query = new QueryBuilder();
        $whereWarehouse = !empty($params['warehouse_id']) ? "IFNULL(sw.warehouse_id, " . (int) $params['warehouse_id'] . ") warehouse_id" : 'sw.warehouse_id';
        $query->setSelectFrom("
            SELECT cs.id, cs.supplier_id, cs.channel_id, cs.user_id, cs.primary_shop_id,
                   s.id shop_id, s.is_active, s.credentials, s.is_risk, sw.cache, $whereWarehouse,
                   sw.id sw_id, sw.download_orders_flag, sw.is_orders_approval_required, sw.is_daily, sw.location_id, sw.last_upload,
                   sw.is_active warehouse_active, sw.is_retain_me
            FROM channels_suppliers cs
                JOIN shops s ON cs.id = s.channel_supplier_id
                LEFT JOIN shop_warehouses sw ON s.id = sw.shop_id");
        if(!isset($params['is_active']))
            $query->addWhere("s.is_active = 1");
        elseif($params['is_active'] !== 'any')
            $query->addWhere("s.is_active = " . (int) $params['is_active']);
        if(!isset($params['is_active_warehouse']))
            $query->addWhere("sw.is_active = 1");
        elseif($params['is_active_warehouse'] !== 'any')
            $query->addWhere("sw.is_active = " . (int) $params['is_active_warehouse']);
        if (!empty($params['channel_id']))
            $query->addWhere("cs.channel_id = " . (int) $params['channel_id']);
        if (!empty($params['supplier_id']))
            $query->addWhere("cs.supplier_id = " . (int) $params['supplier_id']);
        if (!empty($params['user_id']))
            $query->addWhere("cs.user_id = " . (int) $params['user_id']);
        if (!empty($params['id']))
            $query->addWhere("cs.id = " . (int) $params['id']);
        if (!empty($params['sw_id']))
            $query->addWhere("sw.id = " . (int) $params['sw_id']);
        if (!empty($params['shop_id']))
            $query->addWhere("s.id = " . (int) $params['shop_id']);
        if (!empty($params['warehouse_id']))
            $query->addWhere("sw.warehouse_id = " . (int) $params['warehouse_id']);
        $binds = [];
        if (!empty($params['shop_url'])) {
            $shop_url = $params['shop_url'];
            if (!empty($params['shop']) && self::isShopify($params['shop']))
                $shop_url = str_replace(ShopifyCredentials::SUFFIX, "", $shop_url);
            $binds['shop_url_like'] = addcslashes($shop_url, '%_');
            $query->addWhere("s.shop_url LIKE CONCAT(:shop_url_like, '%')");
        }
        $query->setLimit('1');
        $result = (new SafeMysql())->fetchOne($query->build(), $binds);
        if ($result)
            return new Shops($result);
        return null;
    }

    public static function getUserChannels(array $params)
    {
        $query = new QueryBuilder();
        $whereWarehouse = !empty($params['warehouse_id']) ? "IFNULL(sw.warehouse_id, " . (int) $params['warehouse_id'] . ") warehouse_id" : 'sw.warehouse_id';
        $query->setSelectFrom("
            SELECT cs.id, cs.supplier_id, cs.channel_id, cs.user_id, cs.primary_shop_id,
                   s.id shop_id, s.is_active, s.credentials, s.is_risk, sw.cache, $whereWarehouse,
                   sw.id sw_id, sw.download_orders_flag, sw.is_orders_approval_required, sw.is_daily, sw.location_id, sw.last_upload,
                   sw.is_active warehouse_active, sw.is_retain_me
                FROM channels_suppliers cs
                    JOIN shops s ON cs.id = s.channel_supplier_id
                    LEFT JOIN shop_warehouses sw ON s.id = sw.shop_id
        ");
        if(!isset($params['is_active']))
            $query->addWhere("s.is_active = 1");
        elseif($params['is_active'] !== 'any')
            $query->addWhere("s.is_active = " . (int) $params['is_active']);
        if(!isset($params['is_active_warehouse']))
            $query->addWhere("sw.is_active = 1");
        elseif($params['is_active_warehouse'] !== 'any')
            $query->addWhere("sw.is_active = " . (int) $params['is_active_warehouse']);
        if (!empty($params['channel_id']))
            $query->addWhere("cs.channel_id = " . (int) $params['channel_id']);
        if (!empty($params['supplier_id']))
            $query->addWhere("cs.supplier_id = " . (int) $params['supplier_id']);
        if (!empty($params['user_id']))
            $query->addWhere("cs.user_id = " . (int) $params['user_id']);
        if (!empty($params['id']))
            $query->addWhere("cs.id = " . (int) $params['id']);
        if (!empty($params['shop_id']))
            $query->addWhere("s.id = " . (int) $params['shop_id']);
        if (!empty($params['warehouse_id']))
            $query->addWhere("sw.warehouse_id = " . (int) $params['warehouse_id']);
        $binds = [];
        if (!empty($params['shop_url'])) {
            $shop_url = $params['shop_url'];
            if (!empty($params['shop']) && self::isShopify($params['shop']))
                $shop_url = str_replace(ShopifyCredentials::SUFFIX, "", $shop_url);
            $binds['shop_url_like'] = addcslashes($shop_url, '%_');
            $query->addWhere("s.shop_url LIKE CONCAT(:shop_url_like, '%')");
        }
        $result = (new SafeMysql())->fetchAll($query->build(), $binds);
        if (!$result)
            return [];
        $res = [];
        foreach ($result as $shops)
            $res[] = new Shops($shops);
        return $res;
    }

    /**
     * @param $channel_id int
     * @param $supplierId int|null
     * @param $only_active bool
     * @return array
     */
    public static function getUserChannelsByChannelId($channel_id, $supplier_id = null, $only_active = true)
    {
        $channel_id = (int)$channel_id;
        $whereSupplier = $supplier_id ? ("AND cs.supplier_id = " . (int)$supplier_id) : '';
        $sql = "SELECT cs.id, cs.supplier_id, cs.channel_id, cs.user_id, cs.primary_shop_id,
                       s.id shop_id, s.is_active, s.credentials, s.is_risk, sw.cache,
                       sw.id sw_id, sw.warehouse_id, sw.download_orders_flag, sw.is_orders_approval_required, w.name, sw.is_daily, sw.location_id, sw.last_upload, sw.is_active warehouse_active, sw.is_retain_me, sw.notify 
                FROM channels_suppliers cs
                    JOIN shops s ON cs.id = s.channel_supplier_id 
                    LEFT JOIN shop_warehouses sw ON s.id = sw.shop_id 
                    LEFT JOIN warehouses w ON sw.warehouse_id = w.id 
                WHERE cs.channel_id = :channel_id $whereSupplier";
        if ($only_active)
            $sql .= ' AND s.is_active = 1';
        $channel_suppliers = (new SafeMysql())->fetchAll($sql, ['channel_id' => $channel_id]);
        $result = [];
        foreach ($channel_suppliers as $channel_supplier) {
            if (empty($result[$channel_supplier['id']]))
                $result[$channel_supplier['id']] = [
                    'channel_id' => $channel_supplier['channel_id'],
                    'supplier_id' => $channel_supplier['supplier_id'],
                    'user_id' => $channel_supplier['user_id'],
                    'primary_shop_id' => $channel_supplier['primary_shop_id'],
                ];
            if (empty($result[$channel_supplier['id']]['shops'][$channel_supplier['shop_id']]))
                $result[$channel_supplier['id']]['shops'][$channel_supplier['shop_id']] = [
                    'is_active' => $channel_supplier['is_active'],
                    'credentials' => StoredSecret::decrypt($channel_supplier['credentials']),
                    'is_risk' => $channel_supplier['is_risk'],
                ];
            if (empty($result[$channel_supplier['id']]['shops'][$channel_supplier['shop_id']]['shop_warehouses'][$channel_supplier['warehouse_id']]))
                $result[$channel_supplier['id']]['shops'][$channel_supplier['shop_id']]['shop_warehouses'][$channel_supplier['warehouse_id']] = [
                    'sw_id' => $channel_supplier['sw_id'],
                    'name' => $channel_supplier['name'],
                    'download_orders_flag' => $channel_supplier['download_orders_flag'],
                    'is_orders_approval_required' => $channel_supplier['is_orders_approval_required'],
                    'is_daily' => $channel_supplier['is_daily'],
                    'cache' => $channel_supplier['cache'],
                    'location_id' => $channel_supplier['location_id'],
                    'last_upload' => $channel_supplier['last_upload'],
                    'is_active' => $channel_supplier['warehouse_active'],
                    'is_retain_me' => $channel_supplier['is_retain_me'],
                    'notify' => $channel_supplier['notify'],
                ];
        }
        return $result;
    }

    /**
     * @param $channel
     * @return string
     */
    public static function getConnectorName($channel)
    {
        $connectorName = "\\app\\core\\channels\\" . strtolower($channel['name']) . "\\" . ucfirst($channel['name']) . 'Connector';
        if (!class_exists($connectorName)) {
            return false;
        }
        return $connectorName;
    }

    /**
     * @param $channel
     * @return string
     */
    public static function getProcessorName($channel)
    {
        $processorName = "\\app\\core\\channels\\" . strtolower($channel['name']) . "\\" . ucfirst($channel['name']) . 'DataProcessor';
        if (!class_exists($processorName)) {
            return false;
        }
        return $processorName;
    }
}