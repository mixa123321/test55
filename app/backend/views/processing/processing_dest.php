<?php

use app\backend\models\Cart;
use app\backend\models\Processings;
use app\core\entities\Location;
use app\core\entities\SKU;
use app\core\helpers\Arr;
use app\core\helpers\billet\MovementBillet;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\helpers\Html;

/**
 * @var array $processings
 * @var SKU $SKU
 * @var string $SKU_barcode
 * @var string $warehouse_label
 * @var Location $loc_from
 * @var float $weight
 * @var string $back
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Processing movement (<?= View::toHtml($SKU->getName()); ?> <b><?= View::toHtmlConvertWithPrimaryBarcode($SKU_barcode); ?></b>)</h1>
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
                    <input type="hidden" name="SKU_id" value="<?= $SKU->getId(); ?>"/>
                    <input type="hidden" name="loc_id" value="<?= $loc_from->getId(); ?>"/>
                    <input type="hidden" id="qty" name="quantity"/>
                    <input type="hidden" id="id_to" name="loc_to_id"/>
                    <div class="row justify-content-center mb-3">
                        <div class="col-8">
                            <div class="row p-0">
                                <div class="col input-group m-0 p-0">
                                    <?php (new Scan([
                                        'id' => 'search',
                                        'placeholder' => 'Destination location',
                                        'type' => Scan::TYPE__LOCATION,
                                    ], ['id' => 'btn_search']))->render(); ?>
                                </div>
                                <div class="col-12 p-2 px-3 bg-danger text-white d-none" id="err_msg"></div>
                            </div>
                        </div>
                    </div>
                </form>
                <div class="row justify-content-center">
                    <?php foreach ($processings as $processing)
                        echo (new MovementBillet($processing, $loc_from->getId(), false, true, true))
                            ->setColor(Processings::colorMovement($processing))
                            ->put();
                    ?>
                </div>
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
    var locs_to = <?= View::toJs(Arr::maps($processings, 'lt_name', ['loc_to_id', 'quantity'])) ?>;

    $('#btn_search').click(() => {
        var search = $('#search').val().trim();
        if (search !== '') {
            if (search in locs_to) {
                $('#frm_movement').attr('action', '/processing/processing_get_B');
                $('#id_to').val(locs_to[search].loc_to_id);
                $('#qty').val(locs_to[search].quantity);
                return true;
            }
        }
        $('#search').val('');
        $('#search').focus();
        showError('err_msg', 'Scan destination location', 3000);
        $('#barcode').focus();
        return false;
    });
</script>
