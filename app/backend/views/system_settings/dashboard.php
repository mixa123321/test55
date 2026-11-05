<?php
/**
 * @var $warehouses_statistic array
 * @var $locations_statistic array
 * @var $companies_statistic array
 * @var $users_statistic array
 * @var $SKUs_statistic array
 * @var $ASNs_statistic array
 * @var $orders_statistic array
 */

use app\core\helpers\Html;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-chart-line"></i> Statistic
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body pb-0">
            <div class="row">
                <?= Html::widget('Warehouses', $warehouses_statistic, 'warehouse', 'warning'); ?>
                <?= Html::widget('Locations', $locations_statistic, 'map-marker-alt', 'warning'); ?>
                <?= Html::widget('Companies', $companies_statistic, 'industry', 'info'); ?>
                <?= Html::widget('Users', $users_statistic, 'users', 'info'); ?>
                <?= Html::widget('SKUs', $SKUs_statistic, 'boxes', 'info'); ?>
                <?= Html::widget('ASNs', $ASNs_statistic, 'sign-in-alt'); ?>
                <?= Html::widget('Orders', $orders_statistic, 'clipboard-list'); ?>
            </div>
        </div>
    </div>
</div>
