<?php

use app\backend\models\MovementRequests;
use app\backend\models\Movements;
use app\backend\models\Pagination;
use app\backend\models\SKUs;
use app\core\entities\MovementRequest;
use app\core\entities\User;
use app\core\helpers\actions\Actions;
use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\statical\TypeLocation;

/**
 * @var User $authUser
 * @var array $requests
 * @var int $total_count
 * @var int $total_pages
 * @var int $current_page
 * @var string $movements_filters
 * @var string $back
 */

$actions = new Actions('requests');
$actions
    ->addButton(Actions::ANCHOR, 'Create', 'plus', '/movement/create_movement?mode=request');

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4 bg-">
        <h1 class="h3 mb-0 text-gray-800">Movement requests</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-question-circle"></i> Requests list (<?= $total_count; ?> item<?= $total_count > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
                <?= $actions->get(); ?>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="<?= Filter::getFilterTypeKey('barcode'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER; ?>">
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="text" id="barcode" name="barcode" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['barcode']) ? $_GET['barcode'] : '') ?>"/>
                            <label for="barcode">SKU barcode</label>
                        </div>
                    </div>
                    <div class="form-group col-1">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'type_location',
                            ];
                            echo Html::dropDownList('type_location', ['all'] + array_combine(MovementRequest::ALL_TYPES, MovementRequest::ALL_TYPES),
                                !empty($_GET['type_location']) ? $_GET['type_location'] : '', $options);
                            ?>
                            <label for="type_location">Type request</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'type',
                            ];
                            echo Html::dropDownList('type', ['all'] + TypeLocation::getAll(),
                                !empty($_GET['type']) ? $_GET['type'] : '', $options);
                            ?>
                            <label for="type">Type of location</label>
                        </div>
                    </div>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'class' => 'selectpicker form-control form-control-lg custom-select',
                                    'id' => 'status',
                                ];
                                echo Html::dropDownList('status', ['all' => 'all'] + MovementRequest::ALL_STATUSES,
                                    $_GET['status'] ?? 'all', $options);
                            ?>
                            <label for="status">Status</label>
                        </div>
                    </div>
                </div>
                <div class="text-right mb-3">
                    <a href="/movement/requests" class="btn btn-lg btn-outline-orange mr-1">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange ml-1">Filter</button>
                </div>
            </form>
            <?php if (count($requests)) { ?>
                <?php View::pagination("/movement/?" . $movements_filters . '&', 1, $total_pages, $current_page, Pagination::LIMIT_PER_PAGE); ?>
                <div class="row">
                    <table class="table table-hover mt-1 mb-0">
                        <colgroup>
                            <col width="5%">
                            <col width="*">
                            <col width="20%">
                            <col width="10%">
                            <col width="10%">
                            <col width="10%">
                            <col width="10%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>SKU</th>
                                <th>Location</th>
                                <th>Quantity</th>
                                <th>Accepted Quantity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request) { ?>
                                <tr>
                                    <td><?= View::toHtml($request['id']); ?></td>
                                    <td><?= View::toHtml("{$request['name']} (#{$request['SKU_id']})") . '<br/><b>' . View::toHtmlConvertWithPrimaryBarcode($request['barcode']) . '</b>'; ?></td>
                                    <td><?= MovementRequests::getTypeLocation($request['type_location']) . ' ' . View::toHtml($request['loc_name']); ?></td>
                                    <td><?= View::toHtml($request['quantity']); ?></td>
                                    <td><?= View::toHtml($request['accepted_quantity']); ?></td>
                                    <td><span class="badge badge-<?= View::toHtml(MovementRequest::getStatusColor($request['status'])); ?>"> <?= View::toHtml(MovementRequest::getStatusName($request['status'])); ?></span></td>
                                    <td>
                                        <?php if (MovementRequest::canCancelled($request['status']) && !$authUser->isRoleWorker()) { ?>
                                            <button class="btn btn-success btn-icon-split js-btn-create mb-1" data-id="<?= (int)$request['id']; ?>">
                                                <span class="icon text-white"><i class="fas fa-fw fa-cart-plus"></i></span>
                                                <span class="text text-white" style="width: 100px">Create</span>
                                            </button>
                                        <?php } ?>
                                        <button class="btn btn-primary btn-icon-split js-btn-history" data-id="<?= (int)$request['id']; ?>">
                                            <span class="icon text-white"><i class="far fa-fw fa-clock"></i></span>
                                            <span class="text text-white" style="width: 100px">History</span>
                                        </button>
                                        <?php if (MovementRequest::canCancelled($request['status'])) { ?>
                                            <button class="btn btn-danger btn-icon-split js-btn-cancel mt-1" data-id="<?= (int)$request['id']; ?>">
                                                <span class="icon text-white"><i class="fas fa-fw fa-times"></i></span>
                                                <span class="text text-white" style="width: 100px">Cancel</span>
                                            </button>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php View::pagination("/movement/?" . $movements_filters . '&', 1, $total_pages, $current_page, Pagination::LIMIT_PER_PAGE); ?>
            <?php } else {
                echo Html::warning("No movement requests found");
            } ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl($back)); ?>">
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-chevron-left"></i>
                            </span>
                        <span class="text">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    $modal_cancel = new ConfirmModal('cancelMovement', 'Delete Movement Request');
    echo $modal_cancel->addContent('Are you sure?')
        ->addForm('/movement/cancel_request?mode=' . Movements::CREATE_REQUEST, ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons(Html::hidden('id', 0, 'movement_id') . $modal_cancel->getButtons())
        ->put();

    $modal_create = new ConfirmModal('createMovement', 'Create movement from request', ConfirmModal::SIZE_XL);
    echo $modal_create->addContent('
            <div class="alert alert-danger mb-0 d-none" id="err_create"></div>
            <div class="row">
                <div class="col">
                    <span class="text-success"><b>Goods</b></span>
                    <div class="row">
                        <div class="col-auto d-none" id="sku_is_photo">
                            <img class="img-thumbnail" style="max-height: 100px;" id="sku_photo"/>
                        </div>
                        <div class="col">
                            <p class="form-control-static mb-1">
                                <span id="sku_name"></span><br/>
                                <b class="scan-barcode"><span id="sku_barcode"></span></b><br/>
                                SKU ID: <span id="sku_id"></span>
                            </p>
                            <div class="form-group row mb-0">
                                <div class="col-auto">
                                    <p class="form-control-static mt-2 mb-0">Quantity (max <span class="text-danger font-weight-bold" id="sku_max_quantity"></span>)</p>
                                </div>
                                <div class="col-4">
                                    <input class="form-control" type="number" id="sku_qty" name="sku_qty" min="1" autocomplete="off"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-auto">
                    <span class="text-success"><b>Type request</b></span><br/>
                    <span id="sku_type_location"></span>
                    <span class="text-success font-weight-bold" id="sku_location"></span>
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
            </div>' . Html::hiddens([['sku_location_id', 0, true], ['sku_movement_id', 0, true]]))
        ->changeNo(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_CANCEL | ConfirmModal::BUTTON_CONFIRM, ['aria-label' => 'Close'], 'Cancel')
        ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE, ['id' => 'btn_create'], 'Create')
        ->put();

    $modal_history = new InfoModal('historyMovement', 'History of request', InfoModal::SIZE_LG);
    echo $modal_history->addContent('
            <div class="alert alert-danger mb-0 d-none" id="err_history"></div>
            <div class="row d-none" id="history_request">
                <div class="col">
                    <table class="col table table-hover mt-1 mb-0">
                        <colgroup>
                            <col width="20%">
                            <col width="10%">
                            <col width="25%">
                            <col width="*">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Login</th>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>')
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
        <select class="custom-select mb-3" size="5" id="{{available_items}}"></select>
    </div>
</script>
<script>
    var mode = '<?= Movements::CREATE_REQUEST; ?>';
</script>
<?= View::include_js(BACKEND_JS, "movement_common.js")?>
<?= View::include_js(BACKEND_JS, 'movement_request.js')?>
