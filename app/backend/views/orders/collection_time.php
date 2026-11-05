<?php
/**
 * @var $authUser User
 * @var $warehouses array
 * @var $shipping_services array
 * @var $total_count int
 * @var $back string
 */

use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\Html;
use app\core\helpers\View;

?>
<style>
    th {
        vertical-align: top !important;
    }
</style>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Collection Time</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                        <span>
                            <i class="far fa-fw fa-calendar"></i>
                            Shipping services (<?= (int)$total_count; ?> order<?= $total_count > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" onchange="submit();">
                <div class="form-row">
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <input type="hidden" name="back" value="<?= View::toHtml($back); ?>"/>
                            <input class="form-control form-control-lg" type="date" id="date_despatched" name="date_despatch"
                                   value="<?= View::toHtml($_GET['date_despatch'] ?? date('Y-m-d')); ?>"
                                   min="<?= Arr::map($warehouses, 'id', 'start_warehouse')[$warehouse_id]; ?>"
                                   max="<?= date('Y-m-d'); ?>"/>
                            <label for="date_despatch">Despatch date</label>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-end mr-3 mb-3">
                    <a href="/orders/collection_time?back=<?= urlencode($back); ?>" class="btn btn-lg btn-outline-orange mr-1">Clear</a>
                </div>
            </form>
            <?php if (count($shipping_services)) { ?>
                <div class="row mt-3">
                    <div class="table-responsive col">
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col width="*" />
                                <col width="7%" />
                                <col width="7%" />
                                <col width="7%" />
                                <col width="7%" />
                                <col width="7%" />
                                <col width="7%" />
                                <col width="7%" />
                                <col width="7%" />
                                <col width="7%" />
                                <col width="10%" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th rowspan="2" class="font-weight-bold">Shipping Service</th>
                                    <th rowspan="2" class="font-weight-bold">Time</th>
                                    <th rowspan="2" class="font-weight-bold">Total</th>
                                    <th colspan="7" class="font-weight-bold">Stages</th>
                                    <th rowspan="2" class="font-weight-bold">Action</th>
                                </tr>
                                <tr>
                                    <th class="font-weight-light">Indeterminate</th>
                                    <th class="font-weight-light">Created</th>
                                    <th class="font-weight-light">Allocating Stock</th>
                                    <th class="font-weight-light">Picking</th>
                                    <th class="font-weight-light">Packing</th>
                                    <th class="font-weight-light">Despatched</th>
                                    <th class="text-success">Despatched (in time)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shipping_services as $service_id => $shipping_service) { ?>
                                    <tr>
                                        <td><?= View::toHtml($shipping_service['name'] ?: '-'); ?></td>
                                        <td><?= View::toHtml($shipping_service['collection'] ?: '-'); ?></td>
                                        <td><span class="font-weight-bold"><?= View::toHtml($shipping_service['total'] ?: ''); ?></span></td>
                                        <td><?= View::toHtml($shipping_service['stage-1'] ?: ''); ?></td>
                                        <td><?= View::toHtml($shipping_service['stage0'] ?: ''); ?></td>
                                        <td><?= View::toHtml($shipping_service['stage1'] ?: ''); ?></td>
                                        <td><?= View::toHtml($shipping_service['stage3'] ?: ''); ?></td>
                                        <td><?= View::toHtml($shipping_service['stage4'] ?: ''); ?></td>
                                        <td><?= View::toHtml($shipping_service['stage5'] ?: ''); ?></td>
                                        <td><span class="text-success font-weight-bold"><?= View::toHtml($shipping_service['stage5_intime'] ?: ''); ?></span></td>
                                        <td>
                                            <?php if ($shipping_service['total']) {
                                                $date_from = $_GET['date_despatch'] ?? date('Y-m-d');
                                                $date_to = date('Y-m-d', strtotime("$date_from +1 day"));
                                            ?>
                                                <a href="/orders/?<?= http_build_query([
                                                    'date_from' => $date_from,
                                                    'date_to' => $date_to,
                                                    'date_type' => 'despatch_date',
                                                    'shipping_services_id' => $service_id,
                                                ]); ?>"
                                                   class="btn btn-primary btn-block mb-1">View</a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/orders/?<?= View::toHtml(base64_decode($back)); ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-chevron-left"></i>
                        </span>
                        <span class="text">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
