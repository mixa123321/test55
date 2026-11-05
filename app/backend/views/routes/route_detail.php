<?php
/**
 * @var $authUser User
 * @var $route Route
 * @var $destroy_access bool
 * @var $parent_route Route|null
 * @var $route_children array
 * @var $roles array
 * @var $user_groups array
 * @var $users array
 * @var $current_accesses array
 * @var $back string
 */

use app\backend\models\Roles;
use app\core\entities\Route;
use app\core\entities\User;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\helpers\modal\ConfirmModal;
use app\core\interfaces\AccessObjectEntity as AccessObjectEntity;
use app\core\models\perms\Accesses;

$route_link = function (Route $route) {
    return '<a href="/route_accesses/route_detail?route_id=' . $route->getId(). '">' . View::toHtml($route->getName()) . (!empty($route->getAlias())  ? (' (' . View::toHtml($route->getAlias()) . ')') : '') . '</a>';
};

$renderAccessDropdown = function ($object_id, $object_access, $type) use ($route) {
    $select_html = '<div class="form-group-element dropdownAllAccesses" 
                                    data-type="' . $type . '" 
                                    data-object_id="' . $object_id . '"
                                    data-color="' . (Accesses::COLOR_LIST[$object_access] ?? '') . '"
                                    >
                                <select class="form-control form-control-sm custom-select dropdownAllAccesses form-control-' . (Accesses::COLOR_LIST[$object_access] ?? '') . '" ' . (!$route->isEditable() ? 'disabled' : '') . '>';
    foreach (Accesses::LIST as $access_item => $access_name) {
        if ($type !== 'Roles' && $access_item === Accesses::PERM_USER_SELECT_FOR_ROLE)
            continue;
        $select_html .= '<option value="'. $access_item . '" ' . ($object_access == $access_item ? 'selected disabled' : '') . '>' . $access_name . '</option>';
    }
    $select_html .= '</select>
                            </div>';

    return $select_html;
};
$tableAccesses = function ($type, $objects) use ($current_accesses, $renderAccessDropdown, $route) {
    $access_is_user = $type === 'Users' && (empty($objects) || array_values($objects)[0] instanceof User);
    $table_html = '<table id="table_access_' . $type . '" class="table table-hover mt-3 mb-0">
                            <colgroup>';

    if ($access_is_user) {
        $table_html .= '<col width="*">
                        <col width="*">';
    } else
        $table_html .= '<col width="65%">';
    $table_html .= '<col width="35%">';
    if ($access_is_user)
        $table_html .= '<col width="5%">';
    $table_html .= '</colgroup>
                            <thead>
                                <tr>
                                    <th>Name</th>';
    if ($access_is_user)
        $table_html .= '<th>Role</th>';
    $table_html .= '
                                    <th>Access</th>';
    if ($access_is_user && $route->isEditable())
        $table_html .= '<th>Drop</th>';

    $table_html .= '
                                </tr>
                            </thead>
                            <tbody>';
    /** @var AccessObjectEntity $object */
    foreach ($objects as $object) {
        $object_access = Accesses::handleAccess($current_accesses[$type][$object->getId()] ?? Accesses::PERM_NONE);
        $table_html .= '<tr id="' . ($access_is_user ? ('access_user_' . $object->getId())  : '') . '" class="access_user_row '  . ($object->isBlocked() ? 'table-danger' : '') . '">
                                    <td><span class="font-italic access_user_login">' . View::toHtml($object->getName()) . '</span></td>';
        if ($access_is_user)
            $table_html .= '<td>' . View::toHtml(Roles::getNameById($object->getRoleId())) . '</td>';
        $table_html .= '<td>' . $renderAccessDropdown($object->getId(), $object_access, $type) . '</td>';
        if ($access_is_user && $route->isEditable()) {
            $table_html .= '<td><button class="btn btn-danger btn-icon-split btn-user-clear-access" type="button">
                                                    <span class="icon text-white">
                                                        <i class="fas fa-fw fa-trash"></i>
                                                    </span>
                                                </button></td>';
        }
        $table_html .= '</tr>';
    }
    $table_html .= '</tbody></table>';
    return $table_html;

}

?>
<style>
    .ui-autocomplete {
        max-height: 200px;
        overflow-y: scroll;
        /* prevent horizontal scrollbar */
        overflow-x: hidden;
        /* add padding to account for vertical scrollbar */
        padding-right: 20px;
    }
