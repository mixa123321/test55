<?php

use app\core\entities\ASN;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\modal\SwitchModal;
use app\core\models\ASNs;
use app\core\models\SKUs;

/**
 * @var int $total_count
 * @var int $current_page
 * @var int $total_pages
 * @var array $suppliers
 * @var array $countries
 * @var array $asn_list
 * @var array $status_list
 * @var array $statuses
 * @var string $current_filters
 * @var User $authUser
 * @var string $containers
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">ASN List</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-sign-in-alt"></i>
                        ASN records (<?= $total_count; ?> item<?= $total_count > 1 ? 's' : '' ?> total)
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="<?= Filter::getFilterTypeKey('sku_barcode'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER; ?>">
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_expected_from" name="date_expected_from"
                                   value="<?= View::toHtml(!empty($_GET['date_expected_from']) ? $_GET['date_expected_from'] : '') ?>"
                                   min="<?= Arr::map($warehouses, 'id', 'start_warehouse')[$warehouse_id]; ?>"/>
                            <label for="date_expected_from">Date expected from</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_expected_to" name="date_expected_to"
                                   min="<?= View::toHtml(!empty($_GET['date_expected_from']) ? $_GET['date_expected_from'] : '') ?>"
                                   value="<?= View::toHtml(!empty($_GET['date_expected_to']) ? $_GET['date_expected_to'] : '') ?>"/>
                            <label for="date_expected_to">Date expected to</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_arrived_from" name="date_arrived_from"
                                   value="<?= View::toHtml(!empty($_GET['date_arrived_from']) ? $_GET['date_arrived_from'] : '') ?>"
                                   min="<?= Arr::map($warehouses, 'id', 'start_warehouse')[$warehouse_id]; ?>"/>
                            <label for="date_arrived_from">Date arrived from</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_arrived_to" name="date_arrived_to"
                                   min="<?= View::toHtml(!empty($_GET['date_arrived_from']) ? $_GET['date_arrived_from'] : '') ?>"
                                   value="<?= View::toHtml(!empty($_GET['date_arrived_to']) ? $_GET['date_arrived_to'] : '') ?>"/>
                            <label for="date_arrived_to">Date arrived to</label>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <?php if (!$authUser->isRoleSupplierUser()) { ?>
                        <div class="form-group col-3">
                            <div class="form-group-element">
                                <?php
                                $options = [
                                    'class' => 'form-control form-control-lg custom-select',
                                    'id' => 'supplier_id',
                                ];
                                if (empty($suppliers))
                                    $options['disabled'] = true;
                                echo Html::dropDownList('supplier_id', $suppliers,
                                    !empty($_GET['supplier_id']) ? $_GET['supplier_id'] : null, $options);
                                ?>
                                <label for="supplier_id">Client</label>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <input type="text" name="id" id="id" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['id']) ? $_GET['id'] : '') ?>">
                            <label for="id">ID</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="ref" id="ref" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['ref']) ? $_GET['ref'] : '') ?>">
                            <label for="ref">Reference</label>
                        </div>
                    </div>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'status',
                            ];
                            echo Html::dropDownList('status', $status_list,
                                isset($_GET['status']) ? $_GET['status'] : 'all', $options);
                            ?>
                            <label for="status">Status</label>
                        </div>
                    </div>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'schedule',
                            ];
                            echo Html::dropDownList('schedule', ['' => '-', 'on_time' => 'On Time', 'late' => 'Late'],
                                !empty($_GET['schedule']) ? $_GET['schedule'] : null, $options);
                            ?>
                            <label for="schedule">Schedule</label>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="sku_barcode" id="sku_barcode" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['sku_barcode']) ? $_GET['sku_barcode'] : '') ?>">
                            <label for="sku_barcode">SKU barcode</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="batch_number" id="batch_number" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['batch_number']) ? $_GET['batch_number'] : '') ?>">
                            <label for="sku_barcode">SKU Batch No.</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" min="0" type="number" value="<?= View::toHtml($_GET['number_of_skus'] ?? null) ?>" id="number_of_skus" name="number_of_skus" placeholder=" ">
                            <label for="date_arrived_to">Number of accepted SKUs</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" min="0" type="number" value="<?= View::toHtml($_GET['number_of_units'] ?? null) ?>" id="number_of_units" name="number_of_units" placeholder=" ">
                            <label for="date_arrived_to">Number of accepted units</label>
                        </div>
                    </div>
                </div>
                <div class="div row justify-content-end mb-4">
                    <div class="col-auto">
                        <a href="/goods_in/all" class="btn btn-lg btn-outline-orange">Clear</a>
                        <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                    </div>
                </div>
            </form>

            <div class="row">
                <div class="col-md-12">
                    <?php if (count($asn_list)) { ?>
                        <?php View::pagination("/goods_in/all?" . $current_filters . '&', 1, $total_pages, $current_page, ASNs::LIMIT_PER_PAGE); ?>
                        <div class="row justify-content-end mr-3">
                            <a class="btn btn-outline-primary btn-rounded" href="/goods_in/csv_report?<?= $current_filters . "&count={$total_count}"; ?>">
                                <i class="fa fa-download"></i> Download results as CSV
                            </a>
                        </div>
                        <div class="row mt-3">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <colgroup>
                                        <col width="*">
                                        <col width="*">
                                        <col width="*">
                                        <col width="15%">
                                        <col width="*">
                                        <col width="7%">
                                        <?php if (!$authUser->isClientSide()) { ?>
                                            <col width="*">
                                            <col width="*">
                                        <?php } ?>
                                        <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                            <col width="*">
                                        <?php } ?>
                                        <col width="*">
                                    </colgroup>
                                    <thead>
                                    <tr>
                                        <th>ASN ID / Reference</th>
                                        <th>Expected / Arrived date</th>
                                        <th><?= $warehouse_virtual ? 'Warehouse / ' : ''; ?>Container</th>
                                        <th>Batches</th>
                                        <th>Status</th>
                                        <th>SKUs</th>
                                        <?php if (!$authUser->isClientSide() && !$warehouse_virtual) { ?>
                                            <th>Locations</th>
                                            <th>Measurements</th>
                                        <?php } ?>
                                        <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                            <th>Client / Login</th>
                                        <?php } ?>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($asn_list as $asn) { ?>
                                        <tr>
                                            <td>
                                                <?= View::toHtml($asn['id'] . " / " . ($asn['ref'] ?: '-')) .
                                                (!empty($asn['order_id']) ? ("<br/><span class='badge badge-secondary'>Returned Order ID/Ref</span> {$asn['order_id']} / " . ($asn['oref'] ?: '-')) : '') .
                                                ($asn['charge'] > 0 ? "<br/><b>Additional charge:</b> " . sprintf("%9.3f&nbsp;%s", $asn['charge']*1, $asn['billing_charge_currency']) : ''); ?>
                                            </td>
                                            <td>
                                                <span class="text-xs font-weight-bold">Expected</span><br/><?= View::toHtml(date('jS F Y',
                                                    strtotime($asn['arrival_date']))); ?>
                                                <br/>
                                                <span class="text-xs font-weight-bold">Arrived</span><br/><?= View::toHtml(empty($asn['arrived_date']) ? 'Pending' : date('jS F Y',
                                                    strtotime($asn['arrived_date']))); ?>
                                                <br/>
                                                <?php
                                                if (strtotime($asn['arrived_date']) > strtotime($asn['arrival_date'] . ASN::ARRIVAL_END_DAY)
                                                    || (empty($asn['arrived_date']) && strtotime($asn['arrival_date'] . ASN::ARRIVAL_END_DAY) < time())) {
                                                    ?>
                                                    <span class='badge badge-danger text-xs'>Late Arrival</span>
                                                <?php } ?>
                                            </td>
                                            <td><?= ($warehouse_virtual ? ('<b>' . View::toHtml($asn['ship_to_location']) . '</b><br/>') : '') . View::toHtml($asn['container'] ? $containers[$asn['container']] : 'Loose'); ?></td>
                                            <td><?= View::toHtml($asn['sku_batch_list']); ?></td>
                                            <td>
                                                <?php
                                                    $status_style = '';
                                                    switch ($asn['status']) {
                                                        case ASN::CANCELLED:
                                                            $status_style = 'secondary';
                                                            break;
                                                        case ASN::COMPLETED:
                                                            $status_style = 'success';
                                                            break;
                                                        case ASN::NON_COMPLIANT:
                                                            $status_style = 'warning';
                                                            break;
                                                        default:
                                                            $status_style = 'primary';
                                                    }
                                                ?>
                                                <span class="badge badge-<?= $status_style?>"><?= View::toHtml(ASN::getStateName($asn['status'])); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="text-xs font-weight-bold">Expected</span><br/>
                                                <?= View::toHtml($asn['sku_count'])?> / <?= View::toHtml($asn['sku_total'])?>
                                                <br>
                                                <span class="text-xs font-weight-bold">Accepted</span><br/>
                                                <?php if (!empty($asn['sku_accepted_quantity']))  {
                                                    echo View::toHtml($asn['sku_accepted_count']).' / '.View::toHtml($asn['sku_accepted_quantity']);
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <?php if (!$authUser->isClientSide() && !$warehouse_virtual) { ?>
                                                <td>
                                                    <?php if($asn['assigned_skus']) { ?>
                                                        <span class="badge badge-success">Assigned</span>
                                                    <?php } else { ?>
                                                        <?= Html::error("Contains unassigned SKUs")?>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <?php if($asn['sku_sizes_configured']) { ?>
                                                        <span class="badge badge-success">Configured</span>
                                                    <?php } else { ?>
                                                        <?= Html::error("Contains unconfigured")?>
                                                    <?php } ?>
                                                </td>
                                            <?php } ?>
                                            <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                                <td>
                                                    <b><?= View::toHtml($asn['supplier_name'])?></b><?= !empty($asn['login']) ? ('<br/>' . View::toHtml($asn['login'])) : ''; ?>
                                                </td>
                                            <?php } ?>
                                            <td>
                                                <a class="btn btn-primary btn-icon-split mb-1"
                                                   href="/goods_in/view_asn/?id=<?= (int) $asn['id'] . (!empty($asn['oref']) ? "&ref={$asn['oref']}" : ''); ?>">
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-fw fa-eye"></i>
                                                    </span>
                                                    <span class="text text-white" style="width: 100px">Details</span>
                                                </a>
                                                <?php
                                                switch ($asn['status']) {
                                                    case ASN::NEW: ?>
                                                        <?php if (!$authUser->isClientSide()) { ?>
                                                            <?php if (!$warehouse_virtual) { ?>
                                                                <br/>
                                                                <button type="button"
                                                                        class="btn btn-success btn-icon-split mb-1 js-btn-arrive"
                                                                        data-asn_id="<?= (int) $asn['id']; ?>"
                                                                        data-asn_container="<?= View::toHtml($asn['container']); ?>"
                                                                >
                                                                        <span class="icon text-white-50">
                                                                            <i class="fas fa-fw fa-sign-in-alt"></i>
                                                                        </span>
                                                                    <span class="text text-white" style="width: 100px">Arrived</span>
                                                                </button>
                                                            <?php } ?>
                                                        <?php } ?>
                                                        <?php if ($authUser->hasSupplierAccess()) { ?>
                                                            <br/>
                                                            <button class="btn btn-danger btn-icon-split mb-1 js-btn-cancel"
                                                                    data-asn_id="<?= (int) $asn['id']; ?>">
                                                                    <span class="icon text-white-50">
                                                                        <i class="fas fa-fw fa-times"></i>
                                                                    </span>
                                                                <span class="text text-white"
                                                                      style="width: 100px">Cancel</span>
                                                            </button>
                                                <?php }
                                                        break;
                                                    case ASN::ARRIVED:?>
                                                            <?php if (!$authUser->isClientSide() && !$warehouse_virtual) { ?>
                                                                <br/><a class="btn btn-success btn-icon-split mb-1"
                                                                        href='/goods_in/no_verify/?id=<?= (int) $asn['id']; ?>'>
                                                                        <span class="icon text-white-50">
                                                                            <i class="fas fa-fw fa-check"></i>
                                                                        </span>
                                                                    <span class="text text-white" style="width: 100px">No verify</span>
                                                                </a>
                                                                <br/>
                                                                <button type="button" class="btn btn-danger btn-icon-split mb-1 js-btn-revert"
                                                                        data-asn_id="<?= (int) $asn['id']; ?>">
                                                                        <span class="icon text-white-50">
                                                                            <i class="fas fa-fw fa-redo"></i>
                                                                        </span>
                                                                    <span class="text text-white" style="width: 100px">Revert</span>
                                                                </button>
                                                <?php }
                                                        break;
                                                    case ASN::PUTAWAY:?>
                                                            <?php if (!$authUser->isClientSide() && !$warehouse_virtual) { ?>
                                                                <br/><a class="btn btn-success btn-icon-split mb-1"
                                                                        href='/goods_in/complete/?id=<?= (int) $asn['id']; ?>'>
                                                                        <span class="icon text-white-50">
                                                                            <i class="fas fa-fw fa-power-off"></i>
                                                                        </span>
                                                                    <span class="text text-white" style="width: 100px">Complete</span>
                                                                </a>
                                                <?php }
                                                        break;
                                                    case ASN::COMPLETED:
                                                    case ASN::NO_VERIFIED:
                                                    case ASN::NON_COMPLIANT:?>
                                                            <br/><a class="btn btn-success btn-icon-split mb-1"
                                                                    href='/goods_in/print_ASN/?id=<?= (int) $asn['id']; ?>'
                                                                    target="_blank">
                                                                    <span class="icon text-white-50">
                                                                        <i class="fas fa-fw fa-print"></i>
                                                                    </span>
                                                                <span class="text text-white"
                                                                      style="width: 100px">Print</span>
                                                            </a>
                                                <?php
                                                        break;
                                                } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php View::pagination("/goods_in/all?" . $current_filters . '&', 1, $total_pages, $current_page, ASNs::LIMIT_PER_PAGE); ?>
                    <?php } else
                        echo Html::warning('No records found');
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$arrival_ASN_modal = new SwitchModal('arrivalASN', 'Confirm ASN Arrival');
echo $arrival_ASN_modal->addContent('
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="container">Container:</label>
                            ' . Html::dropDownList("container", $containers, null, ['class' => 'form-control js-select-container']) . '
                        </div>
                    </div>')
    ->addForm('/goods_in/arrived_asn', SwitchModal::METHOD_POST, ['id' => 'sendShippingServiceForm'])
    ->addButton('Confirm', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="id" id="asn_arrive_id">' . $arrival_ASN_modal->getButtons())
    ->put();

$modal_cancel_ASN = new ConfirmModal('cancelASN', 'Cancel ASN');
echo $modal_cancel_ASN->addContent(Html::error("Are you sure you want to cancel this ASN?"))
    ->addForm('/goods_in/cancel_asn', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="id" id="asn_cancel_id">' . $modal_cancel_ASN->getButtons())
    ->put();

$modal_revert_ASN = new ConfirmModal('revertASN', 'Revert ASN');
echo $modal_revert_ASN->addContent(Html::error("Are you sure you want to revert this ASN? This action will return ASN to the status 'New'"))
    ->addForm('/goods_in/revert_asn', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="id" id="asn_revert_id">' . $modal_revert_ASN->getButtons())
    ->put();
?>

<?= View::include_js(BACKEND_JS, "asn_all.js") ?>
