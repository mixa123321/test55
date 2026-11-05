<?php

use app\core\entities\Charge;
use app\core\helpers\Html;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\status\ChargeStatus;
use app\core\helpers\View;

/**
 * @var array $records
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Recurring charges (billing)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-file-invoice-dollar"></i>
                            Recurring charges list (<?= count($records); ?> item<?= count($records) > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split" onclick="setCharge()" type="button">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-plus"></i>
                        </span>
                        <span class="text">Create</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($records)) { ?>
                <div class="alert alert-danger d-none" id="msg_error"></div>
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="*">
                        <col width="30%">
                        <col width="10%">
                        <col width="10%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record) { ?>
                            <tr>
                                <td><?= View::toHtml($record['name']); ?></td>
                                <td><?= View::toHtml($record['code']); ?></td>
                                <td><?= ChargeStatus::get($record['status']); ?></td>
                                <td>
                                    <button class="btn btn-success btn-icon-split btn-block" type="button" onclick="setCharge(<?= $record['id']; ?>)">
                                        <span class="icon text-white text-left">
                                            <i class="fas fa-fw fa-edit"></i>
                                        </span>
                                        <span class="text flex-fill">Edit</span>
                                    </button>
                                    <?php if (!Charge::isStandard($record['type'])) { ?>
                                        <?php if (Charge::isActive($record['status'])) { ?>
                                            <button class="btn btn-danger btn-icon-split btn-block btn-lock"
                                                    type="button"
                                                    data-mode="1"
                                                    data-id="<?= $record['id']; ?>">
                                                <span class="icon text-white text-left">
                                                    <i class="fas fa-fw fa-lock"></i>
                                                </span>
                                                <span class="text flex-fill">Lock</span>
                                            </button>
                                        <?php } else { ?>
                                            <button class="btn btn-success btn-icon-split btn-block btn-lock"
                                                    type="button"
                                                    data-mode="0"
                                                    data-id="<?= $record['id']; ?>">
                                                <span class="icon text-white text-left">
                                                    <i class="fas fa-fw fa-unlock"></i>
                                                </span>
                                                <span class="text flex-fill">Unlock</span>
                                            </button>
                                        <?php } ?>
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
    $lock_recurring = ConfirmModal::areYouSure('lockRecurring', '<span id="action_name"></span> recurring charge');
    $lock_recurring->addContent(Html::hiddens([['lr_mode', '', false], ['lr_id', '', false]]));
    echo $lock_recurring->put();

    $modal_charge = new SwitchModal('setCharge', 'Recurring charge');
    $modal_charge
        ->addContent(
            Html::hidden('charge_id', '', false) . '
            <div class="alert alert-danger d-none" id="crg_error"></div>
            <div class="alert alert-warning d-none" id="crg_warning"></div>
            <div class="form-row">
                <div class="col-12">
                    <div class="form-group mb-0">
                        <div class="form-group-element">
                            <input id="charge_name" type="text" class="form-control form-control-lg" placeholder=" " value="">
                            <label for="charge_name">Name</label>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-group mt-3 mb-0">
                        <div class="form-group-element">
                            <input id="charge_code" type="text" class="form-control form-control-lg" placeholder=" " value="">
                            <label for="charge_code">Code <span class="text-danger" id="charge_code_surely">*</span></label>
                        </div>
                    </div>
                </div>
            </div>')
        ->addButton('Update', SwitchModal::TYPE_SUBMIT, SwitchModal::BUTTON_DONE, ['id' => 'btn_update']);
    echo $modal_charge->put();
?>
<script>
    $('.btn-lock').click(function () {
        let mode = $(this).data('mode');
        $('#action_name').html(mode ? 'Lock' : 'Unlock');
        $('#lr_mode').val(mode);
        $('#lr_id').val($(this).data('id'));
        <?= $lock_recurring->show(); ?>
    });

    $('.js-btn-conf').click(function () {
        $.ajax({
            type: "POST",
            data: { mode: $('#lr_mode').val(), id: $('#lr_id').val() },
            url: "/billing/lock_recurring",
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

    function getCharge(charge_id = 0)
    {
        let charge = null;
        $.ajax({
            type: "GET",
            data: { charge_id: charge_id },
            url: "/billing/get_charge",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('msg_error', res.error, 3000);
                else
                    charge = res.data;
            },
            error: function() {
                showError('msg_error', 'Connection error', 3000);
            }
        });
        return charge;
    }

    function setCharge(charge_id)
    {
        let charge = getCharge(charge_id);
        if (charge == null)
            return;
        fillModal(charge);
        <?= $modal_charge->show(); ?>
    }

    function fillModal(charge)
    {
        let code = $('#charge_code');
        $('#charge_id').val(charge.id);
        $('#charge_name').val(charge.name);
        code.val(charge.code);
        code.attr('readonly', charge.standard);
        if (charge.standard)
            $('#charge_code_surely').prop('classList').add('d-none');
        else
            $('#charge_code_surely').prop('classList').remove('d-none');
    }

    $('#btn_update').click(function () {
        $.ajax({
            type: "POST",
            data: { id: $('#charge_id').val(), name: $('#charge_name').val(), code: $('#charge_code').val() },
            url: "/billing/set_charge",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('crg_error', res.error, 3000);
                else if (!res.update)
                    showError('crg_warning', 'Nothing to update', 3000);
                else
                    location.reload();
            },
            error: function() {
                showError('crg_error', 'Connection error', 3000);
            }
        });
    });
</script>
