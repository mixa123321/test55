<?php

use app\core\entities\User;
use app\core\helpers\View;
use app\core\entities\SKU;
use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\models\SKUs;

/**
 * @var User $authUser
 * @var array $SKUs
 * @var array $suppliers
 * @var int $current
 * @var int $size
 * @var int $pages
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Expired (in stock)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        SKUs list (<?= $size; ?> item<?= $size > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="<?= Filter::getFilterTypeKey('barcode'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER ?>"/>
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="text" id="barcode" name="barcode" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['barcode']) ? $_GET['barcode'] : '') ?>"/>
                            <label for="s">SKU barcode</label>
                        </div>
                    </div>
                    <?php if (!empty($suppliers)) { ?>
                    <div class="form-group col-md-3">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'class' => 'selectpicker form-control form-control-lg custom-select',
                                    'id' => 'supplier_id',
                                ];
                                if (count($suppliers) == 1) {
                                    $options['disabled'] = 'disabled';
                                }
                                echo Html::dropDownList('supplier_id', $suppliers, !empty($_GET['supplier_id']) ? $_GET['supplier_id'] : '', $options);
                            ?>
                            <label for="supplier_id">Account</label>
                        </div>
                    </div>
                    <?php } ?>
                    <div class="form-group col-md-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="number" id="days" name="days" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['days']) ? $_GET['days'] : '') ?>" min="0"/>
                            <label for="s">Max Days</label>
                        </div>
                    </div>
                    <div class="col-auto ml-auto">
                        <a href="/batches/expired/?c=<?= $current; ?>" class="btn btn-lg btn-outline-orange">Clear</a>
                        <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                    </div>
                </div>
            </form>
            <div class="row justify-content-end">
                <div class="col-auto mb-3">
                    <a class="btn btn-outline-primary btn-rounded" href="/batches/export_expired/?<?= http_build_query($_GET) . '&count=' . $size ?>">
                        <i class="fa fa-download"></i> Download results as CSV
                    </a>
                </div>
            </div>
            <?php if (count($SKUs)) { ?>
                <?php if ($size) View::pagination('/batches/expired/?' . http_build_query($_GET) . '&', 1, $pages, $current); ?>
                <div class="row">
                    <div class="table-responsive">
                        <table class="table table-hover">
                        <colgroup>
                            <col width="*">
                            <col width="*">
                            <?php if (!$authUser->isRoleSupplierUser()) { ?>
                            <col width="15%">
                            <?php } ?>
                            <col width="12%">
                            <col width="8%">
                            <col width="8%">
                            <col width="8%">
                            <col>
                        </colgroup>
                        <thead>
                        <tr>
                            <th>SKU (id)<br/>Barcode</th>
                            <?php if (!$authUser->isRoleSupplierUser()) { ?>
                            <th>Account (id)</th>
                            <?php } ?>
                            <th>Batch</th>
                            <th>Location</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($SKUs as $SKU) { ?>
                            <tr<?= $SKU['danger'] ? ' style="background-color: lightpink; color: black"' : ''; ?>>
                                <td><?= View::toHtml($SKU['name']); ?> (#<?= View::toHtml($SKU['id']) ?>)<br/><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']) ?></td>
                                <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                <td><?= View::toHtml($SKU['sname']); ?> (#<?= View::toHtml($SKU['sid']) ?>)</td>
                                <?php } ?>
                                <td><?= View::toHtml($SKU['number']); ?><br/><b>Exp#:</b> <?= View::toHtml(date('jS M Y', strtotime($SKU['expired'])))?></td>
                                <td><?= View::toHtml($SKU['loc_name']); ?></td>
                                <td><?= View::toHtml($SKU['quantity']); ?></td>
                                <td>
                                    <span class="badge badge-<?= SKUs::colorStatus($SKU['state']); ?>">
                                        <?= View::toHtml(SKU::getStateName($SKU['state'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="btn btn-primary btn-block justify-content-start btn-icon-split mt-0 mb-1" href="/SKUs/view_sku/?id=<?= View::toHtml($SKU['id']) ?>&back=/batches/expired&c=<?= $current; ?>&<?= http_build_query($_GET); ?>">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-info"></i>
                                        </span>
                                        <span class="text flex-fill">Detail</span>
                                    </a>
                                    <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                    <button class="btn btn-success btn-block justify-content-start btn-icon-split mt-0 mb-1 js-btn-edit"
                                            data-batch_id='<?= $SKU['batch_id']; ?>'
                                            data-number='<?= View::toHtml($SKU['number']); ?>'
                                            data-expired='<?= date('Y-m-d', strtotime($SKU['expired'])); ?>'
                                    >
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-pen"></i>
                                        </span>
                                        <span class="text flex-fill">Batch</span>
                                    </button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                    </div>
                </div>
                <?php if ($size) View::pagination('/batches/expired/?' . http_build_query($_GET) . '&', 1, $pages, $current); ?>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
    </div>
</div>
<?php
    if (!$authUser->isRoleSupplierUser())
        require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'batches.php';
?>
