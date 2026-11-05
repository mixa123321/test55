<?php

use app\core\entities\SKU;
use app\core\entities\User;
use app\core\Environment;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\helpers\modal\InfoModal;
use app\backend\models\Pack;
use app\core\helpers\modal\SwitchModal;

/**
 * @var User $authUser
 * @var array $order
 * @var int $printer_def
 * @var string $location
 * @var string $account
 * @var array $SKUs
 * @var array $packages
 * @var string $sku_qty
 * @var string $sku_data
 * @var float $max_weight
 * @var string $weight_message
 * @var string $mode
 * @var string $back
 * @var bool $to_print_packing_slip
 * @var string $packing_slip_url
 * @var string $regexp_serial_number
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Packing <?= Pack::$type_of_packing_short[$mode] ?? 'unknown'; ?></h1>
    </div>
    <form method="post" action="/pack/packing_conf" id="confirm_pack_form">
        <input type="hidden" id="order_id" name="order_id" value="<?= (int) $order['id']; ?>"/>
        <input type="hidden" id="printer_def" name="printer_def" value="<?= View::toHtml($printer_def); ?>"/>
        <input type="hidden" id="location_id" name="location_id" value="<?= View::toHtml($order['location_id']); ?>"/>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <div class="row">
                    <div class="col">
                        <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-tasks"></i>
                            Order #<?= (int) $order['id']; ?> (<?= View::toHtml($account); ?>) from <?= View::toHtml($location); ?>
                        </span>
                        </h6>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($order['packing']) { ?>
                    <div class="row justify-content-md-center">
                        <div class='col-12'>
                            <div class="alert alert-info">
                                <h4 class="alert-heading">Instructions</h4>
                                <p class="mb-0"><?= View::toHtml($order['packing']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php } ?>
                <div class="row justify-content-md-center <?= $mode === 's' ? 'd-none' : '' ?>">
                    <div class='input-group col-12'>
                        <?php (new Scan([
                            'id' => 'scan_input',
                            'placeholder' => 'Scan SKU barcode here',
                            'type' => Scan::TYPE__SKU,
                        ], ['id' => 'scan_btn', 'type' => 'button']))->render(); ?>
                    </div>
                </div>
                <div class="row my-2">
                    <div class="col-12">
                        <?php if (!is_null($weight_message)) { ?>
                            <div class="alert alert-warning p-2 mb-0"><?= $weight_message; ?></div>
                        <?php } ?>
                        <div class="alert alert-secondary p-2">Expected weight <span class="font-weight-bolder text-danger" id="all_weight"></span> kg</div>
                    </div>
                </div>
                <div class="row justify-content-md-center mt-3">
                    <div class='col-lg-7 mb-md-3'>
                        <h2 class="<?= $mode === 's' ? 'd-none' : '' ?>">Boxes</h2>
                        <div id="boxes_div"></div>
                        <button type="button" class="btn btn-primary <?= $mode === 's' ? 'd-none' : '' ?>" id="add_box_btn">Add new box</button>
                    </div>
                    <div class='col-lg-5 mb-md-3'>
                        <div class="<?= $mode === 's' ? 'd-none' : '' ?>">
                            <h2>Items List</h2>
                            <div id="global-SKU-list" class="p-1" style="border-radius: 5px; border: 1px solid #cccccc;">
                                <?php foreach ($SKUs as $SKU) {
                                    $photo = COMMON_IMG . DIRECTORY_SEPARATOR . 'photo.png';
                                    if (!empty($SKU['photo']))
                                        $photo = SKU::showPhotoSKU(urlencode($SKU['photo'])) ?: COMMON_IMG . DIRECTORY_SEPARATOR . 'photo.png';
                                    ?>
                                    <div class="row m-1 SKU-<?= $SKU['SKU_id'] ?>"
                                        data-scan-barcode="<?= View::toHtml($SKU['barcode']); ?>"
                                        data-scan-type="<?= Scan::TYPE__SKU; ?>"
                                    >
                                        <div class="col-2">
                                            <img class="img-thumbnail" style="max-height: 70px;" src="<?= View::toHtml($photo); ?>" /><br/>
                                        </div>
                                        <div class="col-<?= empty($SKU['serial_number']) ? 8 : 6; ?>">
                                            <span><?= View::toHtml($SKU['name']) ?></span>
                                            <br/>
                                            <span class="scan-barcode"><strong><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']) ?></strong></span>
                                        </div>
                                        <div class="col-<?= empty($SKU['serial_number']) ? 2 : 4; ?>">
                                            <div class="row">
                                                <div class="col-<?= empty($SKU['serial_number']) ? ($mode === 'l' ? 6 : 12) : ($mode === 'l' ? 3 : 4); ?> text-right pl-1 pr-1 <?= empty($SKU['serial_number']) ? 'd-none' : ''; ?>">
                                                    <button type="button" class="btn btn-primary btn-icon-split mt-2 show_serial_numbers" id="show_serial_number_<?= $SKU['SKU_id'] ?>" data-sku-id="<?= $SKU['SKU_id'] ?>" disabled>
                                                        <span class="icon text-white-50">
                                                            <i class="fas fa-fw fa-cube"></i>
                                                        </span>
                                                        <span class="text d-none d-md-block pl-1 pr-1"><span id="quantity_serial_numbers_<?= $SKU['SKU_id']; ?>">0</span></span>
                                                    </button>
                                                </div>
                                                <?php if ($mode === 'l') { ?>
                                                <div class="col-<?= empty($SKU['serial_number']) ? 12 : 6; ?> text-right pl-1 pr-1">
                                                    <button type="button" class="btn btn-success btn-icon-split btn-sm mt-2 js-pack-all-btn" 
                                                            id="pack_all_<?= $SKU['SKU_id'] ?>" 
                                                            data-sku-id="<?= $SKU['SKU_id'] ?>"
                                                            title="Pack all remaining items of this SKU into the active box"
                                                            disabled>
                                                        <span class="icon text-white-50">
                                                            <i class="fas fa-fw fa-box-open"></i>
                                                        </span>
                                                        <span class="text">All</span>
                                                    </button>
                                                </div>
                                                <?php } ?>
                                                <div class="col-<?= empty($SKU['serial_number']) ? ($mode === 'l' ? 12 : 12) : ($mode === 'l' ? 6 : 8); ?> text-right">
                                                    <span>Packed</span>
                                                    <br/>
                                                    <strong>
                                                        <span class="js-scanned-qty">0</span> of
                                                        <span><?= View::toHtml($SKU['qty']) ?></span>
                                                    </strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                            <div id="confirm-dialog" class="mt-2">
                                <span class="alert-info">All items are packed. <br>Check weight and dimensions for all boxes</span>
                                <button class="btn btn-success btn-icon-split js-btn-conf float-right" type="button">
                                    <span class="text">Confirm</span>
                                    <span class="icon text-white-50">
                                    <i class="fas fa-fw fa-chevron-right"></i>
                                </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-secondary btn-icon-split"
                           href="<?= View::toHtml(HTTP::safeLinkUrl($back)); ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-times"></i>
                            </span>
                                <span class="text">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-success btn-icon-split js-btn-conf" type="button">
                            <span class="text">Confirm</span>
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<div class="modal" tabindex="-1" role="dialog" id="scanError">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Scan Error</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger last-div">
                    <div id="error_text"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="serialNumberError">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Serial Number Error</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <div id="error_text_serial_number"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="weightError">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Weight Error</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <div id="weight-error_text"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= (new InfoModal('serialNumbers', 'Serial numbers'))->addContent('<table class="table table-hover mb-0"><tbody id="tbody_serial_numbers"></tbody></table>')->put(); ?>
<?php 
// Only add the order category modal if it's required or has categories
$showParcelCategoryModal = $requiresParcelCategory || !empty($parcelCategories);

if ($showParcelCategoryModal) {
    // Add the order category selection modal
    $parcelCategoryModal = new SwitchModal('parcelCategory', 'Select Parcel Category', SwitchModal::SIZE_NORMAL);
    $parcelCategoryModal->includeFile('packing/modals/parcel_categories.php', [
        'requiresParcelCategory' => $requiresParcelCategory,
        'parcelCategories' => $parcelCategories,
    ]);
    echo $parcelCategoryModal->put();
}
?>
<script>
    let global_sku_data = <?= $sku_data; ?>;
    const packages = Object.values(<?= View::toJs($packages) ?>);
    let order_inserts = <?= $order['order_inserts'] ? 'true' : 'false'; ?>;
    const packing_mode = '<?= $mode; ?>';
    const max_package_weight = parseFloat('<?= $max_weight ?? 0 ?>');
    let order_id = <?= (int)$order['id']; ?>;
    let regexp_serial_number = <?= View::toJs($regexp_serial_number) ?>;
    let weigth_order = <?= array_sum(array_map(function ($SKU) {
        return $SKU['qty']*$SKU['weight'];
    }, $SKUs)); ?>;
    
    <?php if ($showParcelCategoryModal): ?>
    // Pass the category modal selector to JavaScript
    const parcelCategoryModalSelector = '<?= $parcelCategoryModal->getSelector(); ?>';
    <?php endif; ?>

    if (!<?= View::toJs(!Environment::isProduction()) ?> && <?= View::toJs($to_print_packing_slip) ?>)
        printPdfDocument("<?= $packing_slip_url?>", 7000);
</script>
<?= View::include_js(BACKEND_JS, "packing_parcel_v3.js") ?>
