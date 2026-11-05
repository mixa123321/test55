<?php

use app\backend\models\SKUs;
use app\core\entities\Location;
use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\entities\SKU;
use app\core\helpers\Filter;
use app\core\helpers\modal\InfoModal;
use app\core\Permission;

/**
 * @var array $SKUs
 * @var string $back
 * @var Supplier $supplier
 * @var User $authUser
 * @var int $total_count
 * @var int $current_page
 * @var array $current_filters
 * @var array $currencies
 * @var int $total_pages
 * @var array $suppliers
 * @var array $statuses
 * @var array $stock_statuses
 * @var int $available
 * @var int $allocated
 * @var int $backorder_replenish
 * @var array $batches
 * @var bool $supplier_read_only
 * @var array $is_notmapped
 * @var array $is_connection
 * @var array $SKU_warehouses
 * @var Permission $permission
 * @var array $SKUs_with_serial_number
 * @var array $SKUs_with_movements
 */

?>
<style>
    #full-page {
        display: none;
        position: fixed;
        z-index: 9999;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-size: contain;
        background-repeat: no-repeat no-repeat;
        background-position: center center;
        background-color: black;
    }
</style>
<div class="container-fluid p-sm-4 p-2">
    <div id="full-page"></div>

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">SKUs</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        SKUs list (<?= $total_count; ?> item<?= $total_count > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
                <?php if ($authUser->hasAdminsAccess() || $authUser->hasManagerAccess()) { ?>
                    <div class="col-auto">
                        <a class="btn btn-success btn-icon-split" href="/SKUs/bulk_edit" >
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-edit"></i>
                            </span>
                            <span class="text">Bulk edit SKUs</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <a class="btn btn-success btn-icon-split"
                           href="/Upload/SKUs/?<?= $current_filters ?>&back=/SKUs?<?= $current_filters ?>"
                           target="_blank">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-indent"></i>
                            </span>
                            <span class="text">Import SKUs</span>
                        </a>
                    </div>
                <?php } ?>
                <?php if ($authUser->hasAdminsAccess() || $authUser->hasManagerAccess()) { ?>
                    <div class="col-auto">
                        <button class="btn btn-success btn-icon-split js-create-SKU">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-plus"></i>
                            </span>
                            <span class="text">Create new SKU</span>
                        </button>
                    </div>
                <?php } ?>
            </div>
        </div>
        <form method="get" action="/SKUs/">
            <input type="hidden" name="<?= Filter::getFilterTypeKey('barcode'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER ?>"/>
            <input type="hidden" name="back" value="<?= View::toHtml($back); ?>"/>
            <div class="card-body border-0">
                <div class="form-row border-bottom mb-3 border-dark">
                    <div class="form-group col-lg-2">
                        <div class="form-group-element">
                            <input type="text" name="id" id="id" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['id']) ? $_GET['id'] : '') ?>">
                            <label for="id">ID</label>
                        </div>
                    </div>
                    <div class="form-group col-lg-3">
                        <div class="form-group-element">
                            <input type="text" name="product_id" id="product_id" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['product_id']) ? $_GET['product_id'] : '') ?>">
                            <label for="product_id">Product ID (for integrations)</label>
                        </div>
                    </div>
                    <div class="form-group col-lg-3">
                        <div class="form-group-element">
                            <input type="text" name="variant_id" id="variant_id" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['variant_id']) ? $_GET['variant_id'] : '') ?>">
                            <label for="product_id">Variant ID (for integrations)</label>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <?php if (!empty($suppliers)) { ?>
                        <div class="form-group col-lg-2 align-self-center">
                            <div class="form-group-element">
                                <?php if (!$supplier_read_only) { ?>
                                    <select id="supplier_id" name="supplier_id"
                                            class="form-control form-control-lg custom-select">
                                        <?php
                                            if (count($suppliers) > 1)
                                                echo '<option value="" ' . (!empty($_GET['supplier_id']) && 'all' === $_GET['supplier_id'] ? ' selected' : '') . '>All</option>';
                                        ?>
                                        <?php foreach ($suppliers as $row) { ?>
                                            <option value="<?= $row['id'] ?>"<?= !empty($_GET['supplier_id']) && $row['id'] === $_GET['supplier_id'] ? " selected" : "" ?>><?= View::toHtml($row['name']) ?></option>
                                        <?php } ?>
                                    </select>
                                    <label for="supplier_id">Companies</label>
                                <?php } else { ?>
                                    <input type="hidden" id="supplier_id" name="supplier_id" value="<?= View::toHtml($_GET['supplier_id']); ?>"/>
                                    <p class="form-control-static font-weight-bold text-primary pl-2">
                                        <i class="fa fa-user"></i> <?= View::toHtml($suppliers[$_GET['supplier_id']]['name']); ?>
                                    </p>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-group col-lg-3">
                        <div class="form-group-element">
                            <input type="text" name="like_name" id="like_name" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['like_name']) ? $_GET['like_name'] : '') ?>">
                            <label for="like_name">Name</label>
                        </div>
                    </div>
                    <div class="form-group col-lg-2">
                        <div class="form-group-element">
                            <input type="text" name="barcode" id="barcode" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['barcode']) ? $_GET['barcode'] : '') ?>">
                            <label for="barcode">Barcode</label>
                        </div>
                    </div>
                    <div class="form-group col-lg-3">
                        <div class="form-group-element">
                            <input type="text" name="customer_ref" id="customer_ref" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['customer_ref']) ? $_GET['customer_ref'] : '') ?>">
                            <label for="customer_ref">Customer Reference</label>
                        </div>
                    </div>
                    <div class="form-group col-lg-2">
                        <div class="form-group-element">
                            <select id="state" name="state"
                                    class="form-control form-control-lg custom-select">
                                <?php foreach ($statuses as $key => $status) { ?>
                                    <option value="<?= $key; ?>"<?= (isset($_GET['state']) && $key == $_GET['state']) ? ' selected' : ''; ?>>
                                        <?= View::toHtml($status) ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <label for="state">State</label>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-auto mr-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="available"
                                   name="available"<?= !empty($available) ? ' checked' : ''; ?> onclick="submit();">
                            <label class="form-check-label" for="available">Available</label>
                        </div>
                    </div>
                    <div class="form-group col-auto mr-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="allocated"
                                   name="allocated"<?= !empty($allocated) ? ' checked' : ''; ?> onclick="submit();">
                            <label class="form-check-label" for="allocated">Allocated</label>
                        </div>
                    </div>
                    <div class="form-group col-auto">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="backorder_replenish"
                                   name="backorder_replenish"<?= !empty($backorder_replenish) ? ' checked' : ''; ?>
                                   onclick="submit();">
                            <label class="form-check-label" for="backorder_replenish">Backorder replenish</label>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <?php
                    $supplier_uri = (!empty($supplier) && !$authUser->isRoleSupplierUser() && !empty($back))
                        ? "supplier_id={$supplier->getId()}&"
                        : '';
                    $back_uri = !empty($back) ? "back=" . urlencode($back) : '';
                    ?>
                    <a href="/SKUs?<?=$supplier_uri;?><?=$back_uri?>" class="btn btn-lg btn-outline-orange">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                </div>
            </div>
        </form>
        <input type="hidden" id="supplier_id" name="supplier" value="<?= !empty($supplier) ? $supplier->getId() : ''; ?>">
        <?php if (!empty($SKUs)) { ?>
            <div class="card-body py-0 border-0">
                <div class="row justify-content-end">
                    <?php if ($authUser->isRoleSA() && 0) { ?>
                        <div class="col-auto">
                            <a class="btn btn-outline-light btn-rounded px-3"
                               href="/SKUs/export_custom/?<?= $current_filters ?>&back=/SKUs?<?= $current_filters . "&count={$total_count}" ?>">
                                <i class="fa fa-plus"></i>
                            </a>
                        </div>
                    <?php } ?>
                    <div class="col-auto">
                        <a class="btn btn-outline-primary btn-rounded" target="_blank"
                           href="/SKUs/print_barcodes/?<?= $current_filters ?>">
                            <i class="fa fa-print"></i> Print (<?= $total_count; ?>
                            barcode<?= $total_count > 1 ? 's' : '' ?>)
                        </a>
                    </div>
                    <div class="col-auto">
                        <a class="btn btn-outline-primary btn-rounded"
                           href="/SKUs/export/?<?= $current_filters ?>&back=/SKUs?<?= $current_filters . "&count={$total_count}" ?>">
                            <i class="fa fa-download"></i> Download results as CSV
                        </a>
                    </div>
                </div>
            </div>
            <form method="get" action="/SKUs/lock_sku" name="frmDel">
                <div class="card-body">
                    <?php View::pagination("/SKUs/?" . $current_filters . '&', 1, $total_pages, $current_page, SKUs::LIMIT_PER_PAGE); ?>
                    <input type="hidden" name="id" value="">
                    <input type="hidden" id="lockedSKU" name="lock" value="">
                    <input type="hidden" id="form_supplier_id" name="supplier" value="<?= !empty($supplier) ? $supplier->getId() : ''; ?>">
                    <table class="table table-hover">
                        <colgroup>
                            <col width="5%">
                            <?php if ($authUser->isRoleSupplierUser()) { ?>
                                <col width="*">
                            <?php } ?>
                            <?php if (!empty($suppliers)) { ?>
                                <col width="*"><?php } ?>
                            <col width="*">
                            <col width="*">
                            <col width="*">
                            <col width="7%">
                            <col width="7%">
                            <col width="7%">
                            <col width="7%">
                            <col width="12%">
                        </colgroup>
                        <thead>
                        <tr>
                            <th>Id</th>
                            <?php if ($authUser->isRoleSupplierUser()) { ?>
                                <th>Photo</th>
                            <?php } ?>
                            <?php if (!empty($suppliers)) { ?>
                                <th>Company</th><?php } ?>
                            <th>Barcode</th>
                            <th>Name</th>
                            <th>Batches</th>
                            <th>Available</th>
                            <th>Allocated</th>
                            <th>Backorder replenish</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($SKUs as $SKU) { ?>
                            <tr>
                                <td><?= View::toHtml($SKU['id']) ?></td>
                                <?php if ($authUser->isRoleSupplierUser() ) { ?>
                                    <td>
                                        <?php if (empty($SKU['photo']) || !SKU::showPhotoSKU($SKU['photo'])): ?>
                                            <img src="/assets/common/img/photo.png" width="80">
                                        <?php else: ?>
                                            <img class="full-img" style="cursor: pointer" src="<?=SKU::showPhotoSKU(urlencode($SKU['photo']))?>" width="80">
                                        <?php endif  ?>
                                    </td>
                                <?php } ?>
                                <?php if (!empty($suppliers)) { ?>
                                    <td><?= View::toHtml($suppliers[$SKU['supplier_id']]["name"]) ?></td> <?php } ?>
                                <td><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']) ?></td>
                                <td><?= View::toHtml($SKU['name']) ?></td>
                                <td>
                                    <?php if(!empty($batches[$SKU['id']])) { ?>
                                        <?php foreach($batches[$SKU['id']] as $batch) { ?>
                                            <b>Batch#:</b> <?= View::toHtml($batch['batch_number'])?>
                                            (<?= View::toHtml($batch['quantity'])?> units)<br/>
                                            <b>Exp#:</b> <?= View::toHtml(date('jS M Y', strtotime($batch['expired'])))?>
                                            <br/><br/>
                                        <?php } ?>
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="text-lg">
                                        <?= empty($SKU['available']) ? '0' : View::toHtml($SKU['available']); ?>
                                        <?php if (!empty($SKU_warehouses) && !empty($SKU_warehouses[$SKU['id']])) {
                                            echo '<br/>';
                                            foreach ($SKU_warehouses[$SKU['id']] as $name => $quantity)
                                                echo '<span class="badge-info text-xs p-1">' . View::toHtml($name) . ' <b>' . View::toHtml($quantity) . '</b></span><br/>';
                                        } ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-lg">
                                        <?= empty($SKU['allocated']) ? '0' : View::toHtml($SKU['allocated']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-lg">
                                        <?= $SKU['backorder_replenish'] <= 0 ? '0' : View::toHtml($SKU['backorder_replenish']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= SKUs::colorStatus($SKU['state']); ?>">
                                        <?= View::toHtml(SKU::getStateName($SKU['state'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!$authUser->isRoleSupplierUser()): ?>
                                        <button class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1 js-btn-settings"
                                                type="button"
                                                data-sku_id="<?= $SKU['id']; ?>"
                                                data-sku_photo="<?= View::toHtml(!empty($SKU['photo']) ? SKU::showPhotoSKU(addslashes($SKU['photo'])) : (COMMON_IMG . '\photo.png')); ?>"
                                                data-sku_state_id="<?= $SKU['state']; ?>"
                                                data-sku_state="<?= SKU::getStateName($SKU['state']); ?>"
                                                data-sku_color_state="badge-<?= SKUs::colorStatus($SKU['state']); ?>"
                                                data-sku_name="<?= View::toHtml($SKU['name']); ?>"
                                                data-sku_barcode="<?=  View::toHtml(View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']))?>"
                                                data-sku_supplier_id="<?= View::toHtml($SKU['supplier_id']); ?>"
                                                data-sku_customer_ref="<?= View::toHtml($SKU['customer_ref']); ?>"
                                                data-sku_customer_ref2="<?= View::toHtml($SKU['customer_ref2']); ?>"
                                                data-sku_clear_dh="<?= empty($clear_DH[$SKU['id']]) ? 'false' : 'true'; ?>"
                                                data-sku_lock="<?= SKU::isActive($SKU['state']) ? 'false' : 'true'; ?>"
                                                data-sku_connections="<?= empty($is_connection[$SKU['id']]) ? 'false' : 'true'; ?>"
                                                data-sku_mapping="<?= SKU::canBeMapped($SKU['state']) ? 'false' : (empty($is_notmapped[$SKU['id']]) ? 'true' : 'undefined'); ?>"
                                                data-sku_reconciliation="<?= in_array($SKU['id'], $SKUs_with_movements); ?>"
                                        >
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-cog"></i>
                                            </span>
                                            <span class="text flex-fill">Settings</span>
                                        </button>
                                    <?php endif; ?>
                                    <a class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                       href="/housekeeping/barcode_creator/?barcode=<?= urlencode($SKU['barcode']); ?>&name=<?= urlencode($SKU['name']); ?>"
                                       target="_blank">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-barcode"></i>
                                        </span>
                                        <span class="text flex-fill">Barcode</span>
                                    </a>
                                    <?php if (!empty($SKU['allocated']) && $permission->isAccessByUrl('orders')) { ?>
                                        <a class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1 js-btn-orders"
                                           id="<?= $SKU['id']; ?>">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-clipboard-list"></i>
                                            </span>
                                            <span class="text text-white flex-fill">Orders</span>
                                        </a>
                                    <?php } ?>
                                    <?php if ($authUser->isRoleSA() && isset($SKUs_with_serial_number[$SKU['id']])) { ?>
                                        <a class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1 js-btn-serial-numbers" data-sku-id="<?= $SKU['id']; ?>">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-cube"></i>
                                            </span>
                                            <span class="text text-white flex-fill">Serial numbers</span>
                                        </a>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                    <?php View::pagination("/SKUs/?" . $current_filters . '&', 1, $total_pages, $current_page, SKUs::LIMIT_PER_PAGE); ?>
                </div>
            </form>
        <?php } else
            echo Html::div(Html::warning('No records found'), ['class' => 'card-body']);
        ?>
        <?php if (!empty($back)) { ?>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-outline-orange pl-3"
                           href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                            <i class="fas fa-fw fa-chevron-left mr-2"></i>
                            <span class="ml-1">Back</span>
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<?php
$button_hard_delete = '';
if ($authUser->isRoleSA()) {
    $button_hard_delete = '<button class="btn btn-danger btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1 mt-3" type="button" id="btn-hard-delete">
                               <span class="icon text-white-50"><i class="fas fa-fw fa-trash"></i></span>
                               <span class="text flex-fill">Hard Delete</span>
                           </button>';
}

$modal_content = '<div class="row">
                    <input type="hidden" id="SKU_id" />
                    <div class="col-9">
                        <div class="row">
                            <div class="col-2 text-center">
                                <img class="img-responsive" style="max-height: 100px; max-width: 75px;" id="SKU_photo" />
                                <span class="badge text-xs" id="SKU_state"></span>
                            </div>
                            <div class="col-10">
                                <p class="form-control-static text-success font-weight-bold mb-1" id="SKU_name"></p>
                                <p class="form-control-static mb-2"><span class="text-lg text-success font-weight-bold" id="SKU_barcode"></span><br/>
                                    <span id="SKU_customer_ref"></span></p>
                                <p class="form-control-static overflow-auto mb-0" style="max-height: 90px;" id="SKU_customer_ref2"></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <button class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                type="button" id="btn_settings_edit">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-pen"></i>
                            </span>
                            <span class="text flex-fill">Edit</span>
                        </button>
                        <button class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                type="button" id="btn_settings_connections">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-plug"></i>
                            </span>
                            <span class="text flex-fill">Connections</span>
                        </button>
                        <button class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1" id="btn-reconciliation-log" data-sku-id="">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-book"></i>
                            </span>
                            <span class="text pr-2 pl-2">Reconciliation</span>
                        </button> 
                        <button class="btn btn-danger btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                type="button" id="btn-clearDH">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-trash-alt"></i>
                            </span>
                            <span class="text flex-fill">Clear ' . Location::DH . '</span>
                        </button>
                        <button id="btn-mapping" type="button">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-map-signs"></i>
                            </span>
                            <span class="text text-white flex-fill" id="text-mapping"></span>
                        </button>
                        <button id="btn-lock" type="button">
                            <span class="icon text-white-50">
                                <i id="icon-lock"></i>
                            </span>
                            <span class="text text-white flex-fill" id="text-lock"></span>
                        </button>
                        ' . $button_hard_delete . '
                    </div>
                </div>
                <div class="alert alert-danger mt-2 mb-0 d-none" id="custom_error"></div>
                <div id="hard-delete-response" class="mt-2 mb-2"></div>';

$modal_settings_SKU = new InfoModal('settingsSKU', 'Settings', InfoModal::SIZE_LG);
echo $modal_settings_SKU->addBodyAttributes(['class' => 'modal-body pb-0'])->addContent($modal_content)->put();

$modal_lock = new ConfirmModal('lockSKU', 'Change status SKU', ConfirmModal::SIZE_NORMAL, true);
echo $modal_lock->addBodyAttributes(['id' => 'ansLock'])
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'lockConf'])
    ->addCustomButtons('<input type="hidden" id="is_lock" />' . $modal_lock->getButtons())
    ->put();

$modal_clear_SKU = new ConfirmModal('clearSKU', 'Clear Discrepancy Hold', ConfirmModal::SIZE_NORMAL, true);
echo $modal_clear_SKU->addContent('Would you like to clear Discrepancy Hold for this SKU?<div class="alert alert-danger mt-2 mb-0 d-none" id="msgClearError"></div>')
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE, ['id' => 'clearConf'])
    ->put();

$modal_mapping_SKU = new ConfirmModal('mappingSKU', 'Mapping resolved', ConfirmModal::SIZE_NORMAL, false);
echo $modal_mapping_SKU->addContent('<p class="form-control-static text-lg">Enter the SKU barcode for merging</p>
                                                 <div class="form-group-element">
                                                 <input type="text" id="merge_barcode" class="form-control form-control-lg" placeholder=" ">
                                                     <label for="barcode">Barcode</label>
                                                 </div>
                                                 <div class="alert alert-danger mt-2 mb-0 d-none" id="msgMergeError">
                                                     Enter barcode for merge
                                                 </div>')
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE, ['id' => 'mergeConf'])
    ->put();

$modal_check_bundle_SKU = new ConfirmModal('checkBundleSKU', 'SKU used in bundles', ConfirmModal::SIZE_NORMAL, false);
echo $modal_check_bundle_SKU->addContent('<div class="form-control-static text-lg" id="sku-in-bundles"></div>Remove SKU from bundle and handle unmapping?')
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE, ['id' => 'handleUnmapping'])
    ->put();

$modal_hard_delete_SKU = new ConfirmModal('hardDeleteSKU', 'Hard delete SKU');
echo $modal_hard_delete_SKU->addContent('Would you like to remove SKU?<br>This action will also clear SKU history and settings')
    ->addForm('/SKUs/hard_remove', ConfirmModal::METHOD_POST, ['id' => 'hard_remove_form'])
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="SKU_id">' . $modal_hard_delete_SKU->getButtons())
    ->put();

$show_serial_numbers = new InfoModal('serialNumbers', 'Serial numbers for last month (Click on the serial number to copy)', InfoModal::SIZE_XL);
echo $show_serial_numbers->addContent('
    <span id="pagination_serial_numbers"></span>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Order</th>
                <th>Serial numbers</th>
            </tr>
        </thead>
        <tbody id="tbody_serial_numbers">
        </tbody>
    </table>')->put();

$show_serial_numbers_error = new InfoModal('serialNumbersError', 'Serial numbers error');
echo $show_serial_numbers_error->addContent('<div class="alert alert-danger mb-0"><div id="serial_numbers_error"></div></div>')->put();
?>

<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_orders.php'; ?>
<?= View::include('utility/reconciliation_log_widget.php', ['secondary' => true]); ?>
<script>

    $('.full-img').click(function () {
        const fullPage = document.querySelector('#full-page');
        fullPage.style.backgroundImage = 'url(' + this.src + ')';
        fullPage.style.display = 'block';
    });
    $('#full-page').click(function () {
        this.style.display='none';
    });

    let SKU_lock;
    let SKU_mapping;
    let supplier_id;

    $('#hard_remove_form').submit(function(e) {
        e.preventDefault();
        $(this).find('input[name=SKU_id]').val($('#SKU_id').val());
        this.submit();
    })

    $('.js-create-SKU').click(function () {
        location = '/SKUs/add_sku/?supplier_id=' + $('#supplier_id').val() + '&ro=<?= (int)$supplier_read_only; ?>';
    });

    $('.js-btn-settings').click(function () {
        $('#SKU_id').val($(this).data('sku_id'));
        $('#SKU_photo').attr('src', $(this).data('sku_photo'));
        $('#SKU_state').prop('classList', 'badge ' + $(this).data('sku_color_state'));
        $('#SKU_state').html($(this).data('sku_state'));
        $('#SKU_name').text($(this).data('sku_name'));
        $('#SKU_barcode').html($(this).data('sku_barcode'));
        $('#goods [data-scan-barcode]').attr('data-scan-barcode', $(this).data('sku_barcode'));
        supplier_id = $(this).data('sku_supplier_id');
        $('#SKU_customer_ref').text($(this).data('sku_customer_ref'));
        $('#SKU_customer_ref2').text($(this).data('sku_customer_ref2'));
        $('#btn-clearDH').css('display', $(this).data('sku_clear_dh') ? '' : 'none');
        $('#btn_settings_connections').css('display', $(this).data('sku_connections') ? '' : 'none');
        SKU_lock = $(this).data('sku_lock');
        const stateId = +$(this).data('sku_state_id');
        if (stateId === 1) {
            $('#btn-lock').prop('classList', 'btn btn-danger btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1');
            $('#icon-lock').prop('classList', 'fas fa-fw fa-lock');
            $('#text-lock').html('Lock');
        } else if (stateId === 0) {
            $('#btn-lock').prop('classList', 'btn btn-success btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1');
            $('#icon-lock').prop('classList', 'fas fa-fw fa-lock-open');
            $('#text-lock').html('Unlock');
        }  else {
            $('#btn-lock').prop('classList', 'd-none');
        }

        SKU_mapping = $(this).data('sku_mapping');
        if (SKU_mapping == 'undefined')
            $('#btn-mapping').prop('classList', 'd-none');
        else {
            $('#btn-mapping').prop('classList', 'btn btn-' +  (SKU_mapping ? 'danger' : 'success') + ' btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1');
            $('#text-mapping').html((SKU_mapping ? 'Unm' : 'M') + 'apping');
        }
        <?php if ($authUser->isRoleSA()) { ?>
        $('#btn-hard-delete').removeAttr('disabled');
        $('#hard-delete-response').html('');
        <?php } ?>
        $('#btn-reconciliation-log').css('display', $(this).data('sku_reconciliation') ? '' : 'none');
        <?= $modal_settings_SKU->show(); ?>
    });

    $('#btn_settings_edit').click(function () {
        location = '/SKUs/edit_sku/?id=' + $('#SKU_id').val();
    });

    $('#btn_settings_connections').click(function () {
        location = '/SKUs/edit_connections/?id=' + $('#SKU_id').val();
    });

    $('#btn-clearDH').click(function () {
        <?= $modal_clear_SKU->show(); ?>
    });

    $('#clearConf').click(function () {
        $.ajax({
            url: "/SKUs/clear_SKU_in_DH",
            method: "POST",
            dataType: 'json',
            data: {
                SKU_id: $('#SKU_id').val()
            },
            success: function (response) {
                if (response == 'error')
                    showError('msgClearError', '<?= Location::DH; ?> is empty', 3000);
                else if (response == 'success') {
                    <?= $modal_clear_SKU->hide(); ?>
                    location = <?= View::toJs(HTTP::safeLinkUrl($back)) ?>;
                }
            },
            error: function (json) {
                showError('msgClearError', 'Connection error', 3000);
            }
        });
        return false;
    });

    $('#btn-reconciliation-log').click(function () {
        fillReconciliationLogWindow($('#SKU_id').val());
    });

    $('#btn-mapping').click(function () {
        if (SKU_mapping) {
            $('#ansLock').html('Would you like to unmapping SKU (it will be ignored for future orders and ASNs)?');
            $('#is_lock').val(0);
            <?= $modal_lock->show(); ?>
        } else {
            <?= $modal_mapping_SKU->show(); ?>
        }
    });

    $('#btn-lock').click(function () {
        $('#ansLock').html('Would you like to ' + (SKU_lock ? 'unlock SKU' : 'lock SKU (it will be ignored for future orders)') + '?');
        $('#is_lock').val(1);
        <?= $modal_lock->show(); ?>
    });

    function submitHandleUnmapping() {
        frmDel.action = '/SKUs/unmapping_sku';
        frmDel.submit();
    }

    $('#lockConf').click(function () {
        <?= $modal_lock->hide(); ?>
        frmDel.id.value = $('#SKU_id').val();
        if ($('#is_lock').val() == 1) {
            frmDel.lock.value = 1 - +SKU_lock;
            frmDel.submit();
        } else {
            $.ajax({
                url: "/bundles/check_sku",
                method: "POST",
                dataType: 'json',
                data: {
                    SKU_id: $('#SKU_id').val()
                },
                success: function (response) {
                    if (response.length > 0) {                        
                        $('#sku-in-bundles').html(response.reduce((bundles, bundle, i) => {
                            bundles += `<a href="/bundles/bundles_edit?id=${+bundle.id}">${html_escape(bundle.name)}</a><br>`;
                            return bundles;
                        }, ''));
                        <?= $modal_check_bundle_SKU->show(); ?>
                    } else
                        submitHandleUnmapping();
                },
                error: function () {
                    showError('custom_error', 'Check bundle error', 3000);
                }
            });
        }
    });

    $('#handleUnmapping').click(submitHandleUnmapping);

    $('#mergeConf').click(function () {
        frmDel.id.value = $('#SKU_id').val();
        frmDel.lock.value = $('#merge_barcode').val().trim();
        frmDel.form_supplier_id.value = supplier_id;
        frmDel.action = '/SKUs/mapping_sku';
        frmDel.submit();
    });

    <?php if ($authUser->isRoleSA()) { ?>
    $('#btn-hard-delete').click(function() {
        const btn = this;
        $.ajax({
            url: "/SKUs/check_usages",
            method: "POST",
            dataType: 'json',
            data: {
                SKU_id: $('#SKU_id').val()
            },
            success: function (response) {
                if (response.result) {
                    <?= $modal_hard_delete_SKU->show(); ?>
                } else if (response.result === false && response.usages.length > 0) {
                    btn.disabled = true;
                    $('#hard-delete-response').html(response.usages.reduce((temp, usage) => {
                        temp += `<li class="ml-4">${usage}</li>`;
                        return temp;
                    }, '<ul class="alert alert-danger">') + '</ul>');
                } else this.error()
            },
            error: function () {
                showError('custom_error', 'Hard delete error', 3000);
            }
        });
    })
    <?php } ?>

    let pag_block = getPaginationBlock($('#pagination_serial_numbers'), 'pag_serial_number', function () {
        $('.js-btn-serial-numbers').click();
    });

    $('.js-btn-serial-numbers').click(function () {
        let SKU_id = $(this).data("sku-id");
        $.post("/SKUs/get_serial_numbers", {SKU_id: SKU_id, page: pag_block.getPage()}, res => {
            if (res.error !== undefined) {
                $('#serial_numbers_error').html(res.error);
                <?= $show_serial_numbers_error->show(); ?>
                return;
            }
            let tbody_serial_numbers = $('#tbody_serial_numbers');
            tbody_serial_numbers.empty();
            let count_to_show = 7;
            for (const order_id in res.data.serial_numbers) {
                let serial_numbers = res.data.serial_numbers[order_id];
                tbody_serial_numbers.append('<tr><td>' + +order_id + '</td><td class="serial_numbers_list" data-order-id="' + +order_id + '" style="cursor: pointer;">' + html_escape(serial_numbers.slice(0, count_to_show).join(', ')) + (serial_numbers.length > count_to_show ? '...' : '') + '</td></tr>');
            }
            pag_block.setPages(res.data.pages).setPage(res.data.page).render();
            <?= $show_serial_numbers->show(); ?>
            $('#serialNumbersModalInfo').on('hidden.bs.modal', function (e) {
                pag_block.setPage(1)
            });
            $('.serial_numbers_list').on('click', function () {
                copyByClick(res.data.serial_numbers[$(this).data('order-id')].join(', '));
                $(this).tooltip({
                    "html": true,
                    "title": "copied"
                });
                $(this).trigger('mouseenter');
            });
            $(".serial_numbers_list").mouseout(function () {
                $(".serial_numbers_list").tooltip('dispose');
            });
        }, "json").fail(function () {
            $('#serial_numbers_error').html('Access denied');
            <?= $show_serial_numbers_error->show(); ?>
        });
    });

</script>
