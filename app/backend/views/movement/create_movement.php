<?php

use app\backend\models\Movements;
use app\core\entities\MovementRequest;
use app\core\helpers\Arr;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\helpers\modal\ConfirmModal;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\entities\Movement;

/**
 * @var User $authUser
 * @var int $quantity
 * @var string $mode
 * @var string $back
 * @var string $previous_movement
 */

if (empty($mode))
    $mode = Movements::CREATE_MOVEMENT;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Create <?= View::toHtml($mode); ?></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-expand-arrows-alt"></i>
                            SKU & Locations selection
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-md-10 col">
                    <div class="row">
                        <form class="col-12" method="post" id="createFrm">
                            <div class="alert alert-danger d-none" id="add_sku_error"></div>
                            <div class="input-group pl-0">
                                <input type="hidden" id="supplier_id" name="supplier_id"/>
                                <?php (new Scan(
                                    ['id' => 'search', 'placeholder' => 'Select SKU for movement', 'type' => Scan::TYPE__SKU],
                                    ['class' => 'js-btn-scan', 'title' => 'Select', 'data' => [
                                        'send' => 'createFrm',
                                        'empty' => 'false',
                                    ]]
                                ))->render(); ?>
                            </div>
                        </form>
                    </div>
                    <div class="row d-none" id="goods">
                        <div class="col pt-3">
                            <span class="text-success"><b>Goods</b></span>
                            <div class="row">
                                <div class="col-auto d-none" id="sku_is_photo">
                                    <img class="img-thumbnail" style="max-height: 100px;" id="sku_photo"
                                        data-scan-barcode=""
                                        data-scan-type="<?= Scan::TYPE__SKU; ?>"
                                    />
                                </div>
                                <div class="col">
                                    <p class="form-control-static mb-1"
                                        data-scan-barcode=""
                                        data-scan-type="<?= Scan::TYPE__SKU; ?>"
                                    >
                                        <span id="sku_name"></span><br/>
                                        <b class="scan-barcode"><span id="sku_barcode"></span></b><br/>
                                        SKU ID: <span id="sku_id"></span>
                                    </p>
                                    <div class="form-group row mb-0">
                                        <div class='col-auto'>
                                            <p class="form-control-static mt-2">Quantity</p>
                                        </div>
                                        <div class='col col-sm-4 col-lg-2'>
                                            <input class="form-control" type="number" id="qty" name="qty" min="1" value="<?= $quantity; ?>" autocomplete="off"/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto pt-3">
                        <?php
                            switch ($mode) {
                                case Movements::CREATE_MOVEMENT: ?>
                                    <span class="text-success"><b>Type</b></span>
                                    <div class="form-group row mb-0">
                                        <div class="col">
                                            <?php
                                                $options = [
                                                    'class' => 'form-control movement_type',
                                                    'id' => 'type',
                                                    'data-id' => 1
                                                ];
                                                echo Html::dropDownList('type', Movement::$type_name, null, $options);
                                            ?>
                                        </div>
                                    </div>
                                <?php
                                    break;
                                case Movements::CREATE_REQUEST: ?>
                                    <span class="text-success"><b>Type request</b></span>
                                    <div class="form-group row mb-0">
                                        <div class="col">
                                            <?php
                                                $options = [
                                                    'class' => 'form-control movement_type',
                                                    'id' => 'type_location',
                                                    'data-id' => 1,
                                                ];
                                                echo Html::dropDownList('type_location', array_combine(MovementRequest::ALL_TYPES, MovementRequest::ALL_TYPES), MovementRequest::TYPE_LOCATION_FROM, $options);
                                            ?>
                                        </div>
                                    </div>
                        <?php } ?>
                        </div>
                        <div class="col-12 pt-2 d-none" id="panel_src">
                            <span class="text-success"><b>Select origin location</b></span>
                            <div class="row">
                                <div class="btn-group btn-group-toggle col" data-toggle="buttons" id="location_from"></div>
                            </div>
                            <div class="row mt-1 px-md-3 px-2 d-none" id="is_available_locs">
                                <div class="col text-center">
                                    <span class="text-success"><b>Available locations</b></span>
                                    <div class="row justify-content-center" id="available_locs">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 pt-2 d-none" id="panel_dest">
                            <span class="text-success"><b>Select destination location</b></span>
                            <div class="row">
                                <div class="btn-group btn-group-toggle col" data-toggle="buttons" id="location_to"></div>
                            </div>
                            <div class="row mt-1 px-md-3 px-2 d-none" id="is_available_to">
                                <div class="col text-center">
                                    <span class="text-success"><b>Available locations</b></span>
                                    <div class="row justify-content-center" id="available_to">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <button class="btn btn-danger btn-icon-split" id="btn_cancel">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-chevron-left"></i>
                        </span>
                        <span class="text">Cancel</span>
                    </button>
                </div>
                <div class='col-auto'>
                    <button class="btn btn-success btn-icon-split" id="btn_conf" type="button" disabled>
                        <span class="text">Create</span>
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-chevron-right"></i>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
echo (new ConfirmModal('cancelMovement', 'Cancel create ' . View::toHtml($mode)))->addContent('Are you sure?')
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'btn-cancel-movement'])
    ->put();

echo (new ConfirmModal('createAnotherMovement', 'Movement created'))->addContent('Do you want to create another movement for the same product?')
    ->changeNo(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_CANCEL | ConfirmModal::BUTTON_CONFIRM, ['id' => 'btn-cancel-create-another-movement'])
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM)
    ->put();
?>
<script type="text/template" id="sel_location">
    <label class="btn btn-success mx-1" id="{{sel_loc_id}}" style="width:25%">
        <input type="radio" name="options" id="{{sel_loc_name}}" autocomplete="off">
        <i class="fas fa-fw {{sel_loc_icon}}"></i><br/><span class="text-xs" id="{{sel_loc_for_id}}">{{sel_loc_for_name}}</span>
    </label>
</script>
<script type="text/template" id="sel_available">
    <div id="{{available_id}}" class="col-md-5 border border-success rounded m-1 d-none">
        <span class="text-success">{{available_name}}</span><br/>
        <select class="custom-select mb-3" size="5" id="{{available_items}}">
        </select>
    </div>
</script>
<script>
    var cart_id = 0;
    var back = <?= View::toJs(HTTP::safeLinkUrl($back ?? '/')); ?>;
    var mode = <?= View::toJs($mode) ?>;
    var previous_movement = <?= $previous_movement ?>;
    var is_request = <?= $mode === Movements::CREATE_REQUEST ? 'true' : 'false'; ?>;
</script>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'choose_supplier.php'; ?>
<?= View::include_js(BACKEND_JS, "movement_common.js")?>
<?= View::include_js(BACKEND_JS, "autocomplete_wrapper_v2.js")?>
<?= View::include_js(BACKEND_JS, "select_sku_for_movement.js")?>