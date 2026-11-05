<?php
/**
 * @var $authUser User
 * @var $role Role
 * @var $page_title string
 * @var $supplier Supplier|null
 * @var $users_list array
 * @var $persons array
 * @var $parents array
 * @var $admin User
 * @var $existManager bool
 * @var $worker_data array
 * @var $user_groups array
 * @var $access_scopes array
 * @var $create bool
 * @var $user_warehouses array
 * @var $warehouses User
 * @var $warehouse_id int
 * @var $back string
 * @var $total int
 */

use app\backend\models\Roles;
use app\backend\models\Users;
use app\core\entities\Person;
use app\core\entities\Role;
use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\helpers\Html;

if (!isset($is_pagination))
    $is_pagination = true;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= View::toHtml($page_title) ?></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-users"></i>
                        <?= View::toHtml($role->getName()); ?>s list (<?= $total ?>
                        item<?= $total > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
                <div class="col-auto">
                        <span class="d-inline-block btn-span-tooltip" tabindex="0">
                            <button class="btn btn-success btn-icon-split"
                                    type="button"
                                    <?php if ($create) { ?>
                                        onclick="location.href='<?= Roles::getLinkCreate($role, !empty($supplier) ? $supplier->getId() : null) ?>'"
                                    <?php } else { ?>
                                        disabled
                                    <?php } ?>
                            >
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-user-plus"></i>
                            </span>
                                <span class="text">Create new <?= View::toHtml(strtolower($role->getName())); ?></span>
                            </button>
                        </span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if ($role->isSupplierUser() && (empty($admin) || $admin->isBlocked()))
                echo Html::warning('Admin ' . $admin->getLogin() . ' is blocked');
            if ($role->isSupplierUser() && !in_array($warehouse_id, $supplier->getWarehouses() ?? []))
                echo Html::warning('Warehouse not accessed');
            if ($role->isWorker() && !$existManager)
                echo Html::warning('Not found active managers');
            if (!empty($users_list)) { ?>
                <?php if ($is_pagination) { ?>
                    <div class="row col-md-12 mt-3 justify-content-center">
                        <?php View::pagination("/users/" . strtolower($page_title) . "?" . $current_filters . '&', 1
                            , $total_pages, $current_page, Users::LIMIT_PER_PAGE)?>
                    </div>
                <?php } ?>
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="20%"/>
                        <?php if ($authUser->isRoleSA()) { ?>
                            <col width="12%"/>
                        <?php } ?>
                        <col width="*"/>
                        <?php if (($role->isMultipleWarehouses() && !$role->isSupplierAdmin()) ||
                                  $role->hasSelectedWarehouse() || $role->isSupplierAdmin()) { ?>
                            <col width="12"/>
                        <?php } ?>
                        <?php if ($authUser->hasManagerAccess() && $role->isWorker()) { ?>
                            <col width="8%"/>
                        <?php } ?>
                        <col width="8%"/>
                        <col width="8%"/>
                    </colgroup>
                    <thead>
                    <tr>
                        <th>Login</th>
                        <?php if ($authUser->isRoleSA()) { ?>
                            <th>Parent</th>
                        <?php } ?>
                        <th>Access level</th>
                        <?php if ($role->isMultipleWarehouses() && !$role->isSupplierAdmin()) { ?>
                            <th>Warehouses</th>
                        <?php } elseif ($role->hasSelectedWarehouse()) { ?>
                            <th>Warehouse</th>
                        <?php } elseif ($role->isSupplierAdmin()) { ?>
                            <th>Clients warehouses</th>
                        <?php } ?>
                        <?php if ($authUser->hasManagerAccess() && $role->isWorker()) { ?>
                            <th>Trolley</th>
                        <?php } ?>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users_list as $key => $user) { /** @var $user User */ ?>
                        <tr>
                            <td><?= View::toHtml($user->getLogin()) ?>
                                <?php if ($role->hasPerson() && !empty($persons[$user->getId()]) && $persons[$user->getId()] instanceof Person) { ?>
                                    <br/>
                                    <p class='text text-xs text-success my-0 py-0'>
                                        <?= View::toHtml($persons[$user->getId()]->getName()) ?>
                                    </p>
                                    <p class='text text-xs text-success my-0 py-0'>
                                        <?= View::toHtml($persons[$user->getId()]->getEmail()) ?>
                                    </p>
                                    <p class='text text-xs text-success my-0 py-0'>
                                        <?= View::toHtml($persons[$user->getId()]->getPhone()) ?>
                                    </p>
                                <?php } ?>
                            </td>
                            <?php if ($authUser->isRoleSA()) { ?>
                                <td>
                                    <?php if (!empty($parents[$user->getParentId()]) && $parents[$user->getParentId()] instanceof User) { ?>
                                    <span class="<?= $parents[$user->getParentId()]->isBlocked() ? 'alert-danger px-2' : '' ?>">
                                        <?= View::toHtml($parents[$user->getParentId()]->getLogin()); ?>
                                    </span>
                                    <?php } ?>
                                </td>
                            <?php } ?>
                            <td>
                                <?php if (!$role->hasUserGroups()) {
                                    foreach ($access_scopes as $access_level) { ?>
                                    <span class='badge badge-primary'><?= View::toHtml($access_level) ?></span>
                                <?php }} else {
                                    if (!empty($user_groups[$user->getId()])) {
                                        foreach ($user_groups[$user->getId()] as $user_group) { ?>
                                            <span class='badge badge-info'><?= View::toHtml($user_group) ?></span>
                                <?php }}} ?>
                            </td>
                            <?php if ($role->isMultipleWarehouses() || $role->isSupplierAdmin()) { ?>
                                <td>
                                    <?php if (!empty($user_warehouses[$user->getId()]))
                                        echo implode('<br/>', $user_warehouses[$user->getId()]); ?>
                                </td>
                            <?php } elseif ($role->hasSelectedWarehouse()) { ?>
                                <td>
                                    <?= !empty($user_warehouses[$user->getId()]) ? $user_warehouses[$user->getId()] : '' ?>
                                </td>
                            <?php } ?>
                            <?php if ($authUser->hasManagerAccess() && $role->isWorker()) { ?>
                                <td>
                                    <?php if (!empty($user->getCartId())) { ?>
                                        <span class='text-success'><b>
                                                <?= $worker_data[$user->getId()]['location'] ?? '' ?>
                                                </b></span>
                                        <?php if (!empty($worker_data[$user->getId()]['weight'])) { ?>
                                            <br/>
                                            <?= $worker_data[$user->getId()]['weight']?>
                                        <?php } ?>
                                    <?php } ?>
                                </td>
                            <?php } ?>
                            <td>
                                <?php $status_label = $user->isActive() ? "success" : "danger"; ?>
                                <span class="badge badge-<?= $status_label ?>">
                                <?= View::toHtml(Users::getStatusText($user->getStatus())) ?>
                            </span>
                            </td>
                            <td>
                                <a class="btn btn-primary btn-icon-split btn-block d-flex justify-content-start text-nowrap mt-0 mb-1"
                                   href="<?= Roles::getLinkEdit($user->getId()) ?>">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-user-edit"></i>
                                    </span>
                                    <span class="text flex-fill">Edit</span>
                                </a>
                                <?php if ($authUser->hasManagerAccess() && $role->isWorker()) { ?>

                                    <a class="btn btn-success btn-icon-split btn-block d-flex justify-content-start text-nowrap mt-0 mb-1"
                                       href="/cart/set_cart/?id=<?= urlencode($user->getId()); ?>">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-shopping-cart"></i>
                                    </span>
                                        <span class="text flex-fill">Trolley</span>
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
                <?php if ($is_pagination) { ?>
                    <div class="row col-md-12 mt-3 justify-content-center">
                        <?php View::pagination("/users/" . strtolower($page_title) . "?" . $current_filters . '&', 1
                            , $total_pages, $current_page, Users::LIMIT_PER_PAGE)?>
                    </div>
                <?php } ?>
            <?php } else {
                echo Html::warning('No records found');
            } ?>
        </div>
        <?php
        if ($role->isSupplierUser() && !empty($back)) { ?>
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
        <?php } ?>
    </div>
</div>