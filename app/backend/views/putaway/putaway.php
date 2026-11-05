<?php

use app\core\entities\Batch;
use app\core\helpers\Arr;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\entities\SKU;

/**
 * @var User $authUser
 * @var array $SKUs
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Putaway Queue</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-sign-in-alt"></i>
                        SKUs list (<?= count($SKUs); ?> item<?= count($SKUs) > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
                <?php if (count($SKUs)) { ?>
                    <div class="col-auto">
                        <a class="btn btn-success btn-icon-split" href="/goods_in/print_putaway/" target="_blank">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-print"></i>
                            </span>
                            <span class="d-none d-md-block text">Print list</span>
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($SKUs)) { ?>
                <form method="post" action="/goods_in/putaway_SKU" id="putawayFrm">
                    <input type="hidden" name="location_id" id="location_id"/>
                    <input type="hidden" name="qty" id="qty" value=""/>
                    <input type="hidden" id="batch_id" name="batch_id"/>
                    <input type="hidden" id="SKU_id" name="SKU_id"/>
                    <div class="row justify-content-md-center mb-3">
                        <div class='input-group col-lg-10'>
                            <?php (new Scan([
                                'id' => 'search',
                                'placeholder' => 'Scan SKU barcode',
                                'type' => Scan::TYPE__SKU,
                            ], [
                                'class' => 'js-btn-scan',
                                'data' => [
                                    'send' => 'putawayFrm',
                                    'locations' => json_encode(array_values(array_unique(Arr::column($SKUs, 'location_id')))),
                                    'suppliers' => json_encode(array_values(array_unique(Arr::column($SKUs, 'supplier_id')))),
                                    'batches' => json_encode(array_values(array_unique(Arr::column($SKUs, 'batch_id')))),
                                    'type' => '1',
                                    'reason' => '2',
                                    'need_loc' => 'true',
                                ],
                            ]))->render(); ?>
                        </div>
                        <div class="col-lg-10 mt-2 d-none" id="not_SKU"></div>
                    </div>
                </form>
                <div class="row">
                    <?php foreach ($SKUs as $SKU) { ?>
                        <div class="col-sm-6 col-md-4 col-xl-2 my-1"
                            data-scan-barcode="<?= View::toHtml($SKU['barcode']); ?>"
                            data-scan-type="<?= Scan::TYPE__SKU; ?>"
                        >
                            <div class="flex-fill text-center border rounded border-<?= !SKU::isActive($SKU['state']) ? 'danger' : 'info'; ?> p-2" style="border-width: 2px !important">
                                <?= '<b>' . View::toHtml($SKU['name']) . '</b> (#' . View::toHtml($SKU['SKU_id']) . ')'; ?><br/>
                                <?php if (!empty($SKU['batch_id'])) { ?>
                                    <span class="text-xs"><hr class="mt-0 mb-0"/>
                                        <?= Batch::toBatchInfo($SKU['number'], $SKU['expired']); ?>
                                        <hr class="mt-0 mb-1"/>
                                    </span>
                                <?php } ?>
                                <b class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></b><br/>
                                <?php if (!empty($SKU['photo'])) { ?>
                                    <?php if ($link = SKU::showPhotoSKU(addslashes($SKU['photo']))) { ?>
                                        <img class="img-thumbnail" style="max-height: 70px;" src="<?= $link ?>" /><br/>
                                    <?php } ?>
                                <?php } ?>
                                <b>Quantity</b>: <?= View::toHtml($SKU['qty']); ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else
                echo Html::warning('Putaway queue is empty');
            ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_SKU_batch.php'; ?>
<script>
    var SKUs = <?php
            $toSKUs = [];
            foreach ($SKUs as $SKU)
                $toSKUs[$SKU['SKU_id'] . '_' . (empty($SKU['batch_id']) ? '.' : $SKU['batch_id']) . '_' . $SKU['location_id']] = ['barcode' => $SKU['barcode'], 'qty' => $SKU['qty']];
            echo View::toJs($toSKUs);
        ?>;

    $('#putawayFrm').submit(function() {
        var batch_id = $('#batch_id').val();
        if (batch_id == '')
            batch_id = '.';
        var index = $('#SKU_id').val() + '_' + batch_id + '_' + $('#location_id').val();
        if (index in SKUs) {
            $('#qty').val(SKUs[index].qty);
            return true;
        }
        $('#search').val('');
        $('#search').focus();
        showError('not_SKU', 'SKU not found in PA zone', 3000);
        return false;
    });
</script>