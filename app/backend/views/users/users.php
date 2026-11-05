<?php
/**
 * @var $page_title string
 * @var $users_list array
 * @var $owners array
 * @var $access_scopes array
 * @var $authUser User
 * @var $warehouses_arr array
 */

use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\backend\models\Users;

?>
<div class="container-fluid p-sm-4 p-2">
        <!-- Page Heading -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= View::toHtml($page_title); ?>s</h1>
        </div>
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <div class="row">
            <div class="col">
                <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-users"></i>
                        <?= View::toHtml($page_title); ?>s list (<?= count($users_list) ?> item<?= count($users_list) > 1 ? 's' : '' ?>)
                    </span>
                </h6>
            </div>
            <?php if ($authUser->hasUsersAccess()) { ?>
                <div class="col-auto">
                    <a class="btn btn-success btn-icon-split" href="/users/<?= $page_title === 'Manager' ? 'save_staff' : 'save_page' ?>/?user=<?= urlencode(strtolower($page_title)); ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-user-plus"></i>
                        </span>
                        <span class="text">Create new <?= View::toHtml(strtolower($page_title)); ?></span>
                    </a>
                </div>
            <?php } ?>
            <div class="clearfix"></div>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($users_list)) { ?>
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>Login</th>
                    <?php if ($authUser->hasAdminsAccess()) { ?>
                        <th>Account group</th>
                    <?php } ?>
                    <th>Access level</th>
                    <th>
                        <?= $page_title == 'Admin' ? 'Clients warehouses' : 'Warehouse'; ?>
                    </th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users_list as $user_data) { ?>
                    <tr>
                        <td><?= View::toHtml($user_data['login']) ?></td>
                        <?php if ($authUser->hasAdminsAccess()) { ?>
                            <td><?= $owners[$user_data['parent_id']] ?? ''; ?></td>
                        <?php } ?>
                        <td>
                            <?php
                            $access_levels = json_decode($user_data['access_scope'], 1);
                            if(empty($access_levels)) {
                                echo "<span class='badge badge-info'>None</span>";
                            } else {
                                foreach ($access_levels as $access_level) {
                                    echo "<span class='badge badge-primary'>" .
                                        View::toHtml($access_scopes[$access_level]) . "</span> ";
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <?php if(in_array($page_title, ['Admin', 'Manager'])) {
                                if (!empty($user_data['warehouses']))
                                    echo implode('<br/>', $user_data['warehouses']);
                            } else
                                echo View::toHtml($warehouses_arr[$user_data['warehouse_id']]);
                            ?>
                        </td>
                        <td>
                            <?php $status_label = $user_data['status'] == User::USER_STATUS_ACTIVE ? "success" : "danger"; ?>
                            <span class="badge badge-<?= $status_label?>">
                                <?= View::toHtml(Users::getStatusText($user_data['status']))?>
                            </span>
                        </td>
                        <td>
                            <a class="btn btn-primary btn-icon-split" href="/users/save_page/?user=<?= urlencode(strtolower($page_title)) . '&id=' . urlencode($user_data['id']) ?>">
                                <span class="icon text-white-50">
                                    <i class="fas fa-fw fa-user-edit"></i>
                                </span>
                                <span class="text">Edit</span>
                            </a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php } else {
            echo Html::warning('No users found');
        } ?>
    </div>
</div>
</div>