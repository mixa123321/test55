<?php

use app\core\entities\Batch;
use app\core\entities\Location;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\helpers\scan\Scan;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var SKU $SKU
 * @var array $barcodes
 * @var int $quantity
 * @var int $loc_id
 * @var int $qty
 * @var array $locs
 * @var Location $cart
 * @var float $weight
 * @var Batch $batch
 */

$barcode = implode(', ', $barcodes);

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Putaway</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary mt-2">
                    <span>
                        <i class="fas fa-fw fa-sign-in-alt"></i>
                        SKU #<?= $SKU->getId(); ?>.
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
                        <a class="btn btn-primary btn-icon-split" href="/cart/putaway/?back=/goods_in/putaway_SKU&SKU_id=<?= $SKU->getId(); ?>&location_id=<?= $loc_id; ?>&qty=<?= $qty; ?>">
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-shopping-cart"></i>
                            </span>
                            <span class="d-none d-md-block text">Change trolley</span>
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
                    <form method="post" action="/goods_in/putaway_SKU">
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
                            <div class="col-lg-10">
                                <span class="text-success"><b>Goods</b></span>
                                <div class="row">
                                    <?php if (!empty($SKU->getPhoto())) { ?>
                                        <div class="col-auto">
                                            <img class="img-thumbnail" style="max-height: 70px;"
                                                src="/image/public?path=<?= View::toHtml($SKU->getPhoto()); ?>"
                                                data-scan-barcode="<?= View::toHtml($barcode); ?>"
                                                data-scan-type="<?= Scan::TYPE__SKU; ?>"
                                            />
                                        </div>
                                    <?php } ?>
                                    <div class="col-auto text-xs d-md-none">
                                        <p class="form-control-static mb-1" data-scan-type="<?= Scan::TYPE__SKU; ?>"
                                            data-scan-barcode="<?= View::toHtml($barcode); ?>"
                                        >
                                            <?= View::toHtml($SKU->getName()); ?><br/>
                                            <b class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode(implode(', ', $barcodes)); ?></b><br/>
                                            SKU ID: <?= $SKU->getId(); ?>
                                            <?= empty($batch) ? '' : ('<br/>' . $batch->getInfo()); ?>
                                        </p>
                                    </div>
                                    <div class="col d-none d-md-block">
                                        <p class="form-control-static mb-1" data-scan-barcode="<?= View::toHtml($barcode); ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                                            <?= View::toHtml($SKU->getName()); ?><br/>
                                            <b class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode(implode(', ', $barcodes)); ?></b><br/>
                                            SKU ID: <?= $SKU->getId(); ?>
                                            <?= empty($batch) ? '' : ('<br/>' . $batch->getInfo()); ?>
                                        </p>
                                        <div class="form-group row mb-1">
                                            <div class='col-auto'>
                                                <p class="form-control-static mt-2">Quantity (<?= $quantity; ?>)</p>
                                            </div>
                                            <div class='col col-sm-4 col-lg-3'>
                                                <input class="form-control" type="number" id="qty1" name="qty1" min="1" max="<?= $quantity; ?>" value="<?= $qty; ?>" autocomplete="off"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group form-row d-md-none mb-1">
                            <div class='col-auto'>
                                <p class="form-control-static mt-2">Quantity (<?= $quantity; ?>)</p>
                            </div>
                            <div class='col-6'>
                                <input class="form-control" type="number" id="qty2" name="qty2" min="1" max="<?= $quantity; ?>" value="<?= $qty; ?>" autocomplete="off"/>
                            </div>
                        </div>
                        <?php if (count($locs)) {
                            echo '<div class="row mt-1 px-md-3 px-2"><div class="col text-center"><span class="text-success"><b>Available locations</b></span><div class="row justify-content-center">';
                            if (!empty($locs[Location::PF])) {
                                echo "<div class='col-md-5 border border-success rounded m-1'><span class='text-success'>Pick Face locations</span><br/>";
                                foreach ($locs[Location::PF] as $key => $loc)
                                    echo View::toHtml($key) . ' (' . View::toHtml($loc[1]) . ')<br/>';
                                echo '</div>';
                            }
                            if (!empty($locs[Location::ST])) {
                                echo "<div class='col-md-5 border border-success rounded m-1'><span class='text-success'>Storage locations</span><br/>";
                                foreach ($locs[Location::ST] as $key => $loc)
                                    echo View::toHtml($key) . ' (' . View::toHtml($loc[1]) . ')<br/>';
                                echo '</div>';
                            }
                            if (!empty($locs_processing)) {
                                echo "<div class='col-md-5 border border-success rounded m-1'><span class='text-success'>Processing locations</span><br/>";
                                foreach ($locs_processing as $key => $loc)
                                    echo View::toHtml($loc['name']) . "<br/>";
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
                                        <label class="btn btn-success mr-1" style="width:33%">
                                            <input type="radio" name="options" id="to_location" autocomplete="off">
                                            <i class="fas fa-fw fa-map-marker-alt"></i><br/><span class="text-xs" id="loc_name">To location*</span>
                                        </label>
                                    <?php } ?>
                                    <?php if ($cart->getId()) { ?>
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
$modal_cancel_putaway = new ConfirmModal('cancelPutaway', 'Cancel Putaway');
echo $modal_cancel_putaway->addContent('Are you sure?')
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'btn-cancel-putaway'])
    ->put();
