<?php

use app\backend\models\Billing;
use app\backend\models\ChargesBilling;
use app\backend\models\Pagination;
use app\core\entities\Address;
use app\core\entities\Charge;
use app\core\entities\ChargeComplex;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\status\ChargeType;
use app\core\helpers\status\Status;
use app\core\helpers\View;

/**
 * @var array $records
 * @var int $total_count
 * @var int $total_pages
 * @var int $current_page
 */

$status_charge = function ($status, $count) {
    if (!$status) {
        $icon = 'far fa-circle';
        $style = 'secondary';
    } elseif ($status != $count) {
        $icon = 'fa fa-exclamation-triangle';
        $style = 'secondary';
    } else {
        $icon = 'far fa-check-circle';
        $style = 'success';
    }
    return "<i class='$icon text-$style'></i>";
};

$button_lock = function ($title, $icon, $style, $mode, $id) {
    return Html::tag(
        'button',
        Html::span("<i class=\"fas fa-fw fa-$icon\"></i>", ['class' => 'icon text-white text-left']) .
        Html::span($title, ['class' => 'text flex-fill']),
        [
            'class' => "btn btn-$style btn-icon-split btn-block btn-lock",
            'type' => 'button',
            'data-mode' => $mode,
            'data-sw_id' => $id
        ]
    );
};

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Companies (billing)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-user-circle"></i>
                            Companies list (<?= $total_count; ?> item<?= $total_count > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($records)) { ?>
                <div class="alert alert-danger d-none" id="msg_error"></div>
                <?php View::pagination("/billing/companies/?", 1, $total_pages, $current_page, Pagination::LIMIT_PER_PAGE); ?>
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="*">
                        <col width="25%">
                        <col width="15%">
                        <col width="12%">
                        <col width="12%">
                        <col width="12%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Name (ID)</th>
                            <th>Billing Address</th>
                            <th>Charges</th>
                            <th>Recurring charges</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $sw_id => $record) {
                            $billing_address = (new Address(json_decode($record['billing_address'] ?? "[]", true)))->toString();
                        ?>
                            <tr>
                                <td><?= View::toHtml("{$record['name']} ({$record['id']})"); ?></td>
                                <td><?= $billing_address; ?></td>
                                <td>
                                    <?php foreach ($record['charges'] as $type => $sub_charges) {
                                        $status = array_sum($sub_charges);
                                        echo Html::div(
                                            Html::div(ChargeType::get($type) . (in_array($type, array_keys(Charge::TYPE_EQUAL)) ? ' <i class="fa fa-link text-xs"></i>' : ''), ['class' => 'col-10']) .
                                            Html::div($status_charge($status, count($sub_charges)), ['class' => 'col-2']),
                                            ['class' => 'row']);
                                    } ?>
                                </td>
                                <td>
                                    <?php foreach ($record['recurring'] as $name => $quantity) {
                                        echo Html::div(
                                            Html::div($name, ['class' => 'col-10']) . Html::div($quantity, ['class' => 'col-2']),
                                            ['class' => 'row font-weight-bold text-primary']);
                                    } ?>
                                </td>
                                <td><?= Status::getStatus($record['is_billing'] ? 'Active' : 'Locked', $record['is_billing'] ? 'success' : 'danger'); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-icon-split btn-block" type="button" onclick="changeCharges(<?= $sw_id; ?>)">
                                        <span class="icon text-white text-left">
                                            <i class="far fa-fw fa-file-alt"></i>
                                        </span>
                                        <span class="text flex-fill">Charges</span>
                                    </button>
                                    <button class="btn btn-primary btn-icon-split btn-block" type="button" onclick="changeRecurring(<?= $sw_id; ?>)">
                                        <span class="icon text-white text-left">
                                            <i class="far fa-fw fa-file-alt"></i>
                                        </span>
                                        <span class="text flex-fill">Recurring</span>
                                    </button>
                                    <?php if ($record['is_billing']) { ?>
                                        <button class="btn btn-primary btn-icon-split btn-block" type="button" onclick="showTariff(<?= $sw_id; ?>)">
                                            <span class="icon text-white text-left">
                                                <i class="fas fa-fw fa-cash-register"></i>
                                            </span>
                                            <span class="text flex-fill">Tariff</span>
                                        </button>
                                    <?php
                                            echo $button_lock('Lock', 'lock', 'danger', 1, $sw_id);
                                        } else
                                            echo $button_lock('Unlock', 'unlock', 'success', 0, $sw_id);
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php View::pagination("/billing/companies/?", 1, $total_pages, $current_page, Pagination::LIMIT_PER_PAGE); ?>
            <?php } else
                echo Html::warning("No records found");
            ?>
        </div>
    </div>
