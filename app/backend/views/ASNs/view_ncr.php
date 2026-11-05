<?php

use app\backend\models\SKULocations;
use app\core\entities\ASN;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\statical\WithDate;
use app\core\statical\Services;
use app\backend\models\Suppliers;
use app\core\models\SKUs;

/**
 * @var User $authUser
 * @var ASN $NCR
 * @var array $containers
 * @var string $order_ref
 * @var array $SKUs
 * @var array $comments
 * @var string $back
 * @var int $warehouse_id
 * @var bool $warehouse_virtual
 * @var array $lastSKUs
 * @var array $lastDo
 */

$user = $authUser->isRoleSupplierUser();

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">View Manifest</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary col">
                <i class="fas fa-fw fa-sign-in-alt"></i><?= !empty($NCR->getOrderId()) ? ' Return ' : '';?> ASN #<?= $NCR->getId() . ' / ' . View::toHtml($NCR->getRef() ?: '-'); ?><?= !empty($NCR->getOrderId()) ? " (Order reference #{$NCR->getOrderId()} / " . View::toHtml($order_ref) . ")" : '';?> <span class="badge badge-primary">Non-compliant</span>
            </h6>
            <?php if (!$authUser->isClientSide() && $warehouse_virtual) { ?>
                <input type="hidden" id="ASN_id" value="<?= $NCR->getId(); ?>">
                <div class="col-auto pr-0">
                    <button class="btn btn-success btn-icon-split js-in-reality">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-warehouse"></i>
                        </span>
                        <span class="text">In reality</span>
                    </button>
                </div>
            <?php } ?>
            <div class="col-auto justify-content-end">
                <a class="btn btn-success btn-icon-split" href='/goods_in/manifest/?id=<?= $NCR->getId(); ?>' target="_blank">
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
                        <div class="col-md-12">
                            <?php if ($warehouse_virtual) { ?>
                                <p class="mb-1"><span class="text-success"><b>Warehouse: </b></span><?= View::toHtml($NCR->getShipToLocation()); ?></p>
                            <?php } ?>
                            <?php if (!empty($NCR->getComment())) { ?>
                                <p><span class="text-success"><b>Comment: </b></span><?= View::toHtml($NCR->getComment()); ?></p>
                            <?php } ?>
                            <?php if($NCR->getStatus() == 6 && !$user) { ?>
                                <p class="form-control-static">
                                    <form method="post" class="form-inline" action="/goods_in/resolve_non_compliant/">
                                        <input type="hidden" name="id" value="<?= $NCR->getId(); ?>">
                                        <input type="hidden" name="back" value="ncrs">
                                        <button class="btn btn-primary btn-icon-split" type="submit">
                                            <span class="text text-white">Non-compliant resolved</span>
                                            <span class="icon text-white">
                                                    <i class="fas fa-fw fa-check"></i>
                                                </span>
                                        </button>
                                    </form>
                                </p>
                            <?php } ?>
                            <p class="form-control-static text-success"><b>Expected SKUs:</b></p>
                        </div>
                    </div>
                    <form method="post" action="/goods_in/edit_sku">
                        <input type="hidden" name="id" value="<?= $NCR->getId(); ?>">
                        <div class="row">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Barcode</th>
                                    <th>Product Name</th>
                                    <th>Batch No.</th>
                                    <th>Expired</th>
                                    <th>Expected quantity</th>
                                    <?php if (!$authUser->isClientSide() && !$warehouse_virtual) { ?>
                                        <th>Location</th>
                                        <th>Measurements</th>
                                    <?php } ?>
                                    <?php if ($NCR->nonCompliant()) { ?>
                                        <th>Non-Compliant Notes</th>
                                    <?php } ?>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                foreach ($SKUs as $SKU) { ?>
                                    <tr>
                                        <td>
                                            <a class="btn-link" href="/SKUs/<?= $warehouse_virtual ? 'view_sku' : 'edit_sku' ?>/?id=<?= View::toHtml($SKU['id']); ?>&back=/goods_in/view_ncr^id=<?= $NCR->getId(); ?>">
                                                <?= View::toHtml($SKU['id']); ?>
                                            </a>
                                        </td>
                                        <td><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']) ?></td>
                                        <td><?= View::toHtml($SKU['name']) ?></td>
                                        <td><?= View::toHtml($SKU['batch_number']) ?></td>
                                        <td><?= View::toHtml($SKU['expired']) ?></td>
                                        <td><?php if (!$NCR->new()) {
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
                                                        <a href="/housekeeping/sku_locations/?id=<?= $SKU['id'] ?>&back=/goods_in/view_ncr/?id=<?= $NCR->getId()?>">here</a>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <?php if(SKUs::checkMeasuresConfigured($warehouse_id, [$SKU['id']])) { ?>
                                                    <span class="badge badge-success">Configured</span>
                                                <?php } else { ?>
                                                    <div class="alert alert-danger">
                                                        Please configure measurements for this SKU
                                                        <a href="/SKUs/measure/?id=<?= $SKU['id'] ?>&back=/goods_in/view_ncr/?id=<?= $NCR->getId()?>">here</a>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                        <?php } ?>
                                        <?php if ($NCR->nonCompliant()) { ?>
                                            <td><?= View::toHtmlHistory($lastDo[$SKU['id']] ?? '') ?></td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                                <?php foreach ($lastSKUs as $SKU) { ?>
                                    <tr class="table-warning">
                                        <td>
                                            <a class="btn-link" href="/SKUs/edit_sku/?id=<?= View::toHtml($SKU['id']); ?>&back=/goods_in/view_ncr^id=<?= $NCR->getId(); ?>">
                                                <?= View::toHtml($SKU['id']); ?>
                                            </a>
                                        </td>
                                        <?php if (!$authUser->isClientSide()) { ?>
                                            <td>
                                                <a href="/housekeeping/inventory/?s=<?= View::toHtml($SKU['barcode']); ?>">
                                                    <?= View::toHtml($SKU['barcode']); ?>
                                                </a>
                                            </td>
                                        <?php } else { ?>
                                            <td><?= View::toHtml($SKU['barcode']); ?></td>
                                        <?php } ?>
                                        <td><?= View::toHtml($SKU['name']); ?></td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>0</td>
                                        <?php if (!$authUser->isClientSide()) { ?>
                                            <td><span class="badge badge-warning">Unexpected item</span></td>
                                            <td><span class="badge badge-warning">Unexpected item</span></td>
                                        <?php } ?>
                                        <?php if ($NCR->nonCompliant()) { ?>
                                            <td><?= View::toHtmlHistory($lastDo[$SKU['id']] ?? '') ?></td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($NCR->new()) { ?>
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
                    <?php if ($NCR->new() && $user) { ?>
                        <form method="post" action="/goods_in/update_ref">
                            <input type="hidden" name="id" value="<?= $NCR->getId(); ?>">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="arrival_date">ASN Reference:</label>
                                        <input type="text" name="ref" id="ref" class="form-control" value="<?= View::toHtml($NCR->getRef()); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="arrival_date">Carrier:</label>
                                        <input type="text" name="carrier" id="carrier" class="form-control" value="<?= View::toHtml($NCR->getCarrier()); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="arrival_date">Tracking Number:</label>
                                        <input type="text" name="tracking_number" id="tracking_number" class="form-control" value="<?= View::toHtml($NCR->getTrackingNumber()); ?>">
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
                        <input type="hidden" name="id" value="<?= $NCR->getId(); ?>">
                        <input type="hidden" name="back" value="ncr" />
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <p class="form-control-static text-success"><b>Comments</b></p>
                                <?php if (!$NCR->completed()) { ?>
                                    <label for="comment">Add Comment:</label>
                                    <textarea class="form-control"
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
                        <?php if (!$NCR->completed()) { ?>
                            <div class="row justify-content-end mb-2">
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
                            <?php foreach ($comments as $comment) { ?>
                                <div class="row alert p-1 mt-2 mb-0" style="background-color: #f8f8f8">
                                    <div class="col-auto p-0">
                                        <img class="img-thumbnail" style="max-width: 35px" src="<?=
                                            ($comment['status'] == 1 ? COMMON_IMG . DIRECTORY_SEPARATOR . 'system.png' :
                                                (empty($comment['supplier_id']) ? COMMON_IMG . DIRECTORY_SEPARATOR . 'system_logo.png' :
                                                    (empty($comment['logo']) ? COMMON_IMG . DIRECTORY_SEPARATOR . 'client.png' :
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
                            <?php } ?>
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
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'ASN_in_reality.php'; ?>
