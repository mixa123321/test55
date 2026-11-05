<?php

use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\entities\User;

/**
 * @var User $authUser
 * @var int $quantity
 * @var string $back
 */

?>
    <div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Pallet movement</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-expand-arrows-alt"></i>
                            <span class="movement-title">Locations selection</span>
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-1">
                    <ul class="nav nav-pills nav-stacked">
                    </ul>
                </div>
                <div class="col-md-10 col">
                    <div class="row">
                        <form class="col-12" method="post" id="createFrm">
                            <div class="alert alert-danger d-none" id="add_sku_error"></div>
                            <p class="form-control-static text-success mb-2 page-title"><b>Scan Pallet for movement</b></p>
                            <div class="input-group pl-0">
                                <?php (new Scan(
                                    ['id' => 'search', 'type' => Scan::TYPE__LOCATION],
                                    ['class' => 'js-btn-scan', 'title' => 'Scan', 'data' => [
                                        'empty' => 'false',
                                    ]]
                                ))->render(); ?>
                            </div>
                        </form>
                    </div>
                    <div class="row d-none" id="pallet">
                        <div class="col-12 pt-3">
                            <div class="row">
                                <div class="col auto">
                                    <!-- <h6 class="name alert alert-info"></h6> -->
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <table class="table table-hover mb-0 js-goods">
                                        <thead>
                                            <tr>
                                                <th>SKU</th>
                                                <th></th>
                                                <th>Quantity</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="row destinations-container d-none">
                                <div class="col">
                                    <span class="text-success"><b>Scan destination location</b></span>
                                    <div class="row">
                                        <div class="btn-group btn-group-toggle col" data-toggle="buttons" id="pallet_destination_btn"></div>
                                    </div>
                                    <div class="row mt-1 px-md-3 px-2">
                                        <div class="col text-center">
                                            <span class="text-success"><b>Available locations</b></span>
                                            <div class="row justify-content-center destinations" id="pallet_destinations">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row destination-scan d-none">
                                <div class="col">
                                    <div class="row">
                                        <form class="col-12" method="post" id="destFrm">
                                            <div class="alert alert-danger d-none" id="dest_error"></div>
                                            <p class="form-control-static text-success mb-2 page-title"><b>Scan destination location</b><span class="destination-name pl-2"></span></p>
                                            <div class="input-group pl-0">
                                                <?php (new Scan(
                                                    ['id' => 'search_destination', 'type' => Scan::TYPE__LOCATION],
                                                    ['class' => 'js-btn-scan', 'title' => 'Scan', 'data' => [
                                                        'empty' => 'false',
                                                    ]]
                                                ))->render(); ?>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="row d-none" id="destination">
                                        <div class="col-12 pt-3">
                                            <div class="row">
                                                <div class="col auto">
                                                    <!-- <h6 class="name alert alert-info"></h6> -->
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col">
                                                    <table class="table table-hover mb-0 js-goods">
                                                        <thead>
                                                            <tr>
                                                                <th>SKU</th>
                                                                <th></th>
                                                                <th>Quantity</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>      
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-none">
            <div class="row">
                <div class="col">
                    <button class="btn btn-danger btn-icon-split d-none" id="btn_back">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-chevron-left"></i>
                        </span>
                        <span class="text">Back</span>
                    </button>
                </div>
                <div class='col-auto'>
                    <button class="btn btn-success btn-icon-split d-none" type="submit" id="btn_conf" disabled>
                        <span class="text">Move</span>
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-chevron-right"></i>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
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
    var is_request = true;
</script>
<?= View::include_js(BACKEND_JS, "select_sku_for_movement.js")?>
<?= View::include_js(BACKEND_JS, "moment.min.js")?>
