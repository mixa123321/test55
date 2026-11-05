<?php

use app\backend\models\Substitutions;
use app\core\entities\SubstitutionCondition;
use app\core\entities\User;
use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\modal\ConfirmModal;
use app\core\models\SKUs;

/**
 * @var array $substitutions
 * @var int $total_count
 * @var int $current_page
 * @var int $total_pages
 * @var array $suppliers
 * @var array $current_filters
 * @var User $authUser
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Substitution Rules</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                        <span>
                            <i class="fas fa-fw fa-exchange-alt"></i>
                            Rules list (<?= (int)$total_count; ?> item<?= $total_count > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <form class="form-inline" method="GET" action="/substitutions/substitutions_add" id="create_form">
                        <?php if ($authUser->isRoleSupplierUser()) { ?>
                            <input type="hidden" name="supplier_id" value="<?= array_keys($suppliers)[0]; ?>" />
                        <?php } ?>
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control custom-select',
                                'id' => 'create_supplier_id'
                            ];
                            if ($authUser->isRoleSupplierUser())
                                $options['disabled'] = 'disabled';
                            echo Html::dropDownList('supplier_id', $suppliers, null, $options);
                            ?>
                        </div>
                        <button type="submit" class="btn btn-success ml-1">Create new</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" id="filter_form">
                <input type="hidden" name="<?= Filter::getFilterTypeKey('barcode'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER ?>"/>
                <div class="form-row">
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
                        <a href="/substitutions" class="btn btn-lg btn-outline-orange mr-1">Clear</a>
                        <button type="submit" class="btn btn-lg btn-orange ml-1">Filter</button>
                    </div>
                </div>
            </form>
            <?php if (count($substitutions)) { ?>
                <?php View::pagination("/substitutions/?" . $current_filters . '&', 1, $total_pages, $current_page,Substitutions::LIMIT_PER_PAGE); ?>
                <div class="row mt-3">
                    <div class="table-responsive col">
                        <table class="table table-hover" id="table-bundles">
                            <colgroup>
                                <col width="15%">
                                <col width="*">
                                <col width="10%">
                                <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                    <col width="20%">
                                <?php } ?>
                                <col width="10%">
                                <col width="10%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Original SKU</th>
                                    <th>Substitution (SKUs)</th>
                                    <th>Conditions</th>
                                    <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                        <th>Client</th>
                                    <?php } ?>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($substitutions as $substitution) { ?>
                                    <tr<?= !$substitution['check'] ? ' style="background-color: #fee;"' : ''; ?>>
                                        <td><?= View::toHtmlConvertWithPrimaryBarcode($substitution['sub_barcode']) . ' (#' . View::toHtml($substitution['substitution_id']) . ')'?></td>
                                        <td><?= View::toHtmlConvertWithPrimaryBarcode($substitution['SKUs']);?></td>
                                        <td>
                                            <?php
                                                $conds = [];
                                                foreach ($substitution['conditions'] as $cond => $value)
                                                    $conds[] = '<i class="text-success far fa-fw fa' . ($value ? '-check' : '') . "-circle\"></i> $cond";
                                                echo implode('<br/>', $conds);
                                            ?>
                                        </td>
                                        <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                            <td><?= View::toHtml($suppliers[$substitution['supplier_id']]);?></td>
                                        <?php } ?>
                                        <td><span class="badge badge-<?= SubstitutionCondition::COLOR_STATUSES[$substitution['status']]; ?>"><?= SubstitutionCondition::NAME_STATUSES[$substitution['status']]; ?></span></td>
                                        <td>
                                            <a class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                                href="/substitutions/substitutions_edit?id=<?= (int)$substitution['substitution_id']; ?>">
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-fw fa-edit"></i>
                                                </span>
                                                <span class="text text-white flex-fill">Edit</span>
                                            </a>
                                            <?php if (!empty($substitution['exist_warehouse_orders'])) { ?>
                                                <button class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1 js-orders-btn"
                                                        id="orders_<?= $substitution['substitution_id'] ?>">
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-fw fa-clipboard-list"></i>
                                                    </span>
                                                    <span class="text text-white flex-fill">Orders</span>
                                                </button>
                                            <?php }
                                                $not_status = SubstitutionCondition::getNotStatus($substitution['status']);
                                            ?>
                                            <button class="btn btn-<?= SubstitutionCondition::COLOR_STATUSES[$not_status]; ?> btn-block btn-icon-split justify-content-start text-nowrap mt-1 js-lock-btn"
                                                    id="lock_<?= $substitution['substitution_id'] ?>" data-status="<?= $substitution['status'] ?>">
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-fw fa-<?= $not_status ? 'unlock' : 'lock'; ?>"></i>
                                                </span>
                                                <span class="text text-white flex-fill"><?= $not_status ? 'Unlock' : 'Lock'; ?></span>
                                            </button>
                                            <button class="btn btn-danger btn-block btn-icon-split justify-content-start text-nowrap mt-1 mb-1 <?= empty($substitution['exist_orders']) ? 'js-delete-btn' : ('disabled ' . (empty($substitution['exist_warehouse_orders']) ? 'tooltip_delete' : '')) ?> "
                                                    id="rule_<?= $substitution['substitution_id'] ?>">
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
                            title: 'Unable to delete because the rule is used in order for other warehouses'
                        });
                    });
                </script>
                <?php View::pagination("/substitutions/?" . $current_filters . '&', 1, $total_pages, $current_page,Substitutions::LIMIT_PER_PAGE); ?>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="ordersSubstitutionModal">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Substitution in orders</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <span id="ob-loading" style="display: none; margin-bottom: 0px"></span>
                <table id="ordersSubstitutionTable" class="table table-hover w-100">
                    <thead>
                    <tr><th>Order Reference</th>
                        <th>Created Date</th>
                        <th>Source</th>
                        <th>Quantity</th>
                        <th>Status</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <table class="table w-100 mb-0" id="ordersStatTable">
                    <tr>
                        <td class="p-0"><div class="alert-info pr-lg-3 p-2"><span class="ml-md-3">In progress </span><span class="float-right ml-lg-4" id="orders_in_progress"></span></div></td>
                        <td class="p-0"><div class="alert-primary pr-lg-3 p-2"><span class="ml-md-3">Despatched </span><span class="float-right ml-lg-4" id="orders_despatched"></span></div></td>
                        <td class="p-0"><div class="alert-secondary pr-lg-3 p-2"><span class="ml-md-3">Cancelled </span><span class="float-right ml-lg-4" id="orders_canceled"></span></div></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-orange" data-dismiss="modal" type="button">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$modal_lock_substitution = new ConfirmModal('lockSubstitution', '<span id="lockSubstitutionTitle"></span>');
echo $modal_lock_substitution->addContent(Html::error('Are you sure?'))
    ->addForm('/substitutions/substitutions_lock', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="substitution_id" id="substitution_id_lock">' . $modal_lock_substitution->getButtons())
    ->put();

$modal_delete_substitution = new ConfirmModal('deleteSubstitution', 'Delete rule');
echo $modal_delete_substitution->addContent(Html::error("Are you sure?"))
    ->addForm('/substitutions/substitutions_delete', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="id" id="substitution_id">' . $modal_delete_substitution->getButtons())
    ->put();
?>
<script>
    $('.js-lock-btn').click((e) => {
        let id = e.currentTarget.id.split('_')[1];
        $('#substitution_id_lock').val(id);
        let title = e.currentTarget.getAttribute('data-status') === '0' ? 'Unlock rule' : 'Lock rule';
        $('#lockSubstitutionTitle').html(title);
        <?= $modal_lock_substitution->show(); ?>
    });

    $('.js-delete-btn').click((e) => {
        let id = e.currentTarget.id.split('_')[1];
        <?= $modal_delete_substitution->show(); ?>
        $('#substitution_id').val(id);
    });

    const loader = document.getElementById('ob-loading');
    const ordersSubstitutionTable = document.getElementById('ordersSubstitutionTable');
    ordersSubstitutionTable.style.display = 'none';
    const set_stat = (in_progress = 0, despatched = 0, cancelled = 0) => {
        document.getElementById('orders_in_progress').innerText = in_progress.toString();
        document.getElementById('orders_despatched').innerText = despatched.toString();
        document.getElementById('orders_canceled').innerText = cancelled.toString();
    }
    set_stat();
    $('.js-orders-btn').click((e) => {
        let id = e.currentTarget.id.split('_')[1];
        $.ajax({
            url: "/substitutions/get_substitution_orders",
            method: "POST",
            dataType: 'json',
            data: { id: id },
            success: function (response) {
                loader.style.display = 'none';
                if (response.stat !== undefined) {
                    set_stat(
                        response.stat.in_progress || 0,
                        response.stat.despatched || 0,
                        response.stat.cancelled || 0
                    )
                }
                if (response.orders !== undefined && response.orders.length > 0) {
                    ordersSubstitutionTable.style.display = '';
                    const rows = response.orders.reduce((temp, order) => {
                         temp += `<tr>
                                         <td>${html_escape(order.ref)} (#${html_escape(order.id)})<br>${html_escape(order.ref2)}</td>
                                         <td>${html_escape(order.create_date)}</td>
                                         <td>${html_escape(order.type_create)}</td>
                                         <td>${html_escape(order.quantity)}</td>
                                         <td><span class="badge badge-${html_escape(order.status_color)}">${html_escape(order.status)}</span></td>
                                     </tr>`;
                         return temp;
                    }, '');
                    ordersSubstitutionTable.querySelector('tbody').innerHTML = rows;
                }
            },
            error: function (json) {
                set_stat();
                loader.className = 'alert alert-danger';
                loader.innerText = 'Server error...';
                document.getElementById('ordersStatTable').style.display = 'none';
                loader.style.display = 'block';
            }
        });
        $('#ordersSubstitutionModal').modal('show');
    });
</script>