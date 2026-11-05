<?php

use app\backend\models\SKULocations;
use app\core\entities\ASN;
use app\core\entities\Batch;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\statical\WithDate;
use app\core\statical\Services;
use app\backend\models\Suppliers;
use app\core\models\SKUs;

/**
 * @var ASN $ASN
 * @var array $SKUs
 * @var User $authUser
 * @var array $comments
 * @var array $containers
 * @var array $lastSKUs
 * @var array $lastDo
 * @var string $order_ref
 * @var string $back
 * @var int $warehouse_id
 * @var string $warehouse_virtual
 */

$user = $authUser->isRoleSupplierUser();

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= $ASN->new() && $user ? 'Edit' : 'View'; ?> Manifest</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 p-0 font-weight-bold text-primary col">
                <i class="fas fa-fw fa-sign-in-alt"></i><?= !empty($ASN->getOrderId()) ? ' Return ' : '';?> ASN #<?= $ASN->getId() . ' / ' . View::toHtml($ASN->getRef() ?: '-'); ?><?= !empty($ASN->getOrderId()) ? " (Order reference #{$ASN->getOrderId()} / " . View::toHtml($order_ref) . ")" : '';?>
            </h6>
            <?php if ($authUser->isRoleSA() && $warehouse_virtual) { ?>
                <div class="col-auto pr-0">
                    <button class="btn btn-success btn-icon-split js-webhook" data-asn_id="<?= $ASN->getId(); ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-globe"></i>
                        </span>
                        <span class="text">Webhooks</span>
                    </button>
                </div>
            <?php } ?>
            <?php if (!$authUser->isClientSide() && $warehouse_virtual) { ?>
                <input type="hidden" id="ASN_id" value="<?= $ASN->getId(); ?>">
                <div class="col-auto pr-0">
                    <button class="btn btn-success btn-icon-split js-in-reality">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-warehouse"></i>
                        </span>
                        <span class="text">In reality</span>
                    </button>
                </div>
            <?php } ?>
            <div class="col-auto justify-content-end pr-0">
                <a class="btn btn-success btn-icon-split" href='/goods_in/manifest/?id=<?= $ASN->getId(); ?>' target="_blank">
                    <span class="icon text-white-50">
                        <i class="fas fa-fw fa-print"></i>
                    </span>
                    <span class="text text-white">Manifest</span>
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-1">
                    <ul class="nav nav-pills nav-stacked">
                    </ul>
                </div>
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-md-12 align-self-center">
                            <?php if ($warehouse_virtual) { ?>
                                <p class="mb-1"><span class="text-success"><b>Warehouse: </b></span><?= View::toHtml($ASN->getShipToLocation()); ?></p>
                            <?php } ?>
                            <?php if ((!$user && !$ASN->completed()) || ($user && $ASN->new())) { ?>
                                <form method="post" class="form-inline mb-3" action="/goods_in/update_container">
                                    <input type="hidden" name="id" value="<?= $ASN->getId(); ?>">
                                    <span class="text-success"><b>Container:</b></span>
                                    <div class="form-group mx-3">
                                        <?= Html::dropDownList("container", $containers, $ASN->getContainer(),
                                            ['class' => 'form-control']) ?>
                                    </div>
                                    <button class="btn btn-primary">Update</button>
                                </form>
                            <?php } else { ?>
                                <p class="form-control-static text-success mb-1"><b>Container: <?= View::toHtml($containers[$ASN->getContainer()])?></b></p>
                            <?php } ?>

                            <?php if ($user && $ASN->new()) { ?>
                                <form method="post" class="form-inline mb-1" action="/goods_in/update_arrival/">
                                    <input type="hidden" name="id" value="<?= $ASN->getId(); ?>">
                                    <label class="text-success" for="arrival_date"><b>Expected Date:&nbsp;</b></label>
                                    <input type="date" name="arrival_date" id="arrival_date" class="form-control" value="<?= View::toHtml(date('Y-m-d', $ASN->getArrivalDateInt())); ?>" min="<?= View::toHtml(date('Y-m-d', time())); ?>">&nbsp;
                                    <button class="btn btn-primary btn-icon-split" type="submit">
                                        <span class="text text-white">Update</span>
                                        <span class="icon text-white">
                                            <i class="fas fa-fw fa-chevron-up"></i>
                                        </span>
                                    </button>
                                </form><br/>
                            <?php } else { ?>
                                <p class="form-control-static text-success mb-0"><b>Expected Date: <?= View::toHtml(date('jS F Y', $ASN->getArrivalDateInt())) ?></b></p>
                                <?php if (!empty($ASN->getArrivedDateInt())) { ?>
                                    <p class="form-control-static text-success mt-0 mb-0"><b>Arrived Date: <?= View::toHtml(date('jS F Y', $ASN->getArrivedDateInt())) ?></b></p>
                                <?php } ?>
                                <?php if (!empty($ASN->getCompletedDateInt())) { ?>
                                    <p class="form-control-static text-success mt-0 mb-0"><b>Completed Date: <?= View::toHtml(date('jS F Y', $ASN->getCompletedDateInt())) ?></b></p>
                                <?php } ?>
                            <?php } ?>
                            <?php if (!empty($ASN->getComment())) { ?>
                                <p class="my-2"><span class="text-success"><b>Comment: </b></span><?= View::toHtml($ASN->getComment()); ?></p>
                            <?php } ?>
                            <p class="form-control-static text-success mt-3 mb-0"><b>Additional charge</b></p>
                            <div class="row">
                                <?php
                                    $charge = false;
                                    if($authUser->hasManagerAccess() || $ASN->getBillingChargePallet()) {
                                        $charge = true;
                                ?>
                                    <div class="col-lg-3 col-12 border border-info rounded">
                                        <p class="form-control-static mt-1 mb-1"><b>Miscellaneous Pallet Charge:</b></p>
                                        <?php if (!$authUser->hasManagerAccess()) { ?>
                                            <p class="form-control-static mt-1 mb-1"><?= View::toHtml($ASN->getBillingDescriptionPallet()); ?></p>
                                            <p class="form-control-static mt-1 mb-1 mr-4 text-right"><?= sprintf("%5.3f %s", $ASN->getBillingChargePallet(), $ASN->getBillingChargeCurrency()); ?></p>
                                        <?php } else { ?>
                                            <textarea class="form-control mt-1 mb-1" rows="3" id="description_pallet"><?= View::toHtml($ASN->getBillingDescriptionPallet()); ?></textarea>
                                            <div class="input-group mt-1 mb-1">
                                                <input class="form-control" type="number" id="charge_pallet" value="<?= sprintf("%5.3f", $ASN->getBillingChargePallet()); ?>"/>
                                                <div class="input-group-append">
                                                    <span class="input-group-text"><?= View::toHtml($ASN->getBillingChargeCurrency()); ?></span>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                                <?php
                                    if($authUser->hasManagerAccess() || $ASN->getBillingChargeRework()) {
                                        $charge = true;
                                ?>
                                    <div class="col-lg-3 col-12 border border-info rounded ml-1">
                                        <p class="form-control-static mt-1 mb-1"><b>Custom additional charge for rework:</b></p>
                                        <?php if (!$authUser->hasManagerAccess()) { ?>
                                            <p class="form-control-static mt-1 mb-1"><?= View::toHtml($ASN->getBillingDescriptionRework()); ?></p>
                                            <p class="form-control-static mt-1 mb-1 mr-4 text-right"><?= sprintf("%5.3f %s", $ASN->getBillingChargeRework(), $ASN->getBillingChargeCurrency()); ?></p>
                                        <?php } else { ?>
                                            <textarea class="form-control mt-1 mb-1" rows="3" id="description_rework"><?= View::toHtml($ASN->getBillingDescriptionRework()); ?></textarea>
                                            <div class="input-group mt-1 mb-1">
                                                <input class="form-control" type="number" id="charge_rework" value="<?= sprintf("%5.3f", $ASN->getBillingChargeRework()); ?>"/>
                                                <div class="input-group-append">
                                                    <span class="input-group-text"><?= View::toHtml($ASN->getBillingChargeCurrency()); ?></span>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <?php if (!$charge) { ?>
                                <p class="form-control-static mt-1">No additional charge</p>
                            <?php } ?>
                            <?php if($authUser->hasManagerAccess()) { ?>
                                <div class="col-lg-6 col-12 text-right">
                                    <div class="alert alert-danger mt-1 mb-1 d-none" id="charge_err"></div>
                                    <div class="alert alert-success mt-1 mb-1 d-none" id="charge_ok"></div>
                                    <button class="btn btn-primary mt-1" type="button" id="charge_update">Update</button>
                                </div>
                            <?php } ?>
                            <p class="form-control-static text-success">
                                <b>Status: <?= View::toHtml($ASN->getStatusName()) ?></b>
                                <?php if($ASN->getStatus() == 6 && !$user) { ?>
                                    <form method="post" class="form-inline" action="/goods_in/resolve_non_compliant/">
                                        <input type="hidden" name="id" value="<?= $ASN->getId(); ?>">
                                        <button class="btn btn-primary btn-icon-split" type="submit">
                                            <span class="text text-white">Non-compliant resolved</span>
                                            <span class="icon text-white">
                                                    <i class="fas fa-fw fa-check"></i>
                                                </span>
                                        </button>
                                    </form>
                                <?php } ?>
                                <?= !empty($ASN->getCarrier()) && !$user ? ('<br/><b>Carrier: ' . View::toHtml($ASN->getCarrier()) . '</b>') : ''; ?>
                                <?= !empty($ASN->getTrackingNumber()) && !$user ? ('<br/><b>Tracking Number: ' . View::toHtml($ASN->getTrackingNumber()) . '</b>') : ''; ?>
                            </p>
                            <?php if (count($ASN->getServices()) && !$user) { ?>
                                <p class="form-control-static text-danger"><b>The customer has requested the following service(s) to be undertaken if this ASN is non-compliant:</b><br/>
                                <span class="text-xs"><?php
                                    $i = 1;
                                    foreach ($ASN->getServices() as $key => $item)
                                        echo ($i++) . ". " . View::toHtml(is_integer($item) ? Services::getService($key) : $item) . "<br/>";
                                ?>
                                </span></p>
                            <?php } ?>
                            <p class="form-control-static">Expected SKUs:</p>
                        </div>
                    </div>
                    <form method="post" action="/goods_in/edit_sku">
                        <input type="hidden" name="id" value="<?= $ASN->getId(); ?>">
                        <div class="row">
                            <table class="table table-hover">
                                <thead>
                                <colgroup>
                                    <col width="7%">
                                    <col width="12%">
                                    <col width="*">
                                    <col width="12%">
                                    <col width="12%">
                                    <?php if (!$authUser->isClientSide() && !$warehouse_virtual) { ?>
                                        <col width="12%">
                                        <col width="12%">
                                    <?php } ?>
                                    <?php if ($ASN->nonCompliant()) { ?>
                                        <col width="*">
                                    <?php } ?>
                                </colgroup>
                                <tr>
                                    <th>ID</th>
                                    <th>Barcode</th>
                                    <th>Product Name</th>
                                    <th>Batch</th>
                                    <th>Expected quantity</th>
                                    <?php if (!$authUser->isClientSide() && !$warehouse_virtual) { ?>
                                        <th>Location</th>
                                        <th>Measurements</th>
                                    <?php } ?>
                                    <?php if ($ASN->nonCompliant()) { ?>
                                        <th>Non-Compliant Notes</th>
                                    <?php } ?>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($SKUs as $SKU) { ?>
                                    <tr>
                                        <td>
                                            <a class="link" href="/SKUs/<?= $warehouse_virtual ? 'view_sku' : 'edit_sku' ?>/?id=<?= View::toHtml($SKU['id']); ?>&back=/goods_in/view_asn^id=<?= $ASN->getId(); ?>">
                                                <?= View::toHtml($SKU['id']); ?>
                                            </a>
                                        </td>
                                        <td><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']) ?></td>
                                        <td><?= View::toHtml($SKU['name']) ?></td>
                                        <td><?= empty($SKU['batch_number']) ? '-' : '<b>Batch#:</b> ' . View::toHtml($SKU['batch_number']); ?></td>
                                        <td><?php if (!$ASN->new()) {
                                            echo View::toHtml($SKU['quantity']);
                                        } else { ?>
                                            <input name="quantity[<?= $SKU['id'] ?>]" type="text" class="form-control" value="<?= View::toHtml($SKU['quantity']) ?>">
                                        <?php } ?></td>
                                        <?php if (!$authUser->isClientSide() && !$warehouse_virtual) { ?>
                                            <td>
                                                <?php if (SKULocations::checkAssigned($warehouse_id, [$SKU['id']])) { ?>
                                                    <span class="badge badge-success">Assigned</span>
                                                <?php } else { ?>
                                                    <div class="alert alert-danger">
                                                        Please assign location(s) for this SKU
                                                        <a class="text-white" style="text-decoration: underline" href="/housekeeping/sku_locations/?id=<?= $SKU['id'] ?>&back=/goods_in/view_asn/?id=<?= $ASN->getId()?>">here</a>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <?php if(SKUs::checkMeasuresConfigured($warehouse_id, [$SKU['id']])) { ?>
                                                    <span class="badge badge-success">Configured</span>
                                                <?php } else { ?>
                                                    <div class="alert alert-danger">
                                                        Please configure measurements for this SKU
                                                        <a class="text-white" style="text-decoration: underline" href="/SKUs/measure/?id=<?= $SKU['id'] ?>&back=/goods_in/view_asn/?id=<?= $ASN->getId()?>">here</a>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                        <?php } ?>
                                        <?php if ($ASN->nonCompliant()) { ?>
                                            <td><?= View::toHtmlHistory($lastDo[$SKU['id']] ?? '') ?></td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                                <?php foreach ($lastSKUs as $SKU) { ?>
                                    <tr class="table-warning">
                                        <td>
                                            <a class="link" href="/SKUs/edit_sku/?id=<?= View::toHtml($SKU['id']); ?>&back=/goods_in/view_asn^id=<?= $ASN->getId(); ?>">
                                                <?= View::toHtml($SKU['id']); ?>
                                            </a>
                                        </td>
                                        <?php if (!$authUser->isClientSide()) { ?>
                                            <td>
                                                <a class="link" href="/housekeeping/inventory/?s=<?= View::toHtml($SKU['barcode']); ?>">
                                                    <?= View::toHtml($SKU['barcode']); ?>
                                                </a>
                                            </td>
                                        <?php } else { ?>
                                            <td><?= View::toHtml($SKU['barcode']); ?></td>
                                        <?php } ?>
                                        <td><?= View::toHtml($SKU['name']); ?></td>
                                        <td><?= empty($SKU['batch_number']) ? '-' : Batch::toBatchInfo($SKU['batch_number'], $SKU['expired'], true, true); ?></td>
                                        <td>0</td>
                                        <?php if (!$authUser->isClientSide() && !$warehouse_virtual) { ?>
                                            <td><span class="badge badge-warning">Unexpected item</span></td>
                                            <td><span class="badge badge-warning">Unexpected item</span></td>
                                        <?php } ?>
                                        <?php if ($ASN->nonCompliant()) { ?>
                                            <td><?= View::toHtmlHistory($lastDo[$SKU['id']] ?? '') ?></td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($ASN->new()) { ?>
                            <div class="row justify-content-end">
                                <div class="col-auto">
                                    <button class="btn btn-primary btn-icon-split" type="submit">
                                        <span class="text text-white" style="width: 150px;">Update</span>
                                        <span class="icon text-white">
                                            <i class="fas fa-fw fa-chevron-up"></i>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        <?php } ?>
                    </form>
                    <?php if ($ASN->new() && $user) { ?>
                        <form method="post" action="/goods_in/update_ref">
                            <input type="hidden" name="id" value="<?= $ASN->getId(); ?>">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="arrival_date">ASN Reference:</label>
                                        <input type="text" name="ref" id="ref" class="form-control" value="<?= View::toHtml($ASN->getRef()); ?>"<?= $warehouse_virtual ? ' readonly' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="arrival_date">Carrier:</label>
                                        <input type="text" name="carrier" id="carrier" class="form-control" value="<?= View::toHtml($ASN->getCarrier()); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="arrival_date">Tracking Number:</label>
                                        <input type="text" name="tracking_number" id="tracking_number" class="form-control" value="<?= View::toHtml($ASN->getTrackingNumber()); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row justify-content-end">
                                <div class="col-auto">
                                    <button class="btn btn-primary btn-icon-split" type="submit">
                                        <span class="text text-white" style="width: 150px;">Update</span>
                                        <span class="icon text-white">
                                                <i class="fas fa-fw fa-chevron-up"></i>
                                            </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php } ?>
                    <form method="post" action="/goods_in/add_comment">
                        <input type="hidden" name="id" value="<?= $ASN->getId(); ?>">
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <p class="form-control-static text-success"><b>Comments</b></p>
                                <?php if (!$ASN->completed()) { ?>
                                    <label for="comment">Add Comment:</label>
                                    <textarea
                                            class="form-control"
                                            id="comment"
                                            name="comment"
                                            rows="2"
                                            onchange="addComment(event)"
                                    ></textarea>
                                    <script>
                                        function addComment(el) {
                                            const btn = document.getElementById('add_comment_btn');
                                            if (btn)
                                                btn.disabled = !(el.target.value.trim().length > 0)
                                        }
                                    </script>
                                <?php } ?>
                            </div>
                        </div>
                        <?php if (!$ASN->completed()) { ?>
                            <div class="row justify-content-end">
                                <div class="col-auto">
                                    <button class="btn btn-primary btn-icon-split"
                                            type="submit"
                                            id="add_comment_btn"
                                            disabled
                                    >
                                        <span class="text text-white" style="width: 150px;">Add Comment</span>
                                        <span class="icon text-white">
                                            <i class="fas fa-fw fa-comment"></i>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        <?php } ?>
                    </form>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <?php
                                foreach ($comments as $comment) {
//                                    echo "{$comment['comment']}";
//                                        echo " <b>by {$comment['login']}" . ($comment['name'] !== '' ? $comment['name'] : "") . "</b>";
//                                    echo "<br/><b>" . date('jS F Y @ H:i:s', WithDate::dateToInt($comment['create_date'])) . "</b><hr/>";
                            ?>
                                    <div class="row alert p-1 mt-2 mb-0" style="background-color: #f8f8f8">
                                        <div class="col-auto p-0">
                                            <img class="img-thumbnail" style="max-width: 35px" src="<?=
                                            ($comment['status'] == 1 ? COMMON_IMG . DIRECTORY_SEPARATOR .'system.png' :
                                                (empty($comment['supplier_id']) ? COMMON_IMG . DIRECTORY_SEPARATOR .'system_logo.png' :
                                                    (empty($comment['logo']) ? COMMON_IMG . DIRECTORY_SEPARATOR .'client.png' :
                                                        Suppliers::showLogoSrc($comment['logo'])))); ?>"/>
                                        </div>

                                        <div class="col">
                                            <div class="row justify-content-between mb-2">
                                                <div class="col font-weight-bold"><?= View::toHtml($comment['login'] . ($comment['name'] !== '' ? $comment['name'] : "")); ?></div>
                                                <div class="col-auto text-xs"><?= date('jS F Y @ H:i:s', WithDate::dateToInt($comment['create_date'])); ?></div>
                                            </div>
                                            <div class="row"><div class="col"><?= View::toHtmlHistory($comment['comment']); ?></div></div>
                                        </div>
                                    </div>

                                    <?php
                                }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
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
<script>
    $('#charge_update').click(() => {
        var pallet = parseFloat($('#charge_pallet').val().trim());
        var rework = parseFloat($('#charge_rework').val().trim());
        var desc_pallet = $('#description_pallet').val().trim();
        var desc_rework = $('#description_rework').val().trim();
        if (isNaN(pallet) || isNaN(rework) || pallet < 0 || rework < 0) {
            showError('charge_err', 'Incorrect price', 3000);
            return false;
        }
        if ((desc_pallet === '' && pallet != 0) || (desc_rework === '' && rework != 0)) {
            showError('charge_err', 'Empty description', 3000);
            return false;
        }
        $.ajax({
            url: "/goods_in/charge",
            method: "POST",
            data: { ASN_id: <?= $ASN->getId(); ?>, desc_pallet: desc_pallet, pallet: pallet,
                                                   desc_rework: desc_rework, rework: rework },
            success: function (json) {
                showError('charge_ok', 'Updated', 3000);
            },
            error: function (json) {
                showError('charge_err', 'Database error', 3000);
            }
        });
    });
</script>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'ASN_in_reality.php'; ?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'ASN_webhook.php'; ?>
