<?php
/**
 * @var $logistic_company LogisticCompany
 * @var $warehouses array
 * @var $sas array
 */

use app\backend\models\Users;
use app\core\entities\LogisticCompany;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\View;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Super admins of <?= View::toHtml($logistic_company->getName()); ?></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-fw fa-users"></i>
                        SA list (<?= count($sas); ?> item<?= count($sas) > 1 ? 's' : ''; ?>)
                    </h6>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split text-nowrap" type="button" id="btn_new_sa"<?= empty($warehouses) ? ' disabled' : '';?>>
                        <span class="icon">
                            <i class="fas fa-fw fa-plus"></i>
                        </span>
                        <span class="text">Create new</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($sas)) { ?>
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="*">
                        <col width="25%">
                        <col width="15%">
                        <col width="15%">
                        <col width="10%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Login</th>
                            <th>Person</th>
                            <th>Active warehouse</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sas as $sa) { ?>
                            <tr>
                                <td><?= View::toHtml("{$sa['login']} ({$sa['id']})"); ?></td>
                                <td><?= View::toHtml($sa['email']); ?><br/><?= View::toHtml($sa['phone']); ?></td>
                                <td><?= View::toHtml($warehouses[$sa['warehouse_id']]); ?></td>
                                <td>
                                    <?php $status_label = $sa['status'] == User::USER_STATUS_ACTIVE ? 'success' : 'danger'; ?>
                                    <span class="badge badge-<?= $status_label?>">
                                        <?= View::toHtml(Users::getStatusText($sa['status']))?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-success btn-block btn-icon-split text-nowrap mt-0 mb-1" type="button" onclick="initDialogSA(<?= $sa['id']; ?>)"<?= empty($warehouses) ? ' disabled' : '';?>>
                                        <span class="icon">
                                            <i class="fas fa-fw fa-edit"></i>
                                        </span>
                                        <span class="text flex-fill">Edit</span>
                                    </button>
                                    <button class="btn btn-<?= $sa['status'] != User::USER_STATUS_ACTIVE ? 'success' : 'danger'; ?> btn-block btn-icon-split d-flex justify-content-start text-nowrap mt-0 mb-1" type="button" onclick="initDialogLock(<?= "{$sa['id']}, {$sa['status']}"; ?>)">
                                        <span class="icon">
                                            <i class="fas fa-fw fa-<?= $sa['status'] != User::USER_STATUS_ACTIVE ? 'un' : ''; ?>lock"></i>
                                        </span>
                                        <span class="text flex-fill"><?= $sa['status'] != User::USER_STATUS_ACTIVE ? 'Unl' : 'L'; ?>ock</span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/logistic_companies">
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
<?php
    $options = [
        'class' => 'form-control form-control-lg custom-select'
    ];
    $modal_sa = new SwitchModal('editSA', '<span id="action_name"></span> super admin');
    echo $modal_sa
        ->addContent('
                <input type="hidden" name="lc_id" value="' . $logistic_company->getId() . '">
                <input type="hidden" name="id" id="id">
                <div id="response" class="alert alert-danger d-none"></div>
                <div class="row">
                    <div class="form-group col-12 mt-0">
                        <div class="form-group-element">
                            <input type="text" name="login" id="login" class="form-control form-control-lg" value="">
                            <label for="login">Login <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-12 mt-0">
                        <div class="form-group-element">
                            <input type="password" name="pass" id="pass" class="form-control form-control-lg" value="">
                            <label for="login">Password <span id="show_pwd" style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-12 mt-0">
                        <div class="form-group-element">
                            <input type="text" name="email" id="email" class="form-control form-control-lg" value="">
                            <label for="email">Email <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-12 mt-0">
                        <div class="form-group-element">
                            <input type="text" name="phone" id="phone" class="form-control form-control-lg" value="">
                            <label for="phone">Phone <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-12 my-0">
                        <div class="form-group-element">
                            ' . Html::dropDownList('warehouse_id', $warehouses, null, $options + ['id' => 'warehouse_id']) . '
                            <label for="warehouse_id">Active warehouse <span style="color:red">*</span></label>
                        </div>
                    </div>
                </div>
            ')
        ->addForm(SwitchModal::ACTION_DEFAULT, SwitchModal::METHOD_DEFAULT, ['id' => 'sendSAForm'])
        ->addButton('Save', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE)
        ->put();
    $modal_lock = new ConfirmModal('lockSA', 'Change status');
    echo $modal_lock
        ->addContent('
            Do you want to change status of super admin?
            <input type="hidden" name="id" id="lock_sa_id">
            <input type="hidden" name="status" id="lock_sa_status">
        ')
        ->addForm('/logistic_companies/lock_SA', SwitchModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
        ->put();
?>
<script>
    let sas = <?= View::toJs(array_map(function($sa) {
        return array_map(function($item) {
            $tmp = json_decode($item, true);
            return is_array($tmp) ? $tmp : $item;
        }, $sa);
    }, array_values($sas))) ?>;

    $('#btn_new_sa').click(() => {
        initDialogSA(null);
    });

    function initDialogSA(u_id)
    {
        $('#action_name').text(u_id === null ? 'Create' : 'Edit');
        let sa = u_id === null
            ? <?= View::toJs((new User())->toArray()) ?>
            : sas.find(item => item.id == u_id);
        $('#id').val(sa.id);
        $('#login').val(sa.login);
        $('#pass').val('');
        if (u_id === null)
            $('#show_pwd').show();
        else
            $('#show_pwd').hide();
        $('#email').val(sa.email);
        $('#phone').val(sa.phone);
        $('#warehouse_id').val(sa.warehouse_id);
        <?= $modal_sa->show(); ?>;
    }

    $('#sendSAForm').submit((e) => {
        e.preventDefault();
        $.ajax({
            url: "/logistic_companies/create_SA",
            type : 'POST',
            data : $('#sendSAForm').serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.error !== undefined)
                    showError('response', res.error, 3000);
                else
                    window.location.reload(false);
            },
            error: function (res) {
                showError('response', 'Connection error', 3000);
            }
        });
    });

    function initDialogLock(sa_id, status)
    {
        $('#lock_sa_id').val(sa_id);
        $('#lock_sa_status').val(status);
        <?= $modal_lock->show(); ?>;
    }
</script>