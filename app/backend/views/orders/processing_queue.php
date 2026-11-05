<?php
/**
 * @var $authUser User
 * @var $total_count int
 * @var $orders array
 * @var $back string
 * @var $current_filters string
 * @var $total_pages int
 * @var $current_page int
 * @var $sources array
 * @var $suppliers array
 * @var $statuses array
 */

use app\backend\models\Orders;
use app\backend\models\Shippings;
use app\core\entities\OrderQueue;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\Html;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\status\OrderStatus;
use app\core\helpers\View;
use app\core\statical\WithDate;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Processing Queue</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                        <span>
                            <i class="fas fa-fw fa-sort-numeric-down"></i>
                            Orders queue (<?= $total_count; ?> order<?= $total_count > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
                <?php if (!empty($suppliers)) { ?>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split" id="btn_suppliers_queue">
                            <span class="text text-white">Companies Queue</span>
                            <span class="icon text-white"><i class="fas fa-fw fa-user-circle"></i></span>
                        </button>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="back" value="<?= View::toHtml($back); ?>"/>
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control form-control-lg custom-select',
                                'id' => 'supplier_id',
                            ];
                            if (empty($suppliers))
                                $options['disabled'] = true;
                            echo Html::dropDownList('supplier_id', ['All'] + Arr::map($suppliers, 'supplier_id', 'name'),
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
                                    'id' => 'status',
                                ];
                                echo Html::dropDownList('status', $statuses, !empty($_GET['status']) ? $_GET['status'] : null, $options);
                            ?>
                            <label for="supplier_id">Status</label>
                        </div>
                    </div>
<!--                    <div class="form-group col-2">-->
<!--                        <div class="form-group-element">-->
<!--                            <input class="form-control form-control-lg" type="date" id="date_despatched" name="date_despatch"-->
<!--                                   value="--><?//= View::toHtml($_GET['date_despatch'] ?? date('Y-m-d')); ?><!--"-->
<!--                                   min="--><?//= Arr::map($warehouses, 'id', 'start_warehouse')[$warehouse_id]; ?><!--"-->
<!--                                   max="--><?//= date('Y-m-d'); ?><!--"/>-->
<!--                            <label for="date_despatch">Despatch date</label>-->
<!--                        </div>-->
<!--                    </div>-->
                </div>
                <div class="row justify-content-end mr-3 mb-3">
                    <a href="/orders/processing_queue?back=<?= urlencode($back); ?>" class="btn btn-lg btn-outline-orange mr-1">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange ml-1">Filter</button>
                </div>
            </form>
            <?php if (count($orders)) { ?>
                <?php View::pagination("/orders/processing_queue?" . $current_filters . '&', 1, $total_pages, $current_page, Orders::LIMIT_PER_PAGE); ?>
                <div class="row mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <colgroup>
                                <col width="20%"/>
                                <col width="*"/>
                                <col width="*"/>
                                <col width="12%"/>
                                <col width="*"/>
                                <col width="7%"/>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>IDs</th>
                                    <th>Shipping/Date</th>
                                    <th>Delivery Address</th>
                                    <th>Status</th>
                                    <th>Client/Source</th>
                                    <th>Priority</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order) { ?>
                                    <tr>
                                        <td>
                                            <b>ID:</b> <?= View::toHtml($order['order_id']) ?><br/>
                                            <b>Reference:</b> <?= View::toHtml($order['ref'] ?: '-') ?><br/>
                                            <b>Reference 2:</b> <?= View::toHtml($order['ref2'] ?: '-') ?><br/>
                                        </td>
                                        <td>
                                            <b>Carrier:</b> <?= View::toHtml(Shippings::getServiceParamsById($order['shipping_services_id'])['name']) ?>
                                            <br/>
                                            <b>Created:</b> <?= View::toHtml(date(WithDate::DATE_FRONT_FORMAT,
                                                strtotime($order['create_date']))) ?>
                                            <br/>
                                            <b>Despatch:</b> <?= (!empty($order['despatch_date']) && strtotime($order['despatch_date']) > 0) ? View::toHtml(date('jS M Y',
                                                strtotime($order['despatch_date']))) : '-'; ?>
                                        </td>
                                        <td>
                                            <?= $order['address']; ?>
                                        </td>
                                        <td>
                                            <?= OrderStatus::get($order['status']); ?>
                                            <?php if (!$authUser->isRoleSupplierUser() && $order['priority']) { ?>
                                                <br/><span class="badge badge-purple">priority</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?= View::toHtml($order['name_sup']); ?><br/>
                                            <b><?= empty($order['type_create']) ? '-' : View::toHtml($sources[$order['type_create']]); ?></b>
                                        </td>
                                        <td>
                                            <?php
                                                $options = [
                                                    'class' => 'form-control custom-select js-priority',
                                                    'id' => "pri_{$order['order_id']}",
                                                ];
                                                echo Html::dropDownList('', OrderQueue::PRIORITY, "{$order['priority_order']}", $options);
                                            ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php View::pagination("/orders/processing_queue?" . $current_filters . '&', 1, $total_pages, $current_page, Orders::LIMIT_PER_PAGE); ?>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/orders/?<?= View::toHtml(base64_decode($back)); ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-chevron-left"></i>
                        </span>
                        <span class="text">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if (!empty($suppliers)) {
    $modal_queue = new SwitchModal('companiesQueue', 'Companies queue');
    $rows = [];
    foreach ($suppliers as $sw_id => $supplier) {
        $options = [
            'class' => 'form-control custom-select',
            'id' => "sw_$sw_id",
        ];
        $rows[] = [
            'name' => View::toHtml($supplier['name']),
            'priority' => Html::dropDownList("priority[$sw_id]", OrderQueue::PRIORITY, "{$supplier['priority']}", $options)
        ];
    }
    $rows = '<tr>' . implode('</tr><tr>', array_map(function($item) {
        return "<td>{$item['name']}</td><td>{$item['priority']}</td>";
    }, $rows)) . '</tr>';
    echo $modal_queue
        ->addContent("
            <div class='alert alert-danger row m-0 mb-3 p-2 d-none' id='err_msg'></div>
            <input type='hidden' name='filters' value='$current_filters'/>
            <input type='hidden' name='c' value='$current_page'/>
            <div class='row'>
                <div class='col table-responsive'>
                    <table class='table table-hover mb-0'>
                        <colgroup>
                            <col width='*'/>
                            <col width='25%'/>
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Priority</th>
                            </tr>
                        </thead>
                        <tbody>
                            $rows
                        </tbody>
                    </table>
                </div>
            </div>")
        ->addForm(SwitchModal::ACTION_DEFAULT, SwitchModal::METHOD_DEFAULT, ['id' => 'update_suppliers_queue'])
        ->addButton('Update', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE | SwitchModal::BUTTON_CONFIRM, ['class' => 'js-btn-conf'])
        ->put();
} ?>
<script>
    <?php if (!empty($suppliers)) { ?>
        $('#btn_suppliers_queue').click(function() {
            <?php
                foreach ($suppliers as $sw_id => $supplier)
                    echo "$('#sw_$sw_id').val({$supplier['priority']});";
                echo $modal_queue->show();
            ?>
        });

        $('.js-btn-conf').click(function() {
            $.ajax({
                url: "/orders/update_suppliers_queue",
                method: "POST",
                data: $('#update_suppliers_queue').serialize(),
                dataType: "json",
                success: function (json) {
                    if ('error' in json)
                        showError('err_msg', json.error, 3000);
                    else
                        location.reload();
                },
                error: function (json) {
                    showError('err_msg', 'Connection error', 3000);
                }
            });
            return false;
        });
    <?php } ?>

    $('.js-priority').change(function(e) {
        var id = e.currentTarget.id.split('_')[1];
        $.ajax({
            url: "/orders/update_orders_queue",
            method: "POST",
            data: { order_id: id, priority: this.value },
            dataType: "json",
            success: function (json) {
                if ('error' in json)
                    location = json.error;
            },
            error: function (json) {
                const params = <?= View::toJs($current_filters); ?>;
                location = "/orders/processing_queue?back=" + encodeURIComponent(<?= View::toJs($back); ?>) + (params ? "&" + params : "") + "&c=<?= (int)$current_page; ?>";
            }
        });
    });
</script>
