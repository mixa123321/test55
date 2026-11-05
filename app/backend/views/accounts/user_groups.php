<?php
/**
 * @var $authUser User
 * @var $groups array
 * @var $users array
 * @var $group_roles array
 * @var $roles array
 * @var $back string
 */

use app\core\entities\Role;
use app\core\entities\User;
use app\core\entities\UserGroup;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\modal\ConfirmModal;

?>

<style>
    #userListModalInfo .modal-content,
    #userListModalInfo .modal-dialog {
        height: 95%;
    }

    .modal-body-fix {
        height: calc(100% - 150px);
    }

    .modal-table-fix {
        max-height: calc(100% - 80px);
        overflow-y: scroll;
    }

    #btn_up {
        padding: 8px 10px;
        opacity: .4;
        border-radius: 5px;
    }
</style>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">User groups</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-list"></i>
                        </span>
                        Group list (<?= count($groups); ?> item<?= count($groups) > 1 ? 's' : '' ?>)
                    </h6>
                </div>
                    <div class="col-auto">
                        <button class="btn btn-success btn-icon-split" onclick="addUserGroupForm()">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-plus"></i>
                            </span>
                            <span class="text">add</span>
                        </button>
                    </div>
            </div>
        </div>
            <div class="card-body">
                <div class="row justify-content-center mt-2 mb-2 p-0">
                    <div class="col-md-10">
                        <table class="table table-hover">
                            <colgroup>
                                <col width="*">
                                <col width="20%">
                                <col width="10%">
                                <col width="15%">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Roles</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($groups as $group) {
                                /** @var $group UserGroup */ ?>
                                <tr>
                                    <td><?= View::toHtml($group->getName()) ?></td>
                                    <td>
                                        <?php if (!empty($group_roles[$group->getId()]['ids']))  {
                                            foreach ($group_roles[$group->getId()]['roles'] as $role) {
                                                /** @var $role Role */?>
                                                <span class='badge badge-<?= $role->isActive() ? 'primary' : 'danger' ?>'><?= View::toHtml($role->getName()) ?></span>
                                        <?php }} ?>
                                    </td>
                                    <td>
                                        <?php if ($group->getIsActive()) { ?>
                                            <span class="badge badge-success">active</span>
                                        <?php } else { ?>
                                            <span class="badge badge-danger">blocked</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                                type="button" onclick="addUserGroupForm(<?= (int)$group->getId() ?>)">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-cog"></i>
                                            </span>
                                            <span class="text flex-fill">Edit</span>
                                        </button>
                                        <?php if (!empty($users[$group->getId()])) { ?>
                                        <button id="btn_users_group_<?= $group->getId() ?>" class="btn btn-success btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                                type="button" onclick="showUserList(<?= (int)$group->getId() ?>)">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-list"></i>
                                            </span>
                                            <span class="text flex-fill">Users</span>
                                        </button>
                                        <?php } ?>
                                        <button class="btn btn-<?= $group->isActive() ? 'danger' : 'success' ?> btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                                type="button" onclick="openLockModal(<?= (int)$group->getId() ?>, <?= View::toHtml(View::toJs((string)$group->getName())) ?>, <?= $group->isActive() ?>)">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-lock<?= $group->isActive() ? '' : '-open' ?>"></i>
                                            </span>
                                            <span class="text flex-fill"><?= $group->isActive() ? 'L' : 'Unl' ?>ock</span>
                                        </button>
                                        <button id="btn_delete_group_<?= $group->getId() ?>" class="<?= !empty($users[$group->getId()]) ? 'd-none ' : '' ?>btn btn-danger btn-block btn-icon-split justify-content-start text-nowrap mt-2 mb-1 "
                                                type="button" onclick="openDeleteModal(<?= (int)$group->getId() ?>, <?= View::toHtml(View::toJs((string)$group->getName())) ?>)">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-trash"></i>
                                            </span>
                                            <span class="text flex-fill">delete</span>
                                        </button>
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

<div class="modal" tabindex="-1" role="dialog" id="userGroupFormModal" style="display: none;" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span id="action_name">Create</span> group</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form action="/accounts/store_user_group" method="post">
                <div class="modal-body py-0">
                    <div class="response mb-3"></div>
                    <div>
                        <input type="hidden" name="user_group_id" id="user_group_id" value="">
                        <div class="form-group">
                            <div class="form-group-element">
                                <input type="text" name="name" id="user_group_name" class="form-control form-control-lg" value="" placeholder=" "
                                       onkeyup="changeButtonSave(this.value);">
                                <label for="user_group_name">Name <span style="color:red">*</span></label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="group_roles">Group roles: <span style="color:red">*</span></label><br/>
                            <?php foreach ($roles as $role) {
                                /** @var $role Role */
                                    if ($role->isActive()) ?>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input group_roles" id="role_<?= $role->getId() ?>" name="group_roles[<?= $role->getId() ?>]">
                                    <label class='form-check-label' for="role_<?= $role->getId() ?>"><?= View::toHtml($role->getName()) ?></label>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
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

<?php
$title = 'List for <b id="user_group_id_list"></b>
                    (all: <span id="user_group_id_list_count"></span>, for warehouse: <span id="user_group_id_list_count_for_warehouse"></span>)';
$modal_shipping_requests = new InfoModal('userList', $title, InfoModal::SIZE_LG);
$modal_content = '<div class="form-row">
                    <div class="form-group col-auto mt-3 mb-0 float-right">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="for_warehouse">
                            <label class="form-check-label" for="for_warehouse">For warehouse</label>
                        </div>
                    </div>
                    </div>
                    <div class="response mb-3"></div>
                    <div class="modal-table-fix">
                        <table class="table table-hover" id="table_users_in_group">
                            <colgroup>
                                <col width="*">
                                <col width="20%">
                                <col width="10%">
                            </colgroup>
                            <thead><tr><th>Login</th><th>Role</th><th>Delete</th></tr></thead>
                            <tbody id="body_user_list"></tbody>
                        </table>
                    </div>';
echo $modal_shipping_requests
    ->addBodyAttributes(['class' => 'modal-body modal-body-fix py-0'])
    ->addCustomButtons('<div class="col p-0"><button id="btn_up" class="btn btn-secondary btn-icon-split d-none m-1"><i class="fas fa-angle-up"></i></button></div>' .
        '<div class="col-auto p-0">' . $modal_shipping_requests->getButtons() . '</div>')
    ->addContent($modal_content)->put();

$modal_lock_user_group = new ConfirmModal('lockUserGroup', '<span id="lockTitle"></span>');
echo $modal_lock_user_group->addContent('Would you like to <span id="lock_action"></span> user group <b><span id="name_lock"></span></b>?')
    ->addForm('/accounts/lock_user_group', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" id="id_lock" name="group_id"><input type="hidden" id="value_lock" name="lock">' . $modal_lock_user_group->getButtons())
    ->put();
?>

<div class="modal" tabindex="-1" role="dialog" id="deleteUserFromGroupModal" style="display: none;" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content border border-danger" style="border-width: 3px !important;">
            <div class="modal-header">
                <h5 class="modal-title">Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                Would you like to remove user <b><span id="delete_user_login"></span></b> from group <b><span id="delete_user_group_name"></span></b>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-orange" data-dismiss="modal">No</button>
                <input type="hidden" id="id_delete_user">
                <input type="hidden" id="id_delete_group">
                <button type="submit" class="btn btn-danger" onclick="deleteUserFromGroup(+$('#id_delete_user').val(), +$('#id_delete_group').val())">Yes</button>
            </div>
        </div>
    </div>
</div>

<?php
$modal_delete_user_group = new ConfirmModal('deleteUserGroup', 'Delete');
echo $modal_delete_user_group->addContent('Would you like to remove user group <b><span id="name_delete"></span></b>?<br>This action will also clear all settings for this group')
    ->addForm('/accounts/delete_user_group', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" id="id_delete" name="group_id">' . $modal_delete_user_group->getButtons())
    ->put();
?>

<script>

    $('#btn_up').click(function () {
        $('.modal-table-fix').show().scrollTop(0);
    });

    $('.modal-table-fix').scroll(function () {
        const cl_list = document.getElementById('btn_up').classList;
        if ($(this).scrollTop() < 100) {
            if (!cl_list.contains('d-none'))
                cl_list.add('d-none')
        } else if (cl_list.contains('d-none'))
            cl_list.remove('d-none')
    })

    const userGroupArr = <?= View::toJs(array_map(function($item) {
        return $item->toArray();
    }, $groups)) ?>;

    const groupRoles = <?= View::toJs(array_map(function($item) {
        return $item['ids'] ?? [];
    }, $group_roles)) ?>;

    const $responseBlock = $('.response');

    function addUserGroupForm(group_id = null) {
        $responseBlock.html('');
        let create = group_id === null;
        $('#action_name').text(create ? 'Create' : 'Edit');
        let group = create ? {} : userGroupArr.find(g => +g.id === +group_id);
        if (!create && group === undefined) {
            $responseBlock.html('<div class="alert alert-danger">User group error</div>')
            return;
        }
        $('#user_group_id').val(group_id || '');
        $('#user_group_name').val(group.name || '');

        $('.group_roles').each(function() {
            $(this).prop('checked', !create ? groupRoles[group_id].includes(+$(this).attr('id').replace('role_', '')) : false)
        });

        changeButtonSave(create ? '' : group.name);
        $('#userGroupFormModal').modal('show');
    }

    function changeButtonSave(name) {
        $('.js-btn-conf').prop('disabled', name.trim() === '');
    }

    let warehouse_users = [];

    function isForWarehouse() {
        return $('#for_warehouse').is(':checked');
    }

    let opened_group_id = null;

    let user_list = {};

    const $userListBlock = $('#body_user_list');

    function showUserList(group_id) {
        opened_group_id = group_id;

        $responseBlock.html('');
        let group = userGroupArr.find(g => +g.id === +group_id);
        if (group === undefined) {
            $responseBlock.html('<div class="alert alert-danger">User group error</div>')
            return;
        }
        $('#user_group_id_list').text(group.name);

        $userListBlock.html('');

        $responseBlock.html('<div class="alert alert-info">Loading...</div>');

        $.ajax({
            url: "/accounts/get_users_by_group",
            method: "POST",
            data: { group_id: group.id },
            dataType: 'json',
            async: false,
            success :function (response) {
                if (response.result === undefined || response.result === 'error') {
                    $responseBlock.html('<div class="alert alert-danger">Request failed</div>');
                    return;
                }
                if (response.result === 'success') {
                    user_list = response.users;
                    const all_users_count = Object.keys(user_list).length;
                    $('#user_group_id_list_count').text(all_users_count);
                    warehouse_users = response.warehouse_users;
                    $('#user_group_id_list_count_for_warehouse').text(warehouse_users.length);
                    if (all_users_count === 0) {
                        $responseBlock.html('<div class="alert alert-danger">Users not found</div>');
                        $('#btn_users_group_' + group.id).addClass('d-none');
                        $('#btn_delete_group_' + group.id).removeClass('d-none');

                        if (!$('#table_users_in_group').hasClass('d-none'))
                            $('#table_users_in_group').addClass('d-none');
                    } else {
                        let temp = '';
                        for (const [user_id, user] of Object.entries(user_list)) {
                            temp+= `<tr id="user_${+user_id}_group_${+group_id}">
                                    <td class="px-2">${html_escape(user.login)}</td>
                                    <td class="px-2"><span class="badge badge-primary">${html_escape(user.role || '')}</span></td>
                                    <td class="pl-2"><button class="btn btn-danger btn-icon-split" onclick="removeUserFromGroupModal(${+user_id}, ${html_escape(JSON.stringify(String(user.login)))}, ${+group_id}, ${html_escape(JSON.stringify(String(group.name)))})">
                                                            <span class="icon text-white">
                                                                <i class="fas fa-fw fa-trash"></i>
                                                            </span>
                                                        </button></td>
                                </tr>`;
                        }
                        $userListBlock.html(temp);
                        handleForWarehouses();
                    }

                }
            }
        });
        <?= $modal_shipping_requests->show(); ?>
    }

    function removeUserFromGroupModal(user_id, user_name, group_id, group_name)
    {
        $('#delete_user_login').text(user_name);
        $('#delete_user_group_name').text(group_name);

        $('#id_delete_user').val(user_id);
        $('#id_delete_group').val(group_id);

        $('#deleteUserFromGroupModal').modal('show');
    }

    function deleteUserFromGroup(user_id, group_id)
    {
        $responseBlock.html('');
        $.ajax({
            url: "/accounts/remove_user_from_group",
            method: "POST",
            data: {user_id, group_id},
            dataType: 'json',
            async: false,
            success: function (response) {
                if (response.result === undefined || response.result === 'error') {
                    $responseBlock.html('<div class="alert alert-danger">Request failed</div>');
                    return;
                }
                $(`#user_${user_id}_group_${group_id}`).remove();
                if (response.result === 'success') {
                    $responseBlock.append(`<div class="alert alert-warning">${html_escape(response.message)}</div>`);
                    setTimeout(() => showUserList(group_id), 1500);
                }
            }
        });

        $('#deleteUserFromGroupModal').modal('hide');
    }

    function handleForWarehouses()
    {
        $responseBlock.html('');
        const for_warehouse = isForWarehouse();
        const userRows = $userListBlock.children();
        let counter = 0;
        $.map(userRows, tr => {
            if (for_warehouse) {
                if (warehouse_users.length === 0)
                    $responseBlock.html('<div class="alert alert-danger">Users not found</div>');
                let user_id = +tr.id.replace('user_', '').replace('_group_' + opened_group_id, '');
                let include = warehouse_users.includes(user_id);
                $(tr).toggleClass('d-none', !include);
                if (include)
                    counter++;
            } else {
                tr.className = '';
                counter++;
            }
        });
        $('#table_users_in_group').toggleClass('d-none', counter === 0);
        const list_count = for_warehouse ? warehouse_users.length : Object.keys(user_list).length;
        $('#userListModalInfo .modal-content').css('height', list_count > 12 ? '95%' : 'max-content');
    }

    $('#for_warehouse').change(handleForWarehouses)

    function openLockModal(group_id, name, lock) {
        $('#id_lock').val(group_id);
        $('#value_lock').val(+!lock);
        $('#lockTitle').text((!!lock ? 'L' : 'Unl') + 'ock');
        $('#lock_action').text((!lock ? 'un' : '') + 'lock');
        $('#name_lock').text(name);
        <?= $modal_lock_user_group->show(); ?>
    }

    function openDeleteModal(group_id, name) {
        $('#id_delete').val(group_id);
        $('#name_delete').text(name);
        <?= $modal_delete_user_group->show(); ?>
    }

</script>