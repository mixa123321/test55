<?php

use app\core\entities\Dimensioner;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var Dimensioner[] $dimensioners
 * @var array $suppliers
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dimensioners</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-vector-square"></i>
                            Dimensioners list (<?= count($dimensioners); ?> item<?= count($dimensioners) > 1 ? 's' : ''; ?>)
                        </span>
                    </h6>
                </div>
                <?php if (!$authUser->isRoleWorker()) { ?>
                    <div class="col-auto">
                        <button class="btn btn-success btn-icon-split mt-0 mb-1 js-btn-add" type="button">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-plus"></i>
                            </span>
                            <span class="text flex-fill d-none d-sm-block">Add dimensioner</span>
                        </button>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col">
                    <?php if (count($dimensioners) > 0) { ?>
                        <table class="table table-hover mb-0 d-none d-sm-block">
                            <colgroup>
                                <col width="5%">
                                <col width="*">
                                <col width="12%">
                                <col width="12%">
                                <col width="25%">
                                <col width="10%">
                                <col width="10%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Unit ID</th>
                                    <th>Branch ID</th>
                                    <th>Company</th>
                                    <th>State</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dimensioners as $dimensioner_id => $dimensioner) { ?>
                                    <tr>
                                        <td><?= View::toHtml($dimensioner->getId()); ?></td>
                                        <td><?= View::toHtml($dimensioner->getName()); ?></td>
                                        <td><?= View::toHtml($dimensioner->getUnitId()); ?></td>
                                        <td><?= View::toHtml($dimensioner->getBranchId() ?: ''); ?></td>
                                        <td><?= $dimensioner->getSupplierId() ? View::toHtml($suppliers[$dimensioner->getSupplierId()]) : ''; ?></td>
                                        <td>
                                            <span class="badge badge-<?= $dimensioner->getStateColor(); ?>">
                                                <?= $dimensioner->getStateTitle(); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-block btn-icon-split text-nowrap mt-0 mb-1 js-btn-edit"
                                                    id='edit_<?= $dimensioner_id; ?>'
                                                    type="button">
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-fw fa-pen"></i>
                                                </span>
                                                <span class="text flex-fill">Edit</span>
                                            </button>
                                            <button class="btn btn-<?= !$dimensioner->getState() ? 'success' : 'danger'; ?> btn-block btn-icon-split text-nowrap mt-0 js-btn-lock"
                                                    id='lock_<?= $dimensioner_id . '_' . (int)$dimensioner->getState(); ?>'
                                                    type="button">
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-fw fa-lock<?= !$dimensioner->getState() ? '-open' : '' ?>"></i>
                                                </span>
                                                <span class="text text-white flex-fill"><?= !$dimensioner->getState() ? 'Unl' : 'L'; ?>ock</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <div class="mb-0 d-sm-none d-block">
                            <?php foreach ($dimensioners as $dimensioner_id => $dimensioner) { ?>
                                <div class="border rounded p-2 text-center mb-1">
                                    <div class="row">
                                        <div class="col-10">
                                            <span class="text-lg font-weight-bold"><?= View::toHtml($dimensioner->getName()); ?> #<?= View::toHtml($dimensioner->getId()); ?></span>
                                            <span class="badge badge-<?= $dimensioner->getStateColor(); ?>">
                                                <?= $dimensioner->getStateTitle(); ?>
                                            </span><br/>
                                            Unit ID: <?= View::toHtml($dimensioner->getUnitId()); ?>; Branch ID: <?= View::toHtml($dimensioner->getBranchId() ?: ''); ?><br/>
                                            <b><?= $dimensioner->getSupplierId() ? View::toHtml($suppliers[$dimensioner->getSupplierId()]) : ''; ?></b>
                                        </div>
                                        <div class="col-2 text-right">
                                            <button class="btn btn-primary p-2 mt-0 mb-1 js-btn-edit"
                                                    id='edit_<?= $dimensioner_id; ?>'
                                                    type="button">
                                                <span class="icon text-white"><i class="fas fa-fw fa-pen"></i></span>
                                            </button>
                                            <button class="btn btn-<?= !$dimensioner->getState() ? 'success' : 'danger'; ?> p-2 mt-0 js-btn-lock"
                                                    id='lock_<?= $dimensioner_id . '_' . (int)$dimensioner->getState(); ?>'
                                                    type="button">
                                                <span class="icon text-white"><i class="fas fa-fw fa-lock<?= !$dimensioner->getState() ? '-open' : '' ?>"></i></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else
                        echo Html::warning('Records is not found');
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    $modal_lock = new ConfirmModal('lockDimensioner', 'Change status dimensioner');
    echo $modal_lock->addBodyAttributes(['id' => 'ans_lock'])
        ->addForm('/dimensioners/lock_dimensioner', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons(Html::hiddens([['dim_lock_id', 0, true], ['dim_lock', 0, true]]) . $modal_lock->getButtons())
        ->put();

    $content_edit = Html::hidden('dimensioner[id]', 0, 'dimensioner_id') . '
        <div class="col-12 bg-danger text-white mt-0 mb-3 p-2 d-none" id="err_msg"></div>
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <div class="form-group-element">
                        <input id="dimensioner_name" name="dimensioner[name]" type="text" class="form-control form-control-lg" placeholder=" ">
                        <label for="dimensioner_name">Name <span class="text-danger">*</span></label>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <div class="form-group-element">
                        <input id="dimensioner_unit" name="dimensioner[unit_id]" type="number" class="form-control form-control-lg" placeholder=" ">
                        <label for="dimensioner_unit">Unit ID <span class="text-danger">*</span></label>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <div class="form-group-element">
                        <input id="dimensioner_branch" name="dimensioner[branch_id]" type="number" class="form-control form-control-lg" placeholder=" ">
                        <label for="dimensioner_branch">Branch ID</label>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="form-group mb-0">
                    <div class="form-group-element">' .
                        Html::dropDownList('dimensioner[supplier_id]', [0 => 'None'] + $suppliers, '', [
                            'id' => 'dimensioner_supplier',
                            'class' => 'form-control form-control-lg custom-select',
                            'placeholder' => ' '
                        ]) . '
                        <label for="dimensioner_supplier">Companies</label>
                    </div>
                </div>
            </div>
        </div>';
    $modal_edit = new ConfirmModal('editDimensioner', 'Dimensioner');
    echo $modal_edit->addContent($content_edit)
        ->addForm('/dimensioners/edit_dimensioner', ConfirmModal::METHOD_POST, ['id' => 'sendDimensionerForm'])
        ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE, ['id' => 'editConf'])
        ->put();
?>
<script>
    const dimensioners = <?= View::toJs(array_map(function ($el) { return $el->toArray(); }, $dimensioners)) ?>;

    $('.js-btn-lock').click(function (e) {
        let dimensioner = e.currentTarget.id.split('_');
        $('#dim_lock_id').val(dimensioner[1]);
        $('#dim_lock').val(dimensioner[2]);
        $('#ans_lock').html('Would you like to ' + (dimensioner[2] == 0 ? 'un' : '') + 'lock dimensioner?');
        <?= $modal_lock->show(); ?>
    });

    $('.js-btn-add').click(function () {
        $('#dimensioner_id').val('0');
        $('#dimensioner_name').val('');
        $('#dimensioner_unit').val('');
        $('#dimensioner_branch').val('');
        $('#dimensioner_supplier').val(0);
        <?= $modal_edit->show(); ?>
    });

    $('.js-btn-edit').click(function (e) {
        let dimensioner_id = e.currentTarget.id.split('_')[1];
        let dimensioner = dimensioners[dimensioner_id];
        $('#dimensioner_id').val(dimensioner_id);
        $('#dimensioner_name').val(dimensioner.name);
        $('#dimensioner_unit').val(dimensioner.unit_id);
        $('#dimensioner_branch').val(dimensioner.branch_id);
        $('#dimensioner_supplier').val(dimensioner.supplier_id);
        <?= $modal_edit->show(); ?>
    });

    $('#editConf').click(function () {
        $.ajax({
            url: '/dimensioners/edit_dimensioner',
            type : 'POST',
            data : $('#sendDimensionerForm').serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.error !== undefined)
                    showErrorHtml('err_msg', res.error, 3000);
                else
                    window.location = '/dimensioners';
            },
            error: function (res) {
                showError('err_msg', 'Connection error', 3000);
            }
        });
        return false;
    });
</script>