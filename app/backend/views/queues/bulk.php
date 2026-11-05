<?php

use app\core\entities\User;
use app\core\entities\Queue;
use app\core\helpers\Html;
use app\backend\models\Pack;
use app\core\helpers\View;
use app\backend\models\Pagination;
use app\frontend\models\PrintLabel;
use app\core\helpers\HTTP;
use app\core\models\BulkLogic;
use app\core\helpers\modal\InfoModal;

/**
 * @var $queues array
 * @var $type string
 * @var $print_types array
 * @var $document_print_types array
 * @var $total_records int
 * @var $total_pages int
 * @var $current_page int
 * @var $authUser User
 */

$title = 'Packing bulk (queue)';
if ($type === Pack::PACKING_SINGLE)
    $header_name = 'Bulk';
else
    $header_name = 'Bulk list (' .  $total_records . ' item' . ($total_records > 1 ? 's' : '') . ')';
$show_user = $authUser->isRoleSA();
$all_statuses = [
    Queue::STATUS_NEW => '<span class="p-2 mr-1 mb-1 badge badge-primary">new</span>',
    Queue::STATUS_PROCESS => '<span class="p-2 mr-1 mb-1 badge badge-warning">process</span>',
    Queue::STATUS_DONE => '<span class="p-2 mr-1 mb-1 badge badge-success">done</span>',
    Queue::STATUS_ERROR => '<span class="p-2 mr-1 mb-1 badge badge-danger">error</span>',
    Queue::STATUS_SKIP => '<span class="p-2 mr-1 mb-1 badge badge-secondary">skip</span>',
];
?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= $title; ?></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-server"></i>
                        <?= $header_name; ?>
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-danger d-none" id="error-bulk"></div>
            <div class="row justify-content-center mt-2 mb-2 p-0">
                <div class="col-md-12">
                    <?php if(!empty($queues)) { ?>
                        <?php if ($type === Pack::PACKING_MULTI)
                            View::pagination("/queues/bulk_list?" . http_build_query($_GET) . '&', 1, $total_pages, $current_page, Pagination::LIMIT_PER_PAGE);
                        ?>
                        <table class="table table-hover mb-0" id="user_jobs">
                            <colgroup>
                                <?php if ($show_user) { ?>
                                    <col width="10%">
                                    <col width="20%">
                                    <col width="10%">
                                    <col width="20%">
                                    <col width="10%">
                                    <col width="30%">
                                <?php } else { ?>
                                    <col width="10%">
                                    <col width="20%">
                                    <col width="20%">
                                    <col width="10%">
                                    <col width="40%">
                                <?php } ?>
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Id</th>
                                <th>Create date</th>
                                <?php if ($show_user) { ?>
                                    <th>User</th>
                                <?php } ?>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($queues as $id => $queue) { ?>
                                <tr id="queue-<?= $id ?>">
                                    <td><?= $id ?></td>
                                    <td><?= $queue['create_date_front'] ?></td>
                                    <?php if ($show_user) { ?>
                                        <td><?= View::toHtml($queue['user_login']) ?></td>
                                    <?php } ?>
                                    <td>
                                        <div class="row progress-row d-none">
                                            <div class="col-8">
                                                <div class="form-group">
                                                    <input type="range" class="form-control-range mt-1 col-progress-range" disabled min="0" max="100" value="0" style="background: red">
                                                </div>
                                            </div>
                                            <div class="col-4 pl-0">
                                                <span class="col-progress-number"></span><span class="col-progress-percent">%</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="queue-status"></span>
                                        <button class="btn btn-outline-primary p-2 mb-1 mr-1 tip d-none error-ids" style="cursor: pointer; width: 40px; height: 40px" data-id="<?= $id ?>">
                                            <span><i class="fas fa-exclamation"></i></span><span class="tiptext">Error orders</span>
                                        </button>
                                    </td>
                                    <td>
                                        <div class="print-row d-none">
                                            <div class="row">
                                                <div class="col-4">
                                                    <?= Html::dropDownList('print_types', $print_types, null, ['class' => 'custom-select form-control print_types']) ?>
                                                </div>
                                                <div class="col-4">
                                                    <?= Html::dropDownList('label_printer_type', $queue['print_orders'], null, ['class' => 'custom-select form-control print_orders']) ?>
                                                </div>
                                                <div class="col-4">
                                                    <button class="btn btn-success btn-icon-split print mb-1 btn-block d-flex justify-content-start text-nowrap" data-id="<?= $id; ?>" data-hash="<?= $queue['hash']; ?>" type="button">
                                                        <span class="icon float-left"><i class="fas fa-fw fa-print"></i></span>
                                                        <span class="text text-white">Print</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="row mt-2 document-print-row d-none">
                                                <div class="col-4">
                                                    <?php /** @var array $document_print_types */ ?>
                                                    <?= Html::dropDownList('document_print_types', $document_print_types, null, ['class' => 'custom-select form-control document_print_types']) ?>
                                                </div>
                                                <div class="col-4">
                                                    <?= Html::dropDownList('document_print_orders', [BulkLogic::PRINT_ALL => BulkLogic::PRINT_ALL] + ($queue['document_orders'] ?? []), null, ['class' => 'custom-select form-control document_print_orders']) ?>
                                                </div>
                                                <div class="col-4">
                                                    <button class="btn btn-success btn-icon-split print-documents mb-1 btn-block d-flex justify-content-start text-nowrap" data-id="<?= $id; ?>" type="button">
                                                        <span class="icon float-left"><i class="fas fa-fw fa-print"></i></span>
                                                        <span class="text text-white">Print</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="row mt-2">
                                                <div class="col-4 offset-8">
                                                    <button class="btn btn-success btn-icon-split to-new return-button d-none btn-block justify-content-start text-nowrap" data-id="<?= $id; ?>" type="button">
                                                        <span class="icon float-left"><i class="fas fa-fw fa-redo"></i></span>
                                                        <span class="text text-white">Rerun</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    <?php } else {
                        echo Html::warning('No records found');
                    } ?>
                </div>
            </div>
        </div>
        <?php if ($type === Pack::PACKING_SINGLE) { ?>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-outline-orange pl-3 btn-sm" href="<?= current($queues)['back'] ?>">
                            <i class="fas fa-fw fa-chevron-left mr-2"></i>
                            <span class="ml-1">Back</span>
                        </a>
                    </div>
                    <div class="col-auto btn-done d-none">
                        <a class="btn btn-orange pr-3 btn-sm" href="<?= current($queues)['back'] ?>">
                            <span class="ml-1">Done</span>
                            <i class="fas fa-fw fa-chevron-right ml-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
<?php
$content_error_orders = "<div id='error-orders-content'></div>";
$modal_error_orders = new InfoModal('errorOrders', 'Error orders', InfoModal::SIZE_LG);
echo $modal_error_orders->addContent($content_error_orders)->put();
?>
<script>
    $(document).ready(function () {
        let queues = <?= View::toJs($queues) ?>;
        let is_single = <?= View::toJs($type === Pack::PACKING_SINGLE) ?>;
        class UpdateQueue {
            constructor() {
                this.statuses_for_update = [<?= Queue::STATUS_NEW; ?>, <?= Queue::STATUS_PROCESS; ?>];
                this.status_done = <?= Queue::STATUS_DONE ?>;
                this.status_error = <?= Queue::STATUS_ERROR ?>;
                this.all_statuses = <?= View::toJs($all_statuses) ?>;
                this.pausa = 3000;
                this.print_parameters = {};
                this.errors_ids = {};
                this.show_user = <?= View::toJs((int)$show_user) ?>;
            }
            getErrorsIds(id) {
                return this.errors_ids[id] === undefined ? [] : this.errors_ids[id];
            }
            getPrintParameter(order_id) {
                return this.print_parameters[order_id];
            }
            renderDocumentPrintRow(tr, document_orders) {
                let document_row = tr.find('.document-print-row');
                document_row.prop('classList').add('d-none');
                if (!document_orders || Object.keys(document_orders).length === 0)
                    return;
                let select = document_row.find('.document_print_orders');
                select.empty();
                select.append($('<option></option>').val(<?= View::toJs(BulkLogic::PRINT_ALL) ?>).text(<?= View::toJs(BulkLogic::PRINT_ALL) ?>));
                for (const order_id in document_orders) {
                    select.append($('<option></option>').val(order_id).text(document_orders[order_id]));
                }
                document_row.prop('classList').remove('d-none');
            }
            renderQueues(queues) {
                for (const id in queues) {
                    let queue = queues[id];
                    let percent = queue['percent'];
                    let status = this.all_statuses[queue['status']];
                    let tr = $('#queue-' + id);
                    tr.find('.queue-status').html(status);
                    tr.find('.col-progress-range').val(percent);
                    tr.find('.col-progress-number').html(percent);
                    tr.find('.progress-row').prop('classList').add('d-none');
                    tr.find('.print-row').prop('classList').add('d-none');
                    tr.find('.document-print-row').prop('classList').add('d-none');
                    tr.find('.return-button').prop('classList').add('d-none');
                    tr.find('.return-button').prop('classList').remove('d-flex');
                    if (queue.json_data !== undefined && queue.json_data.error_ids !== undefined && queue.json_data.error_ids.length !== 0) {
                        tr.find('.error-ids').prop('classList').remove('d-none');
                        let error_ids = queue.json_data.error_ids;
                        for (const error_id of error_ids) {
                            let option = tr.find('option[value="' + error_id + '"]');
                            option.remove();
                            $(option[0]).addClass("bg-warning");
                        }
                        this.errors_ids[id] = error_ids;
                    } else {
                        this.errors_ids[id] = [];
                    }
                    if (queue.json_data.error_ids.length === queue.json_data.order_ids.length) {
                        tr.find('.print').prop('classList').add('d-none');
                    } else {
                        tr.find('.print').prop('classList').remove('d-none');
                    }
                    if (queue['status'] === this.status_done || queue['status'] === this.status_error) {
                        tr.find('.print-row').prop('classList').remove('d-none');
                        this.renderDocumentPrintRow(tr, queue['document_orders']);
                        if (this.show_user) {
                            tr.find('.return-button').prop('classList').remove('d-none');
                            tr.find('.return-button').prop('classList').add('d-flex');
                        }
                        let print_parameters = queue['print_parameters'];
                        for (const order_id in print_parameters)
                            this.print_parameters[order_id] = print_parameters[order_id];
                    } else {
                        tr.find('.progress-row').prop('classList').remove('d-none');
                    }
                    if ($('.btn-done').length) {
                        if (is_single && queue['status'] === this.status_done) {
                            $('.btn-done').prop('classList').remove('d-none');
                        } else {
                            $('.btn-done').prop('classList').add('d-none');
                        }
                    }
                }
                return this;
            }
            getQueueIdsInProcess(queues) {
                let queues_in_process = [];
                for (const id in queues) {
                    if (this.statuses_for_update.includes(+queues[id]['status']))
                        queues_in_process.push(id);
                }
                return queues_in_process;
            }
            getProcessData(queues_in_process) {
                if (queues_in_process.length === 0)
                    return;
                let self = this;
                $.post(
                    "/queues/get_process_data",
                    {ids: queues_in_process},
                    res => {
                        if (res.error) {
                            showError('error-bulk', res.error, 3000);
                            return;
                        }
                        if (res.data === undefined || res.data.queues === undefined) {
                            showError('error-bulk', 'Unknown error', 3000);
                            return;
                        }
                        self.renderQueues(res.data.queues);
                        self.start(res.data.queues);
                    },
                    "json"
                );
            }
            start(queues) {
                window.setTimeout(() => {
                    this.getProcessData(this.getQueueIdsInProcess(queues));
                }, this.pausa);
                return this;
            }
        }
        let update_queue = (new UpdateQueue()).renderQueues(queues).start(queues);
        $('.to-new').click(function () {
            let id = $(this).data('id');
            $.ajax({
                url: '/queues/to_new',
                method: 'POST',
                dataType: 'json',
                data: {id: id},
                success: function (json) {
                    if (json.error !== '')
                        showError('error-bulk', json.error, 3000);
                    else
                        window.location.reload();
                },
                error: function (json) {
                    showError('error-bulk', 'Connection is not established', 3000)
                }
            });
        });
        $('.error-ids').click(function () {
            let id = $(this).data('id');
            let error_ids = update_queue.getErrorsIds(id);
            let prefix_url = <?= View::toJs('/orders/' . ($authUser->isRoleWorker() ? 'minified' : 'view_order')) ?>;
            let html = '';
            for (const error_id of error_ids) {
                let url = prefix_url + '?id=' + error_id;
                let a = "<a href='" + url + "'>#" + error_id + "</a>";
                html = html + ' ' + a;
            }
            $('#error-orders-content').html(html);
            <?= $modal_error_orders->show(); ?>;
        });
        $('.print').click(function () {
            let type_zpl = <?= View::toJs(PrintLabel::TYPE_ZPL) ?>;
            let type_pdf = <?= View::toJs(PrintLabel::TYPE_PDF) ?>;
            let id = $(this).data('id');
            let queue_tr = $('#queue-' + id);
            let print_type = queue_tr.find('.print_types').val();
            let print_order = queue_tr.find('.print_orders').val();
            if (![type_zpl, type_pdf].includes(print_type))
                return
            let zprn_url = <?= View::toJs(HTTP::getZprnUrl('/main/print_label')) ?>;
            let pdf_url = <?= View::toJs(HTTP::getDomain() . '/main/print_label') ?>;
            let url = print_type === type_pdf ? pdf_url : zprn_url;
            let params = '';
            if (print_order === <?= View::toJs(BulkLogic::PRINT_ALL) ?>) {
                params = '?bulk_queue_id=' + id + '&viewMode=' + print_type + '&hash=' + $(this).data('hash');
            } else {
                let print_parameter = update_queue.getPrintParameter(print_order);
                if (!print_parameter) {
                    showError('error-bulk', 'No label', 3000);
                    return;
                }
                params = '?' + print_parameter['print_type'] + '=' + print_parameter['print_id'] + '&viewMode=' + print_type + '&hash=' + print_parameter['hash'];
            }
            window.open(url + params);
        });
        $('.print-documents').click(function () {
            let id = $(this).data('id');
            let queue_tr = $('#queue-' + id);
            let print_type = queue_tr.find('.document_print_types').val();
            let print_order = queue_tr.find('.document_print_orders').val();
            if (print_type !== <?= View::toJs(PrintLabel::TYPE_PDF) ?> || !print_order)
                return;
            window.open('/queues/print_documents?bulk_queue_id=' + id + '&order_id=' + print_order);
        });
        if (is_single) {
            function before_unload_event(e) {
                // Cancel the event
                e.preventDefault();
                // Chrome requires returnValue to be set
                e.returnValue = '';
            }
            window.addEventListener('beforeunload', before_unload_event);
            $('.btn-done').click(function () {
                removeEventListener('beforeunload', before_unload_event)
            });
        }

    });
</script>
