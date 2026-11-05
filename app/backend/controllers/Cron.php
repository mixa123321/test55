<?php

namespace app\backend\controllers;

use app\backend\models\Orders;
use app\backend\models\SKUPreffLocs;
use app\core\exceptions\RedirectException;
use app\core\models\Warehouses;

class Cron extends AuthController
{
    /** @throws */
    public function index()
    {
        foreach (Warehouses::getAllId($this->logistic_company->getId()) as $warehouse_id) {
//            Shippings::resolveUnmappedOrders($warehouse_id);
            Orders::updateOrders($warehouse_id);
            SKUPreffLocs::replenish($warehouse_id);
            Orders::updateBin($warehouse_id);
//            SKULocations::updateDH($warehouse_id);
//            BillingBob::report($warehouse_id);
//            Movements::updateMovements($warehouse_id);
        }
        throw new RedirectException(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/');
    }
}
