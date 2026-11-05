<?php

/**
 * @var $secondary bool
 **/

use app\core\helpers\modal\InfoModal;

$show_reconciliation_log = new InfoModal('reconciliationLog', 'Reconciliation log', InfoModal::SIZE_XL, $secondary);
echo $show_reconciliation_log->addContent('
    <span id="pagination_reconciliation_log"></span>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Date</th>
                <th>Quantity</th>
                <th>Location</th>
                <th>Reason</th>
                <th>User</th>
            </tr>
        </thead>
        <tbody id="tbody_reconciliation_log">
        </tbody>
    </table>')->put();

$show_reconciliation_log_error = new InfoModal('reconciliationLogError', 'Reconciliation log error', InfoModal::SIZE_NORMAL, $secondary);
echo $show_reconciliation_log_error->addContent('<div class="alert alert-danger mb-0"><div id="reconciliation_log_error"></div></div>')->put();
?>
<script>
    function fillReconciliationLogWindow(SKU_id, pag_block = null) {

        if (pag_block === null) {
            pag_block = getPaginationBlock($('#pagination_reconciliation_log'), 'pag_reconciliation_log', function () {
                fillReconciliationLogWindow(SKU_id, pag_block);
            });
        }
        $.post("/housekeeping/get_reconciliation_log", {SKU_id: SKU_id, page: pag_block.getPage()}, res => {
            if (res.error !== undefined) {
                $('#reconciliation_log_error').html(res.error);
                <?= $show_reconciliation_log_error->show(); ?>
                return;
            }
            let tbody_reconciliation_log = $('#tbody_reconciliation_log');
            tbody_reconciliation_log.empty();

            for (const history of res.data.history) {
                let colour_q = history['quantity'] > 0 ? 'text-success' : 'text-danger';
                let table_body = '<tr>';
                table_body += '<td>' + html_escape(history['event_date']) + '</td>';
                table_body += '<td class="' + colour_q + '"><b>' + (history['quantity'] > 0 ? '+' : '') + (+history['quantity']) + '</b></td>';
                table_body += '<td>' + html_escape(history['location']) + '</td>';
                table_body += '<td>' + html_escape(history['reason']) + '</td>';
                table_body += '<td>' + html_escape(history['user']) + '</td>';
                table_body += '</tr>';
                tbody_reconciliation_log.append(table_body);
            }
            pag_block.setPages(res.data.pages).setPage(res.data.page).render();
            <?= $show_reconciliation_log->show(); ?>;
            $('#reconciliationLogModalInfo').on('hidden.bs.modal', function (e) {
                pag_block.setPage(1)
            });
        }, "json").fail(function () {
            $('#reconciliation_log_error').html('Access denied');
            <?= $show_reconciliation_log_error->show(); ?>
        });
    }
</script>
