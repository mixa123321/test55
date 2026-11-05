<?php

use app\core\entities\Location;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var int $ASN_id
 * @var int $SKU_id
 * @var string $barcode
 * @var string $name
 * @var int $qty
 * @var Location $cart
 * @var float $weight
 * @var int $PA_id
 * @var string $photo_src
 * @var string $back
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Unexpected Goods</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-sign-in-alt"></i>
                        ASN #<?= $ASN_id; ?>.
                        <?= View::include('utility/cart_widget.php', [
                            'cart' => $cart,
                            'warehouse_label' => $warehouse_label,
                            'weight' => $weight,
                        ]); ?>
                    </span>
                    </h6>
                </div>
                <?php if ($authUser->isRoleWorker()) { ?>
                    <div class="col-auto">
                        <a class="btn btn-primary btn-icon-split" href="/cart/unexpected/?back=/goods_in/unexpected&id=<?= (int)$ASN_id; ?>&<?= View::toHtml($back); ?>">
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-shopping-cart"></i>
                            </span>
                            <span class="d-none d-md-block text">Change trolley</span>
                        </a>
                    </div>
                <?php } ?>
                <div class="col-auto">
                    <a class="btn btn-success btn-icon-split text-white" id="gen_barcode" target="_blank">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-barcode"></i>
                        </span>
                        <span class="d-none d-md-block text">Print barcode</span>
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-1">
                    <ul class="nav nav-pills nav-stacked">
                    </ul>
                </div>
                <div class="col-md-10">
                    <form method="post" enctype="multipart/form-data" id="frm_conf" action="/goods_in/unexpected_end">
                        <input type="hidden" name="ASN_id" value="<?= $ASN_id; ?>"/>
                        <input type="hidden" id="qty" name="qty" value="<?= $qty; ?>"/>
                        <input type="hidden" id="location_id" name="location_id"/>
                        <input type="hidden" name="PA_id" value="<?= $PA_id; ?>"/>
                        <input type="hidden" name="cart_id" value="<?= $cart->getId(); ?>"/>
                        <input type="hidden" name="SKU_id" value="<?= empty($SKU_id) ? 0 : $SKU_id; ?>"/>
                        <input type="hidden" name="barcode" value="<?= View::toHtml($barcode); ?>" />
                        <input type="hidden" name="back" value="<?= View::toHtml($back); ?>" />
                        <input type="hidden" name="name" value="<?= View::toHtml($name); ?>" />
                        <div class="row justify-content-md-center mb-2">
                            <div class='input-group col-lg-10'>
                                <?php (new Scan([
                                    'id' => 'barcode',
                                    'placeholder' => 'Scan SKU barcode',
                                    'type' => Scan::TYPE__SKU,
                                ], ['id' => 'btn_scan']))->render(); ?>
                            </div>
                        </div>
                        <div class="row justify-content-md-center mx-1">
                            <div class="col-lg-10 p-2 mb-2 bg-danger text-white d-none" id="err_msg">
                            </div>
                        </div>
                        <div class="row justify-content-md-center">
                            <div class="col-lg-10">
                                <span class="text-success"><b>Goods</b></span>
                                <div class="row">
                                    <div class="col-auto">
                                        <input type="file" id="upload_file" name="upload_file" class="d-none" onchange="readURL(this);"/>
                                        <img class="img-thumbnail" style="max-height: 70px;" title="Create photo" src="<?= empty($photo_src) ? (COMMON_IMG . '/photo.png') : SKU::showPhotoSKU(urlencode($photo_src)); ?>" id="btn_photo" onclick="$('#upload_file').click();"/>
                                    </div>
                                    <div class="col-auto text-xs d-md-none">
                                        <p class="form-control-static mb-1">
                                            <?= View::toHtml($name); ?><br/>
                                            <b><?= View::toHtmlConvertWithPrimaryBarcode($barcode); ?></b><br/>
                                            SKU ID: -</p>
                                    </div>
                                    <div class="col d-none d-md-block">
                                        <p class="form-control-static mb-1">
                                            <?= View::toHtml($name); ?><br/>
                                            <b><?= View::toHtmlConvertWithPrimaryBarcode($barcode); ?></b><br/>
                                            SKU ID: -</p>
                                        <div class="form-group row mb-1">
                                            <div class='col-auto'>
                                                <p class="form-control-static mt-2">Quantity</p>
                                            </div>
                                            <div class='col col-sm-4 col-lg-3'>
                                                <input class="form-control" type="number" id="qty1" min="1" value="<?= $qty; ?>" autocomplete="off"/>
                                            </div>
                                        </div>
                                        <div class="form-group row mb-1">
                                            <div class='col-auto'>
                                                <p class="form-control-static mt-2">Batch No.</p>
                                            </div>
                                            <div class='col col-sm-4 col-lg-3'>
                                                <input class="form-control" type="input" id="batch_number" name="batch_number"  value="<?= !empty($batch_number) ? View::toHtml($batch_number) : ''; ?>" autocomplete="off"/>
                                            </div>
                                        </div>
                                        <div class="form-group row mb-1">
                                            <div class='col-auto'>
                                                <p class="form-control-static mt-2">Expired</p>
                                            </div>
                                            <div class='col col-sm-4 col-lg-3'>
                                                <input class="form-control" type="date" id="batch_expired" name="batch_expired"  value="<?= !empty($batch_expired) ? (new \DateTime($batch_expired))->format('Y-m-d') : ''; ?>" autocomplete="off"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group form-row d-md-none mb-1">
                            <div class='col-auto'>
                                <p class="form-control-static mt-2">Quantity</p>
                            </div>
                            <div class='col-6'>
                                <input class="form-control" type="number" id="qty2" min="1" value="<?= $qty; ?>" autocomplete="off"/>
                            </div>
                        </div>
                        <div class="form-group form-row d-md-none mb-1">
                            <div class='col-auto'>
                                <p class="form-control-static mt-2">Batch No.</p>
                            </div>
                            <div class='col-6'>
                                <input class="form-control" type="input" id="batch_number2" value="<?= !empty($batch_number) ? View::toHtml($batch_number) : ''; ?>" autocomplete="off"/>
                            </div>
                        </div>
                        <div class="form-group form-row d-md-none mb-1">
                            <div class='col-auto'>
                                <p class="form-control-static mt-2">Expired.</p>
                            </div>
                            <div class='col-6'>
                                <input class="form-control" type="date" id="batch_expired2" value="<?= !empty($batch_expired) ? (new \DateTime($batch_expired))->format('Y-m-d') : ''; ?>" autocomplete="off"/>
                            </div>
                        </div>
                   </form>
                    <div class="row justify-content-center mt-2">
                        <div class="col-lg-10">
                            Choose location
                            <div class="row">
                                <div class="btn-group btn-group-toggle col" data-toggle="buttons" id="location_to">
                                    <label class="btn btn-success mx-1" style="width:33%">
                                        <input type="radio" name="options" id="to_PA" autocomplete="off">
                                        <i class="fas fa-fw fa-map-marker-alt"></i><br/><span class="text-xs">To PA zone</span>
                                    </label>
                                    <?php if ($cart->getId()) { ?>
                                        <label class="btn btn-success ml-1" style="width:33%">
                                            <input type="radio" name="options" id="to_cart" autocomplete="off">
                                            <i class="fas fa-fw fa-shopping-cart"></i><br/><span class="text-xs">Trolley</span>
                                        </label>
                                    <?php } ?>
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
                        <span class="icon text-white-50">
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
<div class="modal" tabindex="-1" role="dialog" id="cancelUnexpected">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Unexpected Goods</h5>
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
<script>
    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#btn_photo').attr('src', e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    $('#btn_cancel').click(() => {
        $('#cancelUnexpected').modal('show');
        return false;
    });

    $('.js-btn-conf').click(function() {
        $('#cancelUnexpected').modal('hide');
        location = '/goods_in/arrival';
    });

    $('#qty1').change(function() {
        $('#qty2').val($('#qty1').val());
        $('#qty').val($('#qty2').val());
    });

    $('#qty2').change(function() {
        $('#qty1').val($('#qty2').val());
        $('#qty').val($('#qty2').val());
    });

    $('#qty1').blur(function() {
        if ($('#qty1').val() < 1) {
            $('#qty1').val(1);
            $('#qty2').val(1);
            $('#qty').val(1);
            showError('err_msg','Quantity must be greater than 0', 3000);
        }
    });

    $('#qty2').blur(function() {
        if ($('#qty2').val() < 1) {
            $('#qty1').val(1);
            $('#qty2').val(1);
            $('#qty').val(1);
            showError('err_msg','Quantity must be greater than 0', 3000);
        }
    });

    $('#batch_expired').change(function() {
        $('#batch_expired2').val($('#batch_expired').val());
    });

    $('#batch_expired2').change(function() {
        $('#batch_expired').val($('#batch_expired2').val());
    });

    $('#batch_number').change(function() {
        $('#batch_number2').val($('#batch_number').val());
    });

    $('#batch_number2').change(function() {
        $('#batch_number').val($('#batch_number2').val());
    });

    var ASN_id = <?= $ASN_id; ?>;
    var gen_barcode = <?= View::toJs((string)$barcode); ?>;
    var loc_to = '';

    $('#gen_barcode').click(() => {
        $('#gen_barcode').attr('href', '/goods_in/print_new_barcode/?id=' + ASN_id + '&barcode=' + gen_barcode);
    });

    $('#location_to :input').change(function() {
        loc_to = this.id;
        if (btn_conf.disabled)
            btn_conf.disabled = false;
        if (loc_to == 'to_PA')
            $('#location_id').val(<?= $PA_id; ?>);
        if (loc_to == 'to_cart')
            $('#location_id').val(<?= $cart->getId(); ?>);
    });

    $('#btn_scan').click(() => {
        if ($('#qty1').is(':focus') || $('#qty2').is(':focus')) {
            if ($('#qty1').val() < 1 || $('#qty2').val() < 1) {
                $('#qty1').val(1);
                $('#qty2').val(1);
                showError('err_msg','Quantity must be greater than 0', 3000);
            }
            $('#barcode').focus();
            return false;
        }
        var barcode = $('#barcode').val().trim();
        if (barcode == '') {
            showError('err_msg','Scan SKU barcode', 3000);
            $('#barcode').val('');
            $('#barcode').focus();
            return false;
        }
        if (barcode == gen_barcode) {
            var nums = $('#qty1').val()*1 + 1;
            $('#qty1').val(nums);
            $('#qty2').val(nums);
            $('#qty').val(nums);
            $('#barcode').val('');
            $('#barcode').focus();
            return false;
        }
        showError('err_msg','SKU barcode is not correct', 3000);
        $('#barcode').val('');
        $('#barcode').focus();
        return false;
    });

    $('#btn_conf').click(() => {
        $('#frm_conf').submit();
    });
</script>