</div>
<?php
    $lock_billing = ConfirmModal::areYouSure('lockBilling', '<span id="action_name"></span> billing');
    $lock_billing->addContent(Html::hiddens([['lb_mode', '', false], ['lb_sw_id', '', false]]));
    echo $lock_billing->put();

    $change_billing = new SwitchModal('changeBilling', 'Charges', SwitchModal::SIZE_LG);
    $change_billing
        ->addContent('
            <div class="alert alert-danger d-none mb-0" id="cb_err_message"></div>
            <div id="cb_body">
                <form id="cb_form">' .
                    Html::hidden('sw_id', '', 'cb_sw_id') . '
                    <table class="table mb-0">
                        <colgroup>
                            <col width="*">
                            <col width="*">
                            <col width="14%">
                            <col width="14%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Name / Code</th>
                                <th>Status</th>
                                <th>Usage</th>
                            </tr>
                        </thead>
                        <tbody id="cb_table_body"></tbody>
                    </table>
                </form>
            </div>')
        ->addButton('Update', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE, ['id' => 'cb_update', 'disabled' => 'disabled']);
    echo $change_billing->put();

    $recurring_billing = new SwitchModal('recurringBilling', 'Recurring charges', SwitchModal::SIZE_LG);
    $recurring_billing
        ->addContent('
                <div class="alert alert-danger d-none mb-0" id="rb_err_message"></div>
                <div id="rb_body">
                    <form id="rb_form">' .
                        Html::hidden('sw_id', '', 'rb_sw_id') . '
                        <table class="table mb-0">
                            <colgroup>
                                <col width="*">
                                <col width="*">
                                <col width="14%">
                                <col width="14%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th>Usage</th>
                                </tr>
                            </thead>
                            <tbody id="rb_table_body"></tbody>
                        </table>
                    </form>
                </div>')
        ->addButton('Update', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE, ['id' => 'rb_update', 'disabled' => 'disabled']);
    echo $recurring_billing->put();

    $show_tariff = new SwitchModal('showTariff', 'Customer Tariff', SwitchModal::SIZE_XL);
    $show_tariff
        ->addContent('
            <div class="alert alert-danger d-none mb-0" id="st_err_message"></div>
            <div id="st_body">' .
                Html::hidden('sw_id', '', 'st_sw_id') . '
                <ul class="nav nav-tabs nav-pills" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="' . Billing::CHARGE_TAB . '" data-toggle="tab" data-target="#charges" type="button" role="tab" aria-controls="charges" aria-selected="true">Charges</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="' . Billing::RECURRING_TAB . '" data-toggle="tab" data-target="#recurring" type="button" role="tab" aria-controls="recurring" aria-selected="false">Recurring</button>
                    </li>
                    <div class="col"></div>
                    <div class="form-group col-auto pr-0">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="datetime-local" id="rates_valid"
                                   min="' . ChargesBilling::BILLING_VALID_START . '"
                                   max="' . date("Y-m-d 23:59:59", strtotime("+ 1 day")) . '"
                                   />
                            <label for="rates_valid">Rates Valid</label>
                        </div>
                    </div>
                </ul>
                <div class="tab-content col-12">
                    <div class="tab-pane fade show active" id="charges" role="tabpanel" aria-labelledby="' . Billing::CHARGE_TAB . '">
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col width="*">
                                <col width="*">
                                <col width="10%">
                                <col width="18%">
                                <col width="18%">
                                <col width="12%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Name / Code</th>
                                    <th>Addition</th>
                                    <th>Charge</th>
                                    <th>Valid From</th>
                                    <th>Valid To</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="st_table_body"></tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade show" id="recurring" role="tabpanel" aria-labelledby="' . Billing::RECURRING_TAB . '">
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col width="*">
                                <col width="10%">
                                <col width="10%">
                                <col width="18%">
                                <col width="18%">
                                <col width="12%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Name / Code</th>
                                    <th>Quantity</th>
                                    <th>Charge</th>
                                    <th>Valid From</th>
                                    <th>Valid To</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="rt_table_body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="alert alert-danger d-none mt-3 mb-0" id="file_err_message"></div>
            <div class="alert alert-success d-none mt-3 mb-0" id="file_suc_message"></div>
            <div class="custom-file d-none">
                <input type="file" class="custom-file-input d-none" id="st_import_file">
            </div>')
        ->addButton('Log', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE, ['id' => 'st_log'])
        ->addButton('Export', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE, ['id' => 'st_export'])
        ->addButton('Import', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE, ['id' => 'st_import'])
        ->addForm('', SwitchModal::METHOD_POST, ['enctype' =>  'multipart/form-data', 'id' => 'st_form']);
    echo $show_tariff->put();

    $show_log = new InfoModal('showLog', 'Log', InfoModal::SIZE_LG, true);
    $show_log
        ->addContent('
            <div class="alert alert-danger d-none mb-0" id="sl_err_message"></div>
            <div id="sl_body">
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="25%" />
                        <col width="*" />
                        <col width="*" />
                        <col width="*" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Action Date</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody id="sl_table_body"></tbody>
                </table>
            </div>');
    echo $show_log->put();

    $change_charge = new SwitchModal('changeCharge', 'Edit charge', SwitchModal::SIZE_NORMAL, true);
    $change_charge
        ->addContent('
            <div class="alert alert-danger d-none mb-1" id="cc_err_message"></div>
            <div class="alert alert-warning d-none mb-1" id="cc_wrn_message"></div>
            <div id="cc_body">' .
                Html::hiddens([['cc_cb_id', '', false], ['cc_id', '', false], ['cc_type_charge', '', false]]) . '
                <div class="form-group mb-3" id="field_charge_quantity">
                    <div class="form-group-element">
                        <input id="charge_quantity" type="number" class="form-control form-control-lg" placeholder=" " value="">
                        <label for="charge_quantity">Quantity</label>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <div class="form-group-element">
                        <input id="charge_value" type="number" class="form-control form-control-lg" placeholder=" " value="">
                        <label for="charge_value">Charge</label>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <div class="form-group-element">
                        <input id="charge_valid_from" type="datetime-local" class="form-control form-control-lg" placeholder=" " value="">
                        <label for="charge_valid_from">Valid from</label>
                    </div>
                </div>
                <div class="form-group mb-0">
                    <div class="form-group-element">
                        <input id="charge_valid_to" type="datetime-local" class="form-control form-control-lg" placeholder=" " value="">
                        <label for="charge_valid_to">Valid to</label>
                    </div>
                </div>
            </div>
            <div id="cc_conf" class="row mt-3">
                <div class="col-12">
                    <b>The following changes will be made - do you want to confirm this update? Press button "Update" for confirmation</b>
                    <table class="table mb-0" id="cc_conf_table">
                    </table>            
                </div>            
            </div>')
        ->addButton('Update', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE, ['id' => 'cc_update', 'disabled' => 'disabled']);
    echo $change_charge->put();

    $change_range = new InfoModal('changeRange', 'Ranges for <span id="cr_code"></span>', SwitchModal::SIZE_LG, true);
    $change_range
        ->addContent('
            <div class="alert alert-danger d-none mb-1" id="cr_err_message"></div>
            <div id="cr_body">
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="*">
                        <col width="*">
                        <col width="25%">
                        <col width="15%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Valid From</th>
                            <th>Valid To</th>
                            <th class="text-right"><span id="cr_charge"></span></th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="cr_table_body"></tbody>
                </table>
            </div>');
    echo $change_range->put();
?>
<script>
    function hideResult()
    {
        hideError('file_err_message');
        hideError('file_suc_message');
    }

    $('#st_export').click(function () {
        hideResult();
        location = "/billing/export/?sw_id=" + $('#st_sw_id').val() + "&rates_valid=" + $('#rates_valid').val() + "&type_charge=" + $('#st_body .nav-link.active').attr('id');
    });

    $('#st_log').click(function () {
        hideResult();
        $('#sl_body').hide();
        hideError('sl_err_message');
        $.ajax({
            type: "GET",
            data: { sw_id: $('#st_sw_id').val(), type_charge: $('#st_body .nav-link.active').attr('id') },
            url: "/billing/log",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('sl_err_message', res.error, 0);
                else if (!res.data.length)
                    showError('sl_err_message', 'No records found', 0);
                else {
                    let content = '';
                    $.each(res.data, function (key, history) {
                        let detail = '';
                        if (history.changes.length) {
                            $.each(history.changes, function (index, change) {
                                if (change.old !== undefined)
                                    detail += `"Old ${html_escape(change.field)}": ${html_escape(change.old)}`;
                                if (change.new !== undefined) {
                                    if (change.old !== undefined)
                                        detail += ', ';
                                    detail += `"New ${html_escape(change.field)}": ${html_escape(change.new)}`;
                                }
                                if (detail)
                                    detail += '<br/>';
                            });
                        } else
                            detail = `<a class="link" style="cursor: pointer" href="/billing/download?id=${+history.id}">Download</a>`;
                        content += `
                            <tr>
                                <td>${html_escape(history.event_date)}</td>
                                <td>${html_escape(history.login)}</td>
                                <td>${html_escape(history.event)}</td>
                                <td>${detail}</td>
                            </tr>`;
                    });
                    $('#sl_table_body').html(content);
                    $('#sl_body').show();
                }
            },
            error: function() {
                showError('sl_err_message', 'Connection error', 0);
            }
        });
        <?= $show_log->show(); ?>
    });

    $('#st_import').click(function () {
        hideResult();
        $('#st_import_file').click();
    });

    $('#st_import_file').change(function () {
        let $inputFile = $("#st_import_file");
        let data = new FormData;
        data.append('sw_id', $('#st_sw_id').val());
        data.append('import_file', $inputFile.prop('files')[0] || null);
        data.append('type_charge', $('#st_body .nav-link.active').attr('id'));
        $('#st_import_file').val('');
        $.ajax({
            type: "POST",
            data: data,
            url: "/billing/import",
            processData: false,
            contentType: false,
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('file_err_message', res.error, 0);
                else {
                    if (res.errors.length)
                        showErrorHtml('file_err_message', res.errors, 0);
                    if (res.success) {
                        showError('file_suc_message', 'Was added ' + res.success + ' tariff(s)', 0);
                        showTariff($('#st_sw_id').val(), $('#st_body .nav-link.active').attr('id'), false);
                    }
                }
            },
            error: function() {
                showError('file_err_message', 'Connection error', 0);
            }
        });
    });

    $('.btn-lock').click(function () {
        let mode = $(this).data('mode');
        $('#action_name').html(mode ? 'Lock' : 'Unlock');
        $('#lb_mode').val(mode);
        $('#lb_sw_id').val($(this).data('sw_id'));
        <?= $lock_billing->show(); ?>
    });

    $('.js-btn-conf').click(function () {
        $.ajax({
            type: "POST",
            data: { mode: $('#lb_mode').val(), sw_id: $('#lb_sw_id').val() },
            url: "/billing/lock_billing",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('msg_error', res.error, 3000);
                else
                    location.reload();
            },
            error: function() {
                showError('msg_error', 'Connection error', 3000);
            }
        });
    });

    function getCharges(sw_id)
    {
        let charges = null;
        $.ajax({
            type: "GET",
            data: { sw_id: sw_id },
            url: "/billing/get_charges",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('cb_err_message', res.error, 0);
                else
                    charges = res.data;
            },
            error: function() {
                showError('cb_err_message', 'Connection error', 0);
            }
        });
        return charges;
    }

    function getRecurring(sw_id)
    {
        let recurring = null;
        $.ajax({
            type: "GET",
            data: { sw_id: sw_id },
            url: "/billing/get_recurring",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('rb_err_message', res.error, 0);
                else
                    recurring = res.data;
            },
            error: function() {
                showError('rb_err_message', 'Connection error', 0);
            }
        });
        return recurring;
    }

    function getTariffs(sw_id, type_charge)
    {
        let tariffs = null;
        hideError('st_err_message');
        $.ajax({
            type: "GET",
            data: { sw_id: sw_id, rates_valid: $('#rates_valid').val(), type_charge: type_charge },
            url: "/billing/get_tariffs",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('st_err_message', res.error, 0);
                else {
                    $('#rates_valid').val(res.data.rates_valid);
                    if (res.data.error)
                        tariffs = { error: res.data.error };
                    else
                        tariffs = res.data.tariffs;
                }
            },
            error: function() {
                showError('st_err_message', 'Connection error', 0);
            }
        });
        return tariffs;
    }

    $('#rates_valid').keydown(function (e) {
        if (e.keyCode === 13) {
            e.preventDefault();
            showTariff($('#st_sw_id').val(), $('#st_body .nav-link.active').attr('id'));
        }
    });

    $('#st_body .nav-link').click(function (e) {
        if (!$(this).prop('classList').contains('active'))
            showTariff($('#st_sw_id').val(), $(this).prop('id'));
    });

    function changeCharges(sw_id)
    {
        let charges = getCharges(sw_id);
        fillCharge(sw_id, charges);
        <?= $change_billing->show(); ?>
    }

    function changeRecurring(sw_id)
    {
        let recurring = getRecurring(sw_id);
        fillRecurring(sw_id, recurring);
        <?= $recurring_billing->show(); ?>
    }

    function showTariff(sw_id, type_charge = '', is_hide = true)
    {
        if (is_hide)
            hideResult();
        if (type_charge === '')
            type_charge = $('#st_body .nav-link.active').attr('id');
        $('#st_sw_id').val(sw_id);
        let tariffs = getTariffs(sw_id, type_charge);
        fillTariff(sw_id, tariffs, type_charge);
        <?= $show_tariff->show(); ?>
    }

    function fillCharge(sw_id, charges)
    {
        let body = $('#cb_body');
        $('#cb_update').attr('disabled', charges === null);
        if (charges === null) {
            body.hide();
            return;
        }
        const type_equal = <?= View::toJs(array_keys(Charge::TYPE_EQUAL)) ?>;
        let content = '';
        $.each(charges, function (type, type_charges) {
            let rowspan = ` rowspan="${Object.keys(type_charges.charges).length}"`;
            let first_col = `<td${rowspan}>${html_escape(type_charges.type_name)}${type_equal.indexOf(+type) >= 0 ? '<br/><i class="fa fa-link text-xs"></i>' : ''}</td>`;
            let check_col = `<td${rowspan}><input type="checkbox" name="usage[${type}]" ${+type_charges.is_usage === 1 ? 'checked' : ''}/></td>`;
            $.each(type_charges.charges, function (id, charge) {
                content += `<tr>${first_col}<td><b>${html_escape(charge.name)}</b><br/>${html_escape(charge.code)}</td><td>${html_escape(charge.status_name)}</td>${check_col}</tr>`;
                first_col = check_col = '';
            });
        });
        $('#cb_sw_id').val(sw_id);
        $('#cb_table_body').html(content);
        body.show();
    }

    function fillRecurring(sw_id, recurring)
    {
        let body = $('#rb_body');
        $('#rb_update').attr('disabled', recurring === null);
        if (recurring === null) {
            body.hide();
            return;
        }
        let content = '';
        $.each(recurring, function (id, charge) {
            let check_col = `<td><input type="checkbox" name="usage[${id}]" ${+charge.is_usage === 1 ? 'checked' : ''}/></td>`;
            content += `<tr><td><b>${html_escape(charge.name)}</b></td><td>${html_escape(charge.code)}</td><td>${html_escape(charge.status_name)}</td>${check_col}</tr>`;
        });
        $('#rb_sw_id').val(sw_id);
        $('#rb_table_body').html(content);
        body.show();
    }

    function fillTariff(sw_id, tariffs, type_charge)
    {
        let body = $('#st_body');
        let empty = tariffs === null;
        $('#st_log').attr('disabled', empty || tariffs.error !== undefined);
        $('#st_export').attr('disabled', empty || tariffs.error !== undefined);
        $('#st_import').attr('disabled', empty || tariffs.error !== undefined);
        if (empty) {
            body.hide();
            return;
        }
        $('#st_sw_id').val(sw_id);
        if (type_charge === '<?= Billing::CHARGE_TAB; ?>')
            fillTariffCharges(tariffs);
        else if (type_charge === '<?= Billing::RECURRING_TAB; ?>')
            fillTariffRecurring(tariffs);
        body.show();
    }

    function fillTariffCharges(tariffs)
    {
        if (tariffs.error) {
            $('#st_table_body').html(`<tr><td colspan="6"><div class="alert alert-danger mb-0">${html_escape(tariffs.error)}</div></td></tr>`);
            return;
        }
        let content = '';
        $.each(tariffs, function (complex_id, tariff) {
            let addition = '';
            if (+tariff.cc_type === <?= ChargeComplex::TYPE_PACKAGING; ?>) {
                if (tariff.type_id) {
                    addition = `<b>${html_escape(tariff.type_name)}</b> (#${+tariff.type_id})`;
                    if (tariff.group_id)
                        addition += `<br/><span class="badge badge-info">${html_escape(tariff.group_code)}</span>`;
                } else
                    addition = `<span class="badge badge-danger>">no packaging</span>`;
            }
            let disabled_row = '';
            let disabled_button = '';
            if (+tariff.cc_status === <?= ChargeComplex::STATUS_DISABLE; ?>)
                disabled_row = ' class="text-danger"';
            else
                disabled_button = `
                    <button class="btn btn-success btn-icon-split btn-block" type="button" onclick="changeCharge(${+tariff.cc_id}, ${+tariff.cb_id})">
                        <span class="icon text-white text-left">
                            <i class="fas fa-fw fa-edit"></i>
                        </span>
                        <span class="text flex-fill">Edit</span>
                    </button>
                    <button class="btn btn-primary btn-icon-split btn-block" type="button" onclick="changeRange(${+tariff.cc_id})">
                        <span class="icon text-white text-left">
                            <i class="fas fa-fw fa-arrows-alt-h"></i>
                        </span>
                        <span class="text flex-fill">Ranges</span>
                    </button>`;
            content += `
                <tr${disabled_row}>
                    <td><b>${html_escape(tariff.name)}</b><br/>${html_escape(tariff.code)}</td>
                    <td>${addition}</td>
                    <td>${tariff.value}</td>
                    <td>${tariff.valid_from}</td>
                    <td>${tariff.valid_to === null ? '-' : tariff.valid_to}</td>
                    <td>${disabled_button}</td>
                </tr>`;
        });
        $('#st_table_body').html(content);
    }

    function fillTariffRecurring(tariffs)
    {
        if (tariffs.error) {
            $('#rt_table_body').html(`<tr><td colspan="6"><div class="alert alert-danger mb-0">${html_escape(tariffs.error)}</div></td></tr>`);
            return;
        }
        let content = '';
        $.each(tariffs, function (charge_id, tariff) {
            content += `
                <tr>
                    <td><b>${html_escape(tariff.name)}</b><br/>${html_escape(tariff.code)}</td>
                    <td>${tariff.quantity}</td>
                    <td>${tariff.value}</td>
                    <td>${tariff.valid_from}</td>
                    <td>${tariff.valid_to === null ? '-' : tariff.valid_to}</td>
                    <td>
                        <button class="btn btn-success btn-icon-split btn-block" type="button" onclick="changeCharge(${+tariff.id}, ${+tariff.cb_id})">
                            <span class="icon text-white text-left">
                                <i class="fas fa-fw fa-edit"></i>
                            </span>
                            <span class="text flex-fill">Edit</span>
                        </button>
                        <button class="btn btn-primary btn-icon-split btn-block" type="button" onclick="changeRange(${+tariff.id})">
                            <span class="icon text-white text-left">
                                <i class="fas fa-fw fa-arrows-alt-h"></i>
                            </span>
                            <span class="text flex-fill">Ranges</span>
                        </button>
                    </td>
                </tr>`;
        });
        $('#rt_table_body').html(content);
    }

    $('#cb_update').click(function () {
        let $form = $('<?= $change_billing->getSelector(); ?>').find('form');
        $.ajax({
            type: "POST",
            data: $form.serialize(),
            url: "/billing/set_charges",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('cb_err_message', res.error, 3000);
                else
                    location.reload();
            },
            error: function() {
                showError('cb_err_message', 'Connection error', 3000);
            }
        });
    });

    $('#rb_update').click(function () {
        let $form = $('<?= $recurring_billing->getSelector(); ?>').find('form');
        $.ajax({
            type: "POST",
            data: $form.serialize(),
            url: "/billing/set_recurring",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('rb_err_message', res.error, 3000);
                else
                    location.reload();
            },
            error: function() {
                showError('rb_err_message', 'Connection error', 3000);
            }
        });
    });

    function changeRange(id)
    {
        let type_charge = $('#st_body .nav-link.active').attr('id');
        let range_data = getRangeData(id, type_charge);
        fillRangeData(range_data, type_charge);
        <?= $change_range->show(); ?>
    }

    function getRangeData(id, type_charge)
    {
        let range = null;
        $.ajax({
            type: "GET",
            data: { id: id, type_charge: type_charge },
            url: "/billing/get_range_data",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('cr_err_message', res.error, 0);
                else
                    range = res.data;
            },
            error: function() {
                showError('cr_err_message', 'Connection error', 0);
            }
        });
        return range;
    }

    function getBtnDelete(id)
    {
        return `
            <button class="btn btn-danger btn-icon-split btn-block" type="button" onclick="deleteRange(${id})">
                <span class="icon text-white text-left">
                    <i class="fas fa-fw fa-times"></i>
                </span>
                <span class="text flex-fill">Delete</span>
            </button>`;
    }

    function fillRangeData(range_data, type_charge)
    {
        let body = $('#cr_body');
        let empty = range_data === null;
        if (empty) {
            body.hide();
            return;
        }
        let is_recurring = type_charge === '<?= Billing::RECURRING_TAB; ?>';
        $('#cr_code').text(range_data.code);
        $('#cr_charge').html((is_recurring ? 'Quantity * ' : '') + 'Charge');
        let content = '';
        if (range_data.ranges.length)
            $.each(range_data.ranges, function (index, range) {
                content += `
                    <tr>
                        <td>${range.valid_from}</td>
                        <td>${range.valid_to}</td>
                        <td class="text-right">${is_recurring ? range.quantity + ' * ' : ''}${range.value}</td>
                        <td>${(+range.value || (is_recurring && +range.quantity)) ? getBtnDelete(range.id) : ''}</td>
                    </tr>`;
            });
        else
            content = `<tr><td colspan="4"><div class="alert alert-danger mb-0">No data found</div></td></tr>`;
        $('#cr_table_body').html(content);
        body.show();
    }

    function deleteRange(id)
    {
        $.ajax({
            type: "POST",
            data: { id: id, type_charge: $('#st_body .nav-link.active').attr('id') },
            url: "/billing/delete_range",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('cr_err_message', res.error, 3000);
                else {
                    <?= $change_range->hide(); ?>
                    showTariff($('#st_sw_id').val(), $('#st_body .nav-link.active').attr('id'));
                }
            },
            error: function() {
                showError('cr_err_message', 'Connection error', 3000);
            }
        });
    }

    // Edit charge
    let charge_value = $('#charge_value');
    let charge_valid_from = $('#charge_valid_from');
    let charge_valid_to = $('#charge_valid_to');
    charge_value.change(hideConfirmation);
    charge_valid_from.change(hideConfirmation);
    charge_valid_to.change(hideConfirmation);
    let ranges = null;

    function changeCharge(id, cb_id)
    {
        $('#cc_conf').hide();
        hideError('cc_err_message');
        let charge_data = getChargeData(id, cb_id, $('#st_body .nav-link.active').attr('id'));
        fillChargeData(charge_data);
        <?= $change_charge->show(); ?>
    }

    function getChargeData(id, cb_id, type_charge)
    {
        let charge = null;
        $.ajax({
            type: "GET",
            data: { id: id, cb_id: cb_id, type_charge: type_charge },
            url: "/billing/get_charge_data",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('cc_err_message', res.error, 0);
                else
                    charge = res.data;
            },
            error: function() {
                showError('cc_err_message', 'Connection error', 0);
            }
        });
        return charge;
    }

    function fillChargeData(charge_data)
    {
        let body = $('#cc_body');
        let empty = charge_data === null;
        $('#cc_update').attr('disabled', empty);
        if (empty) {
            body.hide();
            return;
        }
        $('#cc_cb_id').val(charge_data.cb_id);
        $('#cc_id').val(charge_data.id);
        $('#cc_type_charge').val(charge_data.type_charge);
        if (charge_data.type_charge === '<?= Billing::RECURRING_TAB; ?>') {
            $('#field_charge_quantity').show();
            $('#charge_quantity').val(charge_data.quantity);
        } else
            $('#field_charge_quantity').hide();
        charge_value.val(charge_data.value);
        charge_valid_from.val(charge_data.valid_from);
        charge_valid_to.val(charge_data.valid_to);
        body.show();
        $('#cc_conf').hide();
    }

    function hideConfirmation()
    {
        $('#cc_conf').hide();
        ranges = null;
    }

    function showConfirmation(type_charge)
    {
        let content = '';
        $.each(ranges, function (key, charge) {
            let qty = type_charge === '<?= Billing::RECURRING_TAB; ?>' ? `${charge.quantity} * ` : '';
            let icon = charge.action === 'delete'
                ? '<i class="fas fa-times"></i>'
                : (charge.action === 'update'
                    ? '<i class="fas fa-edit"></i>'
                    : (charge.action === 'insert' ? '<i class="fas fa-plus"></i>' : ''));
            let text_color = charge.action !== 'none' ? 'text-white' : '';
            let bg_color = charge.action === 'delete' ? 'danger'
                : (charge.action === 'update' ? 'warning'
                    : (charge.action === 'insert' ? 'success'
                        : 'light'));
            content += `
                <tr class="p-1 ${text_color} bg-${bg_color}">
                    <td class="p-1">${charge.valid_from}</td>
                    <td class="p-1">${charge.valid_to}</td>
                    <td class="p-1">${qty}${charge.value}</td>
                    <td class="p-1">${icon}</td>
                </tr>`;
        });
        $('#cc_conf_table').html(content);
        $('#cc_conf').show();
    }

    function updateRanges()
    {
        let result = false;
        $.ajax({
            type: "POST",
            data: { sw_id: $('#st_sw_id').val(), type_charge: $('#cc_type_charge').val(), ranges: ranges },
            url: "/billing/set_update_ranges",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('cc_err_message', res.error, 3000);
                else
                    result = true;
            },
            error: function() {
                showError('cc_err_message', 'Connection error', 3000);
            }
        });
        return result;
    }

    $('#cc_update').click(function () {
        if (ranges) {
            if (updateRanges()) {
                <?= $change_charge->hide(); ?>
                showTariff($('#st_sw_id').val(), $('#st_body .nav-link.active').attr('id'));
            }
            return;
        }
        let type_charge = $('#cc_type_charge').val();
        $.ajax({
            type: "POST",
            data: {
                sw_id: $('#st_sw_id').val(), cb_id: $('#cc_cb_id').val(), id: $('#cc_id').val(), type_charge: type_charge,
                quantity: type_charge === '<?= Billing::RECURRING_TAB; ?>' ? $('#charge_quantity').val() : 0,
                value: charge_value.val(), valid_from: charge_valid_from.val(), valid_to: charge_valid_to.val()
            },
            url: "/billing/set_charge_data",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('cc_err_message', res.error, 3000);
                else if (res.confirm) {
                    ranges = res.confirm;
                    showConfirmation(type_charge);
                } else if (res.update === false)
                    showError('cc_wrn_message', 'Nothing to change', 3000);
                else {
                    <?= $change_charge->hide(); ?>
                    showTariff($('#st_sw_id').val(), $('#st_body .nav-link.active').attr('id'));
                }
            },
            error: function() {
                showError('cc_err_message', 'Connection error', 3000);
            }
        });
    });

    // Common
    function hideError(id)
    {
        let element = $('#' + id);
        element.html('');
        element.prop('classList').add('d-none');
    }

    $('<?= $change_range->getSelector() ?>').on('hidden.bs.modal', function() {
        $('<?= $show_tariff->getSelector() ?>').focus();
    });
    $('<?= $change_charge->getSelector() ?>').on('hidden.bs.modal', function() {
        $('<?= $show_tariff->getSelector() ?>').focus();
    });
    $('<?= $show_log->getSelector() ?>').on('hidden.bs.modal', function() {
        $('<?= $show_tariff->getSelector() ?>').focus();
    });
</script>