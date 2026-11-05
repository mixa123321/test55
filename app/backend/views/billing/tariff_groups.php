<?php

use app\core\entities\ChargeGroup;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\status\ChargeGroupStatus;
use app\core\helpers\View;

/**
 * @var array $records
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Tariff groups (billing)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-object-group"></i>
                            List of group (<?= count($records); ?> item<?= count($records) > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split" onclick="setGroup()" type="button">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-plus"></i>
                        </span>
                        <span class="text">Create</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-danger d-none" id="msg_error"></div>
            <?php if (!empty($records)) { ?>
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
                            <td><?= View::toHtml($record->getName()); ?></td>
                            <td><?= View::toHtml($record->getCode()); ?></td>
                            <td><?= ChargeGroupStatus::get($record->getStatus()); ?></td>
                            <td>
                                <button class="btn btn-success btn-icon-split btn-block" type="button" onclick="setGroup(<?= $record->getId(); ?>)">
                                        <span class="icon text-white text-left">
                                            <i class="fas fa-fw fa-edit"></i>
                                        </span>
                                    <span class="text flex-fill">Edit</span>
                                </button>
                                <?php if (ChargeGroup::isActive($record->getStatus())) { ?>
                                    <button class="btn btn-danger btn-icon-split btn-block btn-lock" type="button" data-mode="1" data-id="<?= $record->getId(); ?>">
                                            <span class="icon text-white text-left">
                                                <i class="fas fa-fw fa-lock"></i>
                                            </span>
                                        <span class="text flex-fill">Lock</span>
                                    </button>
                                <?php } else { ?>
                                    <button class="btn btn-success btn-icon-split btn-block btn-lock" type="button" data-mode="0" data-id="<?= $record->getId(); ?>">
                                            <span class="icon text-white text-left">
                                                <i class="fas fa-fw fa-unlock"></i>
                                            </span>
                                        <span class="text flex-fill">Unlock</span>
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
    $lock_group = ConfirmModal::areYouSure('lockGroup', '<span id="action_name"></span> tariff group');
    $lock_group->addContent(Html::hiddens([['lg_mode', '', false], ['lg_id', '', false]]));
    echo $lock_group->put();

    $modal_group = new SwitchModal('setGroup', 'Tariff group');
    $modal_group
        ->addContent(
            Html::hidden('group_id', '', false) . '
                <div class="alert alert-danger d-none" id="grp_error"></div>
                <div class="alert alert-warning d-none" id="grp_warning"></div>
                <div class="form-row">
                    <div class="col-12">
                        <div class="form-group mb-0">
                            <div class="form-group-element">
                                <input id="group_name" type="text" class="form-control form-control-lg" placeholder=" " value="">
                                <label for="group_name">Name</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group mt-3 mb-0">
                            <div class="form-group-element">
                                <input id="group_code" type="text" class="form-control form-control-lg" placeholder=" " value="">
                                <label for="group_code">Code <span class="text-danger">*</span></label>
                            </div>
                        </div>
                    </div>
                </div>')
        ->addButton('Update', SwitchModal::TYPE_SUBMIT, SwitchModal::BUTTON_DONE, ['id' => 'btn_update']);
    echo $modal_group->put();
?>
<script>
    $('.btn-lock').click(function () {
        let mode = $(this).data('mode');
        $('#action_name').html(mode ? 'Lock' : 'Unlock');
        $('#lg_mode').val(mode);
        $('#lg_id').val($(this).data('id'));
        <?= $lock_group->show(); ?>
    });

    $('.js-btn-conf').click(function () {
        $.ajax({
            type: "POST",
            data: { mode: $('#lg_mode').val(), id: $('#lg_id').val() },
            url: "/billing/lock_group",
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

    function getGroup(group_id = 0)
    {
        let group = null;
        $.ajax({
            type: "GET",
            data: { group_id: group_id },
            url: "/billing/get_group",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('msg_error', res.error, 3000);
                else
                    group = res.data;
            },
            error: function() {
                showError('msg_error', 'Connection error', 3000);
            }
        });
        return group;
    }

    function setGroup(group_id)
    {
        let group = getGroup(group_id);
        if (group == null)
            return;
        fillGroup(group);
        <?= $modal_group->show(); ?>
    }

    function fillGroup(group)
    {
        let code = $('#group_code');
        $('#group_id').val(group.id);
        $('#group_name').val(group.name);
        code.val(group.code);
    }

    $('#btn_update').click(function () {
        $.ajax({
            type: "POST",
            data: { id: $('#group_id').val(), name: $('#group_name').val(), code: $('#group_code').val() },
            url: "/billing/set_group",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('grp_error', res.error, 3000);
                else if (!res.update)
                    showError('grp_warning', 'Nothing to update', 3000);
                else
                    location.reload();
            },
            error: function() {
                showError('grp_error', 'Connection error', 3000);
            }
        });
    });
</script>