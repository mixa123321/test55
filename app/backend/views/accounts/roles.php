<?php
/**
 * @var $authUser User
 * @var $roles array
 * @var $back string
 */

use app\backend\models\Roles;
use app\core\entities\Role;
use app\core\entities\User;
use app\core\helpers\View;
use app\core\helpers\HTTP;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Roles</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span><i class="fas fa-fw fa-list"></i></span>
                        Roles list
                    </h6>
                </div>
                    <div class="col-auto">
                        <button class="btn btn-success btn-icon-split" onclick="addRoleForm()">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-plus"></i>
                            </span>
                            <span class="text">Add</span>
                        </button>
                    </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center p-0">
                <div class="col">
                    <table class="table table-hover last-div">
                        <colgroup>
                            <col width="20%">
                            <col width="*">
                            <col width="*">
                            <col width="*">
                            <col width="*">
                            <col width="*">
                            <col width="*">
                            <col width="*">
                            <col width="*">
                            <col width="*">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Constant</th>
                                <th>Side</th>
                                <th>Multiple<br>Warehouses</th>
                                <th>Api Key</th>
                                <th>User Groups</th>
                                <th>Person</th>
                                <th>Selectable<br>Warehouse</th>
                                <th>Status</th>
                                <th>Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role) {
                                /** @var $role Role */ ?>
                                <tr class="<?= $role->getConstant() ? 'alert-secondary' : '' ?>">
                                    <td><?= View::toHtml($role->getName()) ?></td>
                                    <td><?= $role->getConstant() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?></td>
                                    <td><?= View::toHtml($role->getSide()) ?></td>
                                    <td><?= $role->isMultipleWarehouses() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?></td>
                                    <td><?= $role->hasApiKey() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?></td>
                                    <td><?= $role->hasUserGroups() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?></td>
                                    <td><?= $role->hasPerson() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?></td>
                                    <td><?= $role->hasSelectedWarehouse() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?></td>
                                    <td>
                                        <?php if ($role->getIsActive()) { ?>
                                            <span class="badge badge-success">active</span>
                                        <?php } else { ?>
                                            <span class="badge badge-danger">blocked</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if (!$role->getConstant()) { ?>
                                            <button class="btn btn-success btn-icon-split" type="button" onclick="addRoleForm(<?= (int)$role->getId() ?>)">
                                                    <span class="icon text-white">
                                                        <i class="fas fa-fw fa-edit"></i>
                                                    </span>
                                            </button>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
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

<div class="modal" tabindex="-1" role="dialog" id="roleForm" style="display: none;" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span id="action_name">Create</span> role</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form action="/accounts/store_role" method="post">
                <div class="modal-body">
                    <div id="response" class="mb-3"></div>
                    <div id="roleFormFields"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-icon-split" data-dismiss="modal" type="button">
                        <span class="text">Close</span>
                    </button>
                    <button class="btn btn-success btn-icon-split js-btn-conf">
                        <span class="text">Save</span>
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-save pt-1"></i>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>

    const WAREHOUSE_SIDE = '<?= Roles::SIDE_WAREHOUSE ?>';

    const createForm = () => `
                    <div class="form-group">
                        <div class="form-group-element">
                            <input type="text" name="name" id="role_name" class="form-control form-control-lg" value="" placeholder=" "
                                onkeyup="changeButtonSave(this.value);">
                            <label for="role_name">Name <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-9"></div>
                            <div class="col-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="multiple_warehouses" id="role_multiple_warehouses">
                                    <label class="form-check-label" for="role_multiple_warehouses">multiple warehouses</label>
                                </div>
                            </div>
                        </div>
                    </div>`;
    const editForm = role => {
        return `    <input type="hidden" name="role_id" value="${+role.id}">
                        <div class="form-group">
                            <div class="form-group-element">
                                <input type="text" name="name" id="role_name" class="form-control form-control-lg" value="${html_escape(role.name)}"
                                    onkeyup="changeButtonSave(this.value);">
                                <label for="role_name">Name <span style="color:red">*</span></label>
                            </div>
                        </div>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-${role.side === WAREHOUSE_SIDE ? '6': '9'}"></div>
                            ${role.side === WAREHOUSE_SIDE ? `<div class="col-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="multiple_warehouses" id="role_multiple_warehouses" ${!!+role.multiple_warehouses ? 'checked' : ''}>
                                        <label class="form-check-label" for="role_multiple_warehouses">multiple warehouses</label>
                                </div>
                            </div>` : ''}
                            <div class="col-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_active" id="role_is_active" ${!!+role.is_active ? 'checked' : ''}>
                                    <label class="form-check-label" for="role_is_active" >Active</label>
                                </div>
                            </div>
                        </div>
                    </div>`;
    };

    const rolesArr = <?= View::toJs(array_map(function($item) {
        return $item->toArray();
    }, $roles)) ?>;

    const $responseBlock = $('#response');
    const $roleFormFields = $('#roleFormFields');

    function addRoleForm(role_id = null) {
        let create = role_id === null;
        $('#action_name').text(create ? 'Create' : 'Edit');
        let role = create ? {} : rolesArr.find(r => +r.id === +role_id);
        if (!create && role === undefined) {
            $responseBlock.text('Role error')
            return;
        }
        $roleFormFields.html(create ? createForm() : editForm(role))
        changeButtonSave(create ? '' : role.name);
        $('#roleForm').modal('show');
    }

    function changeButtonSave(name) {
        $('.js-btn-conf').prop('disabled', name.trim() === '');
    }

</script>