<?php

use app\backend\models\Movements;
use app\core\entities\Location;
use app\core\entities\SKU;
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
 * @var SKU $SKU
 * @var string $SKU_barcode
 * @var Location $loc_from
 * @var float $weight
 * @var string $back
 * @var string $warehouse_label
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Movement (<?= View::toHtml($SKU->getName()); ?> <b><?= View::toHtml($SKU_barcode); ?></b>)</h1>
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
                            'cart' => $loc_from,
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
                    <input type="hidden" name="SKU_id" value="<?= $SKU->getId(); ?>"/>
                    <input type="hidden" name="loc_id" value="<?= $loc_from->getId(); ?>"/>
                    <input type="hidden" id="qty" name="quantity"/>
                    <input type="hidden" id="id_to" name="loc_to_id"/>
                    <div class="row justify-content-center mb-4">
                        <div class="col-md-8 col-12 input-group">
                            <?php (new Scan([
                                'id' => 'search',
                                'placeholder' => 'Destination location',
                                'type' => Scan::TYPE__LOCATION,
                            ], ['id' => 'btn_search']))->render(); ?>
                        </div>
                        <div class="col-md-8 col-12 p-2 mt-2 mx-4 bg-danger text-white d-none" id="err_msg"></div>
                    </div>
                </form>
                <div class="row justify-content-center">
                    <?php foreach ($movements as $movement)
                        echo (new MovementBillet($movement, $loc_from->getId(), false, true, true))
                            ->setColor(Movements::colorMovement($movement))
                            ->put();
                    ?>
                </div>
            <?php } else { echo Html::warning("There are no movements waiting to be executed"); }?>
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
    var locs_to = <?= View::toJs(Arr::maps($movements, 'lt_name', ['loc_to_id', 'quantity'])) ?>;
    var locs_to_alias = <?= View::toJs(Arr::maps($movements, 'lt_alias', ['loc_to_id', 'quantity'])) ?>;

    $('#btn_search').click(() => {
        var search = $('#search').val().trim();
        if (search !== '') {
            if (search in locs_to) {
                $('#frm_movement').attr('action', '/movement/movement_get_B');
                $('#id_to').val(locs_to[search].loc_to_id);
                $('#qty').val(locs_to[search].quantity);
                return true;
            }
            if (search in locs_to_alias) {
                $('#frm_movement').attr('action', '/movement/movement_get_B');
                $('#id_to').val(locs_to_alias[search].loc_to_id);
                $('#qty').val(locs_to_alias[search].quantity);
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
