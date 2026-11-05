<?php

use app\core\entities\Location;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var int $ASN_id
 * @var string $barcode
 * @var int $quantity
 * @var int $qty
 * @var array $locs
 * @var SKU $SKU
 * @var array $barcodes
 * @var Location $cart
 * @var int $PA_id
 * @var float $weight
 * @var bool $unexpected
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Goods Arrival</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-sign-in-alt"></i>
                        <?php if ($ASN_id == -1) { ?>
                            Unexpected ASN
                        <?php } else { ?>
                            ASN #<?= $ASN_id; ?>
                        <?php } ?>.
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
                        <a class="btn btn-primary btn-icon-split" href="/cart/arrival/?back=/goods_in/arrival_SKUs&id=<?= $ASN_id; ?>&sku=<?= urlencode($barcode); ?>">
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-shopping-cart"></i>
                            </span>
                            <span class="d-none d-md-block text">Change trolley</span>
                        </a>
                    </div>
                <?php } ?>
                <?php if ($ASN_id != -1) { ?>
                    <div class="col-auto">
                        <a class="btn btn-success btn-icon-split text-white" id="gen_barcode" target="_blank">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-barcode"></i>
                        </span>
                            <span class="d-none d-md-block text">Print barcode</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <a class="btn btn-success btn-icon-split" href="/goods_in/print_ASN/?id=<?= $ASN_id; ?>" target="_blank">
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-print"></i>
                            </span>
                            <span class="d-none d-md-block text">Print ASN</span>
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-1">
                    <ul class="nav nav-pills nav-stacked">
                    </ul>
                </div>
                <div class="col-md-10">
                    <form method="get" name="frmSKU" action="/goods_in/arrival_SKUs">
                        <input type="hidden" id="id" name="id" value="<?= $ASN_id; ?>"/>
                        <input type="hidden" id="sku" value="<?= urlencode($barcode); ?>" />
                        <div class="row justify-content-md-center mb-2">
                            <div class='input-group col-lg-10'>
                                <?php
                                    if ($unexpected)
                                        echo Html::error("Scanned item ($barcode) is not expected to be a part of this ASN. You can continue processing it as usual or press \"Cancel\" if you are not sure what to do with it.");
                                 (new Scan([
                                    'id' => 'barcode',
                                    'name' => 'barcode',
                                    'placeholder' => 'Scan SKU barcode or Location',
                                    'type' => Scan::TYPE__SKU | Scan::TYPE__LOCATION,
                                ], ['onclick' => 'return changeSKU(frmSKU);']))->render(); ?>
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
                                    <?php if (!empty($SKU->getPhoto())) { ?>
                                        <div class="col-auto">
                                            <img class="img-thumbnail"
                                                data-scan-barcode="<?= View::toHtml($barcode); ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>"
                                                style="max-height: 70px;" src="/image/public?path=<?= View::toHtml($SKU->getPhoto()); ?>"
                                            />
                                        </div>
                                    <?php } ?>
                                    <div class="col-auto text-xs d-md-none">
                                        <p class="form-control-static mb-1" data-scan-barcode="<?= View::toHtml($barcode); ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                                            <?= View::toHtml($SKU->getName()); ?><br/>
                                            <b class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode(implode(', ', $barcodes)); ?></b><br/>
                                            SKU ID: <?= $SKU->getId(); ?>
                                        </p>
                                    </div>
                                    <div class="col d-none d-md-block">
                                        <p class="form-control-static mb-1" data-scan-barcode="<?= View::toHtml($barcode); ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                                            <?= View::toHtml($SKU->getName()); ?><br/>
                                            <b class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode(implode(', ', $barcodes)); ?></b><br/>
                                            SKU ID: <?= $SKU->getId(); ?>
                                        </p>
                                        <div class="form-group row mb-1">
                                            <div class='col-auto'>
                                                <p class="form-control-static mt-2">Quantity</p>
                                            </div>
                                            <div class='col col-sm-4 col-lg-3'>
                                                <input class="form-control" type="number" id="qty1" name="qty1" min="1" value="<?= $qty; ?>" autocomplete="off"/>
                                            </div>
                                        </div>
                                        <div class="form-group row mb-1">
                                            <div class='col-auto'>
                                                <p class="form-control-static mt-2">Batch No.</p>
                                            </div>
                                            <div class='col col-sm-4 col-lg-3'>
                                                <input class="form-control" type="input" id="batch_number" name="batch_number"  value="<?= !empty($ASN_SKUs['batch_number']) ? View::toHtml($ASN_SKUs['batch_number']) : ''; ?>" autocomplete="off"/>
                                            </div>
                                        </div>
                                        <div class="form-group row mb-1">
                                            <div class='col-auto'>
                                                <p class="form-control-static mt-2">Expired</p>
                                            </div>
                                            <div class='col col-sm-4 col-lg-3'>
                                                <input class="form-control" type="date" id="batch_expired" name="batch_expired"  value="<?= !empty($ASN_SKUs['batch_expired']) ? (new DateTime($ASN_SKUs['batch_expired']))->format('Y-m-d') : ''; ?>" autocomplete="off"/>
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
                                <input class="form-control" type="number" id="qty2" name="qty2" min="1" value="<?= $qty; ?>" autocomplete="off"/>
                            </div>
                        </div>
                        <div class="form-group form-row d-md-none mb-1">
                            <div class='col-auto'>
                                <p class="form-control-static mt-2">Batch No.</p>
                            </div>
                            <div class='col-6'>
                                <input class="form-control" type="input" id="batch_number2" name="batch_number2" value="<?= !empty($ASN_SKUs['batch_number']) ? View::toHtml($ASN_SKUs['batch_number']) : ''; ?>" autocomplete="off"/>
                            </div>
                        </div>
                        <div class="form-group form-row d-md-none mb-1">
                            <div class='col-auto'>
                                <p class="form-control-static mt-2">Expired.</p>
                            </div>
                            <div class='col-6'>
                                <input class="form-control" type="date" id="batch_expired2" name="batch_expired2" value="<?= !empty($ASN_SKUs['batch_expired']) ? (new DateTime($ASN_SKUs['batch_expired']))->format('Y-m-d') : ''; ?>" autocomplete="off"/>
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
                                    <?php if (count($locs)) { ?>
                                        <label class="btn btn-success active mr-1" style="width:33%">
                                            <input type="radio" name="options" id="to_location" autocomplete="off" checked>
                                            <i class="fas fa-fw fa-map-marker-alt"></i><br/><span class="text-xs" id="loc_name">To location*</span>
                                        </label>
                                    <?php } ?>
                                    <label class="btn btn-success mx-1" style="width:33%">
                                        <input type="radio" name="options" id="to_PA" autocomplete="off">
                                        <i class="fas fa-fw fa-map-marker-alt"></i><br/><span class="text-xs">To PA zone</span>
                                    </label>
                                    <?php if ($quantity > 0) { ?>
                                        <label class="btn btn-success mx-1" style="width:33%">
                                            <input type="radio" name="options" id="no_verify" autocomplete="off">
                                            <b><?= $quantity; ?></b><br/><span class="text-xs">No verify</span>
                                        </label>
                                    <?php } ?>
                                    <?php if (!empty($cart)) { ?>
                                        <label class="btn btn-success ml-1" style="width:33%">
                                            <input type="radio" name="options" id="to_cart" autocomplete="off">
                                            <i class="fas fa-fw fa-shopping-cart"></i><br/><span class="text-xs">Trolley</span>
                                        </label>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php if (count($locs)) { ?>
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
                    <button class="btn btn-success btn-icon-split" id="btn_conf" type="button" onclick="confArrival(frmSKU);"<?= count($locs) ? ' disabled' : ''; ?>>
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
$modal_cancel_arrival = new ConfirmModal('cancelArrival', 'Cancel Goods Arrival');
echo $modal_cancel_arrival->addContent('Are you sure?')
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'btn-cancel-arrival'])
    ->put();
?>
<script>
    var ASN_id = <?= $ASN_id; ?>;
    var locs = <?= View::toJs($locs) ?>;
    var loc_to = '<?= count($locs) ? 'to_location' : 'to_PA'; ?>';
    var barcodes = <?= View::toJs(array_map('strtoupper', $barcodes)) ?>;
    var location_id = -1;
    var qty_id = 0;
    var gen_barcode = <?= View::toJs((string)$barcode); ?>;

    $('#gen_barcode').click(() => {
        $('#gen_barcode').attr('href', '/goods_in/print_new_barcode/?id=' + ASN_id + '&barcode=' + gen_barcode);
    });

    $('#location_to :input').change(function() {
        loc_to = this.id;
        if (this.id !== 'to_location')
            btn_conf.disabled = false;
        else
        if (location_id == -1)
            btn_conf.disabled = true;
    });

    $('#qty1').change(function() {
        $('#qty2').val($('#qty1').val());
    });

    $('#qty2').change(function() {
        $('#qty1').val($('#qty2').val());
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

    $('#qty1').blur(function() {
        if ($('#qty1').val() < 1) {
            $('#qty1').val(1);
            $('#qty2').val(1);
            showError('err_msg', 'Quantity must be greater than 0', 3000);
        }
    });

    $('#qty2').blur(function() {
        if ($('#qty2').val() < 1) {
            $('#qty1').val(1);
            $('#qty2').val(1);
            showError('err_msg', 'Quantity must be greater than 0', 3000);
        }
    });

    function changeSKU(frm)
    {
        if (frm.qty1 === document.activeElement || frm.qty2 === document.activeElement) {
            if (frm.qty1.value < 1 || frm.qty2.value < 1) {
                frm.qty1.value = frm.qty2.value = 1;
                showError('err_msg', 'Quantity must be greater than 0', 3000);
            }
            frm.barcode.focus();
            return false;
        }
        var barcode = frm.barcode.value.trim().toUpperCase();
        if (barcode == '') {
            showError('err_msg', 'Scan SKU barcode or Location', 3000);
            frm.barcode.value = '';
            frm.barcode.focus();
            return false;
        }
        if (barcodes.includes(barcode)) {
            frm.qty1.value++;
            frm.qty2.value++;
            frm.barcode.value = '';
            frm.barcode.focus();
            return false;
        }
        if (loc_to === 'to_location') {
            if (typeof locs.PF !== 'undefined' && barcode in locs.PF) {
                $('#loc_name').text(barcode);
                $('#not_location').prop('classList').add('d-none');
                btn_conf.disabled = false;
                location_id = locs.PF[barcode][0];
                qty_id = locs.PF[barcode][1];
                frm.barcode.value = '';
                frm.barcode.focus();
                return false;
            }
            if (typeof locs.ST !== 'undefined' && barcode in locs.ST) {
                $('#loc_name').text(barcode);
                $('#not_location').prop('classList').add('d-none');
                btn_conf.disabled = false;
                location_id = locs.ST[barcode][0];
                qty_id = locs.ST[barcode][1];
                frm.barcode.value = '';
                frm.barcode.focus();
                return false;
            }
        } else {
            showError('err_msg', 'Location scan is not available', 3000);
            frm.barcode.value = '';
            frm.barcode.focus();
            return false;
        }
        showError('err_msg', 'SKU barcode or Location is not correct ', 3000);
        frm.barcode.value = '';
        frm.barcode.focus();
        return false;
    }

    function confArrival(frm)
    {
        var qty = frm.qty1.value;
        var batch_number = frm.batch_number.value;
        var batch_expired = frm.batch_expired.value;
        switch(loc_to) {
            case 'to_location':
                if (frm.qty1.value > qty_id) {
                    showError('err_msg', 'Quantity cannot be more than ' + qty_id, 3000);
                    frm.barcode.focus();
                    return;
                }
                break;
            case 'to_PA':
                location_id = <?= $PA_id; ?>;
                break;
            case 'no_verify':
                location_id = <?= $PA_id; ?>;
                qty = <?= $quantity; ?>;
                break;
            case 'to_cart':
                location_id = <?= $cart->getId(); ?>;
                break;
        }
        location = '/goods_in/arrival_end/?id=' + ASN_id +
            '&barcode=' + frm.sku.value +
            '&qty=' + qty +
            '&batch_number=' + batch_number +
            '&batch_expired=' + batch_expired +
            '&loc=' + location_id;
    }

    $('#btn_cancel').click(() => {
        <?= $modal_cancel_arrival->show(); ?>
        return false;
    });

    $('#btn-cancel-arrival').click(function() {
        <?= $modal_cancel_arrival->hide(); ?>
        location = '/goods_in/arrival';
    });
</script>
