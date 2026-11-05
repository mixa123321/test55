<?php

namespace app\frontend\controllers;

use app\backend\models\Carriers;
use app\core\controllers\BaseController;
use app\core\entities\TrackingHistory;
use app\core\helpers\View;
use app\core\models\Orders;
use app\core\models\OrderPackages;
use app\core\models\TrackingLinks;
use app\core\models\TrackingsHistory;

class Tracking extends BaseController
{
    public function index()
    {
        $number = $_GET['number'] ?? null;
        if ($number === null || !mb_ereg_match('^[A-z0-9]{32}$', $number)) {
            $this->response = View::render([], 'page_404');
            return;
        }
        if (TrackingLinks::getByNumber($number))
            TrackingsHistory::addRecord($number, $_SERVER['REMOTE_ADDR'], null, TrackingHistory::ACTION_MAIN);
        $order = TrackingLinks::getOrder($number);
        if ($order === null) {
            $this->response = View::render([], 'page_404');
            return;
        }
        $carrier = Carriers::getById($order->getCarrierId());
        $this->response = View::render([
            'company' => $carrier ? $carrier->getCode() : '',
            'tracking_info' => OrderPackages::getTrackingInfoByOrder(array_merge(Orders::getShortShipsOrders($order), [$order])),
        ], 'tracking_info');
    }

    public function addAction()
    {
        TrackingsHistory::addRecord($_POST['hash'], $_SERVER['REMOTE_ADDR'], $_POST['package_id'], TrackingHistory::ACTION_EXTERNAL);
    }
}