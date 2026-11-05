<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ChannelSupplier;
use app\core\entities\Shop;
use app\core\entities\ShopWarehouse;
use app\core\entities\Supplier;
use app\core\entities\UserChannel;
use app\core\helpers\Arr;

class Shops
{
    /** @var Shop $shop */
    private $shop;
    private $channel_supplier;
    /** @var ShopWarehouse $shop_warehouse */
    private $shop_warehouse;

    public function __construct($data)
    {
        $this->shop = new Shop([
            'id' => $data['shop_id'],
            'channel_supplier_id' => $data['id'],
            'is_active' => $data['is_active'],
            'credentials' => $data['credentials'],
            'is_risk' => $data['is_risk'],
        ]);
        $this->channel_supplier = new ChannelSupplier([
            'id' => $data['id'],
            'channel_id' => $data['channel_id'],
            'supplier_id' => $data['supplier_id'],
            'user_id' => $data['user_id'],
            'primary_shop_id' => $data['primary_shop_id'],
        ]);
        $this->shop_warehouse = new ShopWarehouse([
            'id' => $data['sw_id'],
            'shop_id' => $data['shop_id'],
            'warehouse_id' => $data['warehouse_id'],
            'download_orders_flag' => $data['download_orders_flag'],
            'is_orders_approval_required' => $data['is_orders_approval_required'],
            'location_id' => $data['location_id'],
            'is_daily' => $data['is_daily'],
            'cache' => $data['cache'],
            'last_upload' => $data['last_upload'],
            'is_active' => $data['warehouse_active'],
            'is_retain_me' => $data['is_retain_me'],
        ]);
    }

    public function getShop()
    {
        return $this->shop;
    }

    public function setShop($shop)
    {
        $this->shop = $shop;
    }

    public function getChannelSupplier()
    {
        return $this->channel_supplier;
    }

    public function setChannelSupplier($channel_supplier)
    {
        $this->channel_supplier = $channel_supplier;
    }

    public function getShopWarehouse()
    {
        return $this->shop_warehouse;
    }

    public function setShopWarehouse($shop_warehouse)
    {
        $this->shop_warehouse = $shop_warehouse;
    }

    public function getUserChannel()
    {
        return new UserChannel([
            'id' => $this->channel_supplier->getId(),
            'supplier_id' => $this->channel_supplier->getSupplierId(),
            'user_id' => $this->channel_supplier->getUserId(),
            'channel_id' => $this->channel_supplier->getChannelId(),
            'primary_shop_id' => $this->channel_supplier->getPrimaryShopId(),
            'is_active' => $this->shop->getIsActive(),
            'credentials' => $this->shop->getCredentials(),
            'download_orders_flag' => $this->shop_warehouse->getDownloadOrdersFlag(),
            'is_orders_approval_required' => $this->shop_warehouse->getIsOrdersApprovalRequired(),
            'is_retain_me' => $this->shop_warehouse->getIsRetainMe(),
            'cache' => $this->shop_warehouse->getCache(),
            'shop_id' => $this->shop->getId(),
            'warehouse_id' => $this->shop_warehouse->getWarehouseId(),
            'location_id' => $this->shop_warehouse->getLocationId(),
            'last_upload' => $this->shop_warehouse->getLastUpload(),
            'is_risk' => $this->shop->getIsRisk(),
        ]);
    }

    public static function getById($id, $channel_supplier_id = 0)
    {
        $params = ['id' => (int)$id];
        $whereCS = '';
        if (!empty($channel_supplier_id)) {
            $whereCS = ' AND channel_supplier_id = :channel_supplier_id';
            $params['channel_supplier_id'] = (int)$channel_supplier_id;
        }
        $sql = "SELECT *
                FROM shops
                WHERE id = :id $whereCS
                LIMIT 1";
        $shop_data = (new SafeMysql())->fetchOne($sql, $params);
        if (empty($shop_data))
            return null;
        return new Shop($shop_data);
    }

