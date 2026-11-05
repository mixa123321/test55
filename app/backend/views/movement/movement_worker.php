<?php

use app\backend\models\Movements;
use app\core\entities\Location;
use app\core\entities\Movement;
use app\core\entities\User;
use app\core\helpers\actions\Actions;
use app\core\helpers\Arr;
use app\core\helpers\billet\MovementBillet;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\Filter;

/**
 * @var User $authUser
 * @var array $movements
 * @var array $comment_filters
 * @var Location $cart
 * @var float $weight
 * @var string $warehouse_label
 * @var string $back
 * @var array $filters
 */

$actions = new Actions('movements');
$actions
    ->addButton(Actions::ANCHOR, 'Create movement', 'plus', '/movement/do_movement')
    ->addButton(Actions::ANCHOR, 'Requests', 'question', '/movement/requests')
    ->addDivider()
    ->addButton(Actions::ANCHOR, 'Change trolley', 'shopping-cart', '/cart/movement/?back=' . ($back ?? '/movement'));

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Movement</h1>
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
                <?= $actions->get(); ?>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="form-row justify-content-center">
                    <div class="form-group col col-md-8">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'order_by',
                                'onchange' => 'submit()',
                            ];
                            echo Html::dropDownList('filter[order_by]', Movements::$order_by, (string)$filters['order_by'], $options);
                            ?>
                            <label for="order_by">Order by</label>
                        </div>
                        <div class="form-group-element">
                            <?php
                            $options_comments = [
                                'class' => 'form-control form-control-lg custom-select mt-1',
                                'id' => 'comment_filters',
                                'onchange' => 'submit()',
                                'data-empty-value' => Filter::NOT_SELECTED,
                            ];
                            echo Html::multiSelect('filter[comment_filters][]', 
                                $comment_filters,
                                $filters['comment_filters'] ?? [],
                                $options_comments
                            );
                            ?>
                            <label for="comment_filters">Comment</label>
                        </div>
                    </div>
                </div>
            </form>
            <?php if (!empty($movements)) { ?>
                <form method="post" id="frm_movement">
                    <input type="hidden" id="fixed" value="0"/>
                    <input type="hidden" id="id" name="id"/>
                    <input type="hidden" id="cart_id" name="cart_id" value="<?= $cart->getId(); ?>"/>
                    <input type="hidden" id="SKU_id" name="SKU_id"/>
                    <input type="hidden" id="loc_id" name="loc_id"/>
                    <input type="hidden" id="loc_to_id" name="loc_to_id"/>
                    <input type="hidden" id="supplier_id" name="supplier_id"/>
                    <div class="row justify-content-center mb-4">
                        <div class="col-md-7 col input-group pr-1">
                            <?php (new Scan([
                                'id' => 'search',
                                'placeholder' => 'Origin location or SKU barcode',
                                'type' => Scan::TYPE__SKU | Scan::TYPE__LOCATION,
                            ], ['class' => 'js-btn-scan', 'data' => [
                                'send' => 'frm_movement',
                                'empty' => 'false',
                                'suppliers' => json_encode(array_values(array_unique(Arr::column($movements, 'supplier_id')))),
                            ]]))->render(); ?>
                        </div>
                        <div class="input-group-append">
                            <div class="col-auto align-self-center px-0 mr-3">
                                <button class="btn btn-success btn-icon-split" onclick="smartScan();" type="button">
                                    <span class="icon">
                                        <i class="fas fa-fw fa-angle-double-left"></i>
                                    </span>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-8 col-12 p-2 mt-2 mx-4 bg-danger text-white d-none" id="err_msg"></div>
                    </div>
                    <div class="row justify-content-center">
                        <?php foreach ($movements as $movement)
                            echo (new MovementBillet($movement, $cart->getId()))
                                ->setColor(Movements::colorMovement($movement))
                                ->put();
                        ?>
                    </div>
                </form>
            <?php } else { echo Html::warning("There are no movements waiting to be executed"); }?>
        </div>
    </div>
</div>
<script>
    <?php
        $res = [];
        foreach ($movements as $key => $movement) {
            if (Movement::isInProgress($movement['status']))
                continue;
            $res[$key] = $movement;
            $res[$key]['search'] = $movement['barcode'] . '_' . $movement['supplier_id'];
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
<?= View::include_js(BACKEND_JS, 'movement_worker.js')?>
<?= View::include_js(BACKEND_JS, 'multi_select.js')?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_supplier.php'; ?>
