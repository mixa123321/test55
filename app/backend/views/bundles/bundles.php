<?php

use app\backend\models\Bundles;
use app\core\entities\User;
use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\modal\ConfirmModal;
use app\core\models\SKUs;

/**
 * @var array $bundles
 * @var int $total_count
 * @var int $current_page
 * @var int $total_pages
 * @var array $suppliers
 * @var array $current_filters
 * @var User $authUser
 * @var array $bundles_with_problem
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Bundles</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                    <span>
                        <i class="fas fa-fw fa-sign-in-alt"></i>
                        Bundles list (<?= (int)$total_count; ?> item<?= $total_count > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <form class="form-inline" method="GET" action="/bundles/bundles_add" id="create_form">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'class' => 'form-control custom-select',
                                    'id' => 'create_supplier_id'
                                ];
                                if ($authUser->isRoleSupplierUser() || empty($suppliers))
                                    $options['disabled'] = 'disabled';
                                echo Html::dropDownList('supplier_id', $suppliers ?: ['Suppliers are not found'], null, $options);
                            ?>
                        </div>
                        <button type="submit" class="btn btn-success ml-1"<?= empty($suppliers) ? ' disabled' : ''; ?>>Create new</button>
                    </form>
                </div>
                <div class="col-auto">
                    <a class="btn btn-orange ml-1" href="/bundles/bundles_upload_csv"<?= empty($suppliers) ? ' onclick="return false;"' : ''; ?>>Upload file</a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" id="filter_form">
                <input type="hidden" name="<?= Filter::getFilterTypeKey('barcode'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER ?>"/>
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="name" id="name"
                                   class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['name']) ? $_GET['name'] : '') ?>">
                            <label for="name">Bundle Name/SKU</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="barcode" id="barcode"
                                   class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['barcode']) ? $_GET['barcode'] : '') ?>">
                            <label for="barcode">Barcode</label>
                        </div>
                    </div>
                    <?php if (!$authUser->isRoleSupplierUser()) { ?>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <div class="form-group-element">
                                <?php
                                $options = [
                                    'class' => 'form-control form-control-lg custom-select',
                                    'id' => 'supplier_id'
                                ];
                                echo Html::dropDownList('supplier_id', [0 => 'all'] + $suppliers,
                                    !empty($_GET['supplier_id']) ? (string)$_GET['supplier_id'] : null, $options);
                                ?>
                                <label for="supplier_id">Client</label>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <div class="row justify-content-end">
                    <div class="col-auto mb-3">
                        <a href="/bundles" class="btn btn-lg btn-outline-orange mr-1">Clear</a>
                        <button type="submit" class="btn btn-lg btn-orange ml-1">Filter</button>
                    </div>
                </div>
            </form>
            <?php if (count($bundles)) { ?>
                <?php View::pagination("/bundles/?" . $current_filters . '&', 1, $total_pages, $current_page, Bundles::LIMIT_PER_PAGE); ?>
                <div class="row justify-content-end mr-3">
                    <a class="btn btn-outline-primary btn-rounded" href="/bundles/csv_report?<?= $current_filters . "&count={$total_count}"; ?>">
                        <i class="fa fa-download"></i> Download Results as CSV
                    </a>
                </div>
                <div class="row mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover" id="table-bundles">
                            <colgroup>
                                <col width="10%">
                                <col width="10%">
                                <col width="10%">
                                <col width="5%">
                                <col width="*">
                                <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                    <col width="20%">
                                <?php } ?>
                                <col width="10%">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Bundle ID</th>
                                <th>Bundle Name/SKU</th>
                                <th>Market Value</th>
                                <th>Currency</th>
                                <th>SKUs</th>
                                <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                    <th>Client</th>
                                <?php } ?>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($bundles as $bundle) { ?>
                                <tr id="bundle-<?= $bundle['id'] ?>" class="<?= in_array($bundle['id'], $bundles_with_problem) ? 'table-danger' : '' ?>">
                                    <td>
                                        <?= View::toHtml($bundle['id'])?>
                                    </td>
                                    <td>
                                        <?= View::toHtml($bundle['name'])?>
                                    </td>
                                    <td>
                                        <?= View::toHtml($bundle['cost_value'])?>
                                    </td>
                                    <td>
                                        <?= View::toHtml($bundle['currency'])?>
                                    </td>
                                    <td>
                                        <?= View::toHtmlConvertWithPrimaryBarcode($bundle['sku_list'], false);?>
                                    </td>
                                    <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                        <td><?= View::toHtml($bundle['supplier_name']);?></td>
                                    <?php } ?>
                                    <td>
                                        <a class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                            href="/bundles/bundles_edit?id=<?= (int)$bundle['id'] ?>">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-edit"></i>
                                            </span>
                                            <span class="text text-white flex-fill">Edit</span>
                                        </a>
                                        <?php if (!empty($bundle['exist_warehouse_orders'])) { ?>
                                            <button class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1 js-orders-btn">
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-fw fa-clipboard-list"></i>
                                                </span>
                                                <span class="text text-white flex-fill">Orders</span>
                                            </button>
                                        <?php } ?>
                                        <button class="btn btn-danger btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1 <?= empty($bundle['exist_orders']) ? 'js-delete-btn' : ('disabled ' . (empty($bundle['exist_warehouse_orders']) ? 'tooltip_delete' : '')) ?> ">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-times"></i>
                                            </span>
                                            <span class="text text-white flex-fill">Delete</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <script>
                    $('.tooltip_delete').mouseover(function() {
                        $(this).tooltip({
                            html: false,
                            title: 'Unable to delete because the bundle is used in order for other warehouses'
                        });
                    });
                </script>
                <?php View::pagination("/bundles/?" . $current_filters . '&', 1, $total_pages, $current_page, Bundles::LIMIT_PER_PAGE); ?>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
    </div>
</div>

<?php
$modal_content = '<h3 id="ob-loading" style="display: none"></h3>
                    <table id="ordersBundleTable" class="table table-hover w-100">
                        <thead>
                        <tr><th>Order Reference</th>
                            <th>Created Date</th>
                            <th>Source</th>
                            <th>Quantity</th>
                            <th>Status</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <table class="table w-100 mb-0">
                        <tr>
                            <td class="p-0"><div class="alert-info pr-lg-3 p-2"><span class="ml-md-3">In progress </span><span class="float-right ml-lg-4" id="orders_in_progress"></span></div></td>
                            <td class="p-0"><div class="alert-primary pr-lg-3 p-2"><span class="ml-md-3">Despatched </span><span class="float-right ml-lg-4" id="orders_despatched"></span></div></td>
                            <td class="p-0"><div class="alert-secondary pr-lg-3 p-2"><span class="ml-md-3">Cancelled </span><span class="float-right ml-lg-4" id="orders_canceled"></span></div></td>
                        </tr>
                    </table>';
$modal_orders_bundle = new InfoModal('ordersBundle', 'Bundle in orders', InfoModal::SIZE_LG);
echo $modal_orders_bundle->addContent($modal_content)->put();

$modal_delete_bundle = new ConfirmModal('deleteBundle', 'Delete bundle');
echo $modal_delete_bundle->addContent('Are you sure?')
    ->addForm('/bundles/bundles_delete', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="id" id="bundle_id">' . $modal_delete_bundle->getButtons())
    ->put();
?>

<?= View::include_js(BACKEND_JS, "bundles.js")?>