</style>
<div class="alert alert-info d-none" style="possition: relative" id="response_msg"></div>
<div class="alert alert-danger d-none" style="possition: relative" id="err_msg"></div>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= View::toHtml($route->getPath()) ?></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-road"></i>
                        </span>
                        <?= !empty($route->getAlias()) ? View::toHtml($route->getAlias()) : 'Route detail' ?>
                        <?php if (!$route->isActive()) { ?>
                                <span class="badge badge-danger mx-1">Locked</span>
                            <?php } else { ?>
                                <span class="badge badge-success mx-1">Active</span>
                            <?php } ?>
                    </h6>

                </div>
                <?php if (!empty($parent_route)) { ?>
                    <div class="col-auto">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Parent route:
                            <?= $route_link($parent_route) ?>
                        </h6>
                    </div>
                <?php } ?> 

            </div>
        </div>
        <div class="card-body">
            <div class="row mb-2">
                <div class="col-3 p-3 border mx-2">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Roles
                    </h6>
                    <?= $tableAccesses('Roles', $roles) ?>
                </div>
                <div class="col-3 p-3 border mx-2">
                    <h6 class="m-0 font-weight-bold text-primary">
                        User groups
                    </h6>
                    <?= $tableAccesses('UserGroups', $user_groups) ?>
                </div>
                <div class="col-3 p-3 border mx-2">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Users
                    </h6>
                    <?php if ($route->isEditable()) { ?>
                    <div class="form-group mb-0 ui-widget">
                        <div class="form-group-element">
                            <input type="text" id="search_user_field" class="form-control form-control-lg mt-3" placeholder=" " value="">
                            <label for="search_user_field">Add</label>
                        </div>
                    </div>
                    <?php } ?>
                    <?= $tableAccesses('Users', $users) ?>
                </div>
                <div class="col-2 p-3 border mr-2 ml-auto h-100 bg-light">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Addition
                        </h6>
                        <div class="m-2">
                            <label>Editable <?php if ($route->isEditable()) { ?>
                                    <span>
                            <i class="fas fa-fw fa-check-circle"></i>
                        </span>
                                <?php } ?></label>
                            <br>
                            <label>Virtual access <?php if ($route->isVirtualAccess()) { ?>
                                    <span>
                                    <i class="fas fa-fw fa-check-circle"></i>
                                </span>
                                <?php } ?></label>
                        </div>
                        <?php if ($authUser->hasDevAccess()) { ?>
                        <div class="row mt-3">
                            <div class="col">
                                <button class="btn btn-primary btn-sm float-right" onclick="changeModal()">
                                    <i class="fas fa-fw fa-edit mr-2"></i>
                                    <span class="ml-1">Change</span>
                                </button>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
            </div>
        <?php if ($authUser->hasDevAccess()) { ?>
            <div class="row">
                <div class="col-3 p-3 border mt-2 mb-0 ml-2">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Route children
                    </h6>
                    <?php
                        $route_child_template = function ($id, $link) {
                            return '<tr class="child_routes" id="child_route_' . $id . '">
                                        <td class="child_route_name">' . $link . '</td>
                                        <td>
                                            <button class="btn btn-danger btn-icon-split btn-drop-route-child" type="button">
                                                <span class="icon text-white">
                                                    <i class="fas fa-fw fa-trash"></i>
                                                </span>
                                            </button>
                                        </td>
                                    </tr>';
                        };
                    ?>
                    <div class="form-group mb-0 ui-widget">
                        <div class="form-group-element">
                            <input type="text" id="search_route_field" class="form-control form-control-lg mt-3" placeholder=" " value="">
                            <label for="search_route_field">Add</label>
                        </div>
                        <div id="search_route_res"></div>
                    </div>
                    <div class="alert-info px-2 mt-2 w-100 <?= !empty($route_children) ? 'd-none' : ''  ?>"  id="response_child_routes">
                        Child route not found
                    </div>
                    <table class="table table-sm my-2 <?= empty($route_children) ? 'd-none' : ''  ?>" id="table_child_routes">
                        <colgroup>
                            <col width="80%">
                            <col width="20%">
                        </colgroup>
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Drop</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($route_children as $route_child) { /** @var $route_child Route */
                            echo $route_child_template($route_child->getId(), $route_link($route_child));
                        } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-outline-orange pl-3 btn-sm" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                        <i class="fas fa-fw fa-chevron-left mr-2"></i>
                        <span class="ml-1">Back</span>
                    </a>
                </div>
                <?php if ($destroy_access) { ?>
                <div class="col-auto">
                    <button class="btn btn-danger btn-block btn-icon-split justify-content-start text-nowrap" type="button"
                            onclick="destroyModal()">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-trash"></i>
                        </span>
                        <span class="text flex-fill">Destroy</span>
                    </button>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php if ($destroy_access):
    $modal_form_destroy = new ConfirmModal('destroyRoute', 'Destroy route');
    echo $modal_form_destroy->addContent("Would you like to remove Route?<br>This action will also clear route accesses and settings")->addForm('/route_accesses/destroy', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="route_id" value="' . $route->getId() . '">' . $modal_form_destroy->getButtons())
    ->put(); ?>
<script>
    function destroyModal() {
        <?= $modal_form_destroy->show(); ?>
    }
</script>
<?php endif; ?>

<?php
if ($authUser->hasDevAccess()):
$modal_form = new ConfirmModal('changeRoute', 'Route detail');
echo $modal_form->addContent('<div class="form-row">
        <div class="form-group col-12 mb-0">
            <div class="form-group-element">
                <input type="text" name="alias" id="alias" class="form-control form-control-lg" placeholder=" " value="' . $route->getAlias() . '">
                <label for="alias">Alias</label>
            </div>
        </div>' . (empty($parent_route) ? '
        <div class="form-group mx-3 my-2">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="editable" name="editable" ' . ($route->isEditable() ? 'checked' : '') . '>
                <label class="form-check-label" for="editable">Editable</label>
            </div>
        </div>' : '') . '
        <div class="form-group mx-3 my-2">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="virtual_access" name="virtual_access" ' . ($route->isVirtualAccess() ? 'checked' : '') . '>
                <label class="form-check-label" for="virtual_access">Virtual access</label>
            </div>
        </div>
    </div>')->addForm('/route_accesses/change_route', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="route_id" value="' . $route->getId() . '">' . $modal_form->getButtons())
    ->put();

 ?>
<script>
    const modal_setup = {
        alias: <?= View::toJs((string)$route->getAlias()) ?>,
        editable: !!'<?= $route->getEditable() ?>',
        virtual_access: !!'<?= $route->getVirtualAccess() ?>',
    };

    function changeModal() {
        $('#alias').val(modal_setup.alias);
        $('#editable').prop('checked', modal_setup.editable);
        $('#virtual_access').prop('checked', modal_setup.virtual_access);
        <?= $modal_form->show(); ?>
    }
</script>
<?php
$modal_child_route_clear = new ConfirmModal('deleteChildRouteModal', 'Delete child route');
echo $modal_child_route_clear->addContent('<div class="form-group form-inline m-0 p-0">
                                                       <p class="form-control-static text-lg align-self-center pl-1 mb-0">Delete selected child route <b id="delete_child_route_name"></b></p>
                                               </div>')
    ->addCustomButtons('
                <button aria-label="Close" data-dismiss="modal" type="button" class="btn btn-outline-orange">No</button>
                <button class="btn btn-orange" id="delete_child_route_confirm">Yes</button>
            ')->put();
endif;
if ($route->isEditable()):
    $modal_clear = new ConfirmModal('deleteUserAccessModal', 'Delete user access');
    echo $modal_clear->addContent('<div class="form-group form-inline m-0 p-0">
                                                       <p class="form-control-static text-lg align-self-center pl-1 mb-0">Delete selected user access for <b id="delete_user_access_login"></b></p>
                                               </div>')
        ->addCustomButtons('
                <button aria-label="Close" data-dismiss="modal" type="button" class="btn btn-outline-orange">No</button>
                <button class="btn btn-orange" id="delete_user_access_confirm">Yes</button>
            ')->put();
?>
<script type="text/template" id="user_access_dropdown_template">
    <?= $renderAccessDropdown('{{user_id}}', 0, 'Users') ?>
</script>
<?php endif; ?>

<script>



    $(document).ready(function(){

        let accessed_users = <?= View::toJs(array_keys($current_accesses['Users'])) ?>;
        const route_id = +'<?= $route->getId() ?>';


        $.ajaxSetup({
            method: "POST",
            dataType: 'json',
            error: () => alert('Server error')
        });

        const ACCESSES = <?= View::toJs(Accesses::LIST) ?>;
        const ACCESS_COLORS = <?= View::toJs(Accesses::COLOR_LIST) ?>;

        <?php if ($authUser->hasDevAccess()): ?>

        /**
         * Child routes
         */
        let deleted_child_route = {};
        $('.child_routes button.btn-drop-route-child').on('click', deleteChildRoute);

        $('#delete_child_route_confirm').click(() => {
            const child_route_id = +deleted_child_route.id;
            if (child_route_id > 0) {
                $.ajax({
                    url: '/route_accesses/clear_parent_route',
                    data: {route_id, child_route_id},
                    success: function (response) {
                        if (response.result !== undefined && response.result === 'success') {
                            $('#child_route_' + child_route_id).remove();
                            if ($('#table_child_routes').find('tbody').children().length === 0) {
                                $('#table_child_routes').addClass('d-none');
                                $('#response_child_routes').removeClass('d-none');
                            }
                            showError('response_msg', `Child route ${deleted_child_route.name} deleted successfully`, 3000);
                        } else if (response.result !== undefined && response.result === 'error')
                            showError('err_msg', response.message, 3000);
                        else
                            location.reload();
                    },
                });
                <?= $modal_child_route_clear->hide(); ?>
            }
        });

        function deleteChildRoute() {
            const row = $(this).parents('.child_routes');
            deleted_child_route = {
                id: +row.attr('id').replace('child_route_', ''),
                name: row.find('.child_route_name').text()
            };
            $('#delete_child_route_name').text(deleted_child_route.name);
            <?= $modal_child_route_clear->show(); ?>
        }
        function addChildRoute(route) {
            const successRouteAdded = () => {
                $('#response_child_routes').addClass('d-none');
                $('#table_child_routes').removeClass('d-none').find('tbody')
                    .append(`<tr class="child_routes" id='child_route_${+route.id}'>
                                                    <td class="child_route_name"><a href="/route_accesses/route_detail?route_id=${+route.id}">${html_escape(route.label)}</a></td>
                                                    <td>
                                                        <button class="btn btn-danger btn-icon-split btn-drop-route-child" type="button"">
                                                            <span class="icon text-white">
                                                                <i class="fas fa-fw fa-trash"></i>
                                                            </span>
                                                        </button>
                                                    </td>
                                                </tr>`);
                $(`#child_route_${route.id} button.btn-drop-route-child`).on('click', deleteChildRoute);
            }
            $.ajax({
                url: '/route_accesses/set_parent_route',
                data: {route_id, child_route_id: route.id},
                success: function (response) {
                    if (response.result !== undefined && response.result === 'success') {
                        successRouteAdded();
                        showError('response_msg', `Route ${route.name} added to child routes`, 3000);
                    } else if (response.result !== undefined && response.result === 'error') {
                        showError('err_msg', response.message, 3000);
                    } else
                        location.reload();
                },
            });
        }
        const searchRouterField = $('#search_route_field');
        searchRouterField.focusout(function() {
            $(this).val('');
        })
        searchRouterField.autocomplete({
            scroll: true,
            appendTo: $('#search_route_res'),
            source: function(request, response){
                $.ajax({
                    url: '/route_accesses/find_child_route',
                    type: 'POST',
                    dataType: 'json',
                    data:{
                        route_id, nameStartsWith: request.term
                    },
                    success: function(data){
                        if(!Object.keys(data).length){
                            response([{label: 'No matches found'}]);
                        } else
                            response($.map(data, function(route, route_id) {
                                return {
                                    id: +route_id,
                                    name: route.name,
                                    label: route.name + (((route.alias !== null) && (route.alias.length > 0))  ? (' (' + route.alias + ')') : '')
                                }
                            }));
                    }
                });
            },
            select: function(event, ui) {
                if (ui.item.id !== undefined)
                    addChildRoute(ui.item)
                $(this).val('');
                return false;
            },
            minLength: 2
        });

        <?php endif; ?>

        <?php if ($route->isEditable()): ?>
        $('#table_access_Roles, #table_access_UserGroups, #table_access_Users').on('change', function(e) {
            const $target = $(event.target);

            if (!$target.hasClass('dropdownAllAccesses'))
                return;

            const $block = $target.parents('.dropdownAllAccesses');

            const type = $block.data('type');
            const object_id = $block.data('object_id');

            const access_id = +$target.val();
            if (ACCESSES[access_id] === undefined) {
                showError('err_msg', 'Access not found', 3000);
                return;
            }

            const successResUpdateAccess = () => {
                let color_new = ACCESS_COLORS[access_id];
                let color_old = $block.data('color');
                if (color_new !== color_old) {
                    $target.addClass('form-control-' + color_new);
                    $target.removeClass('form-control-' + $block.data('color'));
                    $block.data('color', color_new);
                }
                $target.find('option').each(function(index,element){
                    if (+element.value === access_id)
                        $(element).attr('disabled', 'disabled');
                    else
                        $(element).removeAttr('disabled').removeAttr('selected');
                });
            };

            $.ajax({
                url: '/route_accesses/set_route_access',
                data: {type, route_id, object_id, access_id},
                success: function (response) {
                    if (response.result !== undefined && response.result === 'success')
                        successResUpdateAccess(access_id);
                    else if (response.result !== undefined && response.result === 'error') {
                        showError('err_msg', response.message, 3000);
                        return;
                    } else
                        location.reload();

                    if (response.message !== undefined)
                        showError('response_msg', response.message, 3000)
                },
            });
        });

        let deleted_access_for_user = {};
        $('#delete_user_access_confirm').click(() => {
            const user_id = +deleted_access_for_user.id;
            if (user_id > 0) {
                $.ajax({
                    url: '/route_accesses/clear_route_user_access',
                    data: {user_id, route_id},
                    success: function (response) {
                        if (response.result !== undefined && response.result === 'success') {
                            $('#access_user_' + user_id).remove();
                            accessed_users = accessed_users.filter(u_id => user_id !== u_id);
                            showError('response_msg', `User access for ${deleted_access_for_user.login} deleted successfully`, 3000);
                        } else if (response.result !== undefined && response.result === 'error')
                            showError('err_msg', response.message, 3000);
                        else
                            location.reload();
                    },
                });
                <?= $modal_clear->hide(); ?>
            }
        });

        /**
         * Users
         */
        function addUser(user) {
            const successUserAdded = () => {
                let userAccessDropDownTemplate = $('#user_access_dropdown_template').html();
                $('#table_access_Users').find('tbody')
                    .append(`<tr class="access_user_row" id='access_user_${+user.id}'>
                                                    <td><span class="font-italic access_user_login">${html_escape(user.login)}</span></td>
                                                    <td>${html_escape(user.role)}</td>
                                                    <td>${userAccessDropDownTemplate.replace(/\{\{user_id\}\}/g, () => +user.id)}</td>
                                                    <td>
                                                        <button class="btn btn-danger btn-icon-split btn-user-clear-access" type="button"">
                                                            <span class="icon text-white">
                                                                <i class="fas fa-fw fa-trash"></i>
                                                            </span>
                                                        </button>
                                                    </td>
                                                </tr>`);
                $(`#access_user_${user.id} button.btn-user-clear-access`).on('click', deleteUserAccess);
            }
            $.ajax({
                url: '/route_accesses/set_route_access',
                data: {type: 'Users', route_id, object_id: user.id, access_id: 0},
                success: function (response) {
                    if (response.result !== undefined && response.result === 'success') {
                        successUserAdded();
                        showError('response_msg', `User ${user.login} added with deny access`, 3000);
                    } else if (response.result !== undefined && response.result === 'error') {
                        showError('err_msg', response.message, 3000);
                    } else
                        location.reload();
                },
            });
        }
        const searchUserField = $('#search_user_field');
        searchUserField.focusout(function() {
            $(this).val('');
        })
        searchUserField.autocomplete({
            source: function(request, response){
                $.ajax({
                    url: '/accounts/find_account',
                    type: 'POST',
                    dataType: 'json',
                    data:{
                        nameStartsWith: request.term
                    },
                    success: function(data){
                        if(!Object.keys(data).length){
                            response([{label: 'No matches found'}]);
                        } else
                            response($.map(data, function(user, user_id) {
                                if (accessed_users.includes(+user_id))
                                    return null;
                                return {
                                    id: +user_id,
                                    login: user.login,
                                    role: user.role,
                                    label: user.login
                                }
                            }));
                    }
                });
            },
            select: function(event, ui) {
                if (ui.item.id !== undefined) {
                    addUser(ui.item);
                    accessed_users.push(ui.item.id);
                }
                $(this).val('');
                return false;
            },
            minLength: 2
        });
        $('.access_user_row button.btn-user-clear-access').on('click', deleteUserAccess);
        function deleteUserAccess() {
            const row = $(this).parents('.access_user_row ');
            deleted_access_for_user = {
                id: +row.attr('id').replace('access_user_', ''),
                login: row.find('.access_user_login').text()
            };
            $('#delete_user_access_login').text(deleted_access_for_user.login);
            <?= $modal_clear->show(); ?>
        }

        <?php endif; ?>
    });
</script>