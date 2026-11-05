<?php

use app\core\entities\User;
use app\core\helpers\billet\PickingBillet;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var string $mode
 * @var array $SKUs
 * @var int $cart_id
 * @var int $supplier_id
 * @var bool $is_mezzanine
 * @var string $back
 */

$SKU = $SKUs[0];

?>

<div class="alert alert-danger d-none" style="position: relative" id="err_msg"></div>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div class="row">
            <div class="col-12 h2 mb-0 text-gray-800">Picking location for <?= View::toHtml($SKU['barcode']); ?></div>
            <?php if (!empty($SKU['batch_id'])) { ?>
                <div class="col-12 h5 mb-0 text-gray-800">(<?= str_replace("<br/>",'; ', implode('', $SKU['batches'])); ?>)</div>
            <?php } ?>
        </div>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        Locations list (<?= count($SKUs); ?> item<?= count($SKUs) > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body p-3">
            <div class="row justify-content-end d-md-none">
                <div class="col-auto mb-1">
                    <button class="btn btn-primary btn-icon-split text-nowrap mr-0" data-toggle='collapse' data-target="#filter_show" onclick="frmPick.search.focus();">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-filter"></i>
                        </span>
                    </button>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-md-10 mb-0">
                    <form method="post" action="/pick/picking_loc" name="frmPick">
                        <input type="hidden" name="mode" value="<?= View::toHtml($mode); ?>"/>
                        <input type="hidden" id="type" name="type" value="loc"/>
                        <input type="hidden" name="SKU_id" value="<?= $SKU['id']; ?>"/>
                        <input type="hidden" name="batch_id" value="<?= empty($SKU['batch_id']) ? '' : $SKU['batch_id']; ?>"/>
                        <input type="hidden" name="cart_id" value="<?= $cart_id; ?>"/>
                        <input type="hidden" name="back" value="<?= View::toHtml($back); ?>"/>
                        <?php (new Scan([
                            'id' => 'search',
                            'name' => 'search',
                            'placeholder' => 'Location',
                            'style' => 'min-width: 150px;',
                            'type' => Scan::TYPE__LOCATION,
                        ], [
                            'title' => 'Location',
                            'icon' => 'fas fa-fw fa-box-open',
                        ]))->render(); ?>
                    </form>
                </div>
            </div>
            <div class="row justify-content-center p-3">
                <?php foreach ($SKUs as $SKU)
                    echo (new PickingBillet($SKU, true, true))->put();
                ?>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/pick/picking_<?= View::toHtml(substr($mode,0,1) . "/?$back"); ?>">
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
<?= View::include_js(BACKEND_JS, "check_picking.js")?>
