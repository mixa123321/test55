<?php

use app\backend\controllers\AuthController;
use app\backend\models\Orders;
use app\core\entities\Order;
use app\core\entities\ShippingService;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\status\OrderStatus;
use app\core\helpers\status\Status;
use app\core\helpers\View;
use app\core\models\config\OrdersConfig;
use app\core\models\Shippings;
use app\core\statical\WithDate;

/**
 * @var AuthController $controller
 * @var Order[] $orders
 * @var int $current_page
 * @var int $total_count
 * @var int $total_pages
 * @var array $suppliers
 * @var array $countries
 * @var array $statuses
 * @var array $shipping_services
 * @var array $sources
 * @var array $docs
 * @var User $authUser
 * @var array $date_types
 * @var array $warehouses
 * @var int $warehouse_id
 * @var string $current_filters
 * @var array $subwarehouses
 * @var bool $warehouse_virtual
 * @var array $exclude_ids
 */

$can_create = $controller->hasAccessToRoute('orders/add');
$can_upload = $can_create && $controller->hasAccessToRoute('upload/orders');
$can_create_similar = $can_create && $controller->hasAccessToRoute('orders/similar');

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Orders</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                        <span>
                            <i class="fas fa-fw fa-sign-in-alt"></i>
                            Orders list (<?= (int)$total_count; ?> item<?= $total_count > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
                <?php if ($authUser->isRoleSA()) { ?>
                    <?php if (!$warehouse_virtual) { ?>
                        <div class="col-auto">
                            <a class="btn btn-primary btn-icon-split" href="/orders/processing_queue?back=<?= base64_encode(http_build_query($_GET)); ?>">
                                <span class="text text-white">Processing Queue</span>
                                <span class="icon text-white"><i class="fas fa-fw fa-sort-numeric-down"></i></span>
                            </a>
                        </div>
                    <?php } ?>
                    <div class="col-auto">
                        <a class="btn btn-primary btn-icon-split" href="/orders/collection_time?back=<?= base64_encode(http_build_query($_GET)); ?>">
                            <span class="text text-white">Collection Time</span>
                            <span class="icon text-white"><i class="far fa-fw fa-calendar-alt"></i></span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-danger btn-icon-split" id="bulk_cancel">
                            <span class="text text-white">Bulk cancel</span>
                            <span class="icon text-white">
                                    <i class="fas fa-fw fa-times"></i>
                                </span>
                        </button>
                    </div>
                <?php } ?>
                <?php if ($can_upload) { ?>
                    <div class="col-auto">
                        <a class="btn btn-primary btn-icon-split" href="/upload/orders">
                            <span class="text text-white">Upload orders</span>
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-chevron-up"></i>
                            </span>
                        </a>
                    </div>
                <?php } ?>
                <?php if ($can_create) { ?>
                    <div class="col-auto">
                        <a class="btn btn-success btn-icon-split" href="/orders/add">
                            <span class="text text-white">Create new</span>
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-plus"></i>
                            </span>
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="form-row mb-3 pb-2" style="border-bottom: grey 1px solid !important;">
                    <div class="form-group col-lg-6 mb-0">
                        <div class="form-group-element">
                            <input type="text" name="global_search" id="global_search"
                                   class="form-control form-control-lg filled"
                                   placeholder="Search anything related to order here, more than 2 characters required"
                                   value="<?= View::toHtml(!empty($_GET['global_search']) ? $_GET['global_search'] : '') ?>">
                            <label for="ref">Global search</label>
                        </div>
                    </div>
                    <div class="form-group col-lg-3 mb-0">
                        <div class="form-group-element">
                            <input type="text" name="id" id="id"
                                   class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['id']) ? $_GET['id'] : '') ?>">
                            <label for="id">ID</label>
                        </div>
                    </div>
                    <div class="form-group col-3 mb-0">
                        <div class="form-group-element">
                            <input type="text" name="ref" id="ref" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['ref']) ? $_GET['ref'] : '') ?>">
                            <label for="ref">Reference</label>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_from"
                                   name="date_from"
                                   value="<?= View::toHtml(!empty($_GET['date_from']) ? $_GET['date_from'] : '') ?>"
                                   min="<?= Arr::map($warehouses, 'id', 'start_warehouse')[$warehouse_id]; ?>"/>
                            <label for="date_from">Date from</label>
                        </div>
                    </div>
                    <div class="form-group col-1">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg time_group" type="time" id="time_from"
                                   name="time_from"
                                   value="<?= View::toHtml(!empty($_GET['time_from']) ? $_GET['time_from'] : '') ?>" />
                            <label for="time_from">Time from</label>
                        </div>
                    </div>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_to"
                                   name="date_to"
                                   min="<?= View::toHtml(!empty($_GET['date_from']) ? $_GET['date_from'] : '') ?>"
                                   value="<?= View::toHtml(!empty($_GET['date_to']) ?  $_GET['date_to'] : '') ?>"/>
                            <label for="date_to">Date to</label>
                        </div>
                    </div>
                    <div class="form-group col-1">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg time_group" type="time" id="time_to"
                                   name="time_to"
                                   value="<?= View::toHtml(!empty($_GET['time_to']) ?  $_GET['time_to'] : '') ?>"/>
                            <label for="time_to">Time to</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control form-control-lg custom-select',
                                'id' => 'date_type',
                            ];
                            echo Html::dropDownList('date_type', $date_types,
                                !empty($_GET['date_type']) ? $_GET['date_type'] : null, $options);
                            ?>
                            <label for="date_type">Date Type</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'status',
                            ];
                            if (empty($statuses))
                                $options['disabled'] = true;
                            echo Html::dropDownList('status', $statuses,
                                !empty($_GET['status']) ? $_GET['status'] : null, $options);
                            ?>
                            <label for="status">Status</label>
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
                        <div class="form-group col-2">
                            <div class="form-group-element">
                                <?php
                                $options = [
                                    'class' => 'form-control form-control-lg custom-select',
                                    'id' => 'type_create',
                                ];
                                echo Html::dropDownList('type_create', $sources,
                                    !empty($_GET['type_create']) ? $_GET['type_create'] : null, $options);
                                ?>
                                <label for="type_create">Source</label>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'shipping_services_id',
                            ];
                            echo Html::dropDownList('shipping_services_id', $shipping_services,
                                (isset($_GET['shipping_services_id']) && $_GET['shipping_services_id'] !== 'all') ? $_GET['shipping_services_id'] : null, $options);
                            ?>
                            <label for="shipping_services_id">Shipping Services</label>
                        </div>
                    </div>
                    <div class="form-group col-1">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'special',
                            ];
                            echo Html::dropDownList('special', ShippingService::SPECIAL_NAMES,
                                isset($_GET['special']) ? $_GET['special'] : 0, $options);
                            ?>
                            <label for="special">Special</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'country_id',
                            ];
                            echo Html::dropDownList('country_id', $countries,
                                !empty($_GET['country_id']) ? $_GET['country_id'] : null, $options);
                            ?>
                            <label for="country_id">Country</label>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-end mr-3 mb-3">
                    <input type="hidden" id="search_focus" name="search_focus" />
                    <a href="/orders?date_from" class="btn btn-lg btn-outline-orange mr-1">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange ml-1">Filter</button>
                </div>
            </form>
            <?php if (count($orders)) { ?>
                <?php View::pagination("/orders/?" . $current_filters . '&', 1, $total_pages, $current_page, Orders::LIMIT_PER_PAGE); ?>
                <div class="row justify-content-end mr-3">
                    <a class="btn btn-outline-primary btn-rounded" href="/orders/csv_report?<?= $current_filters . "&count={$total_count}"; ?>">
                        <i class="fa fa-download"></i> Download all filtered orders as CSV
                    </a>
                </div>
                <div class="row mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>IDs</th>
                                <th>Shipping/Date</th>
                                <th>Delivery Address</th>
                                <th>Status</th>
                                <th>
                                    <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                        Client/<?php } ?>Source
                                </th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($orders as $order) { ?>
                                <tr>
                                    <td>
                                        <b>ID:</b> <?= View::toHtml($order['order_id']) ?><br/>
                                        <b>Reference:</b> <?= View::toHtml($order['ref'] ?: '-') ?><br/>
                                        <b>Reference 2:</b> <?= View::toHtml($order['ref2'] ?: '-') ?><br/>
                                        <?= $order['charge'] > 0 ? "<br/><b>Additional charge:</b> " . sprintf("%9.3f&nbsp;%s",
                                                $order['charge'] * 1, $order['billing_charge_currency']) : ''; ?>
                                        <?= $order['special'] ? Status::getStatus(ShippingService::SPECIAL_NAMES[$order['special']], 'success') : ''; ?>
                                        <?= $order['printed_matter'] ? Status::getStatus('Printed Matter', 'success') : ''; ?>
                                    </td>
                                    <td>
                                        <b>Carrier:</b> <?= View::toHtml(Shippings::getServiceParamsById($order['shipping_services_id'])['name']) ?>
                                        <br/>
                                        <b>Created:</b> <?= View::toHtml(date(WithDate::DATE_FRONT_FORMAT,
                                            strtotime($order['create_date']))) ?>
                                        <br/>
                                        <b>Despatch:</b> <?= (!empty($order['despatch_date']) && strtotime($order['despatch_date']) > 0) ? View::toHtml(date('jS M Y',
                                            strtotime($order['despatch_date']))) : "-" ?>
                                        <br/>
                                        <b>Despatched:</b> <?= (!empty($order['despatched_date']) && strtotime($order['despatched_date']) > 0) ? View::toHtml(date(WithDate::DATE_FRONT_FORMAT,
                                            strtotime($order['despatched_date']))) : "-" ?>
                                        <?php if (!empty($subwarehouses[$order['order_id']])) { ?>
                                            <br/>
                                            <b>Shipped from:</b> <?= View::toHtml($subwarehouses[$order['order_id']]); ?>
                                        <?php } ?>
                                    </td>
                                    <td><?= $order['address']; ?></td>
                                    <td>
                                        <?= OrderStatus::get($order['status']); ?>
                                        <?php if (!$authUser->isRoleSupplierUser() && $order['priority']) { ?>
                                            <br/><span class="badge badge-purple">priority</span>
                                        <?php } ?>
                                        <?php if (!empty($order['nums'])) { ?>
                                            <br/>
                                            <span class="badge badge-secondary mt-0">returned (<?= $order['nums']; ?>)</span>
                                        <?php } ?>
                                        <?php if ($order['risks']) { ?>
                                            <br/><span class="badge badge-danger">Fraud risk</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php
                                            if (!$authUser->isRoleSupplierUser())
                                                echo View::toHtml($order['name_sup']) . '<br/>';
                                            echo '<b>' . (empty($order['type_create']) ? '-' : View::toHtml($sources[$order['type_create']])) . '</b> ';
                                            if (in_array($order['order_id'], $docs))
                                                echo Html::img(COMMON_IMG . DIRECTORY_SEPARATOR . 'attachment.png', ['style' => 'max-width: 25px;']);
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!$order['is_hash']) { ?>
                                            <?php if (!$authUser->isRoleSupplierUser() && !$warehouse_virtual && Order::canBePriority($order['status'], $order['priority'])) { ?>
                                                <button type="button"
                                                        class="btn btn-block btn-purple mt-0 mb-1 js-priority-btn"
                                                        data-order-id="<?= (int)$order['order_id'] ?>">Prioritise
                                                </button>
                                            <?php } ?>
                                            <?php if (Order::isPendingApproval($order['status'])) { ?>
                                                <button type="button"
                                                        class="btn btn-block btn-success mt-0 mb-1 js-approve-btn"
                                                        data-order-id="<?= (int)$order['order_id'] ?>">Approve
                                                </button>
                                            <?php } ?>
                                        <?php } ?>
                                        <a href="/orders/view_order?id=<?= (int)$order['order_id'] . (!empty($order['nums']) ? "&ret=1" : ''); ?>" class="btn btn-primary btn-block mb-1">Details</a>
                                        <?php if (!$order['is_hash']) { ?>
                                            <?php if ($can_create_similar) { ?>
                                                <button type="button"
                                                        class="btn btn-block btn-success mt-0 mb-1 js-similar-btn"
                                                        data-order-id="<?= (int)$order['order_id'] ?>">Duplicate
                                                </button>
                                            <?php } ?>
                                            <?php if (Order::isExpired($order['status'])) { ?>
                                                <button type="button"
                                                        class="btn btn-block btn-success mt-0 mb-1 js-resume-btn"
                                                        data-order-id="<?= (int)$order['order_id'] ?>">Resume
                                                </button>
                                            <?php } ?>
                                            <?php if (Order::canQuery($order['status']) && $authUser->hasManagerAccess()) { ?>
                                                <button type="button"
                                                        class="btn btn-block btn-success mt-0 mb-1 js-query-btn"
                                                        data-order-id="<?= (int)$order['order_id'] ?>">Query
                                                </button>
                                            <?php } elseif (Order::isQuery($order['status']) && $authUser->hasManagerAccess()) { ?>
                                                <button type="button"
                                                        class="btn btn-block btn-success mt-0 mb-1 js-reinstate-btn"
                                                        data-order-id="<?= (int)$order['order_id'] ?>">Reinstate
                                                </button>
                                            <?php } ?>
                                            <?php if ($authUser->isRoleSA()) { ?>
                                                <button type="button"
                                                        class="btn btn-block btn-outline-<?= $exclude_ids[$order['order_id']]['order_exclude_status'] ? 'success' : 'danger' ?> mt-0 mb-1 js-exclude-btn"
                                                        data-order-id="<?= (int)$order['order_id'] ?>"
                                                        data-order-status="<?= !$exclude_ids[$order['order_id']]['order_exclude_status'] ?>"
                                                        data-get-status="<?= OrdersConfig::getExcludeOrderStatus($order['order_id']) ?>">
                                                    <?= $exclude_ids[$order['order_id']]['order_exclude_status'] ? 'In' : 'Ex' ?>clude
                                                </button>
                                            <?php } ?>
                                            <?php if (Order::canCancelOrder($order['status'], $warehouse_virtual, $authUser->isRoleSupplierUser())) { ?>
                                                <button type="button"
                                                        class="btn btn-block btn-danger mt-0 mb-1 js-cancel-btn"
                                                        data-order-id="<?= (int)$order['order_id'] ?>">Cancel
                                                </button>
                                            <?php } elseif (Order::isCancelled($order['status'])) { ?>
                                                <button type="button"
                                                        class="btn btn-block btn-success mt-0 mb-1 js-recreate-btn"
                                                        data-order-id="<?= (int)$order['order_id'] ?>">Recreate
                                                </button>
                                            <?php } elseif (Order::isPaused($order['status'])) { ?>
                                                <button type="button"
                                                        class="btn btn-block btn-success mt-0 mb-1 js-continue-btn"
                                                        data-order-id="<?= (int)$order['order_id'] ?>">Recreate
                                                </button>
                                            <?php } ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php View::pagination("/orders/?" . $current_filters . '&', 1, $total_pages, $current_page, Orders::LIMIT_PER_PAGE); ?>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
    </div>
</div>
<?php
    $modal_cancel = new ConfirmModal('cancelOrderModal', 'Cancel order');
    echo $modal_cancel->addContent('<div class="alert alert-danger mb-0">Are you sure?</div>')
        ->addForm('/orders/cancel', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons('<input type="hidden" name="id" id="cancel_id">' . $modal_cancel->getButtons())
        ->put();

    $modal_priority = new ConfirmModal('priorityOrderModal', 'Prioritise order');
    echo $modal_priority->addContent('<div class="alert alert-info mb-0">Are you sure?</div><div class="priority-response"></div>')
        ->addForm('/orders/priority', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons('<input type="hidden" name="id" id="priority_id">' . $modal_priority->getButtons())
        ->put();

    $modal_approve = new ConfirmModal('approveOrderModal', 'Approve order');
    echo $modal_approve->addContent('<div class="alert alert-info mb-0">Are you sure?</div>')
        ->addForm('/orders/approve', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons('<input type="hidden" name="id" id="approve_id">' . $modal_approve->getButtons())
        ->put();

    $modal_recreate = new ConfirmModal('recreateOrderModal', 'Recreate order');
    echo $modal_recreate->addContent('<div class="alert alert-info mb-0">Are you sure?</div>')
        ->addForm('/orders/recreate', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons('<input type="hidden" name="id" id="recreate_id">' . $modal_recreate->getButtons())
        ->put();

    $modal_continue = new ConfirmModal('continueOrderModal', 'Fixed order');
    echo $modal_continue->addContent('<div class="alert alert-info mb-0">Are you sure?</div>')
        ->addForm('/orders/continue_order', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons('<input type="hidden" name="id" id="continue_id">' . $modal_continue->getButtons())
        ->put();

    $modal_query = new ConfirmModal('queryOrderModal', 'Query order');
    echo $modal_query->addContent('<div class="alert alert-info mb-0">Are you sure?</div>')
        ->addForm('/orders/query_order', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons('<input type="hidden" name="id" id="query_id">' . $modal_query->getButtons())
        ->put();

    $modal_revert_query = new ConfirmModal('revertQueryOrderModal', 'Revert order from query');
    echo $modal_revert_query->addContent('<div class="alert alert-info mb-0">Are you sure?</div>')
        ->addForm('/orders/revert_query_order', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons('<input type="hidden" name="id" id="revert_query_id">' . $modal_revert_query->getButtons())
        ->put();

    $modal_exclude = new SwitchModal('excludeOrder', '<span class="text" id="title"></span>');
    echo $modal_exclude->addContent('
            <div class="row">            
                <div class="col-12">
                    <p class="form-control-static mb-0"><b>Comment:</b></p>
                    <textarea class="form-control" rows="3" name="comment" id="exclude_comment"></textarea>
                </div>
            </div>')
        ->addForm('/orders/exclude_order', SwitchModal::METHOD_POST)
        ->addButton('<span class="text" id="text_btn"></span>', SwitchModal::TYPE_SUBMIT, SwitchModal::BUTTON_DONE, ['id' => 'exclude_btn'])
        ->addCustomButtons('<input type="hidden" name="id" id="exclude_id"> <input type="hidden" name="status" id="exclude_status"> ' . $modal_exclude->getButtons())
        ->put();

    $modal_similar = new ConfirmModal('similarOrderModal', 'Create duplicate');
    echo $modal_similar->addContent('<div class="alert alert-info mb-0">Are you sure?</div>')
        ->addForm('/orders/similar', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons('<input type="hidden" name="id" id="similar_id">' . $modal_similar->getButtons())
        ->put();

    $modal_bulk_cancel = new ConfirmModal('bulkCancelModal', 'Bulk cancel');
    echo $modal_bulk_cancel->addContent('Enter the orders id with the help of any separators
            <textarea class="form-control" id="order_ids" rows="3" style="resize: none"></textarea>
            <div class="alert alert-danger mb-0 d-none" id="bulk_error"></div>')
        ->addCustomButtons('
            <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancel</button>
            <button class="btn btn-danger" id="bulk_confirm" disabled>Yes</button>
        ')
        ->put();

    $modal_resume = new ConfirmModal('resumeOrderModal', 'Resume order');
    echo $modal_resume->addContent('<div class="alert alert-info mb-0">Are you sure?</div>')
        ->addForm('/orders/resume', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons('<input type="hidden" name="id" id="resume_id">' . $modal_resume->getButtons())
        ->put();
?>
<?= View::include_js(BACKEND_JS, 'orders.js') ?>
<script>
    $('.js-cancel-btn').click(function () {
        <?= $modal_cancel->show(); ?>;
        $('#cancel_id').val($(this).data('order-id'));
    });
    $('.js-priority-btn').click(function () {
        <?= $modal_priority->show(); ?>;
        $('#priority_id').val($(this).data('order-id'));
    });
    $('.js-approve-btn').click(function () {
        <?= $modal_approve->show(); ?>;
        $('#approve_id').val($(this).data('order-id'));
    });
    $('.js-recreate-btn').click(function () {
        <?= $modal_recreate->show(); ?>;
        $('#recreate_id').val($(this).data('order-id'));
    });
    $('.js-continue-btn').click(function () {
        <?= $modal_continue->show(); ?>;
        $('#continue_id').val($(this).data('order-id'));
    });
    $('.js-query-btn').click(function () {
        <?= $modal_query->show(); ?>
        $('#query_id').val($(this).data('order-id'));
    });
    $('.js-reinstate-btn').click(function () {
        <?= $modal_revert_query->show(); ?>
        $('#revert_query_id').val($(this).data('order-id'));
    });
    $('.js-exclude-btn').click(function() {
        <?= $modal_exclude->show(); ?>
        $('#exclude_id').val($(this).data('order-id'));
        $('#exclude_status').val($(this).data('order-status'));
        let order_status = $(this).data('get-status');
        if (order_status) {
            $('#title').text('Include order in billing');
            $('#text_btn').text('Include');
        }
        else {
            $('#title').text('Exclude order from billing');
            $('#text_btn').text('Exclude');
        }
        console.log(order_status);
    });

    $('.js-similar-btn').click(function () {
        <?= $modal_similar->show(); ?>;
        $('#similar_id').val($(this).data('order-id'));
    });
    $('.js-resume-btn').click(function () {
        <?= $modal_resume->show(); ?>;
        $('#resume_id').val($(this).data('order-id'));
    });
    $('#bulk_cancel').click(() => {
        $('#order_ids').val('');
        <?= $modal_bulk_cancel->show(); ?>;
    });

    $('#bulk_confirm').click(() => {
        $.ajax({
            url: "/orders/bulk_cancel",
            method: "POST",
            dataType: 'json',
            data: { order_ids: $('#order_ids').val().trim() },
            success: function (json) {
                if ('error' in json) {
                    if ('edit' in json)
                        $('#order_ids').val(json.edit);
                    showError('bulk_error', json.error, 3000);
                } else
                    location = '/orders?<?= $current_filters; ?>';
            },
            error: function (json) {
                showError('bulk_error', 'Connection not found', 3000);
            }
        });
    });

    $('#exclude_comment').on('input', function() {
        const isEmpty = $(this).val().trim() === '';
        $('#exclude_btn').prop('disabled', isEmpty);
    });

    $('#exclude_btn').prop('disabled', $('#exclude_comment').val().trim() === '');
</script>