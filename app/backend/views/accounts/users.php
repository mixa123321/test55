<?php
/**
 * @var $authUser User
 * @var $users array
 * @var $roles array
 * @var $suppliers array
 * @var $user_warehouses array
 * @var $user_groups array
 * @var $create_link string
 * @var $paginate_link string
 * @var $filters array
 * @var $total_count int
 * @var $total_pages int
 * @var $current_page int
 * @var $back string
 * @var $warehouse_virtual bool
 */

use app\backend\models\Roles;
use app\core\entities\Role;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\HTTP;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Users</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-list"></i>
                    </span>
                        User list (<?= $total_count ?> item<?= $total_count > 1 ? 's' : '' ?>)
                    </h6>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split" onclick="$('#uniUserFormModal').modal('show')">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-plus"></i>
                        </span>
                        <span class="text">add</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="get" onchange="$(this).submit();">
                <div class="form-row justify-content-center p-0">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <select class="form-control form-control-lg custom-select" id="roleFilter" name="role_filter">
                                <option value="0">All</option>
                                <?php foreach ($roles as $role) { /** @var $role Role */ ?>
                                    <option value="<?= $role->getId() ?>" <?= !empty($filters['role_filter']) && $filters['role_filter'] == $role->getId() ? 'selected' : '' ?>>
                                        <?= View::toHtml($role->getName()) ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <label for="roleSelect">Roles</label>
                        </div>
                    </div>
                    <div class="form-group col-auto align-self-center">
                        <div class="form-check">
                            <input type="checkbox"
                                   class="form-check-input"
                                   id="only_active"
                                   name="only_active"
                                <?= !empty($filters['only_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="only_active">Only active</label>
                        </div>
                    </div>
                    <div class="form-group col-auto align-self-center">
                        <div class="form-check">
                            <input type="checkbox"
                                   class="form-check-input"
                                   id="for_warehouse"
                                   name="for_warehouse"
                                <?= !empty($filters['for_warehouse']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="for_warehouse">For warehouse</label>
                        </div>
                    </div>
                </div>
            </form>
            <div class="row justify-content-center p-0">
                <div class="col">
                    <?php if (!empty($users)) {
                        View::pagination($paginate_link . (!empty($filters) ? ('?' . http_build_query($filters)) : '') . '&', 1, $total_pages, $current_page,Roles::LIMIT_PER_PAGE); ?>
                        <table class="table table-hover last-div">
                            <colgroup>
                                <col width="*">
                                <col width="15%">
                                <col width="25%">
                                <col width="15%">
                                <col width="10%">
                                <col width="10%">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Login</th>
                                <th>Role</th>
                                <th>Groups</th>
                                <th>Warehouses</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($users as $user) {
                                /** @var $user User */ ?>
                                <tr>
                                    <td><?= View::toHtml($user->getLogin()) ?></td>
                                    <td>
                                        <span class="badge badge-secondary">
                                             <?= View::toHtml($roles[$user->getRoleId()] instanceof Role ? $roles[$user->getRoleId()]->getName() : '') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($roles[$user->getRoleId()] instanceof Role && $roles[$user->getRoleId()]->hasUserGroups() && !empty($user_groups[$user->getId()])) {
                                            foreach ($user_groups[$user->getId()] as $user_group) { ?>
                                                <span class='badge badge-info'><?= View::toHtml($user_group) ?></span>
                                        <?php }} ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($user_warehouses[$user->getId()]))
                                            echo implode('<br/>', $user_warehouses[$user->getId()]); ?>
                                    </td>
                                    <td>
                                        <?php if ($user->isActive()) { ?>
                                            <span class="badge badge-success">active</span>
                                        <?php } else { ?>
                                            <span class="badge badge-danger">blocked</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if ($user->getRoleId() !== Roles::SA) { ?>
                                            <a class="btn btn-success btn-icon-split"
                                               href="/accounts/edit_account?id=<?= (int)$user->getId() ?>&advanced=1<?= !empty($filters) ? ('&' . http_build_query($filters)) : '' ?>">
                                                <span class="icon text-white">
                                                    <i class="fas fa-fw fa-edit"></i>
                                                </span>
                                            </a>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                        <?php View::pagination($paginate_link . (!empty($filters) ? ('?' . http_build_query($filters)) : '') . '&', 1, $total_pages, $current_page,Roles::LIMIT_PER_PAGE); ?>
                    <?php } else
                        echo Html::warning('No records found');
                    ?>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-outline-orange pl-3 btn-sm" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                        <i class="fas fa-fw fa-chevron-left mr-2"></i>
                        <span class="ml-1">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="uniUserFormModal" style="display: none;" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create user</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-12 mb-0">
                            <div class="form-group-element">
                                <select class="form-control form-control-lg custom-select" id="roleSelect">
                                    <?php foreach ($roles as $role) { /** @var $role Role */
                                        if (!($role->isSA() || ($warehouse_virtual && $role->isWorker()))) { ?>
                                        <option value="<?= $role->getId() ?>"><?= View::toHtml($role->getName()) ?></option>
                                    <?php }} ?>
                                </select>
                                <label for="roleSelect">Roles</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-12 mb-0 mt-3 d-none" id="supplierSelectBlock">
                            <div class="form-group-element">
                                <select class="form-control form-control-lg custom-select" id="supplierSelect">
                                    <?php foreach ($suppliers as $supplier_id => $supplier) {  ?>
                                        <option value="<?= $supplier_id ?>"><?= View::toHtml($supplier) ?></option>
                                    <?php } ?>
                                </select>
                                <label for="supplierSelect">Supplier</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-icon-split" data-dismiss="modal" type="button">
                        <span class="text">Close</span>
                    </button>
                    <button class="btn btn-success btn-icon-split js-btn-conf" onclick="createUser()">
                        <span class="text">Create</span>
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-save pt-1"></i>
                        </span>
                    </button>
                </div>
        </div>
    </div>
</div>

<script>


    $('#roleSelect').change(function() {
        $('#supplierSelectBlock').toggleClass('d-none', +$(this).val() !== parseInt('<?= Roles::SUPPLIER_USER ?>'));
    });

    function createUser() {
        const role_id = +$('#roleSelect').val();
        let link = '<?= $create_link ?>?role=' + role_id;
        if (role_id === parseInt('<?= Roles::SUPPLIER_USER ?>'))
            link += '&supplier_id=' + $('#supplierSelect').val();
        location.href = link + '&advanced=1<?= !empty($filters) ? ('&' . http_build_query($filters)) : '' ?>';
    }

</script>