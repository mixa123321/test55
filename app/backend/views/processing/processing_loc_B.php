<?php

use app\backend\models\Cart;
use app\backend\models\Processings;
use app\core\entities\Location;
use app\core\entities\Movement;
use app\core\helpers\Arr;
use app\core\helpers\billet\MovementBillet;
use app\core\helpers\scan\Scan;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var array $processings
 * @var Location $loc_from
 * @var int $loc_to
 * @var float $weight
 * @var string $warehouse_label
 * @var string $back
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Processing movement</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-arrows-alt"></i>
                            Processing movements list (<?= count($processings); ?> item<?= count($processings) > 1 ? 's' : '' ?>).
                            My trolley (<?= $loc_from->US() ? 'virtual' : $loc_from->getLocation($warehouse_label); ?>) <span class="text-<?= $weight < Cart::CART_WEIGHT ? 'success' : 'danger'; ?>"><?= sprintf("%.3f",$weight); ?></span> Kg
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($processings)) { ?>
                <form method="post" id="frm_movement">
                    <input type="hidden" id="SKU_id" name="SKU_id"/>
                    <input type="hidden" name="loc_id" value="<?= $loc_from->getId(); ?>"/>
                    <input type="hidden" name="loc_to_id" value="<?= View::toHtml($loc_to); ?>"/>
                    <input type="hidden" id="supplier_id" name="supplier_id"/>
                    <input type="hidden" id="qty" name="quantity"/>
                    <div class="row justify-content-center mb-3">
                        <div class="col-8">
                            <div class="row p-0">
                                <div class="col input-group m-0 p-0">
                                    <?php (new Scan([
                                        'id' => 'search',
                                        'placeholder' => 'SKU barcode',
                                        'type' => Scan::TYPE__SKU,
                                    ], ['class' => 'js-btn-scan', 'data' => [
                                        'send' => 'frm_movement',
                                        'empty' => 'false',
                                        'suppliers' => json_encode(array_values(array_unique(Arr::column($processings, 'supplier_id')))),
                                    ]]))->render(); ?>
                                </div>
                                <div class="col-12 p-2 px-3 bg-danger text-white d-none" id="err_msg"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row justify-content-center">
                        <?php foreach ($processings as $processing)
                            echo (new MovementBillet($processing, $loc_from->getId(), false, false))
                                ->setColor(Processings::colorMovement($processing))
                                ->put();
                        ?>
                    </div>
                </form>
            <?php } else
                echo Html::warning("There are no movements waiting to be executed");
            ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl($back ?? '/processing')) ?>">
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
        foreach ($processings as $key => $processing) {
            if (Movement::isInProgress($processing['status']))
                continue;
            $res[$processing['barcode'] . '_' . $processing['supplier_id']] = ['supplier_id' => $processing['supplier_id'], 'SKU_id' => $processing['SKU_id'], 'quantity' => $processing['quantity'], 'barcodes' => $processing['barcodes'], 'barcode' => $processing['barcode']];
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
                    let key_sku = SKUs[barcode].barcode  + '_' + supplier_id;
                    $('#frm_movement').attr('action', '/processing/processing_get_B');
                    $('#SKU_id').val(SKUs[key_sku].SKU_id);
                    $('#qty').val(SKUs[key_sku].quantity);
                    return true;
                }
            }
        }
        $('#search').val('');
        $('#search').focus();
        showError('err_msg', 'SKU barcode is not correct', 3000);
        $('#barcode').focus();
        return false;
    });
</script>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_supplier.php'; ?>
