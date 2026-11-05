<?php

use app\backend\models\SKUs;
use app\core\entities\ASN;
use app\core\entities\User;
use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var array $ASNs
 * @var string $cont_arrival
 * @var int $size
 * @var int $pages
 * @var int $current
 * @var int $supplier_id
 * @var string $search
 * @var array $filter
 */

$select_SKU_modal = new ConfirmModal('selectSKU', View::getTranslate('Select SKU'), ConfirmModal::SIZE_NORMAL);
$select_SKU_modal->includeFile('arrival/modals/select_SKU.php', [
    'supplier_id' => $supplier_id,
    'filter_type' => SKUs::DEFAULT_BARCODE_FILTER,
]);

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
                        ASNs list (<?= $size; ?> item<?= $size > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
                <form method="POST" id="srchFrm" >
                    <input type="hidden" id="supplier_id" name="supplier_id"/>
                    <input type="hidden" name="current_query" value="<?= empty($search) ? '' : View::toHtml($search) ?>">
                    <input type="hidden" name="<?= Filter::getFilterTypeKey('search'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER; ?>">
                    <div class="row justify-content-md-center mb-2">
                        <div class='input-group col-lg-10'>
                            <?php (new Scan([
                                        'id' => 'search',
                                        'name' => 'search',
                                        'placeholder' => 'SKU barcode',
                                        'type' => Scan::TYPE__SKU,
                                    ], [
                                        'class' => 'js-btn-scan',
                                        'data' => [
                                            'send' => 'srchFrm',
                                            'empty' => 'true',
                                            'suppliers' => '[]',
                                        ],
                                    ]))->render(); ?>
                        </div>
                    </div>
                    <div class="row justify-content-md-center mx-1">
                        <div class="col-lg-10 p-2 mb-2 bg-danger text-white d-none" id="err_msg">
                        </div>
                    </div>
                </form>
                <form method="get" action="/goods_in/unexpected_noASN">
                    <input type="hidden" name="p" value="arrival" />
                    <input type="hidden" name="name" value="<?= View::toHtml($search); ?>" />
                    <input type="hidden" name="supplier_id" value="<?= $supplier_id; ?>"/>
                    <div class="row justify-content-center mb-4">
                        <div class="col-md-10">
                            <?= Html::info(
                                "<div class='row align-items-center'>
                                        <div class='col'>" . (empty($search) ? '' : ('<b>Scanned:</b> ' . View::toHtml($search) . (!empty($cont_arrival) ? ("<br/>" . $cont_arrival) : ''))) . "
                                        </div>
                                        <div class='col-auto mt-0 mr-2'>
                                            <button class='btn btn-warning btn-icon-split' id='unx_asn'>
                                                <span class='icon text-white-50'>
                                                    <i class='fas fa-fw fa-ghost'></i>
                                                </span>
                                                <span class='text text-white'>" . (empty($search) ? 'Add unbarcoded item' : 'Add to unexpected ASN') . "</span>
                                            </button>
                                        </div>
                                    </div>", false
                            ); ?>
                        </div>
                    </div>
                </form>
            <?php if (!empty($search) || count($ASNs)) { ?>
                <?php if ($pages > 1) View::pagination("/goods_in/arrival/?". http_build_query($filter) . '&', 1, $pages, $current); ?>
                <?php if (count($ASNs)) { ?>
                <form method="get" name="frmDel">
                    <div class="row">
                        <input type="hidden" name="id" value="">
                        <?php foreach ($filter as $key => $value) { ?>
                            <input type='hidden' name='<?= $key; ?>' value='<?= View::toHtml($value); ?>'>
                        <?php } ?>
                        <?php foreach ($ASNs as $ASN) { ?>
                            <div class="col-sm-6 col-md-4 col-xl-3 my-2 d-flex js-ASN-badge" data-id="<?= $ASN['id']; ?>" data-barcodes="<?= View::toHtml($ASN['barcodes']); ?>">
                                <div class="flex-fill text-center border border-info p-2 block_hover">
                                    <b>ASN #<?= View::toHtml($ASN['id']); ?></b>
                                    <?php if (!empty($ASN['ref'])) { ?>
                                        <span class="text-xs">(<?= View::toHtml($ASN['ref']); ?>)</span>
                                    <?php } ?> -
                                    <?= View::toHtml($ASN['name']); ?><br/>
                                    <b>Carrier: <?= empty($ASN['carrier']) ? '-' : View::toHtml($ASN['carrier']); ?></b>
                                    <?= empty($ASN['tracking_number']) ? '' : ('<span class="text-xs">#' . View::toHtml($ASN['tracking_number']) . '</span>'); ?><br/>
                                    Expected <b><?= View::toHtml(date('jS F Y', strtotime($ASN['arrival_date']))); ?></b><br/>
                                    <?= empty($ASN['arrived_date']) ? 'Pending' : ('Arrived <b>' . View::toHtml(date('jS F Y', strtotime($ASN['arrived_date']))) . '</b>'); ?>
                                    <?php
                                        if (strtotime($ASN['arrived_date']) > strtotime($ASN['arrival_date'] . ASN::ARRIVAL_END_DAY) || (empty($ASN['arrived_date']) && strtotime($ASN['arrival_date'] . ASN::ARRIVAL_END_DAY) < time()))
                                            echo "<span class='badge badge-danger text-xs'>Late Arrival</span>";
                                    ?><br/>
                                    <?php if (!empty($search) && $ASN['skus'] == 1) { ?>
                                        Units <b><?= View::toHtml($ASN['units']); ?></b><br/>
                                    <?php } else { ?>
                                        SKUs <b><?= View::toHtml($ASN['skus']); ?></b> / Units <b><?= View::toHtml($ASN['units']); ?></b><br/>
                                    <?php } ?>
                                    Status <span class='badge badge-info text-md mb-2'><?= View::toHtml(!empty($ASN['order_id']) ? ASN::getStateNameReturn($ASN['status']) : ASN::getStateName($ASN['status'])); ?></span><br/>
                                    <?php if (ASN::isArrived($ASN['status'])) { ?>
                                        <a class="btn btn-success btn-icon-split" href='/goods_in/no_verify/?id=<?= $ASN['id']; ?>&p=arrival&s=<?= View::toHtml($search); ?>&c=<?= $current; ?>'>
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-check"></i>
                                            </span>
                                            <span class="text text-white">No verify</span>
                                        </a>
                                    <?php } ?>
                                    <?php if (ASN::isPutaway($ASN['status']) || ASN::isWarehouse($ASN['status'])) { ?>
                                        <a class="btn btn-success btn-icon-split"
                                           href='/<?= ASN::isPutaway($ASN['status']) ? 'goods_in' : 'return_ASNs'; ?>/complete/?id=<?= $ASN['id']; ?>&p=arrival&s=<?= View::toHtml($search); ?>&c=<?= $current; ?>'>
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-power-off"></i>
                                            </span>
                                            <span class="text text-white">Complete</span>
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </form>
                <?php } ?>
                <?php if ($pages > 1) View::pagination('/goods_in/arrival/?' . http_build_query($filter) . '&', 1, $pages, $current); ?>
            <?php } else
                echo Html::warning('ASNs list is empty');
            ?>
        </div>
    </div>
</div>
<?= $select_SKU_modal->put(); ?>
<?= View::include_js(BACKEND_JS, "autocomplete_wrapper.js"); ?>
<script>
    $(document).ready(function () {
        let supplier_id = <?= View::toJs($supplier_id) ?>;
        let autocomplete_wrapper = (new AutocompleteWrapper).initTypeInput('arrival', supplier_id, 'edit_ASN_error', 'search', '/goods_in/get_SKUs_for_search');


        $('.js-ASN-badge').on('click', function () {
            var id = $(this).data('id');
            if (!id)
                return;
            var search = "<?= View::toHtml($search); ?>";
            var supplier_id = "<?= $supplier_id; ?>";
            var barcodes = $(this).data('barcodes').toString().split(', ');
            if (barcodes.length > 1) {
                <?= $select_SKU_modal->showWithParameteres([
                    'data-ASN_id' => 'id',
                    'data-barcode' => 'search',
                ]); ?>
                return;
            }
            window.location = '/goods_in/arrival_skus/?id=' + id + '&sku=' + barcodes[0] + '&supplier_id=' + supplier_id;
        });
    });
</script>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_supplier.php'; ?>
