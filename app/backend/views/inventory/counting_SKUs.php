<?php

use app\core\entities\Location;
use app\core\entities\SKU;
use app\core\helpers\Arr;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

/**
 * @var string $search
 * @var Location $location
 * @var bool $count
 * @var array $SKUs
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reconciliation</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-sign-in-alt"></i>
                        Location <?= View::toHtml($location->getLocation($warehouse_label)); ?>
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!$count) { ?>
                <div class="alert alert-danger">
                    Some of the items are currently being processed. Please contact a manager for change quantity
                </div>
            <?php } ?>
            <form method="post" action="/housekeeping/counting_check" id="countingFrm">
                <div class="form-group row">
                    <div class="col">
                        <input type="hidden" name="id" value="<?= $location->getId(); ?>"/>
                        <input type="hidden" id="batch_id" name="batch_id"/>
                        <input type="hidden" id="SKU_id" name="SKU_id"/>
                        <?php (new Scan([
                            'id' => 'search',
                            'placeholder' => 'SKU barcode',
                            'value' => empty($search) ? '' : htmlentities($search),
                            'type' => Scan::TYPE__SKU,
                        ], ['title' => 'Search', 'class' => 'js-btn-scan', 'data' => [
                                'send' => 'countingFrm',
                                'locations' => '["' . $location->getId() . '"]',
                                'suppliers' => json_encode(array_values(array_unique(Arr::column($SKUs, 'id')))),
                                'batches' => json_encode(array_values(array_unique(Arr::column($SKUs, 'batch_id')))),
                                'type' => '1',
                                'reason' => '2',
                        ]]))->render(); ?>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="*">
                        <col width="100px">
                        <col width="*">
                        <col width="*">
                        <col width="*">
                        <col width="*">
                        <col width="*">
                        <col width="*">
                    </colgroup>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th></th>
                        <th>Barcode</th>
                        <th>Account</th>
                        <th>Batch</th>
                        <th>Quantity</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($SKUs as $SKU) { ?>
                        <tr>
                            <td><?= View::toHtml($SKU['SKU_id']); ?></td>
                            <td>
                                <?php
                                if (!empty($SKU['photo'])) {
                                    if ($link = View::toHtml(SKU::showPhotoSKU(urlencode($SKU['photo']))))
                                        echo '<img class="img-thumbnail" style="max-width: 100px;" src="' . $link . '" /><br/>';
                                }
                                ?>
                            </td>
                            <td><?= View::toHtml($SKU['name']); ?></td>
                            <td><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></td>
                            <td><?= View::toHtml(sprintf("%s (%d)", $SKU['sname'], $SKU['id'])); ?></td>
                            <td><?= empty($SKU['number']) ? '-' : ('<b>Batch#:</b> ' . View::toHtml($SKU['number']) . '<br/><b>Exp#:</b> ' . date('jS M Y', strtotime($SKU['expired']))); ?></td>
                            <td><span class="text-<?= empty($SKU['real']) ? 'danger' : 'success'; ?> font-weight-bold"><?= View::toHtml($SKU['real']); ?></span> / <?= View::toHtml($SKU['summa']); ?></td>
                            <td>
                                <?php if (!empty($SKU['real'])) { ?>
                                    <a class="btn btn-success btn-icon-split" href="/housekeeping/counting_check/?id=<?= $location->getId(); ?>&SKU_id=<?= View::toHtml($SKU['SKU_id']); ?>&batch_id=<?= View::toHtml($SKU['batch_id']); ?>&btn=1">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-calculator"></i>
                                        </span>
                                        <span class="text d-md-block d-none">Count</span>
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/housekeeping/counting">
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
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_SKU_batch.php'; ?>
