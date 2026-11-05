<?php

use app\core\entities\User;
use app\core\entities\SKU;
use app\core\entities\Location;
use app\backend\models\Movements_;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\modal\ConfirmModal;

/**
 * @var User $authUser
 * @var string $warehouse_label
 * @var SKU $SKU
 * @var string $SKU_barcode
 * @var Location $cart
 * @var bool $to_cart
 * @var int $quantity
 * @var array $locs
 * @var array $locs_to
 * @var string $location_from;
 */

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
                            <i class="fas fa-fw fa-sign-in-alt"></i>
                            Create movement<?= !is_null($cart) ? '<br/>(trolley ' . View::toHtml($cart->getLocation($warehouse_label)) . ')' : ''; ?>
                        </span>
                    </h6>
                </div>
                <?php if ($authUser->isRoleWorker()) { ?>
                    <div class='col-auto'>
                        <a class="btn btn-primary btn-icon-split" href="/cart/movement/?back=/movement/do_movement">
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-shopping-cart"></i>
                            </span>
                            <span class="d-none d-md-block text">Change trolley</span>
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
        <form method="post" name="frmSKU">
            <div class="card-body">
                <div class="row justify-content-md-center mb-2">
                    <div class='input-group col-lg-10'>
                        <?php (new Scan([
                            'id' => 'barcode',
                            'name' => 'barcode',
                            'placeholder' => 'Scan SKU barcode or Location',
                            'type' => Scan::TYPE__SKU | Scan::TYPE__LOCATION,
                        ], ['id' => 'btn_scan']))->render(); ?>
                    </div>
                </div>
                <div class="row justify-content-md-center mx-1">
                    <div class="col-lg-10 p-2 mb-2 bg-danger text-white d-none" id="err_msg">
                    </div>
                </div>
                <div class="row justify-content-md-center">
                    <div class="col-lg-10 pt-2">
                        <b>Step 1 - Scan goods</b><br/>
                        <span class="text-success"><b>Goods</b></span>
                        <div class="row">
                            <?php if (!empty($SKU->getPhoto())) { ?>
                                <div class="col-auto">
                                    <img class="img-thumbnail" style="max-height: 70px;" src="/image/public?path=<?= View::toHtml($SKU->getPhoto()); ?>"
                                        data-scan-barcode="<?= View::toHtml($SKU_barcode); ?>"
                                        data-scan-type="<?= Scan::TYPE__SKU; ?>"
                                    />
                                </div>
                            <?php } ?>
                            <div class="col-auto text-xs d-md-none">
                                <p class="form-control-static mb-1" data-scan-barcode="<?= View::toHtml($SKU_barcode) ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                                    <?= View::toHtml($SKU->getName()); ?><br/>
                                    <b class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode($SKU_barcode); ?></b><br/>
                                    SKU ID: <?= $SKU->getId(); ?>
                                </p>
                            </div>
                            <div class="col d-none d-md-block">
                                <p class="form-control-static mb-1" data-scan-barcode="<?= View::toHtml($SKU_barcode) ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                                    <?= View::toHtml($SKU->getName()); ?><br/>
                                    <b class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode($SKU_barcode); ?></b><br/>
                                    SKU ID: <?= $SKU->getId(); ?>
                                </p>
                                <div class="form-group row mb-0">
                                    <div class='col-auto'>
                                        <p class="form-control-static mt-2">Quantity</p>
                                    </div>
                                    <div class='col col-sm-4 col-lg-3'>
                                        <input class="form-control" type="number" id="qty1" name="qty1" min="1" value="<?= $quantity; ?>" autocomplete="off"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group form-row d-md-none mb-0">
                            <div class='col-auto'>
                                <p class="form-control-static mt-2">Quantity</p>
                            </div>
                            <div class='col-6'>
                                <input class="form-control" type="number" id="qty2" name="qty2" min="1" value="<?= $quantity; ?>" autocomplete="off"/>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-10 pt-2">
                        <div class="row justify-content-between">
                            <div class="col mt-2">
                                <b>Step 2 - Scan origin location</b>
                            </div>
                            <div id="pnl_discard" class="col-auto mr-1 d-none">
                                <a class="btn btn-outline-primary" id="btn_discard"><small><b><i class="fas fa-fw fa-undo-alt"></i>Discard</b></small></a>
                            </div>
                        </div>
                        <div class="row">
                            <div class="btn-group btn-group-toggle col" data-toggle="buttons" id="location_from">
                                <?php if (!empty($locs[Location::PF]) || !empty($locs[Location::ST])) { ?>
                                    <label class="btn btn-success mx-1" style="width:25%">
                                        <input type="radio" name="options" id="from_location" autocomplete="off">
                                        <i class="fas fa-fw fa-map-marker-alt"></i><br/><span class="text-xs" id="loc_from_name">Location*</span>
                                    </label>
                                <?php } ?>
                                <?php if (!empty($locs[Location::PA])) { ?>
                                    <label class="btn btn-success mx-1" style="width:25%">
                                        <input type="radio" name="options" id="from_PA" autocomplete="off">
                                        <i class="fas fa-fw fa-sign-in-alt"></i><br/><span class="text-xs" id="loc_from_PA">PA zone*</span>
                                    </label>
                                <?php } ?>
                                <label class="btn btn-success mx-1" style="width:25%">
                                    <input type="radio" name="options" id="from_DH" autocomplete="off">
                                    <i class="fas fa-fw fa-ghost"></i><br/><span class="text-xs"><?= Location::DH; ?></span>
                                </label>
                                <?php if (!empty($locs[Location::CA])) { ?>
                                    <label class="btn btn-success mx-1" style="width:25%">
                                        <input type="radio" name="options" id="from_cart" autocomplete="off">
                                        <i class="fas fa-fw fa-shopping-cart"></i><br/><span class="text-xs">Trolley (<?= $locs[Location::CA][$cart->getLocation($warehouse_label)][1]; ?>)</span>
                                    </label>
                                <?php } ?>
                            </div>
                        </div>
                        <?= Movements_::availableLocations($locs, 'origin'); ?>
                    </div>
                    <div class="col-lg-10 pt-3">
                        <b>Step 3 - Scan destination location</b>
                        <div class="row">
                            <div class="btn-group btn-group-toggle col" data-toggle="buttons" id="location_to"">
                                <?php if (!empty($locs_to[Location::PF]) || !empty($locs_to[Location::ST])) { ?>
                                    <label class="btn btn-success mr-1" id="locLocto" style="width:25%">
                                        <input type="radio" name="options" id="to_location" autocomplete="off">
                                        <i class="fas fa-fw fa-map-marker-alt"></i><br/><span class="text-xs" id="loc_to_name">Location*</span>
                                    </label>
                                <?php } ?>
                                <label class="btn btn-success mx-1" id="locPAto" style="width:25%">
                                    <input type="radio" name="options" id="to_PA" autocomplete="off">
                                    <i class="fas fa-fw fa-sign-in-alt"></i><br/><span class="text-xs">PA zone</span>
                                </label>
                                <label class="btn btn-success mx-1" id="locDHto" style="width:25%">
                                    <input type="radio" name="options" id="to_DH" autocomplete="off">
                                    <i class="fas fa-fw fa-ghost"></i><br/><span class="text-xs"><?= Location::DH; ?></span>
                                </label>
                                <?php if ($to_cart) { ?>
                                    <label class="btn btn-success ml-1" id="locCartto" style="width:25%">
                                        <input type="radio" name="options" id="to_cart" autocomplete="off">
                                        <i class="fas fa-fw fa-shopping-cart"></i><br/><span class="text-xs">Trolley</span>
                                    </label>
                                <?php } ?>
                            </div>
                        </div>
                        <?= Movements_::availableLocations($locs_to, 'destination'); ?>
                    </div>
                    <div class="col-lg-10 pt-2">
                        <span id="not_location">*Please scan the location to complete</span>
                    </div>
                </div>
            </div>
        </form>
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
                        <span class="text">Confirm</span>
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-chevron-right"></i>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="cancelMovement">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Movement</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure?
            </div>
            <div class="modal-footer">
                <?= View::btnModalDismiss('No') ?>

                <button class="btn btn-danger btn-icon-split js-btn-conf" type="button">
                    <span class="text">Yes</span>
                    <span class="icon text-white-50">
                        <i class="fas fa-fw fa-check"></i>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
echo (new ConfirmModal('createAnotherMovement', 'Movement created'))->addContent('Do you want to create another movement for the same product?')
    ->changeNo(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_CANCEL | ConfirmModal::BUTTON_CONFIRM, ['id' => 'btn-cancel-create-another-movement'])
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'btn-confirm-create-another-movement'])
    ->put();
