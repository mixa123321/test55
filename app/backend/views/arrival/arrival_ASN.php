<?php

use app\core\entities\SKU;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var int $ASN_id
 * @var array $SKUs
 * @var array $SKUs_response
 * @var bool $enough_SKU
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
                        ASN #<?= $ASN_id; ?>
                    </span>
                    </h6>
                </div>
                <?php if ($enough_SKU) { ?>
                    <a class="btn btn-success btn-icon-split mb-1" href='/goods_in/complete/?id=<?= $ASN_id; ?>&p=arrival'>
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-power-off"></i>
                        </span>
                        <span class="text text-white" style="width: 100px">Complete</span>
                    </a>
                <?php } ?>
                <div class="col-auto">
                    <a class="btn btn-success btn-icon-split" href="/goods_in/print_ASN/?id=<?= $ASN_id; ?>" target="_blank">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-print"></i>
                        </span>
                        <span class="d-none d-md-block text">Print ASN</span>
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
                    <form method="get" name="frmSKU" action="/goods_in/arrival_SKUs">
                        <input type="hidden" id="id" name="id" value="<?= $ASN_id; ?>"/>
                        <div class="row justify-content-md-center">
                            <div class='input-group col-lg-10'>
                                <?php (new Scan([
                                        'id' => 'barcode',
                                        'name' => 'sku',
                                        'placeholder' => 'Scan SKU barcode',
                                        'type' => Scan::TYPE__SKU,
                                    ], ['id' => 'btn_scan']))->render(); ?>
                            </div>
                        </div>
                        <div class="row justify-content-center mt-2">
                            <div class="col-md-10">
                                <?= Html::info(
                                    "<div class='row align-items-center'>
                                        <div class='col' id='show_barcode'>
                                        </div>
                                        <div class='col-auto mt-0 mr-2'>
                                            <button class='btn btn-warning btn-icon-split' id='unexpected' type='button'>
                                                <span class='icon text-white-50'>
                                                    <i class='fas fa-fw fa-ghost'></i>
                                                </span>
                                                <span class='text text-white' id='not_SKU'>Add unbarcoded item</span>
                                            </button>
                                        </div>
                                    </div>", false
                                ); ?>
                                <div class="alert alert-info pl-1">
                                    <?php if (!empty($SKUs_response)) { ?>
                                        <ul class="pl-4">
                                            <?php foreach ($SKUs_response as $barcode => $msg) { ?>
                                                <?php $SKU = new SKU(Arr::findOneBy($SKUs, 'barcode', $barcode) ?? []); ?>
                                                <li data-scan-barcode="<?= View::toHtml($barcode); ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>">
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <b class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode($barcode) ?></b>:<br/>
                                                            <?= $msg ?>
                                                        </div>
                                                        <div class="col-auto">
                                                            <?= View::toHtml($SKU->getName()); ?><br/>
                                                            <?php if ($SKU->getPhoto()): ?>
                                                                <img class="img-thumbnail"
                                                                    data-scan-barcode="<?= View::toHtml($barcode); ?>" data-scan-type="<?= Scan::TYPE__SKU; ?>"
                                                                    style="max-height: 70px;" src="/image/public?path=<?= View::toHtml($SKU->getPhoto()); ?>"
                                                                />
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/goods_in/arrival">
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
<script>
    $('#unexpected').click(() => {
        location = '/goods_in/unexpected/?id=<?= $ASN_id; ?>&p=arrival_SKUs&name=' + new_barcode;
    });

    $('#btn_scan').click(() => {
        var barcode = $('#barcode').val().trim();
        if (barcode == '') {
            $('#barcode').val('');
            $('#barcode').focus();
            $('#show_barcode').text('');
            $('#not_SKU').text('Add unbarcoded item');
            return false;
        }
        var SKU = SKUs.filter((item) => {
            return item.barcodes && item.barcodes.includes(barcode);
        });

        if (SKU.length == 0) {
            new_barcode = $('#barcode').val();
            $('#barcode').val('');
            $('#barcode').focus();
            $('#show_barcode').html("<b>Scanned:</b> " + html_escape(new_barcode));
            $('#not_SKU').text('Add unexpected item');
            return false;
        }
        return true;
    });

    var new_barcode = '';
    var SKUs = <?= View::toJs($SKUs) ?>;
</script>
