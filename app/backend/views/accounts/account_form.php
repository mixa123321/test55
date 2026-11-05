<?php

/**
 * @var $authUser User
 * @var $user User
 * @var $role Role
 * @var $access_scopes array
 * @var $userPerson Person
 * @var $persons array
 * @var $parents array
 * @var $user_warehouses array
 * @var $warehouse_list array
 * @var $user_groups array
 * @var $user_groups_exist array
 * @var $supplier Supplier|null
 * @var $back string
 */

use app\core\entities\Person;
use app\core\entities\Role;
use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\entities\UserGroup;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\HTTP;

?>
<div id="response"></div>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= !empty($user->getId()) ? 'Edit' : 'Create new' ?> <?= View::toHtml($role->getName()) ?></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col" id="action_name"></div>
            </div>
        </div>
        <div class="card-body">
            <?php
                $addPerson = empty($user->getId()) && $role->getPerson() && (empty($userPerson) || empty($userPerson->getId()));
                if ($addPerson) { ?>
                    <div class="personBlock">
                        <div class="row justify-content-center">
                            <div class="col-md-10">
                                <div class="form-row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <select class="form-control form-control-lg custom-select" id="selectedPerson" onchange="changePersonOption(event.target);">
                                                    <option value="0" selected>New person</option>
                                                    <?php foreach ($persons as $person) { /** @var $person Person */ ?>
                                                        <option value="<?= $person->getId() ?>"><?= View::toHtml($person->getName()) ?> (<?= View::toHtml($person->getEmail()) ?>)</option>
                                                    <?php } ?>
                                                </select>
                                                <label for="user_id">Person <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-9" id="addPersonForm">
                                        <form id="personForm">
                                            <div class="form-row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <div class="form-group-element">
                                                            <input type="text"
                                                                   id="person_name"
                                                                   name="name"
                                                                   class="form-control form-control-lg"
                                                                   placeholder=" "
                                                                   value="<?= empty($user->getId()) && $userPerson instanceof Person ? View::toHtml($userPerson->getName()) : '' ?>">
                                                            <label for="person_name">Name <span class="text-danger">*</span></label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <div class="form-group-element">
                                                            <input type="email"
                                                                   id="person_email"
                                                                   name="email"
                                                                   class="form-control form-control-lg"
                                                                   placeholder=" "
                                                                   value="<?= empty($user->getId()) && $userPerson instanceof Person ? View::toHtml($userPerson->getEmail()) : '' ?>">
                                                            <label for="email">Email <span class="text-danger">*</span></label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <div class="form-group-element">
                                                            <input type="text"
                                                                   name="phone"
                                                                   id="person_phone"
                                                                   class="form-control form-control-lg"
                                                                   placeholder=" "
                                                                   value="<?= empty($user->getId()) && $userPerson instanceof Person ? View::toHtml($userPerson->getPhone()) : '' ?>">
                                                            <label for="person_phone">Phone</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row justify-content-end"></div>
                    </div>
            <?php } ?>
            <form id="mainForm">
                <?php if (!empty($user->getId())) { ?>
                    <input type="hidden" name="user[id]" value="<?= $user->getId() ?>">
                <?php } else { ?>
                    <input type="hidden" name="role_id" value="<?= $role->getId() ?>">
                <?php } ?>
                <?php if (empty($user->getId()) && $role->isSupplierUser()) { ?>
                    <input type="hidden" name="supplier_id" value="<?= $supplier->getId() ?>">
                <?php } ?>
                <div class="row justify-content-center <?= empty($user->getId()) && $role->getPerson() && (empty($userPerson) || empty($userPerson->getId())) ? 'd-none' : '' ?>" id="mainBlock">
                    <div class="col-md-11">
                        <div class="row">
                            <div class="col-md-3">
                                <?php if ((empty($user->getId()) && $role->getPerson()) || (!empty($user->getId()) && !empty($userPerson))) { ?>
                                    <div class="form-group border p-3">
                                        <div class="row">
                                            <div class="col">Person</div>
                                            <div class="col-auto">
                                                <input type="hidden" name="person_id" id="person_id" value="<?= $userPerson instanceof Person ? $userPerson->getId() : '' ?>">
                                                <button type="button" class="btn btn-success btn-icon-split" id="editPerson">
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-fw fa-edit"></i>
                                                    </span>
                                                    <span class="text text-white flex-fill">Edit</span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col">
                                                <p class="form-control-static text-success"><b>
                                                    <span id="person_name"><?= $userPerson instanceof Person ? View::toHtml($userPerson->getName()) : '' ?></span><br/>
                                                    <span id="person_email"><?= $userPerson instanceof Person ? View::toHtml($userPerson->getEmail()) : '' ?></span><br/>
                                                    <span id="person_phone"><?= $userPerson instanceof Person ? View::toHtml($userPerson->getPhone()) : '' ?></span></b>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                                <div class="form-group" id="parent_block">
                                    <div class="form-group-element">
                                        <?php if (count($parents) > 1) { ?>
                                            <select class="form-control form-control-lg custom-select" id="parent_id" name="user[parent_id]" <?= count($parents) > 1 ? '' : 'disabled' ?>>
                                                <?php foreach ($parents as $parent) /** @var $parent User */ { ?>
                                                    <option value="<?= $parent->getId() ?>"><?= View::toHtml($parent->getLogin()) ?></option>
                                                <?php } ?>
                                            </select>
                                        <?php } elseif (count($parents) === 1) {
                                            /** @var $parent User */
                                            $parent = $parents[0] ?>
                                            <input type="hidden" name="user[parent_id]" value="<?= $parent->getId() ?>">
                                            <p class="form-control form-control-lg form-control-static">
                                                <b><?= View::toHtml($parent->getLogin()) ?></b>
                                            </p>
                                        <?php } ?>
                                        <label for="parent_id">Parent</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <?php if ($authUser->getId() !== $user->getId()) { ?>
                                            <input name="user[login]" id="login" type="text" class="form-control form-control-lg" autocomplete="username" value="<?= View::toHtml($user->getLogin()) ?>">
                                            <label for="login">Login <span style="color:red">*</span></label>
                                        <?php } else { ?>
                                            <p class="form-control form-control-lg form-control-static">
                                                <b><?= View::toHtml($user->getLogin()) ?></b>
                                            </p>
                                            <label for="">Login</label>
                                            <input type="hidden" name="user[login]" value="<?= View::toHtml($user->getLogin()) ?>">
                                        <?php }?>
                                    </div>
                                </div>
                                <?php if (!empty($user->getId()) && $authUser->getId() !== $user->getId()) { ?>
                                    <div class="form-group">
                                        <div class="form-group-element">
                                            <select id="status" class="form-control form-control-lg custom-select" name="user[status]">
                                                <?php foreach (User::$STATUSES as $STATUS => $status_name) { ?>
                                                    <option value="<?= $STATUS ?>" <?= $user->getStatus() == $STATUS ? 'selected' : ''?>><?= $status_name ?></option>
                                                <?php } ?>
                                            </select>
                                            <label for="status">Status</label>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input type="password" name="user[pass]" autocomplete="new-password" id="password" class="form-control form-control-lg">
                                        <label for="password">Password
                                            <?php if (empty($user->getId())) { ?>
                                                <span style="color:red">*</span>
                                            <?php } ?>
                                        </label>
                                    </div>
                                </div>
                                <?php if (!empty($access_scopes) && !$role->hasUserGroups()) { ?>
                                    <div class="form-group">
                                        <label for="access_scopes">Access scope</label><br/>
                                        <?php foreach ($access_scopes as $scope) { ?>
                                            <span class='badge badge-primary'><?= View::toHtml($scope) ?></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                                <?php if ($role->hasUserGroups() && !empty($user_groups)) { ?>
                                    <div class="form-group">
                                        <label for="access_scopes">User groups</label><?php if ($role->isWorker()) { ?><span style="color:red">*</span><?php } ?><br/>
                                        <?php foreach ($user_groups as $user_group) {
                                            /** @var $user_group UserGroup */ ?>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="user_groups_<?= $user_group->getId() ?>" name="user_groups[<?= $user_group->getId() ?>]" <?= $user->getId() && (!isset($user_groups_exist) || in_array($user_group->getId(), array_keys($user_groups_exist)))  ? 'checked' : '' ?>>
                                                <label class='form-check-label' for='user_groups_<?= $user_group->getId() ?>'><?= View::toHtml($user_group->getName()) ?></label>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-3">
                                <?php if ($role->hasApiKey()) { ?>
                                    <div class="form-group">
                                        <div class="form-group-element">
                                            <input name="user[api_key]" id="api_key" type="password" autocomplete="new-password" class="form-control form-control-lg<?= $user->hasApiKey() ? ' filled" placeholder="Set - leave empty to keep"' : '" placeholder=" "' ?>>
                                            <label for="api_key">Api key:</label>
                                        </div>
                                    </div>
                                <?php }
                                if ($role->isMultipleWarehouses() && !$role->isSupplierAdmin()) { ?>
                                    <label>Warehouses <span style="color:red">*</span></label><br>
                                    <?php foreach ($warehouse_list as $w_id => $w_name) { ?>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="user_warehouses_<?= $w_id ?>" name="user_warehouses[<?= $w_id ?>]" <?= in_array($w_id, array_keys($user_warehouses)) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="user_warehouses_<?= $w_id ?>"><?= View::toHtml($w_name) ?></label>
                                        </div>
                                    <?php } ?>
                                <?php } elseif ($role->hasSelectedWarehouse() && !empty($user->getId()) && $authUser->getId() !== $user->getId() && !empty($warehouse_list)) { ?>
                                    <div class="form-group-element">
                                        <?= Html::dropDownList('warehouse_id', $warehouse_list, (string)$user->getWarehouseId(), ['id' => 'warehouse_id']); ?>
                                        <label for="warehouse_id">Warehouses</label>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
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
                    <?php if (!empty($addPerson)) { ?>
                        <div class="col-auto personBlock">
                            <button type="button" class="btn btn-primary btn-icon-split" id="newPerson">
                                <span class="text text-white">New</span>
                                <span class="icon text-white" id="iconPerson"><i class="fas fa-fw fa-plus"></i></span>
                            </button>
                            <button type="button" class="btn btn-primary btn-icon-split d-none" id="selPerson">
                                <span class="text text-white">Select</span>
                                <span class="icon text-white" id="iconPerson"><i class="fas fa-fw fa-check"></i></span>
                            </button>
                        </div>
                    <?php } ?>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split <?= $role->getPerson() && (empty($userPerson) || empty($userPerson->getId())) ? 'd-none' : '' ?>"
                                type="submit"
                                id="submitAccountForm">
                            <span class="text text-white">Done</span>
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="editPersonModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="titleModal">Edit person</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div id="edit_person_group" class="col-12 justify-content-center">
                        <div id="person_response"></div>
                        <br>
                        <div class="form-group">
                            <div class="form-group-element">
                                <input id="edit_person_name" type="text" class="form-control form-control-lg" value="">
                                <label for="edit_person_name">Name: <span style="color:red">*</span></label>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-group-element">
                                <input id="edit_person_email" type="text" class="form-control form-control-lg" value="">
                                <label for="edit_person_email">Email: <span style="color:red">*</span></label>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-group-element">
                                <input id="edit_person_phone" type="text" class="form-control form-control-lg" value="">
                                <label for="edit_person_phone">Phone: <span style="color:red">*</span></label>
                            </div>
                        </div>
                    </div>
                    <div class="col-2"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal" id="cancelPerson" type="button">Cancel</button>
                <button class="btn btn-success" id="storePerson" type="button">Store</button>
            </div>
        </div>
    </div>
</div>

<script>
    const $action_name = $('#action_name');
    $(document).ready(function() {
        <?php if (empty($user->getId()) && $role->getPerson()) { ?>
        $action_name.html(`<h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-fw fa-user-check"></i> Select person
                    </h6>`);
        <?php } else { ?>
        $action_name.html(getAction())
        <?php }  ?>
    });

    const $selPerson = $('#selPerson');
    const responseBlock = $('#response');

    function clearResponse() {
        if (responseBlock.prev().hasClass('alert alert-danger'))
            responseBlock.prev().remove();
        responseBlock.html('');
    }

    function changePersonOption(t) {
        let person_id = $(t).val();
        let add_person = person_id === '0';
        $('#addPersonForm, #newPerson').toggleClass('d-none', !add_person);
        $selPerson.toggleClass('d-none', add_person);
    }

    function clearErrors() {
        $('#main-flash-errors').remove();
    }

    $('#selectedPerson').change(function () {
        clearErrors();
    });

    $('#newPerson').click(function () {
        clearErrors();
        $.ajax({
            type: "POST",
            data: $('#personForm').serialize(),
            url: "/persons",
            dataType: "json",
            success: function(res)
            {
                if (res.result === undefined || res.result !== 'success' || res.person === undefined)
                    location.reload();
                else
                    setPerson(+res.person.id, res.person)
            }
        });
    })

    $selPerson.click(function () {
        clearErrors();
        setPerson(+$('#selectedPerson').val());
    });

    function getPerson(person_id) {
        let person = {name: '', email: '', phone: ''};
        $.ajax({
            type: "GET",
            data: { person_id },
            url: "/persons",
            dataType: "json",
            async: false,
            success: function(res)
            {
                if (res.result === undefined || res.result !== 'success' || res.person === undefined)
                    location.reload();
                person = res.person;
            }
        });
        return person;
    }

    function setPerson(person_id, person = null) {
        clearResponse();

        if (person === null)
             person = getPerson(person_id);

        $('#person_id').val(person_id);
        $('.personBlock').remove();

        $('#person_name').text(person.name);
        $('#person_email').text(person.email);
        $('#person_phone').text(person.phone);

        $action_name.html(getAction());
        $('#mainBlock, #submitAccountForm').removeClass('d-none');
    }

    const Person = new Proxy(
        {
            id: null,
            name: null,
            email: null,
            phone: null,
            owner: false,
        },
        {
            set: function(target, prop, value) {
                target[prop] = value;
                $(`input#edit_person_${prop}`).val(value);
                return true;
            }
        }
    );

    const storePersonBtn = $('#storePerson');
    const responsePersonBlock = $('#person_response');

    $('#editPerson').on('click', function() {
        responsePersonBlock.html(null);
        const person_id = +$('#person_id').val();
        if (person_id > 0) {
            let personObj = getPerson(person_id);
            if (!personObj)
                return;
            Person.id = person_id;
            Person.name = personObj.name;
            Person.email = personObj.email;
            Person.phone = personObj.phone;

            let owner = <?= $authUser->getId() === $user->getId() ? 'true' : 'false' ?>;

            if (owner)
                $('#edit_person_name').parents('.form-group').remove()
            Person.owner = owner;

            $('#editPersonModal').modal('show');
        }
    });

    $('#edit_person_group').on('change', function(e) {
        const field = e.target.id.replace('edit_person_', '');
        if (Person[field] !== undefined)
            Person[field] = e.target.value;
    });

    storePersonBtn.on('click',function() {
        responsePersonBlock.html(null);
        $.ajax({
            url: "/persons",
            method: "PUT",
            data: Person,
            dataType: "json",
            async: false,
            success :function (res) {
                if (res.result !== undefined) {
                    if (res.result === 'error' && res.errors.length > 0) {
                        responsePersonBlock.html(res.errors.reduce((temp, error) => {
                            temp += `<li class="alert-danger p-2">${html_escape(error)}</li>`;
                            return temp;
                        }, ''));
                    } else if (res.result === 'success' && res.person !==undefined){
                        setPerson(res.person.id, res.person);
                        $('#cancelPerson').trigger('click');
                    } else {
                        responsePersonBlock.html('<li class="alert-danger p-2">Server error</li>')
                    }
                }
            }
        });
    })

    function getAction() {
        return `<h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-fw fa-user"></i>
                                                ${html_escape(<?= View::toJs(!empty($user->getId()) ? $user->getLogin() : ('New ' . strtolower($role->getName()))) ?>)}
                                            </h6>`;
    }

    $('#mainForm').submit(function(event) {
        event.preventDefault();
        clearResponse();
        $.ajax({
            type: "POST",
            data: $(this).serialize(),
            url: "/accounts/store_account",
            dataType: "json",
            success: function(res)
            {
                if (res.result === undefined)
                    location.reload();
                if (res.result === 'success') {
                    location.href = <?= View::toJs(HTTP::safeLinkUrl($back)) ?>;
                } else if (res.errors !== undefined && res.errors.length > 0) {
                    $('#response').html(`<div class="alert alert-danger" style="position: relative">
                          ${res.errors.reduce((temp, e, i, errors) => {
                        temp += e + (errors.length - 1 !== i ? '<hr class="my-1">' : '');
                        return temp;
                    }, '')}</div>`)
                }
             }
        });
    })

    $("#person_phone").keyup(function () {
        $(this).val(this.value.replace(/[^+0-9]/g,''));
    });

    $("#edit_person_phone").keyup(function () {
        $(this).val(this.value.replace(/[^+0-9]/g,''));
    });

</script>