<?php

namespace app\backend\controllers;

use app\backend\models\Additional as AdditionalModel;
use app\core\exceptions\RedirectException;
use app\core\models\Warehouses;
use app\core\statical\WithDate;

class Additional extends AuthController
{
    /** @throws */
    public function index()
    {
        $back = empty($_GET['back']) ? 'order_bin' : addslashes($_GET['back']);
        throw new RedirectException("/additional/$back");
    }

    public function orderBin()
    {
        $OBs = AdditionalModel::getOrderBin($this->warehouse_id);
        $orders = AdditionalModel::getOldOrder($OBs);
        $data = [
            'OBs' => $OBs,
            'orders' => $orders,
            'OB_info' => Warehouses::getById($this->warehouse_id)->getOB(),
        ];
        $this->render($data, 'additional/order_bin');
    }

    public function tablePack()
    {
        $tables = AdditionalModel::getTablePack($this->warehouse_id);
        $orders = AdditionalModel::getOldOrderTables($tables);
        $data = [
            'tables' => $tables,
            'orders' => $orders,
            'name' => 'Pack Stations',
            'warehouse' => "$this->warehouse_label-TP-TP-TP-",
        ];
        $this->render($data, 'additional/table_pack');
    }

    public function largePack()
    {
        $tables = AdditionalModel::getLargePack($this->warehouse_id);
        $orders = AdditionalModel::getOldOrderTables($tables);
        $data = [
            'tables' => $tables,
            'orders' => $orders,
            'name' => 'Large Packs',
            'warehouse' => "{$this->warehouse_label}-LP-LP-LP-",
        ];
        $this->render($data, 'additional/table_pack');
    }

    public function oldestOrders()
    {
        $backorder = !empty($_GET['backorder']);
        $num_orders = (empty($_GET['num_orders']) || $_GET['num_orders'] < 5) ? 5 : (int)$_GET['num_orders'];
        $back = empty($_GET['back']) ? 'order_bin' : addslashes($_GET['back']);
        $orders = AdditionalModel::getOldestOrders($this->warehouse_id, $num_orders, $backorder);
        $data = [
            'orders' => $orders,
            'num_orders' => $num_orders,
            'backorder' => $backorder,
            'back' => $back,
        ];
        $this->render($data, 'additional/oldest_orders');
    }

    public function pickingHistory()
    {
        $warehouse = Warehouses::getById($this->warehouse_id);
        $picking_histories = [];
        if (!empty($warehouse->getPickingHistory())) {
            $picking_histories = $warehouse->getPickingHistory();
            array_walk_recursive($picking_histories, function (&$item) {
                if (!empty($this->time_diff))
                    $item = strtotime($this->handleTimezone(date(WithDate::DATE_SQL_FORMAT, $item)));
                if (is_int($item) && $item > 0)
                    $item = date(WithDate::DATE_FRONT_DETAIL, $item);
            });
        }
        $this->render([
            'picking_histories' => $picking_histories,
            'mezzanine' => !empty($warehouse) ? $warehouse->getMezzanine() : false,
        ], 'additional/picking_history');
    }

    /** @throws */
    public function viewOrder()
    {
        if (empty($_GET['id']))
            throw new RedirectException('/additional/oldest_orders');
        $num_orders = $_GET['num_orders'] ?? '';
        throw new RedirectException("/orders/detail_order/?id={$_GET['id']}&num_orders={$num_orders}&back={$_GET['back']}");
    }
}