<?php
/**
 * @var $suppliers_list array
 * @var $exist_admin bool
 * @var $authUser User
 * @var $active int
 * @var $total int
 * @var $total_pages int
 * @var $current_page int
 * @var $current_filters array
 * @var $admins array
 * @var $suppliers_active array
 * @var $statuses array
 */

use app\backend\models\Suppliers;
use app\backend\models\Users;
use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\modal\SwitchModal;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Companies</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-fw fa-user-circle"></i>
                        Companies list (<?= $active; ?> item<?= $active > 1 ? 's' : ''; ?>)
                    </h6>
                </div>
                <div class="col-auto">
                    <span class="d-inline-block btn-span-tooltip" tabindex="0">
                        <button class="btn btn-success btn-icon-split"
                                type="button"
                                    <?php if ($authUser->hasUsersAccess() && $exist_admin) { ?>
                                        onclick="location.href='/suppliers/save_supplier/'"
                                    <?php } else { ?>
                                        disabled
                                    <?php } ?>>
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-plus-circle"></i>
                            </span>
                            <span class="text">Create new company</span>
                        </button>
                    </span>
                    <?php if (!$exist_admin) { ?>
                        <script>
                            $('.btn-span-tooltip').mouseover(function(){
                                $(this).tooltip({
                                    "html": true,
                                    "title": "Unable to create supplier: <br>Admins are not available"
                                });
                            });
                        </script>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php if (!$exist_admin) { ?>
            <div class="m-1">
                <?= Html::warning('Not found active admins') ?>
            </div>
        <?php } ?>
        <div class="card-body">
            <div class="col-md-12">
                <form method="GET" action="suppliers" name="frmSearch">
                    <div class="form-row">
                        <div class="form-group col-3">
                            <div class="form-group-element">
                                <input type="text" name="search_name" id="search_name" class="form-control form-control-lg" placeholder=" "
                                       value="<?= !empty($_GET['search_name']) ? View::toHtml($_GET['search_name']) : ''?>">
                                <label for="search_name">Company name</label>
                            </div>
                        </div>
                        <?php if ($authUser->isRoleSA()) { ?>
                            <div class="form-group col-6 col-lg-3">
                                <div class="form-group-element">
                                    <?=
                                        Html::dropDownList("acc_admin", $admins, (isset($_GET['acc_admin']) && $_GET['acc_admin'] != -1 ) ? $_GET['acc_admin'] : Supplier::STATUSES[0], [
                                        "class" => "form-control form-control-lg custom-select",
                                        "id" => "acc_admin",
                                    ]) ?>
                                    <label for="type_id">Account Manager</label>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="form-group col-6 col-lg-3">
                            <div class="form-group-element">
                                <?=
                                    Html::dropDownList("state", $statuses, (isset($_GET['state']) && $_GET['state'] != -1 ) ? $_GET['state'] : Supplier::STATUSES[0], [
                                        "class" => "form-control form-control-lg custom-select",
                                        "id" => "state",
                                    ]); ?>
                                <label for="zone">Status</label>
                            </div>
                        </div>
                    </div>
                    <div class="text-right mb-3">
                        <a href="/suppliers" class="btn btn-lg btn-outline-orange">Clear</a>
                        <button type="submit" id="search" class="btn btn-lg btn-orange">Filter</button>
                    </div>
                </form>
                <form method="get" action="/suppliers/delete" name="frmDel" class="mb-0">
                    <?php if (!empty($suppliers_list)) { ?>
                        <div class="row col-md-12 my-2 justify-content-center">
                            <?php View::pagination("/suppliers?" . $current_filters . '&', 1, $total_pages, $current_page, Suppliers::LIMIT_PER_PAGE)?>
                        </div>
                    <?php } ?>
                    <input type="hidden" name="id" value="">
                    <input type="hidden" id="lockedCompany" name="lock" value="">
                    <?php if (!empty($suppliers_list)) { ?>
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col width="5%">
                                <col width="30%">
                                <col width="25%">
                                <col width="12%">
                                <col width="14%">
                                <col width="14%">
                                <col width="0">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Logo</th>
                                    <th>Company name</th>
                                    <th>Account manager</th>
                                    <th>Warehouses</th>
                                    <th>Status</th>
                                    <th>Account setup</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suppliers_list as $supplier) { ?>
                                    <tr>
                                        <td>
                                            <?php if ($supplier['logo']) { ?>
                                                <img class="img-thumbnail"
                                                     style="max-height: 70px; max-width: 70px;"
                                                     title="logo"
                                                     src="<?= Suppliers::showLogoSrc($supplier['logo']) ?>"/>
                                            <?php } ?>
                                        </td>
                                        <td><?= View::toHtml($supplier['name'] . " (" . $supplier['id'] . ")") ?></td>
                                        <td><?php
                                            /** @var $admin User */
                                            $admin = $supplier['admin']; ?>
                                            <span class="<?= $admin->isBlocked() ? 'alert-danger' : '' ?> px-2"><?= View::toHtml($admin->getLogin()) ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($supplier['warehouses']))
                                                echo implode('<br/>', $supplier['warehouses']);
                                            ?>
                                        </td>
                                        <td>
                                            <?php $status_label = $supplier['del'] ? "danger" : "success"; ?>
                                            <span class="badge badge-<?= $status_label?>">
                                                <?= View::toHtml(Supplier::STATUSES[$supplier['del']]); ?>
                                            </span>
                                        </td>
                                        <td><?= $supplier['is_visible'] ? Html::setUp($supplier['setup'], $supplier['is_virtual']) : ''; ?></td>
                                        <td>
                                            <a class="btn btn-primary btn-block btn-icon-split d-flex justify-content-start text-nowrap mt-0 mb-1" href="/suppliers/settings/?id=<?= (int)$supplier['id'] ?>">
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-fw fa-cog"></i>
                                                </span>
                                                <span class="text flex-fill">Settings</span>
                                            </a>
                                            <a class="btn btn-<?= $supplier['del'] ? "success" : "danger"; ?> btn-block btn-icon-split d-flex justify-content-start text-nowrap mt-0 mb-1 js-btn-lock"
                                               onclick="frmDel.id.value=<?= (int) $supplier['id'] ?>; frmDel.lock.value=<?= (int)(!$supplier['del']) ?>" style="cursor: pointer">
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-fw fa-lock<?= $supplier['del'] ? "-open" : "" ?>"></i>
                                                </span>
                                                <span class="text flex-fill text-white"><?= $supplier['del'] ? "Unl" : "L"; ?>ock</span>
                                            </a>
                                            <?php if ($authUser->isRoleSA()) {
                                                $disabled_lock = !isset($suppliers_active[(int)$supplier['id']]) ? 'disabled' : '';
                                                $tool_tip_loc = !isset($suppliers_active[(int)$supplier['id']]) ? 'data-toggle="tooltip" title="No active users"' : '';
                                            ?>
                                                <a class="btn btn-danger btn-block btn-icon-split d-flex justify-content-start text-nowrap mt-0 js-btn-lock-all-users <?= $disabled_lock;?>" data-supplier-id="<?= (int)$supplier['id']; ?>" style="cursor: pointer;pointer-events: auto;" <?=$tool_tip_loc;?>>
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-fw fa-lock"></i>
                                                    </span>
                                                    <span class="text flex-fill text-white">Lock Users</span>
                                                </a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } else { ?>
                        <div class="m-1">
                            <?= Html::warning('No records found!') ?>
                        </div>
                    <?php } ?>
                    <?php if (!empty($suppliers_list)) { ?>
                        <div class="row col-md-12 my-2 justify-content-center">
                            <?php View::pagination("/suppliers?" . $current_filters . '&', 1, $total_pages, $current_page, Users::LIMIT_PER_PAGE)?>
                        </div>
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$modal_cancel_arrival = new ConfirmModal('lockSupplier', 'Change status company');
echo $modal_cancel_arrival->addBodyAttributes(['id' => 'ansLock'])
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE, ['id' => 'lockConf'])
    ->put();

$modal_warehouse_lock = new SwitchModal('warehouseLock', 'Choose warehouse');
echo $modal_warehouse_lock->addBodyAttributes(['class' => 'modal-body pb-0 pt-0'])->addContent('
        <input type="hidden" id="supplierForLock" name="supplier_id" value=""/>
        <p class="form-control-static mt-2 mb-1"><b>Select a warehouse with active users to lock them</b></p>
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    ' . Html::dropDownList("warehouse_id", [], null, ['class' => 'form-control warehouse_id']) . '
                </div>
            </div>
        </div>
        ')
    ->addForm('/suppliers/delete_all_users', SwitchModal::METHOD_POST, [])
    ->addButton('Lock', SwitchModal::TYPE_SUBMIT, SwitchModal::BUTTON_DONE)
    ->put();
?>
<script>
    $('.js-btn-lock').click(function () {
        $('#ansLock').html('Would you like to ' + ($('#lockedCompany').val() != 1 ? 'un' : '') + 'lock this company?');
        <?= $modal_cancel_arrival->show(); ?>
    });

    $('.js-btn-lock-all-users').click(function () {
        let suppliers_active = <?= View::toJs($suppliers_active) ?>;
        let supplier_id = $(this).data("supplier-id");
        let warehouses = suppliers_active[supplier_id];
        if (warehouses === undefined)
            return;
        $('.warehouse_id').find('option').remove();
        for (const warehouse_id in warehouses) {
            $(".warehouse_id").append(new Option(warehouses[warehouse_id], warehouse_id));
        }
        $('#supplierForLock').val(supplier_id);
        <?= $modal_warehouse_lock->show(); ?>
    });

    $('#lockConf').click(function () {
        <?= $modal_cancel_arrival->hide(); ?>
        frmDel.submit();
    });
</script>
