<?php

use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\entities\User;

/**
 * @var User $authUser
 * @var int $ASN_id
 * @var array $SKUs
 * @var array $addSKUs
 * @var string $back
 * @var string $page
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Complete ASN</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-fw fa-sign-in-alt"></i> ASN #<?= $ASN_id; ?>
            </h6>
        </div>
        <form method="post" action="/goods_in/completed" name="frmCreate">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-1">
                        <ul class="nav nav-pills nav-stacked">
                        </ul>
                    </div>
                    <div class="col-md-10">
                        <input type="hidden" name="id" value="<?= $ASN_id; ?>">
                        <input type="hidden" name="back" value="<?= View::toHtml($back); ?>">
                        <?php if (!empty($page)) { ?>
                            <input type="hidden" name="page" value="<?= View::toHtml($page); ?>">
                        <?php } ?>
                        <p class="form-control-static text-success mb-1"><b>Expected SKUs</b>:</p>
                        <div class="row">
                            <?php $nums = 0;
                            foreach ($SKUs as $SKU) {
                                if ($SKU['quantity']) {
                                    $nums++; ?>
                                <input type="hidden" name="SKUs[<?= View::toHtml($SKU['id']); ?>][quantity]" value="<?= View::toHtml($SKU['quantity']); ?>" />
                                <input type="hidden" name="SKUs[<?= View::toHtml($SKU['id']); ?>][qty]" value="<?= View::toHtml($SKU['qty']); ?>" />
                                <div class="col-md-12 text-center border-bottom mt-1 border-dark">
                                    <?= '<b>' . View::toHtml($SKU['name']) . '</b> (#' . View::toHtml($SKU['id']) . ')'; ?><br/>
                                    <b><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></b>
                                    (<?= View::toHtml($SKU['quantity']); ?> / <span class="text-<?= $SKU['qty'] < $SKU['quantity'] ? 'danger' : 'success'; ?>"><b><?= $SKU['qty']; ?></b></span>)
                                </div>
                            <?php }
                            } ?>
                        </div>
                        <?php if (count($addSKUs) || $nums < count($SKUs)) { ?>
                            <p class="form-control-static text-danger mt-3 mb-1"><b>Unexpected existing SKUs</b>:</p>
                            <div class="row">
                                <?php foreach ($SKUs as $SKU) {
                                    if (!$SKU['quantity']) { ?>
                                        <input type="hidden" name="SKUs[<?= View::toHtml($SKU['id']); ?>][quantity]" value="<?= View::toHtml($SKU['quantity']); ?>" />
                                        <input type="hidden" name="SKUs[<?= View::toHtml($SKU['id']); ?>][qty]" value="<?= View::toHtml($SKU['qty']); ?>" />
                                        <div class="col-md-12 text-center border-bottom mt-1 border-dark">
                                            <?= '<b>' . View::toHtml($SKU['name']) . '</b> (#' . View::toHtml($SKU['id']) . ')'; ?><br/>
                                            <b><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></b>
                                            (<span class="text-<?= $SKU['quantity'] < 0 ? 'danger' : 'success'; ?>"><b><?= $SKU['qty']; ?></b></span>)
                                        </div>
                                    <?php }
                                } ?>
                            </div>
                            <p class="form-control-static text-danger mt-3 mb-1"><b>Unexpected SKUs</b>:</p>
                            <div class="row">
                                <?php foreach ($addSKUs as $SKU) { ?>
                                    <input type="hidden" name="addSKUs[<?= View::toHtml($SKU['id']); ?>][qty]" value="<?= View::toHtml($SKU['quantity']); ?>" />
                                    <input type="hidden" name="addSKUs[<?= View::toHtml($SKU['id']); ?>][loc_id]" value="<?= View::toHtml($SKU['location_id']); ?>" />
                                    <div class="col-md-12 text-center border-bottom mt-1 border-dark">
                                        <?= '<b>' . View::toHtml($SKU['name']) . '</b> (#' . View::toHtml($SKU['id']) . ')'; ?><br/>
                                        <b><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></b>
                                        (<span class="text-success"><b><?= $SKU['quantity']; ?></b></span>)
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-secondary btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl("$back?$page")); ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-chevron-left"></i>
                        </span>
                        <span class="text">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-success btn-icon-split" type="submit">
                            <span class="text text-white">Complete</span>
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-power-off"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
