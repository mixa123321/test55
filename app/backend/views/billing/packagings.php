<?php

use app\backend\models\Consumables;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\status\ConsumableStatus;
use app\core\helpers\status\Status;
use app\core\helpers\View;

/**
 * @var array $records
 * @var int $total
 * @var int $current_page
 * @var int $pages
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Packagings (billing)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-boxes"></i>
                            List of packagings (<?= $total; ?> item<?= $total > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-danger d-none" id="msg_error"></div>
            <?php if (!empty($records)) { ?>
                <?php View::pagination("/billing/packagings/?", 1, $pages, $current_page, Consumables::LIMIT_PER_PAGE); ?>
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="*">
                        <col width="12%">
                        <col width="12%">
                        <col width="*">
                        <col width="12%">
                        <col width="10%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Sizes, cm<br/>(L x W x H)</th>
                            <th>Weight /<br/>Max Weight, kg</th>
                            <th>Group name<br/>Code</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record) { ?>
                            <tr>
                                <td><?= View::toHtml($record['name']); ?></td>
                                <td><?= View::toHtml($record['LWH']); ?></td>
                                <td><?= View::toHtml("{$record['weight']} / {$record['max_weight']}"); ?></td>
                                <td><?= $record['charge_group_id'] ? (View::toHtml($record['group_name']) . "<br/>" . Status::getStatus($record['code'], 'primary')) : ''; ?></td>
                                <td>
                                    <?php
                                        echo ConsumableStatus::get($record['status']);
                                        if ($record['charge_group_id'] && $record['charge_default'])
                                            echo '<br/>' . Status::getStatus('Default', 'info');
                                    ?>
                                </td>
                                <td>
                                    <button class="btn btn-success btn-icon-split btn-block" type="button" onclick="setGroup(<?= $record['id']; ?>)">
                                        <span class="icon text-white text-left">
                                            <i class="fas fa-fw fa-layer-group"></i>
                                        </span>
                                        <span class="text flex-fill">Group</span>
                                    </button>
                                    <?php if ($record['charge_group_id']) { ?>
                                        <button class="btn btn-success btn-icon-split btn-block" type="button" onclick="setDefault(<?= $record['id']; ?>)">
                                            <span class="icon text-white text-left">
                                                <i class="fas fa-fw fa-check"></i>
                                            </span>
                                            <span class="text flex-fill">Default</span>
                                        </button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php View::pagination("/billing/packagings/?", 1, $pages, $current_page, Consumables::LIMIT_PER_PAGE); ?>
            <?php } else
                echo Html::warning("No records found");
            ?>
        </div>
    </div>
</div>
<?php
    $modal_default = new ConfirmModal('defaultGroup', 'Default for tariff group');
    $modal_default
        ->addContent(
            Html::hidden('packaging_id', '', false) . '
            Only one packaging can be set as default for tariff group - continue?')
        ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE, ['id' => 'btn_default_group']);
    echo $modal_default->put();

    $modal_group = new SwitchModal('setGroup', 'Tariff group');
    $modal_group
        ->addContent(
            Html::hidden('packaging_id', '', false) . '
            <div class="alert alert-danger d-none" id="pack_error"></div>
            <div class="form-row">
                <div class="col-12">
                    <div class="form-group-element">' .
                        Html::dropDownList('packaging_groups', [], null, [
                            'id' => 'packaging_groups',
                            'class' => 'form-control form-control-lg custom-select'
                        ]) . '
                        <label for="packaging_groups">Name</label>
                    </div>
                </div>
            </div>')
        ->addButton('Update', SwitchModal::TYPE_SUBMIT, SwitchModal::BUTTON_DONE, ['id' => 'btn_update']);
    echo $modal_group->put();
?>
<script>
    function setDefault(packaging_id)
    {
        $('#packaging_id').val(packaging_id);
        <?= $modal_default->show(); ?>
    }

    $('#btn_default_group').click(function () {
        <?= $modal_default->hide(); ?>
        $.ajax({
            type: "POST",
            data: { id: $('#packaging_id').val() },
            url: "/billing/set_default_packaging",
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

    function getGroupsInfo(packaging_id)
    {
        let groups_info = null;
        $.ajax({
            type: "GET",
            data: { packaging_id: packaging_id },
            url: "/billing/get_groups",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('msg_error', res.error, 3000);
                else
                    groups_info = res.data;
            },
            error: function() {
                showError('msg_error', 'Connection error', 3000);
            }
        });
        return groups_info;
    }

    function setGroup(packaging_id)
    {
        let groups_info = getGroupsInfo(packaging_id);
        if (groups_info == null)
            return;
        $('#packaging_id').val(packaging_id);
        fillGroup(groups_info);
        <?= $modal_group->show(); ?>
    }

    function fillGroup(groups_info)
    {
        let groups = $('#packaging_groups');
        groups.children().remove().end();
        groups.append($('<option>', { value: 0 }).text('None'));
        $.each(groups_info.groups, function (key, item) {
            groups.append($('<option>', { value: key }).text(item));
        });
        groups.val(groups_info.current);
    }

    $('#btn_update').click(function () {
        $.ajax({
            type: "POST",
            data: { id: $('#packaging_id').val(), group_id: $('#packaging_groups').val() },
            url: "/billing/set_packaging_group",
            dataType: "json",
            async: false,
            success: function(res) {
                if (res.error)
                    showError('pack_error', res.error, 3000);
                else
                    location.reload();
            },
            error: function() {
                showError('pack_error', 'Connection error', 3000);
            }
        });
    });
</script>
