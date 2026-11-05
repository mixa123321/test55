 <?php

use app\core\entities\Batch;
use app\core\entities\Location;
use app\core\entities\SKU;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\modal\InfoModal;

/**
 * @var Location $location
 * @var SKU $SKU
 * @var string $SKU_barcode
 * @var Batch $batch
 * @var int $qty
 * @var int $actual
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reconciliation</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-sign-in-alt"></i>
                        Location <?= View::toHtml($location->getLocation($warehouse_label)); ?>
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
                <div class="col-md-10">
                    <b data-scan-barcode="<?= View::toHtml($SKU_barcode); ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                        <div class="row text-success text-lg">
                            <div class="col text-center">
                                <?= View::toHtml($SKU->getName()); ?>
                            </div>
                        </div>
                        <div class="row justify-content-center">
                            <?php if (!empty($SKU->getPhoto())) { ?>
                                <img class="img-responsive" style="max-height: 300px; max-width: 250px" src="/image/public?path=<?= View::toHtml($SKU->getPhoto()); ?>" />
                            <?php } ?>
                        </div>
                        <?php if (!empty($batch)) { ?>
                            <div class="row my-2">
                                <span class="col text-center border-bottom border-top">
                                    <b>Batch#:</b> <?= View::toHtml($batch->getNumber()); ?><br/><b>Exp#:</b> <?= date('jS M Y', strtotime($batch->getExpired())); ?>
                                </span>
                            </div>
                        <?php } ?>
                        <div class="row justify-content-center">
                            <span class="text-success text-lg"><b class="scan-barcode"><?= View::toHtml($SKU_barcode); ?></b> (<b>#<?= $SKU->getId(); ?></b>) x</span>&nbsp;
                            <span class="text-primary text-lg"><?= $qty; ?></span>
                        </div>
                    </b>
                    <span class="js-scan-barcode" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                        <input type="hidden" name="loc_id" value="<?= $location->getId(); ?>"/>
                        <input type="hidden" name="SKU_id" value="<?= $SKU->getId(); ?>"/>
                        <input type="hidden" name="batch_id" value="<?= empty($batch) ? '' : $batch->getId(); ?>"/>
                        <input type="hidden" name="qty" value="<?= $qty; ?>"/>
                        <div class='row'>
                            <input class="form-control text-center js-quantity"
                                   type="text"
                                   id="quantity"
                                   name="quantity"
                                   autofocus
                            />
                        </div>
                        <div class='row'>
                            <button class="btn btn-success form-control js-confirm-btn" type="submit"
                                    data-barcode="<?= View::toHtml($SKU_barcode); ?>"
                            >Confirm (<?= $actual; ?>)</button>
                        </div>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/housekeeping/SKUs/?search=<?= urlencode((string)$location->getLocation($warehouse_label)); ?>">
                        <span class="icon text-white-50">
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
 $counting_confirm_content = '<div class="row">
                                <div class="col-12" id="counting-confirm-explanation"></div>
                             </div>
                             <div class="row">
                                <div class="col-12 mb-1 form-group">
                                    <div class="alert alert-danger d-none" id="counting_confirm_error"></div>
                                    <div class="form-group-element">
                                        <input name="reason" id="js-reconciliation-reason" type="text" class="form-control form-control-lg" placeholder=" " maxlength="255">
                                        <label for="reason">Reconciliation Reason <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                             </div>';
 $counting_confirm = new ConfirmModal('countingConfirm', 'Confirm counting');
 echo $counting_confirm->addContent($counting_confirm_content)->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'countingConfirm'])->put();

 $min_q_modal_content = '<div class="row">
                            <div class="col-12" id="countingErrorText"></div>
                         </div>';
 $min_q_modal = new InfoModal('countingError', 'Warning');
 echo $min_q_modal->addContent($min_q_modal_content)->put();
 ?>
<script>
    $(document).ready(function() {
        let qty_scan = <?= $actual; ?>;
        let qty = <?= (int)$qty; ?>;
        let loc_id = <?= (int)$location->getId(); ?>;
        let batch_id = <?= View::toJs(empty($batch) ? '' : (int)$batch->getId()) ?>;
        let SKU_id = <?= (int)$SKU->getId(); ?>;
        let min_q = 0;
        let quantity = $('#quantity');
        let js_confirm_btn = $('.js-confirm-btn');

        function changeQuantity() {
            let quantity = $('#quantity');
            let quantity_val = quantity.val().trim();
            if (this === document.activeElement && quantity_val === '') {
                if (min_q > qty_scan) {
                    let text = '<div class="alert alert-danger row m-0 p-1">' +
                        '<div class="col-auto p-1"><i class="fa fa-fw fa-exclamation-circle"></i></div>' +
                        '<div class="col p-1">Minimal value x ' + min_q + '</div>' +
                        '</div>';
                    $('#countingErrorText').html(text);
                    <?= $min_q_modal->show(); ?>;
                    return false;
                } else if (qty === qty_scan) {
                    sendPostForm({'loc_id': loc_id, 'SKU_id': SKU_id, 'batch_id': batch_id, 'qty': qty, 'quantity': qty_scan, 'reason': 'Nothing to change'}, '/housekeeping/counting_change');
                    return true;
                }
                let text = '';
                text += "<div><b>Are you sure there are " + qty_scan + " items in the location?</b></div>";
                text += "<div>Enter the reason for the reconciliation.</div>";
                $('#counting-confirm-explanation').html(text);
                <?= $counting_confirm->show(); ?>;
                return;
            }
            let barcodes = $('.js-confirm-btn').data('barcode').toString().split(', ');
            if (barcodes.includes(quantity_val))
                qty_scan++;
            else {
                let num = parseInt(quantity_val, 10);
                if (!isNaN(num) && num >= 0)
                    qty_scan = num;
            }
            quantity.val('');
            js_confirm_btn.html('Confirm (' + qty_scan + ')');
        }

        quantity.keyup(function () {
            js_confirm_btn.html($(this).val().trim() !== '' ? 'Scan...' : ('Confirm (' + qty_scan + ')'));
        });
        quantity.bind('keypress', function (e) {
            if (e.keyCode === 13) {
                changeQuantity();
            }
        });
        js_confirm_btn.click(changeQuantity);
        $('#countingConfirm').click(function () {
            let reason = $('#js-reconciliation-reason').val();
            if (reason.length === 0) {
                showError('counting_confirm_error', 'Please, fill reason', 3000);
                return false;
            }
            sendPostForm({'loc_id': loc_id, 'SKU_id': SKU_id, 'batch_id': batch_id, 'qty': qty, 'quantity': qty_scan, 'reason': reason}, '/housekeeping/counting_change');
        });
    });
</script>