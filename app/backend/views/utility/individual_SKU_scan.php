<?php

use app\core\entities\SKU;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\helpers\modal\SwitchModal;

/**
 * @var string $action
 * @var string $title
 * @var array $data
 * @var array $SKU
 * @var string $instruction
 * @var string $back
 * @var int $actual
 */

if (!empty($instruction) && is_array($instruction))
    $instruction = implode('<br/>', $instruction);
$actual = $actual ?? 1;

$query_status_candidates_modal = new SwitchModal('queryStatus', 'Query orders');
echo $query_status_candidates_modal->addContent('<div class="alert alert-info mb-0">Are you sure you want to reconcile stock to 0?<br>Orders with missing items moved to Query status can only be restored to Backorder.</div>')
    ->addButton('Confirm', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE, ['id' => 'confirm-query'])->put();
?>

<form method="post" action="<?= $action; ?>" id="SKU-scan-form">
    <?php foreach ($data as $key => $value) : ?>
        <input type="hidden" name="<?= $key; ?>" value="<?= View::toHtml($value); ?>" />
    <?php endforeach; ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-box"></i>
                            SKU (<?= $SKU['quantity']; ?> item<?= $SKU['quantity'] > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <?php if (!empty($instruction)) { ?>
                    <div class="col-md-10 text-center" onclick="showInstructions();">
                        <div class="alert alert-info p-1">
                            <h5 class="alert-heading mb-0">Instructions <i class="text-xs fas fa-fw fa-chevron-down"></i></h5>
                            <p class="mb-0 collapse" id="instructions"><?= View::toHtml($instruction); ?></p>
                        </div>
                    </div>
                <?php } ?>
                <div class="col-md-10 text-center js-scan-barcode" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                    <p class="form-control-static text-success text-lg"><b><?= View::toHtml("{$SKU['warehouse']}-{$SKU['type_id']}-{$SKU['zone']}-{$SKU['column']}-{$SKU['row']}"); ?></b></p>
                    <p class="form-control-static" data-scan-barcode="<?= View::toHtml($SKU['barcode']); ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                        <?= View::toHtml($SKU['name']); ?><br/>
                        <b class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></b><br/>
                        SKU ID: <?= $SKU['id']; ?>
                    </p>
                    <?php if (!empty($SKU['photo'])) {
                        $image = SKU::showPhotoSKU(urlencode($SKU['photo']));
                        if (!empty($image)) { ?>
                            <p><img class="img-responsive" style="max-height: 300px; max-width: 250px" src="<?= View::toHtml($image); ?>"
                                    data-scan-barcode="<?= View::toHtml($SKU['barcode']); ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>"
                            /></p>
                        <?php }
                    }?>
                    <?php if (!empty($SKU['batches'])) { ?>
                        <span class="text-xs"><hr class="mt-0 mb-0"/>
                            <?php foreach($SKU['batches'] as $batch) { ?>
                                <?= $batch; ?>
                                <hr class="mt-0 mb-1"/>
                            <?php } ?>
                        </span>
                    <?php } ?>
                    <div class="row justify-content-center">
                        <p class="form-control-static text-success text-lg" style="margin-top: 4px;"><b><?= $title; ?> x&nbsp;<span class="text-primary"><?= View::toHtml($SKU['quantity']); ?></span></b></p>
                    </div>
                    <div class="row">
                        <input class="form-control text-center js-quantity" type="text" name="quantity" id="quantity" autofocus inputmode="numeric" />
                    </div>
                    <input type="hidden" id="query" name="query" value="false"/>
                    <input type="hidden" id="qty" name="qty" value="<?= View::toHtml($SKU['quantity']); ?>"/>
                    <div class='row'>
                        <button class="btn btn-success form-control js-confirm-btn" type="submit"
                                data-barcode="<?= View::toHtml($SKU['barcode']); ?>"
                                data-location="<?= View::toHtml("{$SKU['warehouse']}-{$SKU['type_id']}-{$SKU['zone']}-{$SKU['column']}-{$SKU['row']}"); ?>"
                        >Confirm (<?= $actual; ?>)</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-danger btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-times"></i>
                        </span>
                        <span class="text">Cancel</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>
<script>
    var qty_scan = <?= $actual; ?>;
    $('.js-quantity').keyup(function () {
        $('.js-confirm-btn').html($(this).val().trim() !== '' ? 'Scan...' : ('Confirm (' + qty_scan + ')'));
    });
    $('.js-confirm-btn').click(function () {
        function showModalBeforeSendQuantity(quantity) {
            quantity = parseInt(quantity);
            let qty = <?= (int)$SKU['quantity']; ?>;
            if (qty > quantity) {
                <?= $query_status_candidates_modal->show(); ?>;
                return false;
            }
            return true;
        }
        var qty0 = quantity.value;
        if (this === document.activeElement || (navigator.userAgent.indexOf('Safari') != -1 && this !== quantity) /** safari fix **/ ) {
            if (qty0.trim() == '') {
                quantity.value = qty_scan;
                return showModalBeforeSendQuantity(qty_scan);
            } else
                quantity.focus();
        }
        var barcode = $(this).data('barcode');
        var barcodes = barcode.split(', ');
        if (barcodes.includes(qty0)) {
            if (qty_scan < qty.value)
                qty_scan++;
        } else {
            var location = $(this).data('location');
            if (qty0 == location) {
                quantity.focus();
            } else {
                var parts = qty0.split('-');
                if (parts.length == 5) {
                    quantity.value = qty_scan;
                    new_loc.value = qty0;
                    return true;
                } else {
                    var num = parseInt(quantity.value, 10);
                    if (!isNaN(num) && num >= 0)
                        qty_scan = Math.min(num, qty.value);
                }
            }
        }
        quantity.value = '';
        $('.js-confirm-btn').html('Confirm (' + qty_scan + ')');
        return false;
    });
    $('#confirm-query').click(function () {
        $('#query').val(true);
        $('#SKU-scan-form').trigger("submit");
    });
    $('<?= $query_status_candidates_modal->getSelector() ?> button[data-dismiss]:contains("Cancel")').click(function () {
        if (qty_scan)
            $('#SKU-scan-form').trigger("submit");
    });

    function showInstructions()
    {
        if ($('#instructions').prop('classList').contains('collapse'))
            $('#instructions').prop('classList').remove('collapse');
        else
            $('#instructions').prop('classList').add('collapse');
    }
</script>