?>

<script>
    $('#btn_cancel').click(() => {
        $('#cancelMovement').modal('show');
        return false;
    });

    $('.js-btn-conf').click(() => {
        $('#cancelMovement').modal('hide');
        location = '/movement/do_movement/?clear=1';
    });

    $('#btn-cancel-create-another-movement').click(() => {
        $('#createAnotherMovementModalConfirm').modal('hide');
        location = '/movement?clear=1';
    });
    $('#btn-confirm-create-another-movement').click(() => {
        $('#createAnotherMovementModalConfirm').modal('hide');
    });

    $('#location_to').prop('classList').add('display-only');

    $('#location_from input:radio').change(function() {
        loc_from = this.id;
        showAvailableLocationsFrom(loc_from);
    });

    $('#location_to input:radio').change(function() {
        loc_to = this.id;
        showAvailableLocationsTo(loc_to);
    });

    function showLocations(id_locs)
    {
        var item = $('#' + id_locs);
        if (!item.length || !item.prop('classList').length)
            return;
        if (item.prop('classList').contains('d-none'))
            item.prop('classList').remove('d-none');
    }

    function hideLocations(id_locs)
    {
        var item = $('#' + id_locs);
        if (!item.length || !item.prop('classList').length)
            return;
        if (!item.prop('classList').contains('d-none'))
            item.prop('classList').add('d-none');
    }

    function displayLocations(id_locs, show = false)
    {
        if (show)
            showLocations(id_locs);
        else
            hideLocations(id_locs);
    }

    function showAvailableLocationsFrom(loc)
    {
        var isShow;
        switch (loc) {
            case 'from_location':
                isShow = false;
                if (typeof locs.PF !== 'undefined')
                    displayLocations('locPF', isShow = true);
                if (typeof locs.ST !== 'undefined')
                    displayLocations('locST', isShow = true);
                if (typeof locs.PA !== 'undefined')
                    displayLocations('locPA');
                displayLocations('available_origin_locs', isShow);
                break;
            case 'from_PA':
                isShow = false;
                if (typeof locs.PF !== 'undefined')
                    displayLocations('locPF');
                if (typeof locs.ST !== 'undefined')
                    displayLocations('locST');
                if (typeof locs.PA !== 'undefined')
                    displayLocations('locPA', isShow = true);
                displayLocations('available_origin_locs', isShow);
                break;
            case 'from_DH':
                location_id = dh_id;
                qty_id = <?= PHP_INT_MAX; ?>*1;
                endSelectFrom();
                displayLocations('locDHto');
                break;
            case 'from_cart':
                location_id = cart_id;
                qty_id = cart_qty*1;
                endSelectFrom();
                displayLocations('locCartto');
                break;
        }
    }

    function showAvailableLocationsTo(loc)
    {
        var isShow;
        switch (loc) {
            case 'to_location':
                isShow = false;
                if (typeof locs_to.PF !== 'undefined')
                    displayLocations('locPFto', isShow = true);
                if (typeof locs_to.ST !== 'undefined')
                    displayLocations('locSTto', isShow = true);
                if (typeof locs_to.PA !== 'undefined')
                    displayLocations('locPAto');
                displayLocations('available_destination_locs', isShow);
                if (location_id_to == -1)
                    $('#btn_conf').attr('disabled', true);
                else
                    $('#btn_conf').removeAttr('disabled');
                break;
            case 'to_PA':
            case 'to_DH':
            case 'to_cart':
                displayLocations('available_destination_locs');
                $('#btn_conf').removeAttr('disabled');
                break;
        }
    }

    function processingLocs(locs, name, barcode, from = true)
    {
        if (typeof locs !== 'undefined' && barcode in locs) {
            $('#loc_' + name).text(barcode);
            if (from) {
                location_id = locs[barcode][0];
                qty_id = locs[barcode][1]*1;
                endSelectFrom();
            } else {
                location_id_to = locs[barcode][0];
                qty_id_to = locs[barcode][1]*1;
                $('#barcode').val('');
                $('#btn_conf').removeAttr('disabled');
            }
            return true;
        }
        return false;
    }

    function endSelectFrom()
    {
        var qty = $('#qty1').val()*1;
        $('#barcode').val('');
        if (qty > qty_id) {
            showError('err_msg', 'Quantity cannot be more than ' + qty_id, 3000);
            hideLocations('available_origin_locs');
            setTimeout(() => {
                $('#barcode').focus();
            }, 100);
            setTimeout(() => {
                $('#location_from input[type="radio"]:checked').parent().prop('classList').remove('active');
            }, 110);
            return ;
        }
        $('#barcode').attr('placeholder', 'Scan location to');
        $('#barcode').focus();
        loc_from_active = $('#location_from input[type="radio"]:checked');
        $('#location_from').prop('classList').add('display-only');
        hideLocations('available_origin_locs');
        showLocations('pnl_discard');
        $('#qty1').prop('disabled', true);
        $('#qty2').prop('disabled', true);
        $('#location_to').prop('classList').remove('display-only');
        selectLocFrom = false;
        selectLocTo = true;
    }

    function selectLocation(barcode)
    {
        if (loc_from === 'from_location') {
            if (processingLocs(locs.PF, 'from_name', barcode))
                return false;
            if (processingLocs(locs.ST, 'from_name', barcode))
                return false;
        } else if (loc_from === 'from_PA')
            if (processingLocs(locs.PA, 'from_PA', barcode)) {
                hideLocations('locPAto');
                return false;
            }
    }

    $('#qty1').change(() => {
        $('#qty2').val($('#qty1').val());
    });

    $('#qty2').change(() => {
        $('#qty1').val($('#qty2').val());
    });

    $('#qty1').blur(() => {
        if ($('#qty1').val() < 1) {
            $('#qty1').val(1);
            $('#qty2').val(1);
            showError('err_msg', 'Quantity must be greater than 0', 3000);
            $('#barcode').focus();
        }
    });

    $('#qty2').blur(() => {
        if ($('#qty2').val() < 1) {
            $('#qty1').val(1);
            $('#qty2').val(1);
            showError('err_msg', 'Quantity must be greater than 0', 3000);
            $('#barcode').focus();
        }
    });

    var selectLocFrom = true;
    var selectLocTo = false;
    var cart_id = <?= !empty($cart) ? $cart->getId() : -1; ?>;
    var dh_id = <?= $_SESSION[Location::DH] ?? 0; ?>;
    var pa_id = <?= $_SESSION[Location::PA] ?? 0; ?>;
    var cart_qty = <?= !empty($locs[Location::CA]) ? $locs[Location::CA][$cart->getLocation($warehouse_label)][1] : 0; ?>;
    var SKU = <?= View::toJs(!empty($SKU) ? $SKU->toArray() : (object)[]); ?>;
    var barcodes = <?= View::toJs(!empty($SKU_barcode) ? explode(', ', $SKU_barcode) : []); ?>;
    var locs = <?= View::toJs(!empty($locs) ? $locs : (object)[]); ?>;
    var locs_to = <?= View::toJs(!empty($locs_to) ? $locs_to : (object)[]); ?>;
    var loc_from = <?= View::toJs($_GET['loc_from'] ?? ''); ?>;
    var loc_to;
    var location_id = -1;
    var location_id_to = -1;
    var qty_id = 0;
    var qty_id_to = 0;
    var loc_from_active = null;

    <?php if ($location_from): ?>
        $('#location_from #' + loc_from).prop('checked', true).trigger('change');
        selectLocation(<?= View::toJs($location_from); ?>);
    <?php endif; ?>

    $('#btn_scan').click(() => {
        if ($('#qty1').is(':focus') || $('#qty2').is(':focus')) {
            if ($('#qty1').val() < 1 || $('#qty2').val() < 1) {
                $('#qty1').val(1);
                $('#qty2').val(1);
                showError('err_msg', 'Quantity must be greater than 0', 3000);
            }
            $('#barcode').focus();
            return false;
        }
        var barcode = $('#barcode').val().trim();
        if (barcode == '') {
            showError('err_msg', 'Scan ' + (selectLocFrom ? 'SKU barcode or ' : '') + 'Location', 3000);
            $('#barcode').val('');
            $('#barcode').focus();
            return false;
        }
        if (barcodes.includes(barcode)) {
            $('#qty1').val($('#qty1').val()*1 + 1);
            $('#qty2').val($('#qty1').val());
            $('#barcode').val('');
            $('#barcode').focus();
            return false;
        }

        if (selectLocFrom) {
            if (loc_from === 'from_location') {
                if (processingLocs(locs.PF, 'from_name', barcode))
                    return false;
                if (processingLocs(locs.ST, 'from_name', barcode))
                    return false;
            } else if (loc_from === 'from_PA')
                if (processingLocs(locs.PA, 'from_PA', barcode)) {
                    hideLocations('locPAto');
                    return false;
                }
            showError('err_msg', (selectLocFrom ? 'SKU barcode or ' : '') + 'Location scan is not available', 3000);
            $('#barcode').val('');
            $('#barcode').focus();
            return false;
        }
        if (selectLocTo) {
            if (loc_to === 'to_location') {
                if (processingLocs(locs_to.PF, 'to_name', barcode, false))
                    return false;
                if (processingLocs(locs_to.ST, 'to_name', barcode, false))
                    return false;
            }
            showError('err_msg', 'Location scan is not available', 3000);
            $('#barcode').val('');
            $('#barcode').focus();
            return false;
        }
        showError('err_msg', (selectLocFrom ? 'SKU barcode or ' : '') + 'Location is not correct', 3000);
        $('#barcode').val('');
        $('#barcode').focus();
        return false;
    });

    $('#btn_discard').click(() => {
        $('#barcode').attr('placeholder', 'Scan SKU barcode or location to');
        $('#barcode').focus();
        $('#qty1').prop('disabled', false);
        $('#qty2').prop('disabled', false);
        showLocations('locDHto');
        showLocations('locCartto');
        showLocations('locPAto');
        loc_from_active.parent().prop('classList').remove('active');
        loc_from_active = null;
        var elem_to = $('#location_to input[type="radio"]:checked');
        if (elem_to.length > 0)
            elem_to.parent().prop('classList').remove('active');
        $('#location_from').prop('classList').remove('display-only');
        $('#location_to').prop('classList').add('display-only');
        hideLocations('pnl_discard');
        hideLocations('available_destination_locs');
        $('#loc_from_name').text('Location*');
        $('#loc_from_PA').text('PA zone*');
        $('#btn_conf').attr('disabled', true);
        selectLocFrom = true;
        selectLocTo = false;
    });

    $('#btn_conf').click(() => {
        var qty = $('#qty1').val();
        switch(loc_to) {
            case 'to_location':
                if (qty > qty_id_to) {
                    showError('err_msg', 'Quantity cannot be more than ' + qty_id_to, 3000);
                    $('#barcode').focus();
                    return;
                }
                break;
            case 'to_PA':
                location_id_to = pa_id;
                break;
            case 'to_DH':
                location_id_to = dh_id;
                break;
            case 'to_cart':
                location_id_to = cart_id;
                break;
        }
        location = '/movement/movement_do/?id=' + SKU.id +
            '&loc_from_id=' + location_id +
            '&loc_to_id=' + location_id_to +
            '&qty=' + qty +
            '&loc_from=' + loc_from;
    });

    $(function() {
        <?php if(!empty($_GET['id'])): ?>
        $('#createAnotherMovementModalConfirm').modal('show');
        <?php endif; ?>
    });

</script>
<style>
    .btn-group.display-only label.btn {
        pointer-events: none;
        cursor: not-allowed;
        opacity: 1;
        color: #333;
        background-color: #e6e6e6;
        border-color: #adadad;
    }
    .btn-group.display-only label.btn.active {
        pointer-events: none;
        cursor: not-allowed;
        opacity: 1;
        color: #333;
        background-color: #a6a6a6;
        border-color: #adadad;
    }
</style>
