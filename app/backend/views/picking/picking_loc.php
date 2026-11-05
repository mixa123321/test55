<?php

use app\core\entities\Location;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\billet\PickingBillet;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var string $mode
 * @var array $SKUs
 * @var int $cart_id
 * @var string $back
 * @var Location $location
 */

$take_pallet_modal = ConfirmModal::areYouSure('take_pallet', 'Take whole pallet');
echo $take_pallet_modal->put();

?>

<div class="alert alert-danger d-none" style="position: relative" id="err_msg"></div>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Picking SKU for <?= View::toHtml("{$SKUs[0]['warehouse']}-{$SKUs[0]['type_id']}-{$SKUs[0]['zone']}-{$SKUs[0]['column']}-{$SKUs[0]['row']}"); ?></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header p-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        SKUs list (<?= count($SKUs); ?> item<?= count($SKUs) > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
                <?php if ($location && $location->isMoveable()): ?>
                    <!-- Take whole pallet -->
                    <div class="col-auto">
                        <a class="btn btn-primary btn-icon-split" href="#" onclick="<?= $take_pallet_modal->show(); ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-box-open"></i>
                            </span>
                            <span class="text">Take whole pallet</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-3">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <form method="post" action="/pick/picking_bar" id="formPickSKU" class="p-0">
                        <input type="hidden" id="mode" name="mode" value="<?= View::toHtml($mode); ?>"/>
                        <input type="hidden" id="type" name="type" value="bar"/>
                        <input type="hidden" id="loc" name="loc" value="<?= $SKUs[0]['location_id']; ?>"/>
                        <input type="hidden" id="size" name="size" value="<?= count($SKUs); ?>"/>
                        <input type="hidden" id="cart_id" name="cart_id" value="<?= $cart_id; ?>"/>
                        <input type="hidden" id="batch_id" name="batch_id"/>
                        <input type="hidden" id="SKU_id" name="SKU_id"/>
                        <input type="hidden" name="back" value="<?= View::toHtml($back); ?>"/>
                        <?php (new Scan([
                            'id' => 'search',
                            'placeholder' => 'SKU',
                            'style' => 'min-width: 150px;',
                            'type' => Scan::TYPE__SKU,
                        ], [
                            'title' => 'SKU',
                            'icon' => 'fas fa-fw fa-box-open',
                            'class' => 'js-btn-scan',
                            'data' => [
                                'send' => 'formPickSKU',
                                'locations' => '["' . $SKUs[0]['location_id'] . '"]',
                                'suppliers' => json_encode(array_values(array_unique(Arr::column($SKUs, 'supplier_id')))),
                                'batches' => json_encode(array_values(array_unique(Arr::column($SKUs, 'batch_id')))),
                                'type' => '1',
                                'reason' => '1',
                            ],
                        ]))->render(); ?>
                    </form>
                </div>
            </div>
            <div class="row justify-content-center p-3">
                <?php foreach ($SKUs as $SKU)
                    echo (new PickingBillet($SKU, false))->put();
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
<script>
    $('<?= $take_pallet_modal->getSelector(); ?>').on('confirm', function() {
        window.location = <?= View::toJs('/pick/picking_loc/?loc_id=' . (int)$SKUs[0]['location_id'] . '&back=' . str_replace('&', '^', '/pick/picking_' . substr($mode,0,1) . "/?$back")); ?>
    });
</script>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_SKU_batch.php'; ?>
<?= View::include_js(BACKEND_JS, "check_picking.js")?>