    public static function getCountActive($shop_id, $warehouse_ids)
    {
        if (empty($warehouse_ids) || !is_array($warehouse_ids))
            return 0;
        return (new SafeMysql())->fetchScalar("
            SELECT count(*)
            FROM shops s
                JOIN shop_warehouses sw ON s.id = sw.shop_id AND sw.is_active = 1 AND sw.warehouse_id IN (:warehouse_ids)
            WHERE s.id = :shop_id AND s.is_active = 1", [
            'shop_id' => (int)$shop_id,
            'warehouse_ids' => $warehouse_ids,
        ]);
    }

    public static function isRisk($shop_id)
    {
        $shop_id = (int)$shop_id;
        if (empty($shop_id))
            return 0;
        return (int)(new SafeMysql())->fetchScalar("
            SELECT is_risk FROM shops WHERE id = :shop_id", [
            'shop_id' => $shop_id,
        ]);
    }

    public static function checkByIdSync($warehouse_id, $id)
    {
        return (new SafeMysql())->fetchScalar("
            SELECT cs.supplier_id
            FROM shops s
                JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id
                JOIN shop_warehouses sw ON s.id = sw.shop_id AND sw.warehouse_id = :warehouse_id AND sw.is_active = 1
            WHERE s.id = :id AND s.is_active = 1", [
            'warehouse_id' => (int)$warehouse_id,
            'id' => (int)$id,
        ]);
    }

    public static function getAllShopsName($warehouse_id, $channel_id = 0)
    {
        $params = ['warehouse_id' => (int)$warehouse_id];
        $whereChannel = '';
        if (!empty($channel_id)) {
            $whereChannel = 'AND c.id = :channel_id';
            $params['channel_id'] = (int)$channel_id;
        }
        $sql = "SELECT s.id, s.shop_url, c.name
                FROM shops s
                    JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id
                    JOIN channels c ON cs.channel_id = c.id $whereChannel
                    JOIN shop_warehouses sw ON s.id = sw.shop_id AND sw.warehouse_id = :warehouse_id";
        $shops_data = (new SafeMysql())->fetchAll($sql, $params);
        $res = [];
        if (empty($shops_data))
            return $res;
        foreach ($shops_data as $shop_data)
            $res[$shop_data['id']] = Channels::getFullShopUrl($shop_data['name'], $shop_data['shop_url']) . " ({$shop_data['name']})";
        asort($res);
        return $res;
    }

    public static function getChannelName($shop_id)
    {
        return (new SafeMysql())->fetchScalar("
            SELECT c.name
            FROM shops s
                JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id
                JOIN channels c ON cs.channel_id = c.id
            WHERE s.id = :shop_id", [
            'shop_id' => (int)$shop_id,
        ]);
    }

    public static function getAllShops($warehouse_id, $channel_id = 0, $supplier_id = 0)
    {
        $params = ['warehouse_id' => (int)$warehouse_id];
        $whereChannel = '';
        if (!empty($channel_id)) {
            $whereChannel = 'AND c.id = :channel_id';
            $params['channel_id'] = (int)$channel_id;
        }
        $whereSupplier = '';
        if (!empty($supplier_id)) {
            $whereSupplier = 'AND sup.id = :supplier_id';
            $params['supplier_id'] = (int)$supplier_id;
        }
        $sql = "SELECT s.id, IF(s.is_active = 1, sw.is_active, 0) is_active, s.shop_url, c.name cname, sup.name sname, sup.id sid, sw.is_daily
                FROM shops s
                    JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id
                    JOIN channels c ON cs.channel_id = c.id $whereChannel
                    JOIN suppliers sup ON cs.supplier_id = sup.id $whereSupplier
                    JOIN shop_warehouses sw ON s.id = sw.shop_id AND sw.warehouse_id = :warehouse_id";
        $shops_data = (new SafeMysql())->fetchAll($sql, $params);
        if (empty($shops_data))
            return [];
        foreach ($shops_data as $key => $shop_data)
            $shops_data[$key]['shop_url'] = Channels::getFullShopUrl($shop_data['cname'], $shop_data['shop_url']);
        uasort($shops_data, function($el1, $el2) {
            return strcmp($el1['shop_url'], $el2['shop_url']);
        });
        return $shops_data;
    }

    public static function getDailyShops($warehouse_id, $is_daily = false)
    {
        $whereDaily = '';
        if ($is_daily)
            $whereDaily = 'AND sw.is_daily = 1';
        $sql = "SELECT s.id, s.shop_url, c.name, sw.is_daily
                FROM shops s
                    JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id
                    JOIN channels c ON cs.channel_id = c.id
                    JOIN shop_warehouses sw ON s.id = sw.shop_id AND sw.warehouse_id = :warehouse_id AND sw.is_active = 1 $whereDaily
                WHERE s.is_active = 1";
        $shops_data = (new SafeMysql())->fetchAll($sql, ['warehouse_id' => (int)$warehouse_id]);
        if (empty($shops_data))
            return [];
        foreach ($shops_data as $key => $shop_data)
            $shops_data[$key]['shop_url'] = Channels::getFullShopUrl($shop_data['name'], $shop_data['shop_url']);
        uasort($shops_data, function($el1, $el2) {
            return strcmp($el1['shop_url'], $el2['shop_url']);
        });
        return $shops_data;
    }

    /**
     * Shop URLs of the supplier's shops by channel name. Goes to the page (SKU connections), so no credentials
     * (ADR-002 Phase 2 step 4c).
     */
    public static function getBySupplierId($supplier_id)
    {
        $shops_data = (new SafeMysql())->fetchAll("
            SELECT c.name, s.id, s.shop_url
            FROM shops s
                JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id AND cs.supplier_id = :supplier_id
                JOIN channels c ON cs.channel_id = c.id", [
            'supplier_id' => (int)$supplier_id,
        ]);
        if (empty($shops_data))
            return [];
        $res = [];
        foreach ($shops_data as $shop_data)
            $res[$shop_data['name']][$shop_data['id']] = ['shop_url' => $shop_data['shop_url']];
        return $res;
    }

    public static function getSupplier($id)
    {
        $supplier_data = (new SafeMysql())->fetchOne("
            SELECT sup.*
            FROM shops s
                JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id
                JOIN suppliers sup ON cs.supplier_id = sup.id
            WHERE s.id = :id", [
            'id' => (int)$id,
        ]);
        if (empty($supplier_data))
            return null;
        return new Supplier($supplier_data);
    }

    public static function getConnections($warehouse_id, $SKU_ids)
    {
        if (empty($SKU_ids) || !is_array($SKU_ids))
            return null;
        $res = (new SafeMysql())->fetchAll("
            SELECT s.id, COUNT(*) nums
            FROM SKUs s
                JOIN channels_suppliers cs ON s.supplier_id = cs.supplier_id
                JOIN shops sh ON cs.id = sh.channel_supplier_id AND sh.is_active = 1
                JOIN shop_warehouses sw ON sh.id = sw.shop_id AND sw.warehouse_id = :warehouse_id AND sw.is_active = 1
            WHERE s.id IN (:SKU_ids)
            GROUP BY s.id", [
            'warehouse_id' => (int)$warehouse_id,
            'SKU_ids' => $SKU_ids,
        ]);
        return Arr::map($res, 'id', 'nums');
    }

    public static function getIdByName($name, $channel_id, $suppler_id)
    {
        return (new SafeMysql())->fetchScalar("
            SELECT sh.id
            FROM shops sh
                JOIN channels_suppliers cs ON sh.channel_supplier_id = cs.id AND cs.channel_id = :channel_id AND cs.supplier_id = :supplier_id
            WHERE sh.shop_url = :shop_url
            LIMIT 1", [
            'channel_id' => (int)$channel_id,
            'supplier_id' => (int)$suppler_id,
            'shop_url' => (string)$name,
        ]);
    }
}