?>
<script>
    $('#btn_cancel').click(() => {
        <?= $modal_cancel_putaway->show(); ?>
        return false;
    });

    $('#btn-cancel-putaway').click(function() {
        <?= $modal_cancel_putaway->hide(); ?>
        location = '/goods_in/putaway';
    });

    $('#qty1').change(function() {
        $('#qty2').val($('#qty1').val());
    });

    $('#qty2').change(function() {
        $('#qty1').val($('#qty2').val());
    });

    $('#qty1').blur(function() {
        if ($('#qty1').val() < 1) {
            $('#qty1').val(1);
            $('#qty2').val(1);
            showError('err_msg', 'Quantity must be greater than 0', 3000);
            $('#barcode').focus();
        }
        if ($('#qty1').val() > quantity) {
            $('#qty1').val(quantity);
            $('#qty2').val(quantity);
            showError('err_msg', 'Quantity cannot be more than ' + quantity, 3000);
            $('#barcode').focus();
        }
    });

    $('#qty2').blur(function() {
        if ($('#qty2').val() < 1) {
            $('#qty1').val(1);
            $('#qty2').val(1);
            showError('err_msg', 'Quantity must be greater than 0', 3000);
            $('#barcode').focus();
        }
        if ($('#qty2').val() > quantity) {
            $('#qty1').val(quantity);
            $('#qty2').val(quantity);
            showError('err_msg','Quantity cannot be more than ' + quantity, 3000);
            $('#barcode').focus();
        }
    });

    var loc_to = '';
    var locs = <?= View::toJs($locs) ?>;
    var locs_processing = <?= View::toJs($locs_processing) ?>;
    var location_id = -1;
    var qty_id = 0;
    let barcodes = <?= View::toJs($barcodes) ?>;
    var quantity = <?= $quantity; ?>;

    $('#location_to input:radio').change(function() {
        loc_to = this.id;
        if (this.id !== 'to_location')
            btn_conf.disabled = false;
        else
            if (location_id == -1)
                btn_conf.disabled = true;
    });

    $('#btn_conf').click(() => {
        var qty = $('#qty1').val();
        if (qty > quantity) {
            showError('err_msg', 'Quantity cannot be more than ' + quantity, 3000);
            $('#barcode').focus();
            return;
        }
        switch(loc_to) {
            case 'to_location':
                if (qty > qty_id) {
                    showError('err_msg', 'Quantity cannot be more than ' + qty_id, 3000);
                    $('#barcode').focus();
                    return;
                }
                break;
            case 'to_cart':
                location_id = <?= $cart->getId(); ?>;
                break;
        }
        location = '/goods_in/putaway_end/?id=<?= $SKU->getId(); ?>' +
            '&PA_id=<?= $loc_id; ?>' +
            '&qty=' + qty +
            '&batch_id=<?= empty($batch) ? 0 : $batch->getId(); ?>' +
            '&loc=' + location_id;
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
        if (barcode === '') {
            showError('err_msg', 'Scan SKU barcode or Location', 3000);
            $('#barcode').val('');
            $('#barcode').focus();
            return false;
        }
        if (barcodes.includes(barcode)) {
            var nums = $('#qty1').val()*1 + 1;
            $('#qty1').val(nums);
            $('#qty2').val(nums);
            $('#barcode').val('');
            $('#barcode').focus();
            return false;
        }
        if (loc_to === 'to_location') {
            if (typeof locs.PF !== 'undefined' && barcode in locs.PF) {
                $('#loc_name').text(barcode);
                $('#not_location').prop('classList').add('d-none');
                btn_conf.disabled = false;
                location_id = locs.PF[barcode][0];
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
                qty_id = locs.ST[barcode][1];
                $('#barcode').val('');
                $('#barcode').focus();
                return false;
            }
            if (barcode in locs_processing) {
                $('#loc_name').text(barcode);
                $('#not_location').prop('classList').add('d-none');
                btn_conf.disabled = false;
                location_id = locs_processing[barcode]['id'];
                qty_id = 999999999999999;
                $('#barcode').val('');
                $('#barcode').focus();
                return false;
            }
            showError('err_msg', 'Location scan is not available', 3000);
            $('#barcode').val('');
            $('#barcode').focus();
            return false;
        } else {
            showError('err_msg', 'SKU barcode is not correct ', 3000);
            $('#barcode').val('');
            $('#barcode').focus();
            return false;
        }
    });
</script>
