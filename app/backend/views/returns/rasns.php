<?php

use app\backend\models\ReturnASNs;
use app\core\entities\ASN;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\models\SKUs;

/**
 * @var array $suppliers
 * @var array $rasn_list
 * @var array $status_list
 * @var int $total_count
 * @var int $total_pages
 * @var int $current_page
 * @var string $current_filters
 * @var User $authUser
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Return ASNs</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-sign-in-alt"></i>
                            Return ASN records (<?= $total_count; ?> item<?= $total_count > 1 ? 's' : '' ?> total)
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="<?= Filter::getFilterTypeKey('barcode'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER; ?>"/>
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_created_from" name="date_created_from"
                                   value="<?= View::toHtml(!empty($_GET['date_created_from']) ? $_GET['date_created_from'] : '') ?>"
                                   min="<?= Arr::map($warehouses, 'id', 'start_warehouse')[$warehouse_id]; ?>"/>
                            <label for="date_created_from">Date created from</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_created_to" name="date_created_to"
                                   min="<?= View::toHtml(!empty($_GET['date_created_from']) ? $_GET['date_created_from'] : '') ?>"
                                   value="<?= View::toHtml(!empty($_GET['date_created_to']) ? $_GET['date_created_to'] : '') ?>"/>
                            <label for="date_created_to">Date created to</label>
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
                                    echo Html::dropDownList('supplier_id', $suppliers, !empty($_GET['supplier_id']) ? $_GET['supplier_id'] : null, $options);
                                ?>
                                <label for="supplier_id">Client</label>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'actioned',
                            ];
                            echo Html::dropDownList('actioned', ['all' => 'All', 1 => 'Yes', 0 => 'No'],
                                isset($_GET['actioned']) ? $_GET['actioned'] : 'all', $options);
                            ?>
                            <label for="actioned">CS Actioned</label>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <input type="text" name="id" id="id" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['id']) ? $_GET['id'] : '') ?>">
                            <label for="id">ID</label>
                        </div>
                    </div>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <input type="text" name="order_id" id="order_id" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['order_id']) ? $_GET['order_id'] : '') ?>">
                            <label for="order_id">Order ID</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="ref" id="ref" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['ref']) ? $_GET['ref'] : '') ?>">
                            <label for="ref">Order reference</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="ref2" id="ref2" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['ref2']) ? $_GET['ref2'] : '') ?>">
                            <label for="ref2">Order reference 2</label>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="contact" id="contact" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['contact']) ? $_GET['contact'] : '') ?>">
                            <label for="contact">Contact Name</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="email" id="email" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['email']) ? $_GET['email'] : '') ?>">
                            <label for="email">Email</label>
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
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="barcode" id="barcode" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['barcode']) ? $_GET['barcode'] : '') ?>">
                            <label for="ref">SKU Barcode</label>
                        </div>
                    </div>
                </div>
                <div class="text-right mb-3">
                    <a href="/return_ASNs" class="btn btn-lg btn-outline-orange">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                </div>
            </form>
            <div class="row">
                <div class="col-md-12">
                    <?php if (count($rasn_list)) { ?>
                        <?php View::pagination("/return_ASNs?" . $current_filters . '&', 1, $total_pages,
                            $current_page, ReturnASNs::LIMIT_PER_PAGE); ?>
                        <div class="row justify-content-end mr-3">
                            <a class="btn btn-outline-primary btn-rounded" href="/return_ASNs/csv_report?<?= $current_filters . "&count={$total_count}"; ?>">
                                <i class="fa fa-download"></i> Download all filtered return ASNs as CSV
                            </a>
                        </div>
                        <div class="row mt-3">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <colgroup>
                                        <col width="10%">
                                        <col width="*">
                                        <col width="15%">
                                        <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                            <col width="*">
                                        <?php } ?>
                                        <col width="15%">
                                        <col width="10%">
                                        <col width="10%">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th class="align-top">ASN ID / Reference</th>
                                            <th class="align-top">Order ID / Reference / Reference 2<hr class="m-0 p-0"/>Contact Name<br/>Email</th>
                                            <th class="align-top">Created / Completed date</th>
                                            <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                                <th class="align-top">Client / Login</th>
                                            <?php } ?>
                                            <th class="align-top">Stored location</th>
                                            <th class="align-top">Status</th>
                                            <th class="align-top">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rasn_list as $rasn) { ?>
                                            <tr>
                                                <td><?= View::toHtml($rasn['id'] . " / " . ($rasn['ref'] ?: '-')); ?></td>
                                                <td<?= $rasn['unscheduled'] ? ' class="alert-warning"' : ''; ?>><?= "<b>{$rasn['order_id']} / Ref: " . View::toHtml($rasn['order_ref'] ?: '-') . " / Ref2: " . View::toHtml($rasn['order_ref2'] ?: '-') . "</b><hr class=\"m-0 p-0\"/>" . View::toHtml($rasn['contact']) . '<br/>' . View::toHtml($rasn['email']); ?></td>
                                                <td class="pt-2">
                                                    <span class="text-xs font-weight-bold">Created</span><br/><?= View::toHtml(date('jS F Y', strtotime($rasn['arrival_date']))); ?>
                                                    <?php if (!empty($rasn['completed_date'])) { ?>
                                                        <br/>
                                                        <span class="text-xs font-weight-bold">Completed</span><br/><?= View::toHtml(date('jS F Y', strtotime($rasn['completed_date']))); ?>
                                                    <?php } ?>
                                                </td>
                                                <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                                    <td><b><?= View::toHtml($rasn['supplier_name'])?></b><?= !empty($rasn['login']) ? ('<br/>' . View::toHtml($rasn['login'])) : ''; ?></td>
                                                <?php } ?>
                                                <td><?= View::toHtml($rasn['ship_to_location'] ?: '-'); ?></td>
                                                <td>
                                                    <span class="badge badge-<?= ASN::getStateStyleReturn($rasn['status']); ?>"><?= View::toHtml(ASN::getStateNameReturn($rasn['status'])); ?></span><br/>
                                                    <?= $rasn['actioned'] ? '<span class="badge badge-nextday"><i class="fas fa-fw fa-flag"></i> CS Actioned</span>' : ''; ?>
                                                </td>
                                                <td>
                                                    <a class="btn btn-primary btn-icon-split mb-1" href="/return_ASNs/view_rasn?id=<?= (int) $rasn['id']; ?>">
                                                        <span class="icon text-white-50">
                                                            <i class="fas fa-fw fa-eye"></i>
                                                        </span>
                                                        <span class="text text-white" style="width: 100px">Details</span>
                                                    </a>
                                                    <?php if (ASN::isWarehouse($rasn['status'])) {
                                                        if ($authUser->isWarehouseSide()) { ?>
                                                            <br/>
                                                            <a class="btn btn-success btn-icon-split mb-1" href='/return_ASNs/complete/?id=<?= (int) $rasn['id']; ?>'>
                                                                <span class="icon text-white-50">
                                                                    <i class="fas fa-fw fa-power-off"></i>
                                                                </span>
                                                                <span class="text text-white" style="width: 100px">Complete</span>
                                                            </a>
                                                        <?php } } elseif (!ASN::isCompleted($rasn['status']) && !ASN::isCancelled($rasn['status']) && !$authUser->isRoleWorker()) { ?>
                                                        <button class="btn btn-danger btn-icon-split mb-1 js-btn-cancel" data-rasn_id="<?= (int) $rasn['id']; ?>">
                                                            <span class="icon text-white-50">
                                                                <i class="fas fa-fw fa-times"></i>
                                                            </span>
                                                            <span class="text text-white" style="width: 100px">Cancel</span>
                                                        </button>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php View::pagination("/return_ASNs?" . $current_filters . '&', 1, $total_pages,
                            $current_page, ReturnASNs::LIMIT_PER_PAGE); ?>
                    <?php } else
                        echo Html::warning('No records found');
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="cancelRASNModal">
    <form action="/return_ASNs/cancel_rasn" method="POST">
        <input type="hidden" name="id" id="rasn_cancel_id">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Return ASN</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?= Html::error("Are you sure you want to cancel this Return ASN?")?>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal" type="button">No</button>
                    <button class="btn btn-primary" type="submit">Yes</button>
                </div>
            </div>
        </div>
    </form>
</div>
<?= View::include_js(BACKEND_JS, "rasns.js") ?>
