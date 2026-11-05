<?php

use app\backend\models\Cart;
use app\backend\models\Processings;
use app\core\entities\Location;
use app\core\entities\Movement;
use app\core\helpers\Arr;
use app\core\helpers\billet\MovementBillet;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

/**
 * @var array $processings
 * @var Location $cart
 * @var float $weight
 * @var string $warehouse_label
 * @var string $back
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Processing Movement</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary align-content-center">
                        <span>
                            <i class="fas fa-fw fa-arrows-alt"></i>
                            Movements list (<?= count($processings); ?> item<?= count($processings) > 1 ? 's' : '' ?>).
                            My trolley (<?= $cart->US() ? 'virtual' : $cart->getLocation($warehouse_label); ?>) <span class="text-<?= $weight < Cart::CART_WEIGHT ? 'success' : 'danger'; ?>"><?= sprintf("%.3f",$weight); ?></span> Kg
                        </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/cart/movement/?back=<?= View::toHtml($back ?? '/processing') ?>">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-shopping-cart"></i>
                        </span>
                        <span class="text d-none d-md-block">Change trolley</span>
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($processings)) { ?>
                <form method="post" id="frm_movement">
                    <input type="hidden" id="fixed" value="0"/>
                    <input type="hidden" id="id" name="id"/>
                    <input type="hidden" id="cart_id" name="cart_id" value="<?= $cart->getId(); ?>"/>
                    <input type="hidden" id="SKU_id" name="SKU_id"/>
                    <input type="hidden" id="loc_id" name="loc_id"/>
                    <input type="hidden" id="loc_to_id" name="loc_to_id"/>
                    <input type="hidden" id="supplier_id" name="supplier_id"/>
                    <div class="row justify-content-center mb-3">
                        <div class="col-8">
                            <div class="row p-0">
                                <div class="col input-group m-0 p-0 pr-2">
                                    <?php (new Scan([
                                        'id' => 'search',
                                        'placeholder' => 'Origin location or SKU barcode',
                                        'type' => Scan::TYPE__SKU | Scan::TYPE__LOCATION,
                                    ], ['class' => 'js-btn-scan', 'data' => [
                                        'send' => 'frm_movement',
                                        'empty' => 'false',
                                        'suppliers' => json_encode(array_values(array_unique(Arr::column($processings, 'supplier_id')))),
                                    ]]))->render(); ?>
                                </div>
                                <div class="col-auto align-self-center p-0">
                                    <div class="input-group-append">
                                        <button class="btn btn-success btn-icon-split" onclick="smartScan();" type="button">
                                            <span class="icon">
                                                <i class="fas fa-fw fa-angle-double-left"></i>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-12 bg-danger text-white p-2 px-3 d-none" id="err_msg"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row justify-content-center">
                        <?php foreach ($processings as $processing)
                            echo (new MovementBillet($processing, $cart->getId()))
                                ->setColor(Processings::colorMovement($processing))
                                ->put();
                        ?>
                    </div>
                </form>
            <?php } else
                echo Html::warning("There are no movements waiting to be executed");
            ?>
        </div>
    </div>
</div>
<script>
    <?php
        $res = [];
        foreach ($processings as $key => $processing) {
            if (Movement::isInProgress($processing['status']))
                continue;
            $res[$key] = $processing;
            $res[$key]['search'] = $processing['barcode'] . '_' . $processing['supplier_id'];
        }
    ?>
    var locs_from = <?= View::toJs(Arr::maps($res, 'lf_name', ['loc_from_id'])) ?>;
    var locs_from_alias = <?= View::toJs(Arr::maps($res, 'lf_alias', ['loc_from_id'])) ?>;
    var SKUs = <?= View::toJs(Arr::maps($res, 'search', ['SKU_id', 'barcodes', 'supplier_id'])) ?>;
    var cart_id = <?= $cart->getId(); ?>;

   function smartScan()
   {
       $('#search').val(<?= View::toJs((string)$cart->getLocationReal($warehouse_label)); ?>);
       $('.js-btn-scan').click();
   }
</script>
<?= View::include_js(BACKEND_JS, 'processing_worker.js')?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_supplier.php'; ?>
