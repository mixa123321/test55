<?php

use app\core\helpers\Arr;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var array $SKUs
 * @var int $quantity
 * @var array $available_skus
 * @var array $skus_info
 * @var string $back
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Create processing movement</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-arrows-alt"></i>SKU & Locations selection
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-10">
                    <div class="row">
                        <form class="col-12" method="post" id="createFrm">
                            <div class="alert alert-danger d-none" id="add_sku_error"></div>
                            <p class="form-control-static text-success mb-2"><b>Select SKU for movement</b></p>
                            <div class="input-group pl-0">
                                <input type="hidden" id="supplier_id" name="supplier_id"/>
                                <?php (new Scan(
                                    ['id' => 'search', 'type' => Scan::TYPE__SKU],
                                    ['class' => 'js-btn-scan', 'title' => 'Select', 'data' => [
                                        'send' => 'createFrm',
                                        'empty' => 'false',
                                        'suppliers' => json_encode(array_values(array_unique(Arr::column($SKUs, 'supplier_id')))),
                                    ]]
                                ))->render(); ?>
                            </div>
                        </form>
                    </div>
                    <div class="row d-none" id="goods">
                        <div class="col-12 pt-3">
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
                        <div class="col-6 pt-2">
                            <span class="text-success"><b>Scan origin location</b></span>
                            <input class="form-control" type="text" id="origin_location" name="origin_location" autocomplete="off"/>
                        </div>
                        <div class="col-6 pt-2">
                            <span class="text-success"><b>Scan destination location</b></span>
                            <input class="form-control" type="text" id="dest_location" name="dest_location" autocomplete="off"/>
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
    echo (new ConfirmModal('cancelMovement', 'Cancel Create Movement'))->addContent('Are you sure?')
        ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'btn-cancel-movement'])
        ->put();
?>
<script>
    var global_skus_to_search = <?= $available_skus ?>;
    var global_skus_info = <?= $skus_info ?>;
    var back = <?= View::toJs(HTTP::safeLinkUrl($back ?? '/')); ?>;
    var error_id = 'add_sku_error';
</script>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_supplier.php'; ?>
<?= View::include_js(BACKEND_JS, "processing.js")?>
