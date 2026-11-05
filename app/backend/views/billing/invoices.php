<?php

use app\backend\models\ChargeASNBookings;
use app\backend\models\ChargeOrderPackagings;
use app\backend\models\ChargeOrderPicks;
use app\core\entities\Charge;
use app\core\entities\InvoiceBilling;
use app\core\helpers\Html;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\status\Status;
use app\core\helpers\View;

/**
 * @var array $invoices
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Invoices (billing)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-file-invoice-dollar"></i>
                            Invoices list (<?= count($invoices); ?> item<?= count($invoices) > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($invoices)) { ?>
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="8%">
                        <col width="8%">
                        <col width="*">
                        <col width="12%">
                        <col width="12%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Month</th>
                            <th>Billing</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice) { ?>
                            <tr>
                                <td><?= View::toHtml($invoice['year']); ?></td>
                                <td><?= View::toHtml($invoice['month']); ?></td>
                                <td>
                                    <div class="d-flex flex-wrap">
                                        <?php foreach ($invoice['files'] as $date => $files)
                                            echo Html::div(
                                                "$date<br/><span class='badge badge-success '>" .
                                                implode("</span><br/><span class='badge badge-success'>", $files) . "</span>",
                                                ['class' => 'border border-secondary p-3 mt-1 mr-1 rounded']);
                                        ?>
                                    </div>
                                </td>
                                <td><?= Status::getStatus(InvoiceBilling::statusName($invoice['status']), $invoice['status'] ? 'success' : 'primary'); ?></td>
                                <td>
                                    <button class="btn btn-success btn-icon-split btn-block" type="button" onclick="createInvoice(<?= $invoice['id']; ?>)">
                                        <span class="icon text-white text-left">
                                            <i class="fas fa-fw fa-plus"></i>
                                        </span>
                                        <span class="text flex-fill">Create<?= $invoice['status'] ? ' Again' : ''; ?></span>
                                    </button>
                                    <?php if ($invoice['status']) { ?>
                                        <button class="btn btn-primary btn-icon-split btn-block" type="button" onclick="downloadInvoice(<?= $invoice['id']; ?>)">
                                            <span class="icon text-white text-left">
                                                <i class="fas fa-fw fa-print"></i>
                                            </span>
                                            <span class="text flex-fill">Download</span>
                                        </button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else
                echo Html::warning("No records found");
            ?>
        </div>
    </div>
</div>
<?php
    $modal_create = new SwitchModal('createInvoice', 'Create invoice <span id="ci_invoice_date"></span>');
    $modal_create
        ->addContent(
            Html::hidden('ci_id', '', false) . '
            <div class="alert alert-danger d-none mb-0" id="ci_error"></div>
            <div id="ci_body">
                <div>From file(s):<br/><span id="ci_files"></span></div>
                <div>Created at: <span class="text-success font-weight-bold" id="ci_created_at"></span></div>
            </div>')
        ->addButton('Create', SwitchModal::TYPE_SUBMIT, SwitchModal::BUTTON_DONE, ['id' => 'btn_ci_create']);
    echo $modal_create->put();

    $get_option = function ($id) {
        return [
            'class' => 'selectpicker form-control form-control-lg custom-select',
            'id' => $id,
        ];
    };
    $btn_common = function($name, $type) {
        return "<button class=\"btn btn-link btn-outline-primary mb-1 mr-1 js-download-csv\" data-csv=\"$type\">$name (csv)</button>";
    };
    $btn_csv = function($type) use($btn_common) {
        if (!empty(ChargeOrderPicks::TYPE_NAME[$type]))
            $name = ChargeOrderPicks::TYPE_NAME[$type];
        elseif (!empty(ChargeOrderPackagings::TYPE_NAME[$type]))
            $name = ChargeOrderPackagings::TYPE_NAME[$type];
        elseif (!empty(ChargeASNBookings::TYPE_NAME[$type]))
            $name = ChargeASNBookings::TYPE_NAME[$type];
        else
            $name = 'unknown';
        return $btn_common("charge-for-$name", $type);
    };
    $modal_invoice = new InfoModal('downloadInvoice', 'Download invoice <span id="di_invoice_date"></span>');
    $modal_invoice
        ->addContent(
            Html::hidden('di_id', '', false) . '
            <div class="alert alert-danger d-none mb-0" id="di_error"></div>
            <div id="di_body">
                <div class="form-group-element mb-3">' .
                    Html::dropDownList('di_create_date', [], null, $get_option('di_create_date')) . '
                    <label for="di_create_date">Created at</label>
                </div>
                <div class="form-group-element mb-3">' .
                    Html::dropDownList('di_login', [], null, $get_option('di_login')) . '
                    <label for="di_login">Created by</label>
                </div>
                <div class="form-group-element">' .
                    Html::dropDownList('di_supplier', [], null, $get_option('di_supplier')) . '
                    <label for="di_supplier">For company</label>
                </div>
                <hr/>
                <button class="btn btn-link btn-primary mb-1 mr-1 js-download-pdf">Invoice (pdf)</button>' .
                $btn_csv(Charge::TYPE_PICK) .
                $btn_csv(Charge::TYPE_CULLING_PICK) .
                $btn_csv(Charge::TYPE_BUNDLING_PICK) .
                $btn_csv(Charge::TYPE_PACKAGING) .
                $btn_csv(Charge::TYPE_CULLING_PACKAGING) .
                $btn_csv(Charge::TYPE_BUNDLING_PACKAGING) .
                $btn_csv(Charge::TYPE_BOOKING_IN) .
                $btn_common('recurring-charge', 0) . '
            </div>');
    echo $modal_invoice->put();
?>
<script>
    function getInvoice(id)
    {
        let invoice = null;
        $.ajax({
            type: "GET",
            data: { id: id },
            url: "/billing/get_invoice",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('ci_error', res.error, 0);
                else
                    invoice = res.data;
            },
            error: function() {
                showError('ci_error', 'Connection error', 0);
            }
        });
        return invoice;
    }

    let btn_create = $('#btn_ci_create');

    function createInvoice(id)
    {
        btn_create.attr('disabled', true);
        $('#ci_body').prop('classList').add('d-none');
        $('#ci_id').val(id);
        hideError('ci_error');
        let invoice = getInvoice(id);
        fillInvoice(invoice);
        <?= $modal_create->show(); ?>
    }

    function fillInvoice(invoice)
    {
        if (!invoice)
            return;
        $('#ci_invoice_date').html(` ${invoice.month}/${invoice.year}`);
        let files = invoice.files.split(',')
        let content = '';
        $.each(files, function (key, file) {
            content += `<span class="badge badge-success">${html_escape(file)}</span> `;
        });
        $('#ci_files').html(content);
        $('#ci_created_at').html(invoice.created_at);
        btn_create.attr('disabled', false);
        $('#ci_body').prop('classList').remove('d-none');
    }

    btn_create.click(function () {
        $.ajax({
            type: "POST",
            data: { id: $('#ci_id').val() },
            url: "/billing/set_invoice",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('ci_error', res.error, 3000);
                else
                    location.reload();
            },
            error: function() {
                showError('ci_error', 'Connection error', 3000);
            }
        });
    });

    function hideError(id)
    {
        $('#' + id).html('');
        $('#' + id).prop('classList').add('d-none');
    }

    let invoices = null;

    function downloadInvoice(id)
    {
        $('#di_body').prop('classList').add('d-none');
        $('#di_id').val(id);
        hideError('di_error');
        let invoice = getInvoices(id);
        fillInvoices(invoice);
        <?= $modal_invoice->show(); ?>
    }

    function getInvoices(id)
    {
        let invoice = null;
        $.ajax({
            type: "GET",
            data: { id: id },
            url: "/billing/get_invoices",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('di_error', res.error, 0);
                else {
                    invoice = res.data.invoice;
                    invoices = res.data.documents;
                }
            },
            error: function() {
                showError('di_error', 'Connection error', 0);
            }
        });
        return invoice;
    }

    let create_date = $('#di_create_date');
    let login = $('#di_login');
    let supplier = $('#di_supplier');

    create_date.change(function () {
        fillLogin();
    });

    login.change(function () {
        fillSupplier();
    });

    function fillCreateDate()
    {
        let content = '';
        $.each(invoices, function (date) {
            content += `<option value="${date}">${date}</option> `;
        });
        create_date.html(content);
        fillLogin();
    }

    function fillLogin()
    {
        let content = '';
        $.each(invoices[create_date.val()], function (login) {
            content += `<option value="${html_escape(login)}">${html_escape(login)}</option> `;
        });
        login.html(content);
        fillSupplier();
    }

    function fillSupplier()
    {
        let content = '';
        $.each(invoices[create_date.val()][login.val()], function (id, name) {
            content += `<option value="${+id}">${html_escape(name)}</option> `;
        });
        supplier.html(content);
    }

    function fillInvoices(invoice)
    {
        if (!invoice || !invoices)
            return;
        $('#di_invoice_date').html(`${invoice.month}/${invoice.year}`);
        fillCreateDate();
        $('#di_body').prop('classList').remove('d-none');
    }

    $('.js-download-pdf').click(function () {
        location = "/billing/save_pdf/?id=" + $('#di_supplier').val();
    });

    $('.js-download-csv').click(function () {
        location = "/billing/save_csv/?id=" + $('#di_supplier').val() + "&csv=" + $(this).data('csv');
    });
</script>
