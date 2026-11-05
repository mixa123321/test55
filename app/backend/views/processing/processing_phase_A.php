<?php

use app\core\entities\Location;
use app\core\entities\SKU;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

/**
 * @var array $movement
 * @var Location $cart
 * @var float $weight
 * @var array $consists
 * @var int $actual
 */

$qty = $consists['replenish'] + $consists['reserved'];

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Do processing movement</h1>
    </div>
    <form method="post" action="/processing/processing_phase_a">
        <input type="hidden" name="id" value="<?= View::toHtml($movement['id']); ?>" />
        <input type="hidden" name="SKU_id" value="<?= View::toHtml($movement['SKU_id']); ?>" />
        <input type="hidden" name="loc_from_id" value="<?= View::toHtml($movement['loc_from_id']); ?>" />
        <input type="hidden" name="loc_to_id" value="<?= View::toHtml($movement['loc_to_id']); ?>" />
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <div class="row">
                    <div class="col">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <span>
                                <i class="fas fa-fw fa-arrows-alt"></i>
                                Processing movement (created by <b><?= View::toHtml($movement['clogin']); ?></b> <?= View::toHtml($movement['create_date']); ?>)
                            </span>
                        </h6>
                    </div>
                </div>
            </div>
            <div class="card-body js-scan-barcode" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                <div class="text-center">
                    <p>
                        <span class="form-control-static text-success font-weight-bold text-lg"><b><?= View::toHtml($movement['lf_name']); ?></b></span><br/>
                        <span class="text-primary font-weight-bold">(<?= $qty; ?> item<?= $qty > 1 ? 's' : ''; ?><?= $consists['reserved'] ? " - {$consists['reserved']} of them reserved" : ''; ?>)</span>
                    </p>
                    <div data-scan-barcode="<?= View::toHtml($movement['barcode']); ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                        <p class="form-control-static">
                            <?= View::toHtml($movement['name']); ?><br/>
                            <b class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode($movement['barcode']); ?></b><br/>
                            SKU ID: <?= $movement['SKU_id']; ?>
                        </p>
                        <?php if (!empty($movement['photo'])) { ?>
                            <p><img class="img-responsive" style="max-height: 300px; max-width: 250px" src="<?= View::toHtml(SKU::showPhotoSKU(urlencode($movement['photo']))); ?>"/></p>
                        <?php } ?>
                    </div>
                    <div class="row justify-content-center">
                        <p class="form-control-static text-success text-lg"><b>PICK x&nbsp;<span class="text-primary"><?= View::toHtml(min($movement['quantity'], $qty)); ?></span></b></p>
                    </div>
                    <div class="row justify-content-center">
                        <div class="col-12 col-md-4">
                            <input class="form-control text-center js-quantity" type="text" name="quantity" id="quantity" autofocus />
                        </div>
                    </div>
                    <input type="hidden" id="qty" name="qty" value="<?= View::toHtml(min($movement['quantity'], $qty)); ?>"/>
                    <div class="row justify-content-center">
                        <div class="col-12 col-md-4">
                            <button class="btn btn-success form-control js-confirm-btn" type="submit"
                                    data-barcode="<?= View::toHtml($movement['barcode']); ?>"
                            >Confirm (<?= $actual; ?>)</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <button class="btn btn-danger btn-icon-split" type="button" id="btn_cancel">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-times"></i>
                            </span>
                            <span class="text">Cancel</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<?php
    $modal_cancel = new ConfirmModal('cancelMovement', 'Cancel Movement');
    echo $modal_cancel->addContent('Are you sure?')
        ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'btn-conf'])
        ->put();
?>
<script>
    var movement_id = <?= $movement['id']; ?>;
    var qty_scan = <?= $actual; ?>;

    $('#btn_cancel').click(() => {
        <?= $modal_cancel->show(); ?>;
        return false;
    });

    $('#btn-conf').click(() => {
        <?= $modal_cancel->hide(); ?>;
        location = '/processing/processing_clear/?id=' + movement_id;
    });

    $('.js-quantity').keyup(function () {
        $('.js-confirm-btn').html($(this).val().trim() !== '' ? 'Scan...' : ('Confirm (' + qty_scan + ')'));
    });

    $('.js-confirm-btn').click(function() {
        var qty0 = quantity.value;
        if (this === document.activeElement) {
            if (qty0.trim() == '') {
                quantity.value = qty_scan;
                return true;
            } else
                quantity.focus();
        }
        var barcode = $(this).data('barcode');
        var barcodes = barcode.split(', ');
        if (barcodes.includes(qty0)) {
            if (qty_scan < qty.value)
                qty_scan++;
        } else {
            var num = parseInt(quantity.value, 10);
            if (!isNaN(num) && num >= 0)
                qty_scan = Math.min(num, qty.value);
        }
        quantity.value = '';
        $('.js-confirm-btn').html('Confirm (' + qty_scan + ')');
        return false;
    });
</script>