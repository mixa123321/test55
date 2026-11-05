<?php
///**
// * @var $authUser User
// * @var $user User
// * @var $parent User
// * @var $access_scopes array
// * @var $user_role string
// * @var $warehouse_id int
// * @var $person Person
// * @var $is_multiple_warehouses bool
// * @var $user_warehouses array
// * @var $warehouse_list array
// */
//
//use app\core\entities\Person;
//use app\core\entities\User;
//use app\core\helpers\View;
//?>
<!--<div class="container-fluid p-sm-4 p-2">-->
<!--    <!-- Page Heading -->-->
<!--    <div class="d-sm-flex align-items-center justify-content-between mb-4">-->
<!--        <h1 class="h3 mb-0 text-gray-800">--><?//= View::toHtml(($user->getLogin() ? "Edit " : "Create ") . $user_role) ?><!--</h1>-->
<!--    </div>-->
<!--    <div class="card shadow mb-4">-->
<!--        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">-->
<!--            <h6 class="m-0 font-weight-bold text-primary">-->
<!--                <i class="fas fa-fw fa-user"></i> --><?//= View::toHtml($user->getLogin() ?: ("New " . $user_role)) ?>
<!--            </h6>-->
<!--        </div>-->
<!--        <form method="post" action="/users/save_process">-->
<!--        <div class="card-body">-->
<!--            <input type="hidden" name="user[id]" value="--><?//= $user->getId(); ?><!--">-->
<!--            <input type="hidden" name="user[role]" value="--><?//= $user_role; ?><!--">-->
<!--            <input type="hidden" name="user[warehouse_id]" value="--><?//= $warehouse_id; ?><!--">-->
<!--            <div class="row justify-content-center" id="main">-->
<!--                <div class="col-md-11">-->
<!--                    <div class="row">-->
<!--                        <div class="col-md-3">-->
<!--                            --><?php //if (in_array($user_role, [User::ROLE_MANAGER, User::ROLE_GOODSIN_MANAGER]) && !empty($person)) { ?>
<!--                            <div class="form-group border p-3">-->
<!--                                <div class="row">-->
<!--                                    <div class="col">Person:</div>-->
<!--                                    <div class="col-auto">-->
<!--                                        <button type="button" class="btn btn-success btn-icon-split" id="editPerson"-->
<!--                                                data-person-id="--><?//= $person->getId(); ?><!--"-->
<!--                                                data-person-name="--><?//= View::toHtml($person->getName()); ?><!--"-->
<!--                                                data-person-email="--><?//= View::toHtml($person->getEmail()); ?><!--"-->
<!--                                                data-person-phone="--><?//= View::toHtml($person->getPhone()); ?><!--"-->
<!--                                                data-owner="0"-->
<!--                                        >-->
<!--                                                                <span class="icon text-white-50">-->
<!--                                                                    <i class="fas fa-fw fa-edit"></i>-->
<!--                                                                </span>-->
<!--                                            <span class="text text-white flex-fill">Edit</span>-->
<!--                                        </button>-->
<!--                                    </div>-->
<!--                                </div>-->
<!--                                <div class="row">-->
<!--                                    <div class="col">-->
<!--                                        <p class="form-control-static text-success">-->
<!--                                            <b>--><?//=
//                                                View::toHtml($person->getName()) . "<br/>" .
//                                                View::toHtml($person->getEmail()) . "<br/>" .
//                                                View::toHtml($person->getPhone()) ?><!--</b>-->
<!--                                        </p>-->
<!--                                    </div>-->
<!--                                </div>-->
<!--                            </div>-->
<!--                            --><?php //} ?>
<!--                            --><?php //if ($authUser->isRoleSA()) { ?>
<!--                            <div class="form-group">-->
<!--                                <div class="form-group-element">-->
<!--                                    --><?php //if (!$user->getId() || empty($parent)) { ?>
<!--                                        <input type="hidden" name="user[parent_id]" value="--><?//= $authUser->getId(); ?><!--">-->
<!--                                        <p class="form-control form-control-lg form-control-static"><b>--><?//= $authUser->getLogin(); ?><!--</b></p>-->
<!--                                    --><?php //} else { ?>
<!--                                        <p class="form-control form-control-lg form-control-static"><b>--><?//= $parent->getLogin(); ?><!--</b></p>-->
<!--                                    --><?php //} ?>
<!--                                    <label>Parent:</label>-->
<!--                                </div>-->
<!--                            </div>-->
<!--                            --><?php //} ?>
<!--                        </div>-->
<!--                        <div class="col-md-3">-->
<!--                            <div class="form-group">-->
<!--                                <div class="form-group-element">-->
<!--                                    <input name="user[login]" id="login" type="text" class="form-control form-control-lg" required-->
<!--                                           value="--><?//= View::toHtml($user->getLogin()); ?><!--">-->
<!--                                    <label for="login">Login: <span class="text-danger">*</span></label>-->
<!--                                </div>-->
<!--                            </div>-->
<!--                            --><?php //if (!empty($user->getId())) { ?>
<!--                                <div class="form-group">-->
<!--                                    <div class="form-group-element">-->
<!--                                        --><?php //if ($authUser->hasAdminsAccess()) { ?>
<!--                                            <select id="status" class="form-control form-control-lg custom-select" name="user[status]">-->
<!--                                                --><?php //foreach (User::$STATUSES as $status => $title) { ?>
<!--                                                    <option value="--><?//= View::toHtml($status) ?><!--"-->
<!--                                                        --><?//= $user->getStatus() === $status ? "selected" : "" ?><!-->-->
<!--                                                        --><?//= View::toHtml($title) ?>
<!--                                                    </option>-->
<!--                                                --><?php //} ?>
<!--                                            </select>-->
<!--                                        --><?php //} else { ?>
<!--                                            <p class="form-control form-control-lg form-control-static">-->
<!--                                                --><?php //$status_label = $user->getStatus() == User::USER_STATUS_ACTIVE ? "success" : "danger"; ?>
<!--                                                <span class="badge badge---><?//= $status_label ?><!--">-->
<!--                                                --><?//= View::toHtml(\app\backend\models\Users::getStatusText($user->getStatus())) ?>
<!--                                            </span>-->
<!--                                            </p>-->
<!--                                        --><?php //} ?>
<!--                                        <label for="status">Status:</label>-->
<!--                                    </div>-->
<!--                                </div>-->
<!--                            --><?php //} else { ?>
<!--                                <input type="hidden" name="user[status]" value="1">-->
<!--                            --><?php //} ?>
<!--                        </div>-->
<!--                        <div class="col-md-3">-->
<!--                            <div class="form-group">-->
<!--                                <div class="form-group-element">-->
<!--                                    <input name="user[pass]" id="password" type="text" class="form-control form-control-lg">-->
<!--                                    <label for="password">Password:-->
<!--                                        --><?php //if (empty($user->getId())) { ?>
<!--                                            <span class="text-danger">*</span>-->
<!--                                        --><?php //} ?>
<!--                                    </label>-->
<!--                                </div>-->
<!--                            </div>-->
<!--                            <div class="form-group">-->
<!--                                <label for="access_scopes">Access scope:</label><br/>-->
<!--                                --><?php //if ($user->getId() !== null)
//                                    foreach ($user->getAccessScope() as $access_level) {
//                                        echo "<span class='badge badge-primary'>" .
//                                            View::toHtml($access_scopes[$access_level]) . "</span> ";
//                                    }
//                                else
//                                    foreach($access_scopes as $access)
//                                        echo "<span class='badge badge-primary'>" .
//                                            View::toHtml($access) . "</span> ";
//                                ?>
<!--                            </div>-->
<!--                        </div>-->
<!--                        <div class="col-md-3">-->
<!--                            <div class="form-group">-->
<!--                                --><?php //if ($user_role === User::ROLE_ADMIN) { ?>
<!--                                    <div class="form-group-element">-->
<!--                                        <input name="user[api_key]" id="api_key" type="text" class="form-control form-control-lg" placeholder=" "-->
<!--                                               value="--><?//= View::toHtml($user->getApiKey()); ?><!--">-->
<!--                                        <label for="api_key">Api key:</label>-->
<!--                                    </div>-->
<!--                                --><?php //} ?>
<!--                                --><?php //if ($is_multiple_warehouses && !in_array($user_role, [User::ROLE_ADMIN, User::ROLE_GOODSIN_MANAGER])) { ?>
<!--                                        <label>Warehouses <span style="color:red">*</span></label><br>-->
<!--                                    --><?php //foreach ($warehouse_list as $w_id => $w_name) { ?>
<!--                                        <div class="form-check">-->
<!--                                            <input type="checkbox" class="form-check-input" id="user_warehouses_--><?//= $w_id ?><!--" name="user_warehouses[--><?//= $w_id ?><!--]" --><?//= in_array($w_id, $user_warehouses) ? 'checked' : '' ?><!-->-->
<!--                                            <label class="form-check-label" for="user_warehouses_--><?//= $w_id ?><!--">--><?//= View::toHtml($w_name) ?><!--</label>-->
<!--                                        </div>-->
<!--                                    --><?php //} ?>
<!--                                --><?php //} ?>
<!--                            </div>-->
<!--                        </div>-->
<!--                    </div>-->
<!--                </div>-->
<!--            </div>-->
<!--        </div>-->
<!--        <div class="card-footer">-->
<!--            <div class="row">-->
<!--                <div class="col">-->
<!--                    <a class="btn btn-secondary btn-icon-split" href="/users/--><?//= $user_role === User::ROLE_GOODSIN_MANAGER ? User::ROLE_MANAGER : $user_role; ?><!--s">-->
<!--                            <span class="icon text-white-50">-->
<!--                                <i class="fas fa-fw fa-chevron-left"></i>-->
<!--                            </span>-->
<!--                        <span class="text">Back</span>-->
<!--                    </a>-->
<!--                </div>-->
<!--                <div class="col-auto">-->
<!--                    <button class="btn btn-primary btn-icon-split" type="submit">-->
<!--                        <span class="text text-white">Done</span>-->
<!--                        <span class="icon text-white">-->
<!--                                <i class="fas fa-fw fa-chevron-right"></i>-->
<!--                            </span>-->
<!--                    </button>-->
<!--                </div>-->
<!--            </div>-->
<!--        </div>-->
<!--        </form>-->
<!--    </div>-->
<!--</div>-->
<!---->
<?php //require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_Person_edit_modal.php'; ?>
<!---->
