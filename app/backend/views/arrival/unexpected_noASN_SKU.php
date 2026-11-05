<?php

use app\core\entities\Location;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var array $suppliers
 * @var array $barcodes
 * @var int $qty
 * @var Location $cart
 * @var float $weight
 * @var SKU $SKU
 * @var int $PA_id
 * @var string $back
 * @var string $batch_number
 * @var ?array $locs
 */

$barcode = reset($barcodes);

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
                        No ASN.
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
                        <a class="btn btn-primary btn-icon-split" href="/cart/unexpected_noASN/?back=/goods_in/unexpected_noASN&<?= View::toHtml($back); ?>">
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
                    <form method="post" enctype="multipart/form-data" id="frm_conf" action="/goods_in/unexpected_noASN_end">
                        <input type="hidden" id="qty" name="qty" value="<?= $qty; ?>"/>
                        <input type="hidden" id="location_id" name="location_id"/>
                        <input type="hidden" name="PA_id" value="<?= $PA_id; ?>"/>
                        <input type="hidden" name="cart_id" value="<?= $cart->getId(); ?>"/>
                        <input type="hidden" name="SKU_id" value="<?= $SKU->getId(); ?>"/>
                        <input type="hidden" name="barcode" value="<?= View::toHtml($barcode); ?>" />
                        <input type="hidden" name="back" value="<?= View::toHtml($back); ?>" />
                        <input type="hidden" name="name" value="<?= View::toHtml($SKU->getName()); ?>" />
                        <div class="row justify-content-md-center mb-2">
                            <div class='input-group col-lg-10'>
                                <?php (new Scan([
                                    'id' => 'barcode',
                                    'placeholder' => 'Scan SKU barcode' . (!empty($locs) ? ' or Location' : ''),
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
                                <?php if (is_array($suppliers)) { ?>
                                    <div class="row">
                                        <div class="col-auto mt-2">Choose company</div>
                                        <div class="col">
                                            <?php
                                            echo Html::dropDownList('supplier_id', $suppliers, '0',
                                                [
                                                    'class' => 'selectpicker form-control',
                                                    'id' => 'suppliers',
                                                ]);
                                            ?>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <input id="suppliers" type="hidden" name="supplier_id" value="<?= View::toHtml($suppliers); ?>"/>
                                <?php } ?>
                                <span class="text-success"><b>Goods</b></span>
                                <div class="row">
                                    <?php if (!empty($SKU->getPhoto())) { ?>
                                        <div class="col-auto">
                                            <img class="img-thumbnail"
                                                data-scan-barcode="<?= View::toHtml($barcode); ?>"
                                                data-scan-type="<?= Scan::TYPE__SKU; ?>"
                                                style="max-height: 70px;"
                                                src="/image/public?path=<?= View::toHtml($SKU->getPhoto()); ?>"
                                            />
                                        </div>
                                    <?php } elseif (SKU::isUnexpected($SKU->getState())) { ?>
                                        <div class="col-auto">
                                            <input type="file" id="upload_file" name="upload_file" class="d-none" onchange="readURL(this);"/>
                                            <img class="img-thumbnail" style="max-height: 70px;" title="Create photo" src="<?= empty($photo_src) ? (COMMON_IMG . '/photo.png') : SKU::showPhotoSKU(urlencode($photo_src)); ?>" id="btn_photo" onclick="$('#upload_file').click();"/>
                                        </div>
                                    <?php } ?>
                                    <div class="col-auto text-xs d-md-none">
                                        <p class="form-control-static mb-1" data-scan-barcode="<?= View::toHtml($barcode); ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                                            <?= View::toHtml($SKU->getName()); ?><br/>
                                            <b class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode(implode(', ', $barcodes)); ?></b><br/>
                                            SKU ID: <?= $SKU->getID() ?: '-' ?></p>
                                    </div>
                                    <div class="col d-none d-md-block">
                                        <p class="form-control-static mb-1" data-scan-barcode="<?= View::toHtml($barcode); ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                                            <?= View::toHtml($SKU->getName()); ?><br/>
                                            <b class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode(implode(', ', $barcodes)); ?></b><br/>
                                            SKU ID: <?= $SKU->getID() ?: '-' ?></p>
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
                        <?php if (count($locs)) {
                            echo '<div class="row mt-1 px-md-3 px-2"><div class="col text-center"><span class="text-success"><b>Available locations</b></span><div class="row justify-content-center">';
                            if (!empty($locs[Location::PF])) {
                                echo "<div class='col-md-5 border border-success rounded m-1'><span class='text-success'>Pick Face locations</span><br/>";

                                echo "<table class='text-left mt-2 w-100'>";
                                foreach ($locs[Location::PF] as $key => $loc) {
                                    echo "<tr>";
                                    echo "<td class='pb-2'>";
                                    echo View::toHtml($key);
                                    if ($loc[2])
                                        echo "<br><span class='small'>(#" . View::toHtml($loc[2]) . ", " . View::toHtml($loc[3]) . ")</span> ";
                                    echo "</td>";

                                    echo "<td valign='top' class='text-right'>";
                                    echo View::toHtml($loc[1]);
                                    echo "</td>";
                                    echo "</tr>";
                                }
                                echo '</table>';

                                echo '</div>';
                            }
                            if (!empty($locs[Location::ST])) {
                                echo "<div class='col-md-5 border border-success rounded m-1'><span class='text-success'>Storage locations</span><br/>";
                                echo "<table class='text-left mt-2 w-100'>";
                                foreach ($locs[Location::ST] as $key => $loc) {
                                    echo "<tr>";
                                    echo "<td class='pb-2'>";
                                    echo View::toHtml($key);
                                    if ($loc[2])
                                        echo "<br><span class='small'>(#" . View::toHtml($loc[2]) . ", " . View::toHtml($loc[3]) . ")</span> ";
                                    echo "</td>";

                                    echo "<td valign='top' class='text-right'>";
                                    echo View::toHtml($loc[1]);
                                    echo "</td>";
                                    echo "</tr>";
                                }
                                echo '</table>';

                                echo '</div>';
                            }
                            echo '</div></div></div>';
                        } ?>
                    </form>
                    <div class="row justify-content-center mt-2">
                        <div class="col-lg-10">
                            Choose location
                            <div class="row">
                                <div class="btn-group btn-group-toggle col" data-toggle="buttons" id="location_to">
                                    <?php if (!empty($locs)) { ?>
                                        <label class="btn btn-success active mr-1" style="width:33%">
                                            <input type="radio" name="options" id="to_location" autocomplete="off" checked>
                                            <i class="fas fa-fw fa-map-marker-alt"></i><br/><span class="text-xs" id="loc_name">To location*</span>
                                        </label>
                                    <?php } ?>
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
                            <?php if (!empty($locs)) { ?>
                                <span id="not_location">*Please scan the location to complete</span>
                            <?php } ?>
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
<?php
    $modal = new ConfirmModal('cancelUnexpected', 'Cancel Unexpected Goods');
    echo $modal->addContent('Are you sure?')
        ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['class' => 'js-btn-conf'])
        ->put();
?>
<script>
    $('#gen_barcode').click(() => {
        $('#gen_barcode').attr('href', '/goods_in/print_new_barcode/?barcode=' + gen_barcode);
    });

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
        <?= $modal->show(); ?>
        return false;
    });

    $('.js-btn-conf').click(function() {
        <?= $modal->hide(); ?>
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
            showError('err_msg', 'Quantity must be greater than 0', 3000);
        }
    });

    $('#qty2').blur(function() {
        if ($('#qty2').val() < 1) {
            $('#qty1').val(1);
            $('#qty2').val(1);
            $('#qty').val(1);
            showError('err_msg', 'Quantity must be greater than 0', 3000);
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

    var gen_barcode = <?= View::toJs((string)$barcode); ?>;
    var barcodes = <?= View::toJs($barcodes) ?>;
    var loc_to = '';
    var loc_to = 'to_location';
    var locs = <?= View::toJs($locs ?? []) ?>;
    var location_id = -1;

    $('#location_to :input').change(function() {
        loc_to = this.id;
        if (this.id !== 'to_location')
            btn_conf.disabled = false;
        else
        if (location_id == -1)
            btn_conf.disabled = true;
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
                showError('err_msg', 'Quantity must be greater than 0', 3000);
            }
            $('#barcode').focus();
            return false;
        }
        var barcode = $('#barcode').val().trim();
        if (barcode == '') {
            showError('err_msg', 'Scan SKU barcode', 3000);
            $('#barcode').val('');
            $('#barcode').focus();
            return false;
        }
        if (barcodes.includes(barcode)) {
            var nums = $('#qty1').val()*1 + 1;
            $('#qty1').val(nums);
            $('#qty2').val(nums);
            $('#qty').val(nums);
            $('#barcode').val('');
            $('#barcode').focus();
            return false;
        }
        <?php if(!empty($locs)) { ?>
            if (loc_to === 'to_location') {
                if (typeof locs.PF !== 'undefined' && barcode in locs.PF) {
                    $('#loc_name').text(barcode);
                    $('#not_location').prop('classList').add('d-none');
                    btn_conf.disabled = false;
                    location_id = locs.PF[barcode][0];
                    $('#location_id').val(location_id);
                    qty_id = locs.PF[barcode][1];
                    $('#barcode').val('');
                    $('#barcode').focus();
                    return false;
                }
                if (typeof locs.ST !== 'undefined' && barcode in locs.ST) {
                    $('#loc_name').text(barcode);
                    $('#not_location').prop('classList').add('d-none');
                    btn_conf.disabled = false;
                    location_id = locs.ST[barcode][0];
                    $('#location_id').val(location_id);
                    qty_id = locs.ST[barcode][1];
                    $('#barcode').val('');
                    $('#barcode').focus();
                    return false;
                }
            } else {
                showError('err_msg', 'Location scan is not available', 3000);
                $('#barcode').val('');
                $('#barcode').focus();
                return false;
            }
            showError('err_msg', 'SKU barcode or Location is not correct ', 3000);
        <?php } else { ?>
            showError('err_msg', 'SKU barcode is not correct', 3000);
        <?php } ?>
        $('#barcode').val('');
        $('#barcode').focus();
        return false;
    });

    $('#btn_conf').click(() => {
        if ($('#suppliers').val() == 0) {
            showError('err_msg', 'Choose company', 3000);
            $('#suppliers').focus();
            return false;
        }
        $('#frm_conf').submit();
    });
</script>
