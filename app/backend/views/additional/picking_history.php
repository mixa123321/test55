<?php

use app\backend\models\PickingHistory;
use app\core\entities\Location;
use app\core\helpers\Html;
/**
 * @var User $authUser
 * @var array $picking_histories
 * @var bool $mezzanine
 */

$picking_histories_attr = [];

$check_storage = function ($picking_histories, $level) {
    return empty($picking_histories[$level]) ||
        (empty($picking_histories[$level][Location::ST]) && empty($picking_histories[$level][Location::PF]));
};

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-inbox"></i>
                        Picking History
                    </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/additional/order_bin">
                        <span class="icon text-white-50">
                                <i class="fas fa-fw fa-th"></i>
                            </span>
                        <span class="text text-white">Order Bin</span>
                    </a>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/additional/table_pack">
                        <span class="icon text-white-50">
                                <i class="fas fa-fw fa-desktop"></i>
                            </span>
                        <span class="text text-white">Pack Stations</span>
                    </a>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/additional/large_pack">
                        <span class="icon text-white-50">
                                <i class="fas fa-fw fa-desktop"></i>
                            </span>
                        <span class="text text-white">Large Packs</span>
                    </a>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/additional/oldest_orders">
                        <span class="icon text-white-50">
                                <i class="fas fa-fw fa-clock"></i>
                            </span>
                        <span class="text text-white">Oldest Orders</span>
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-10">
                    <?php
                    if (empty($picking_histories))
                        echo Html::warning('No records found!');
                    else {
                        echo Html::div('Main Storage', ['class' => 'row h4 justify-content-center'] + $picking_histories_attr) .
                            ($check_storage($picking_histories, 0)
                                ? Html::warning('No data found!')
                                : PickingHistory::showPickingHistory($picking_histories[0]));
                        if ($mezzanine) {
                            echo Html::div('Mezzanine', ['class' => 'row h4 justify-content-center mt-4'] + $picking_histories_attr ) .
                                ($check_storage($picking_histories, 1)
                                    ? Html::warning('No data found!')
                                    : PickingHistory::showPickingHistory($picking_histories[1]));
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
