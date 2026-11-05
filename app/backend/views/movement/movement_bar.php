<?php

use app\backend\models\Movements;
use app\core\entities\Location;
use app\core\entities\Movement;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\billet\MovementBillet;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\helpers\Html;

/**
 * @var User $authUser
 * @var array $movements
 * @var Location $loc_from
 * @var Location $cart
 * @var float $weight
 * @var string $back
 * @var string $warehouse_label
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Movement (origin location <?= View::toHtml($loc_from->getLocation($warehouse_label)); ?>)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        Movements list (<?= count($movements); ?> item<?= count($movements) > 1 ? 's' : '' ?>).
                        <?= View::include('utility/cart_widget.php', [
                            'cart' => $cart,
                            'warehouse_label' => $warehouse_label,
                            'weight' => $weight,
                        ]); ?>
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($movements)) { ?>
                <form method="post" id="frm_movement">
                    <input type="hidden" id="id" name="SKU_id"/>
                    <input type="hidden" name="cart_id" value="<?= $cart->getId(); ?>"/>
                    <input type="hidden" name="loc_id" value="<?= $loc_from->getId(); ?>"/>
                    <input type="hidden" id="supplier_id" name="supplier_id"/>
                    <input type="hidden" id="id_to" name="loc_to_id"/>
                    <div class="row justify-content-center mb-4">
                        <div class="col-md-8 col-12 input-group">
                            <?php (new Scan([
                                'id' => 'search',
                                'name' => 'barcode',
                                'placeholder' => 'SKU barcode',
                                'type' => Scan::TYPE__SKU,
                            ], ['class' => 'js-btn-scan', 'data' => [
                                'send' => 'frm_movement',
                                'empty' => 'false',
                                'suppliers' => json_encode(array_values(array_unique(Arr::column($movements, 'supplier_id')))),
                            ]]))->render(); ?>
                        </div>
                        <div class="col-md-8 col-12 p-2 mt-2 mx-4 bg-danger text-white d-none" id="err_msg"></div>
                    </div>
                </form>
                <div class="row justify-content-center">
                    <?php foreach ($movements as $movement)
                        echo (new MovementBillet($movement, $cart->getId(), false))
                            ->setColor(Movements::colorMovement($movement))
                            ->put();
                    ?>
                </div>
            <?php } else echo Html::warning("There are no movements waiting to be executed"); ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl($back ?? '/movement')) ?>">
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
    <?php
        $res = [];
        foreach ($movements as $key => $movement) {
            if (Movement::isInProgress($movement['status']))
                continue;
            $res[$movement['barcode'] . '_' . $movement['supplier_id']] = ['supplier_id' => $movement['supplier_id'], 'SKU_id' => $movement['SKU_id'], 'loc_to_id' => $movement['loc_to_id'], 'barcodes' => $movement['barcodes'], 'barcode' => $movement['barcode']];
        }
    ?>
    var SKUs = <?= View::toJs($res) ?>;

    $('#frm_movement').submit(() => {
        var search = $('#search').val().trim();
        if (search !== '') {
            let supplier_id = $('#supplier_id').val();
            for (const barcode in SKUs) {
                let barcodes = SKUs[barcode].barcodes;
                if (SKUs[barcode].supplier_id != supplier_id)
                    continue;

                if (barcodes.includes(search) || search === SKUs[barcode].barcode) {
                    $('#frm_movement').attr('action', '/movement/movement_get');
                    let key_sku = SKUs[barcode].barcode  + '_' + supplier_id;
                    $('#id').val(SKUs[key_sku].SKU_id);
                    $('#id_to').val(SKUs[key_sku].loc_to_id);
                    return true;
                }
            }
        }
        $('#search').val('');
        $('#search').focus();
        showError('err_msg', 'Scan SKU barcode', 3000);
        $('#barcode').focus();
        return false;
    });
</script>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_supplier.php'; ?>
