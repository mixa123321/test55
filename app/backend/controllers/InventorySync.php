<?php

namespace app\backend\controllers;

use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\View;
use app\core\models\ShopInventoryLogs;
use app\core\models\Shops;
use app\backend\models\InventorySync as InventorySyncModel;
use app\core\models\Channels as ChannelsModel;
use app\backend\models\Suppliers as SuppliersModel;
use app\core\statical\WithDate;

class InventorySync extends AuthController
{
    public function sync()
    {
        $shops_daily = Shops::getDailyShops($this->warehouse_id);
        $shops = Shops::getAllShops($this->warehouse_id, $_GET['channel_id'] ?? 0, $_GET['supplier_id'] ?? 0);
        $channels = Arr::map(ChannelsModel::getAll(), 'id', 'name');
        $suppliers = Arr::map(SuppliersModel::getSuppliers($this->logistic_company->getId(), $this->warehouse_id), 'id', 'name');
        foreach ($shops as $key => $shop)
            if (empty($suppliers[$shop['sid']]))
                $shops[$key]['is_active'] = 0;
        $data = [
            'shops' => $shops,
            'shops_daily' => $shops_daily,
            'channels' => (count($channels) > 1 ? ['all'] : []) + $channels,
            'suppliers' => (count($suppliers) > 1 ? ['all'] : []) + $suppliers,
        ];
        $this->render($data, 'tools/sync');
    }

    /** @throws */
    public function updateSync()
    {
        if (empty($_POST) || empty($_POST['shop']))
            throw new RedirectException('/');
        $num = InventorySyncModel::updateSync($_POST['shop'], $this->warehouse_id, $_POST['daily'] ?? []);
        View::addFlash('inventory_sync', 'warning', "Update $num record(s)");
        throw new RedirectException("/inventory_sync/sync?{$_POST['back']}");
    }

    /** @throws */
    public function syncShop()
    {
        if (empty($_GET) || empty($_GET['id']))
            throw new RedirectException('/');
        $shop_id = (int)$_GET['id'];
        /** @var Shops $shop */
        $shop = ChannelsModel::getUserChannel([
            'shop_id' => $shop_id,
            'warehouse_id' => $this->warehouse_id,
        ]);
        $page = empty($_GET['c']) ? 1 : (int)$_GET['c'];
        unset($_GET['c']);
        $back = $_GET;
        unset($back['id'], $back['SKU_id'], $back['barcode']);
        $back_url = '/inventory_sync/sync?' . http_build_query($back);
        if (empty($shop)) {
            View::addFlash('inventory_sync', 'danger', 'Shop not found');
            throw new RedirectException($back_url);
        }
        $wms_stock_raw = InventorySyncModel::getInventory($shop->getShopWarehouse(), $page, false, $_GET);
        $channel = ChannelsModel::getById($shop->getChannelSupplier()->getChannelId());
        InventorySyncModel::changeInventory($shop, $channel['name'], $wms_stock_raw['records']);
        $shop_name = ChannelsModel::getFullShopUrl($channel['name'], $shop->getShop()->getCredentials()['shop_url']);
        if (($external_name = InventorySyncModel::getExternalName($channel['name'])) === false) {
            View::addFlash('inventory_sync', 'danger', 'Coming soon');
            throw new RedirectException($back_url);
        }
        try {
            $data_inventory = InventorySyncModel::getInventoryManual($channel, $shop, Arr::column($wms_stock_raw['records'], 'inventory'));
        } catch (\Exception $e) {
            View::addFlash('inventory_sync', 'danger', $e->getMessage());
            throw new RedirectException($back_url);
        }
        $records = $wms_stock_raw['records'];
        if (!empty($records))
            array_walk($records, function(&$record) use ($data_inventory) {
                $record['available_shop'] = !isset($data_inventory[$record['inventory']]) ? '-' : (int)$data_inventory[$record['inventory']];
            });
        $data = [
            'shop_id' => $shop_id,
            'shop_name' => $shop_name,
            'supplier_name' => SuppliersModel::getById($shop->getChannelSupplier()->getSupplierId())->getName(),
            'SKUs' => $records,
            'external_name' => $external_name,
            'total' => $wms_stock_raw['total'],
            'page' => $wms_stock_raw['page'],
            'total_pages' => ceil($wms_stock_raw['total'] / InventorySyncModel::LIMIT_PER_PAGE),
            'now_update' => date(WithDate::DATE_FRONT_FORMAT, empty($this->time_diff) ? time() : strtotime($this->handleTimezone(date(WithDate::DATE_SQL_FORMAT)))),
            'back' => http_build_query($back),
        ];
        $this->render($data, 'tools/sync_shop');
    }

    public function updateInventory()
    {
        if (empty($_POST) || empty($_POST['shop_id']) || empty($_POST['inventory_id']) || empty($_POST['SKU_id']))
            return $this->response = json_encode(['error' => 'Empty or wrong request']);
        /** @var Shops $shop */
        $shop = ChannelsModel::getUserChannel([
            'shop_id' => (int)$_POST['shop_id'],
            'warehouse_id' => $this->warehouse_id,
        ]);
        unset($_POST['shop_id']);
        $res = InventorySyncModel::updateInventoryManual($shop, $_POST, $this->user);
        if (!empty($res['error']))
            return $this->response = json_encode(['error' => $res['error']]);
        return $this->response = json_encode(['success' => $res['success']]);
    }

    public function updateShop()
    {
        if (empty($_POST) || empty($_POST['shop_id'])) {
            $this->response = json_encode(['error' => 'Empty or wrong request']);
            return;
        }
        /** @var Shops $shop */
        $shop = ChannelsModel::getUserChannel([
            'shop_id' => (int)$_POST['shop_id'],
            'warehouse_id' => $this->warehouse_id,
        ]);
        $res = InventorySyncModel::updateInventoryDaily($shop, InventorySyncModel::SYNC_SHOP);
        if (!empty($res['error']))
            $this->response = json_encode(['error' => $res['error']]);
        else
            $this->response = json_encode(['success' => $res['success']]);
        return;
    }

    public function history()
    {
        $raw_data = ShopInventoryLogs::getRecords($this->warehouse, $_GET);
        $records = $raw_data['records'];
        if (!empty($records) && !empty($this->time_diff))
            array_walk($records, function(&$record) {
                $record['create_date'] = $this->handleTimezone($record['create_date']);
            });
        if (isset($_GET))
            unset($_GET['c']);
        $shops = Shops::getAllShopsName($this->warehouse_id, $_GET['channel_id'] ?? 0);
        $channels = Arr::map(ChannelsModel::getAll(), 'id', 'name');
        $data = [
            'inventory_records' => $records,
            'current_page' => $raw_data['current_page'],
            'shops' => (count($shops) > 1 ? ['all'] : []) + $shops,
            'channels' => (count($channels) > 1 ? ['all'] : []) + $channels,
            'types' => [0 => 'All'] + InventorySyncModel::$types_sync,
            'next_page' => $raw_data['next_page'],
        ];
        $this->render($data, 'tools/history');
    }

    /** @throws */
    public function clearHistory()
    {
        if (empty($_POST) || empty($_POST['clear_date']) || strtotime($_POST['clear_date']) > (time() - InventorySyncModel::HISTORY_MIN_DAYS*86400))
            throw new RedirectException('/');
        $num = InventorySyncModel::clearHistory($_POST['clear_date'], $this->warehouse_id);
        View::addFlash('inventory_sync', 'warning', "From history of inventory was deleted $num row(s)");
        throw new RedirectException('/inventory_sync/history');
    }
}