<?php

use app\core\entities\User;
use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\View;
use app\core\models\SKUs;

/**
 * @var User $authUser
 * @var array $SKUs
 * @var int $size
 * @var array $suppliers
 * @var int $pages
 * @var int $current
 * @var string $current_filters
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Expired (goods out)</h1>
    </div>
    <div class="alert alert-danger p-2 d-none" id="msg_error"></div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-tasks"></i>
                            SKUs list (<?= $size; ?> item<?= $size > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
                <?php if ($authUser->hasManagerAccess()) { ?>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split js-btn-history">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-history"></i>
                            </span>
                            <span class="text">History</span>
                        </button>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="/batches/expired_tool">
                <input type="hidden" name="<?= Filter::getFilterTypeKey('barcode'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER ?>"/>
                <div class="form-row">
                    <div class="form-group col-lg-2">
                        <div class="form-group-element">
                            <input type="text" name="id" id="id" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['id']) ? $_GET['id'] : '') ?>">
                            <label for="id">ID</label>
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
                    <div class="form-group col-lg-2">
                        <div class="form-group-element">
                            <input type="text" name="batch" id="batch" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['batch']) ? $_GET['batch'] : '') ?>">
                            <label for="barcode">Batch</label>
                        </div>
                    </div>
                    <?php if (!empty($suppliers)) { ?>
                        <div class="form-group col-lg-2 align-self-center">
                            <div class="form-group-element">
                                <select id="supplier_id" name="supplier_id" class="form-control form-control-lg custom-select">
                                    <?php foreach ($suppliers as $supplier_id => $supplier_name) { ?>
                                        <option value="<?= $supplier_id; ?>"<?= $supplier_id == $_GET['supplier_id'] ? ' selected' : "" ?>><?= View::toHtml($supplier_name); ?></option>
                                    <?php } ?>
                                </select>
                                <label for="supplier_id">Companies</label>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="text-right mb-3">
                    <a href="/batches/expired_tool" class="btn btn-lg btn-outline-orange">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                </div>
            </form>
            <?php if (!empty($SKUs)) { ?>
                <?php View::pagination("/batches/expired_tool?" . $current_filters . '&', 1, $pages, $current,RECORD_PAGE); ?>
                <table class="table table-hover">
                    <colgroup>
                        <col width="7%">
                        <col width="*">
                        <col width="*">
                        <?php if (!empty($suppliers))
                            echo "<col width=\"*\">\n";
                        ?>
                        <col width="*">
                        <col width="12%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>SKU</th>
                            <th>Batches</th>
                            <?php if (!empty($suppliers))
                                echo "<th>Company</th>\n";
                            ?>
                            <th>Quantity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($SKUs as $SKU) { ?>
                            <tr>
                                <td><?= View::toHtml($SKU['id']) ?></td>
                                <td><?= View::toHtml($SKU['name']); ?><br/><b><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></b></td>
                                <td>
                                    <b>Batch#:</b> <?= View::toHtml($SKU['number'])?><br/>
                                    <b>Exp#:</b> <?= View::toHtml(date('jS M Y', strtotime($SKU['expired'])))?>
                                </td>
                                <?php if (!empty($suppliers)) { ?>
                                    <td><?= View::toHtml($suppliers[$SKU['supplier_id']]) ?></td>
                                <?php } ?>
                                <td><?= View::toHtml($SKU['quantity']) ?></td>
                                <td>
                                    <button class="btn btn-danger btn-icon-split btn-block justify-content-start text-nowrap mt-0 mb-1 js-btn-destroy"
                                            data-sku_id="<?= View::toHtml($SKU['id']) ?>"
                                            data-quantity="<?= View::toHtml($SKU['quantity']) ?>"
                                            data-batch_id="<?= View::toHtml($SKU['batch_id']) ?>"
                                            data-batch_number="<?= View::toHtml($SKU['number']) ?>"
                                            data-batch_expired="<?= View::toHtml(date('jS M Y', strtotime($SKU['expired']))) ?>">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-trash"></i>
                                        </span>
                                        <span class="text flex-fill">Destroy</span>
                                    </button>
                                    <button class="btn btn-primary btn-icon-split btn-block justify-content-start text-nowrap mt-0 mb-1 js-btn-expired-order"
                                            data-sku_id="<?= View::toHtml($SKU['id']) ?>"
                                            data-quantity="<?= View::toHtml($SKU['quantity']) ?>"
                                            data-batch_id="<?= View::toHtml($SKU['batch_id']) ?>"
                                            data-batch_number="<?= View::toHtml($SKU['number']) ?>"
                                            data-batch_expired="<?= View::toHtml(date('jS M Y', strtotime($SKU['expired']))) ?>">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-share-square"></i>
                                        </span>
                                        <span class="text flex-fill">Expired Batch Order</span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php View::pagination("/batches/expired_tool?" . $current_filters . '&', 1, $pages, $current,RECORD_PAGE); ?>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
    </div>
</div>
<?php
    $modal_destroy = new ConfirmModal('destroyBatch', 'Destroy expired batch');
    echo $modal_destroy->addContent('
                Would you like to destroy batch \'<span id="d_number"></span>\' expired at <span id="d_expired"></span> (SKU #<span id="d_SKU_id"></span>)?
                <input type="hidden" id="SKU_id"/>
                <input type="hidden" id="batch_id"/>
                <input type="hidden" id="quantity"/>
            ')
            ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'destroyConf'])
            ->put();
    $modal_order = new ConfirmModal('expiredOrder', 'Expired batch order');
    echo $modal_order->addContent('
                <div class="row">
                    <div class="col">
                        Would you like to destroy batch \'<span id="o_number"></span>\' expired at <span id="o_expired"></span> (SKU #<span id="o_SKU_id"></span>) and manually create order?
                        <input type="hidden" id="oSKU_id"/>
                        <input type="hidden" id="obatch_id"/>
                        <input type="hidden" id="oquantity"/>
                    </div>
                </div>
                <div class="row justify-content-end">
                    <div class="col-auto mt-2">
                        <a class="btn btn-success btn-icon-split" id="print_manifest" target="_blank">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-print"></i>
                            </span>
                            <span class="text text-white">Print manifest</span>
                        </a>
                    </div>
                </div>
            ')
            ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'orderConf'])
            ->put();
    $modal_history = new InfoModal('historyConsumable', 'History (last 1 month)', InfoModal::SIZE_XL);
    echo $modal_history->addContent('
                <ul class="nav nav-tabs nav-pills mt-1" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="destroy-tab" data-toggle="tab" data-target="#destroy" type="button" role="tab" aria-controls="stock" aria-selected="true">Destroy</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="order-tab" data-toggle="tab" data-target="#order" type="button" role="tab" aria-controls="order" aria-selected="false">Expired batch order</button>
                    </li>
                </ul>
                <div class="tab-content col-12">
                    <div class="tab-pane fade show active" id="destroy" role="tabpanel" aria-labelledby="destroy-tab"></div>
                    <div class="tab-pane fade show" id="order" role="tabpanel" aria-labelledby="order-tab"></div>
                </div>
        ')
    ->put();
?>
<script>
    $('.js-btn-destroy').click(function (e) {
        e.preventDefault();
        let destroy_batch = $(this);
        $('#d_number').text(destroy_batch.data('batch_number'));
        $('#d_expired').text(destroy_batch.data('batch_expired'));
        $('#d_SKU_id').text(destroy_batch.data('sku_id'));
        $('#SKU_id').val(destroy_batch.data('sku_id'));
        $('#batch_id').val(destroy_batch.data('batch_id'));
        $('#quantity').val(destroy_batch.data('quantity'));
        <?= $modal_destroy->show(); ?>
    });

    $('.js-btn-expired-order').click(function (e) {
        e.preventDefault();
        let destroy_batch = $(this);
        $('#o_number').text(destroy_batch.data('batch_number'));
        $('#o_expired').text(destroy_batch.data('batch_expired'));
        $('#o_SKU_id').text(destroy_batch.data('sku_id'));
        $('#oSKU_id').val(destroy_batch.data('sku_id'));
        $('#obatch_id').val(destroy_batch.data('batch_id'));
        $('#oquantity').val(destroy_batch.data('quantity'));
        $('#print_manifest').attr('href', '/batches/manifest/?id=' + destroy_batch.data('sku_id') + '&batch_id=' + destroy_batch.data('batch_id'));
        <?= $modal_order->show(); ?>
    });

    $('#destroyConf').click(() => {
        location = '/batches/destroy_expired/?SKU_id=' + $('#SKU_id').val() + '&batch_id=' + $('#batch_id').val() + '&quantity=' + $('#quantity').val() + '&<?= $current_filters; ?>';
    });

    $('#orderConf').click(() => {
        location = '/batches/destroy_expired/?SKU_id=' + $('#oSKU_id').val() + '&batch_id=' + $('#obatch_id').val() + '&quantity=' + $('#oquantity').val() + '&<?= $current_filters; ?>&order=1';
    });

    $('.js-btn-history').click((e) => {
        $.ajax({
            url: "/batches/get_history_destroy",
            method: "POST",
            dataType: "json",
            data: { },
            success: function (json) {
                $('#destroy').html(json.destroy === '[]' ? '<div class="alert alert-warning mt-3 mb-0">No records found</div>' : getHistory(JSON.parse(json.destroy)));
                $('#order').html(json.order === '[]' ? '<div class="alert alert-warning mt-3 mb-0">No records found</div>' : getHistory(JSON.parse(json.order)));
                <?= $modal_history->show(); ?>
            },
            error: function (json) {
                showError('msg_error', 'Database error', 3000);
            }
        });
    });

    function getHistory(histories)
    {
        let history = '';
        $.each(histories, (key, item) => {
            history += '<tr><td>' + html_escape(item.event_date) + '</td><td>' + item.SKU + '</td><td>' + item.batch + '</td><td>' + html_escape(item.supplier) + '</td><td>' + html_escape(item.quantity) + '</td></tr>';
        });
        return `
            <div class="col-12 mt-3">
                <div class="table-responsive" style="max-height: 500px">
                     <table class="table table-hover mb-0">
                        <thead>
                            <th>Date</th>
                            <th>SKU</th>
                             <th>Batches</th>
                            <th>Company</th>
                            <th>Quantity</th>
                        </thead>
                        <tbody>` + history + `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
</script>

