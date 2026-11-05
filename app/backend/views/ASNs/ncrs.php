<?php

use app\backend\models\ASNs;
use app\core\entities\ASN;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\models\SKUs;

/**
 * @var User $authUser
 * @var int $total_count
 * @var int $current_page
 * @var int $total_pages
 * @var array $suppliers
 * @var array $ncr_list
 * @var string $current_filters
 * @var string $containers
 * @var bool $warehouse_virtual
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">NCR List</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-sign-in-alt"></i>
                        NCR records (<?= $total_count; ?> item<?= $total_count > 1 ? 's' : '' ?> total)
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="<?= Filter::getFilterTypeKey('sku_barcode'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER; ?>"/>
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
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="sku_barcode" id="sku_barcode" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['sku_barcode']) ? $_GET['sku_barcode'] : '') ?>">
                            <label for="sku_barcode">SKU barcode</label>
                        </div>
                    </div>
                </div>
                <div class="div row justify-content-end mb-4">
                    <div class="col-auto">
                        <a href="/goods_in/ncrs" class="btn btn-lg btn-outline-orange">Clear</a>
                        <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                    </div>
                </div>
            </form>
            <div class="row">
                <div class="col-md-12">
                    <?php if (!empty($ncr_list)) { ?>
                        <?php View::pagination("/goods_in/ncrs?" . $current_filters . '&', 1, $total_pages, $current_page, ASNs::LIMIT_PER_PAGE); ?>
                        <div class="row mt-3">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                    <tr>
                                        <th>ASN ID / Reference</th>
                                        <th>Expected / Arrived date</th>
                                        <th><?= $warehouse_virtual ? 'Warehouse / ' : ''; ?>Container</th>
                                        <th>Expected SKUs</th>
                                        <th>Last comment</th>
                                        <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                            <th>Client / Login</th>
                                        <?php } ?>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($ncr_list as $ncr) { ?>
                                        <tr>
                                            <td>
                                                <?= View::toHtml($ncr['id'] . " / " . ($ncr['ref'] ?: '-')) .
                                                (!empty($ncr['order_id']) ? ("<br/><span class='badge badge-secondary'>Return</span> {$ncr['order_id']} / " . ($ncr['oref'] ?: '-')) : '') .
                                                ($ncr['charge'] > 0 ? "<br/><b>Additional charge:</b> " . sprintf("%9.3f&nbsp;%s", $ncr['charge']*1, $ncr['billing_charge_currency']) : ''); ?>
                                            </td>
                                            <td>
                                                <b>Expected:</b> <?= View::toHtml(date('jS F Y',
                                                    strtotime($ncr['arrival_date']))); ?>
                                                <br/>
                                                <b>Arrived:</b> <?= View::toHtml(empty($ncr['arrived_date']) ? 'Pending' : date('jS F Y',
                                                    strtotime($ncr['arrived_date']))); ?>
                                                <br/>
                                                <?php
                                                    if (strtotime($ncr['arrived_date']) > strtotime($ncr['arrival_date'] . ASN::ARRIVAL_END_DAY)
                                                        || (empty($ncr['arrived_date']) && strtotime($ncr['arrival_date'] . ASN::ARRIVAL_END_DAY) < time())) {
                                                ?>
                                                        <span class='badge badge-danger text-xs'>Late Arrival</span>
                                                <?php } ?>
                                            </td>
                                            <td><?= ($warehouse_virtual ? ('<b>' . View::toHtml($ncr['ship_to_location']) . '</b><br/>') : '') . View::toHtml($ncr['container'] ? $containers[$ncr['container']] : 'Loose'); ?></td>
                                            <td>
                                                <?= View::toHtml($ncr['sku_count'])?> / <?= View::toHtml($ncr['sku_total'])?>
                                            </td>
                                            <td>
                                                <span class="badge border border-dark bg-<?= $ncr['last_comment']['supplier_id'] == 1 ? 'default' : 'warning'; ?>">
                                                    <?= View::toHtml($ncr['last_comment']['last_date']); ?><br/><b><?= $ncr['last_comment']['user_id'] == $authUser->getId() ? 'My comment' : (View::toHtml($ncr['last_comment']['login']) . ((!empty($ncr['last_comment']['name']) && !empty($ncr['last_comment']['user_id'])) ? '<br/>(' . View::toHtml($ncr['last_comment']['name']) . '#' . (int)$ncr['last_comment']['user_id'] . ')' : '')); ?></b>
                                                </span>
                                            </td>
                                            <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                                <td>
                                                    <b><?= View::toHtml($ncr['supplier_name'])?></b><?= !empty($ncr['login']) ? ('<br/>' . View::toHtml($ncr['login'])) : ''; ?>
                                                </td>
                                            <?php } ?>
                                            <td>
                                                <a class="btn btn-primary btn-icon-split"
                                                   href="/goods_in/view_ncr/?id=<?= (int) $ncr['id'] . (!empty($ncr['oref']) ? "&ref={$ncr['oref']}" : ''); ?>">
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-fw fa-eye"></i>
                                                    </span>
                                                    <span class="text text-white">View Comments</span>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php View::pagination("/goods_in/ncrs?" . $current_filters . '&', 1, $total_pages, $current_page, ASNs::LIMIT_PER_PAGE); ?>
                    <?php } else
                        echo Html::warning('No records found');
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= View::include_js(BACKEND_JS, "asn_all.js") ?>
