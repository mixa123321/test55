<?php

use app\core\entities\LogisticCompany;
use app\core\entities\User;
use app\core\Environment;
use app\core\helpers\Html;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\headers\UserSideBar;
use app\core\helpers\View;
use app\core\helpers\Request;
use app\core\models\TokensTranslations;
use app\core\Permission;

/**
 * @var User $authUser
 * @var int $warehouse_id
 * @var string $warehouse_lang
 * @var array $user_queue [has_queue, flash]
 * @var bool $multiple_warehouses
 * @var array $warehouses
 * @var array $langs
 * @var bool $change_warehouse
 * @var bool $mobile
 * @var string $referer
 * @var string $view
 * @var bool $warehouse_virtual
 * @var string|null $title
 * @var Permission $permission
 * @var LogisticCompany $logistic_company
 * @var bool $bulk_queue
 */

View::setTemplateTokens(TokensTranslations::getDefaultTemplateTokens($warehouse_lang));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function () {
            <?= Request::fixConfirmFormResubmissionFront(); ?>
        })();
    </script>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <title><?= View::toHtml((empty($title) ? '' : ($title . ' | ')) . View::getTranslate('Fulfillment Control Panel')); ?></title>
    <?= View::include_css(COMMON_CSS, '/jquery-ui.min.css')?>
    <?= View::include_css(COMMON_CSS, '/jquery-ui.theme.min.css')?>
    <link href="<?= VENDOR_PATH . '/fontawesome-free/css/all.min.css'?>" rel="stylesheet" type="text/css">
    <link href="<?= COMMON_CSS . '/sb-admin-2.min.css'?>" rel="stylesheet">
    <link href="<?= COMMON_CSS . '/font-roboto.css' ?>" rel="stylesheet">
    <?= View::include_css(BACKEND_CSS, DIRECTORY_SEPARATOR . $logistic_company->getCss())?>
    <script src="<?= VENDOR_PATH . '/jquery/jquery.min.js'?>"></script>
    <?= View::include_js(BACKEND_JS, "helper.js")?>
    <style>
        .scrolling-panel {
            position:fixed;
            right:1rem;
            top:5.5rem;
            z-index: 100;
        }
        .scrolling-but {
            float: right;
            width:2.75rem;
            height:2.75rem;
            text-align:center;
            color:#fff;
            margin-left:0.1em;
            background:rgba(90,92,105,.5);
            line-height:46px
        }
        .scrolling-but:focus, .scrolling-but:hover {
            color:#fff
        }
        .scrolling-but:hover {
            background:#5a5c69
        }
        .scrolling-but i {
            font-weight:800
        }
    </style>
</head>
<body id="page-top">
<div id="wrapper">
    <ul class="navbar-nav sidebar sidebar-dark accordion toggled sidebar-background" id="accordionSidebar">
        <?= (new UserSideBar($logistic_company, $permission, $warehouse_lang))->put(); ?>
    </ul>
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <?php if ($authUser) { ?>
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-sm-none rounded-circle mr-3" style="background-color: inherit !important;">
                        <i class="fa fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ml-auto">
                        <?php if ($user_queue[0]) { ?>
                            <li class="nav-item dropdown no-arrow">
                                <a class="nav-link dropdown-toggle" href="/user_jobs">
                                    <span class="mr-2 d-none d-lg-inline text-gray-600 small <?= $user_queue[1] ? 'border border-warning p-1' : '' ?>">
                                        <i class="fas fa-clipboard-list"></i> <b><?= View::getTranslate('Queue'); ?></b>
                                    </span>
                                </a>
                            </li>
                        <?php } ?>
                        <?php if ($authUser->hasDevAccess()) { ?>
                            <li class="nav-item dropdown no-arrow">
                                <a class="nav-link dropdown-toggle" style="cursor: pointer" onclick="listOfActiveUsers()">
                                    <span class="mr-2 d-none d-lg-inline text-gray-600 small"><i class="fas fa-users"></i> <b><?= View::getTranslate('Active users'); ?></b></span>
                                </a>
                            </li>
                        <?php } ?>
                        <?php if (Environment::isDev() || (Environment::isProduction() && $authUser->hasDevAccess())) { ?>
                            <li class="nav-item dropdown no-arrow">
                                <a class="nav-link dropdown-toggle" href="/tests/test">
                                    <span class="mr-2 d-none d-lg-inline text-gray-600 small"><i class="fas fa-tasks"></i> <b><?= View::getTranslate('Test'); ?></b></span>
                                </a>
                            </li>
                            <li class="nav-item dropdown no-arrow">
                                <a class="nav-link dropdown-toggle" href="/cron">
                                    <span class="mr-2 d-none d-lg-inline text-gray-600 small"><i class="fas fa-redo"></i> <b><?= View::getTranslate('Cron'); ?></b></span>
                                </a>
                            </li>
                        <?php }  ?>
                        <?php if ($multiple_warehouses) { ?>
                            <li class="nav-item">
                                <form method="post" action="/dashboard/reload_warehouse">
                                    <div class="form-group mt-3">
                                        <select class="form-control" name="warehouse_id" onchange="submit();" <?= !$change_warehouse ? 'disabled' : ''; ?>>
                                            <?php foreach ($warehouses as $tmp_warehouse) { ?>
                                                <option value="<?= View::toHtml($tmp_warehouse['id']); ?>"
                                                    <?= $warehouse_id == $tmp_warehouse['id'] ? "selected" : ""; ?>>
                                                    <?= View::toHtml($tmp_warehouse['name']) . ($tmp_warehouse['is_virtual'] ? (' (' . View::getTranslate('virtual') . ')') : ''); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </form>
                            </li>
                        <?php } else { ?>
                            <li class="nav-item dropdown">
                                <div class="nav-link text-black-50 font-weight-bold">
                                    <?= View::toHtml(($warehouses[$warehouse_id]['name'] ?? '') . (($warehouses[$warehouse_id]['is_virtual'] ?? false) ? (' (' . View::getTranslate('virtual') . ')') : '')) ?>
                                </div>
                            </li>
                        <?php }  ?>
                        <div class="topbar-divider d-none d-sm-block"></div>
                        <li class="nav-item">
                            <form method="post" action="/dashboard/reload_lang">
                                <div class="form-group mt-3">
                                    <?= Html::dropDownList('lang_id', $langs, $warehouse_lang, ['class' => 'form-control', 'onchange' => 'submit();']); ?>
                                </div>
                            </form>
                        </li>
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-lg-inline text-gray-600 small">
                                <i class="fas fa-user"></i>
                                <b><?= View::toHtml($authUser->getLogin()) ?></b>
                                </span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <?php if ($authUser->isRoleSupplierUser()) { ?>
                                    <a class="dropdown-item" href="/account">
                                        <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                        <?= View::getTranslate('Account'); ?>
                                    </a>
                                    <a class="dropdown-item" href="/account/edit_user">
                                        <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                                        <?= View::getTranslate('Edit user'); ?>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                <?php } elseif ($authUser->isRoleSA()) { ?>
                                    <?php if (!$warehouse_virtual) { ?>
                                        <a class="dropdown-item" href="/additional">
                                            <i class="fas fa-plus fa-sm fa-fw mr-2 text-gray-400"></i>
                                            <?= View::getTranslate('Additional'); ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($view == 'orders/view_order') { ?>
                                        <button class="dropdown-item" data-toggle="modal" data-target="#forceModal">
                                            <i class="fas fa-ban fa-sm fa-fw mr-2 text-gray-400"></i>
                                            <?= View::getTranslate('Force cancel'); ?>
                                        </button>
                                    <?php } ?>
                                <?php } ?>

                                <button class="dropdown-item" onclick="changePass()">
                                    <i class="fas fa-key fa-sm fa-fw mr-2 text-gray-400"></i>
                                    <?= View::getTranslate('Change password'); ?>
                                </button>
                                <script>
                                    function changePass() {
                                        const openModal = () => {
                                            setTimeout(() => {
                                                $('.pass_input').val('');
                                                $('#change_pass_modal').modal('show');
                                            }, 0);
                                        }
                                        if ($('#change_pass_modal').length) {
                                            openModal();
                                        } else {
                                            $.get('/account/get_pass_template', res => {
                                               $('#change_pass_block').html(res);
                                            }).done(openModal);
                                        }
                                    }
                                </script>
                                <div class="dropdown-divider"></div>

                                <?php if ($bulk_queue && $authUser->isRoleWorker()) { ?>
                                    <a class="dropdown-item" href="/queues/bulk_list">
                                        <i class="fas fa-clipboard fa-sm fa-fw mr-2"></i>
                                        Packing bulk (queue)
                                    </a>
                                    <div class="dropdown-divider"></div>
                                <?php } ?>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    <?= View::getTranslate('Logout'); ?>
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
            <?php } ?>
            <?php View::outputAllFlash(); ?>
            <?php if (Environment::isDev())
                View::outputDbLogging(); ?>
            <?php require_once View::getBackend("$view.php"); ?>
        </div>
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>Copyright &copy; <?= View::getTranslate('Fulfillment') . ' ' . date('Y') ?></span>
                </div>
            </div>
        </footer>
    </div>
</div>
<!-- Scroll to Top Button-->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>
    <!-- Change Pass Modal-->
    <div id="change_pass_block"></div>
<!-- Logout Modal-->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel"><?= View::getTranslate('Ready to Leave?'); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body"><?= View::getTranslate('Select "Logout" below if you are ready to end your current session.'); ?></div>
            <div class="modal-footer">
                <form action="/auth/logout" method="POST" class="navbar-form navbar-right">
                    <input type="hidden" name="logout" value="1">
                    <button class="btn btn-outline-orange" type="button" data-dismiss="modal"><?= View::getTranslate('Cancel'); ?></button>
                    <button class="btn btn-orange"><?= View::getTranslate('Logout'); ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php if ($authUser->isRoleSA() && $view == 'orders/view_order' && !empty($order_id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0)))) { ?>
    <div class="modal fade" id="forceModal" tabindex="-1" role="dialog" aria-labelledby="forceModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="forceModalLabel"><?= View::getTranslate('Force cancel order') . " #$order_id"; ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body"><?= View::getTranslate('Are you sure?'); ?></div>
                <div class="modal-footer">
                    <form action="/orders/force_cancel" method="POST">
                        <input type="hidden" name="id" value="<?= $order_id; ?>">
                        <button class="btn btn-outline-orange" type="button" data-dismiss="modal"><?= View::getTranslate('Cancel'); ?></button>
                        <button class="btn btn-orange"><?= View::getTranslate('Yes'); ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php } ?>
<?php
    if ($authUser->hasDevAccess()) {
        $list_users = new InfoModal('listOfActiveUsers', View::getTranslate('List of active users') . ' (<span id="count_users"></span>)', InfoModal::SIZE_XL);
        echo $list_users->addContent('
                <div class="alert alert-danger d-none" id="active_users"></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tableUsers">
                        <thead>
                            <tr>
                                <th>' . View::getTranslate('Login') . '</th>
                                <th>' . View::getTranslate('Role') . '</th>
                                <th>' . View::getTranslate('Warehouse') . '</th>
                                <th>' . View::getTranslate('Last access') . '</th>
                                <th>' . View::getTranslate('Country') . '</th>
                                <th>' . View::getTranslate('IP') . '</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>')
                ->put();
    }
?>
<script src="<?= COMMON_JS . '/jquery-ui.min.js'; ?>"></script>
<script src="<?= VENDOR_PATH . '/bootstrap/js/bootstrap.bundle.min.js'?>"></script>
<script src="<?= VENDOR_PATH . '/jquery-easing/jquery.easing.min.js'?>"></script>
<script src="<?= COMMON_JS . '/sb-admin-2.min.js'; ?>"></script>
<script>
    <?php if ($authUser->hasDevAccess()) { ?>
        function listOfActiveUsers() {
            $('#listOfActiveUsersModalInfo .modal-dialog').addClass('modal-dialog-scrollable');
            <?= $list_users->show(); ?>
            $.ajax({
                url: "/main/get_list_of_users",
                method: "POST",
                data: {},
                dataType: "json",
                success: function (json) {
                    if ('error' in json)
                        showError('active_users', json.error, 3000);
                    else {
                        $('#tableUsers > tbody').children().remove().end();
                        if (Object.keys(json).length > 0) {
                            $('#count_users').html(json.length);
                            json.forEach(function (item) {
                                $('#tableUsers > tbody:last-child').append(
                                    '<tr>' +
                                    '<td>' + html_escape(item.login) + '</td>' +
                                    '<td>' + html_escape(item.role_name) + '</td>' +
                                    '<td' + (item.warehouse_id == <?= (int)$warehouse_id; ?> ? ' class="font-weight-bold"' : '') + '>' + html_escape(item.warehouse_name) + '</td>' +
                                    '<td>' + html_escape(item.last_access) + '</td>' +
                                    '<td>' + html_escape(item.country_id) + '</td>' +
                                    '<td>' + html_escape(item.IPv4) + '</td>' +
                                    '</tr>');
                            });
                        }
                        else
                            $('#tableUsers > tbody:last-child').append('<tr><td colspan="5" class="bg-danger text-white"><?= View::getTranslate('Users are not found'); ?></td></tr>');
                    }
                },
                error: function (json) {
                    showError('active_users', '<?= View::getTranslate('Connection is not found!'); ?>', 3000);
                }
            });
            return false;
        }
    <?php } ?>

    $(document).ready(function () {
        let update_list = {
            time_key: 'update_list_time',
            request_interval: 120000,
            chack_interval: 5000,
            setTime: function (key, value) {
                localStorage.setItem(key, value);
            },
            getTime: function (key) {
                return localStorage.getItem(key);
            },
            updateList: function () {
                $.ajax({url: "/main/update_list", method: "POST", data: {}, dataType: "json", success: function (json) { }, error: function (json) { }});
            },
            init: function () {
                let last_time = this.getTime(this.time_key);
                let now = Date.now();
                if (last_time && this.request_interval > now - last_time)
                    return;
                this.setTime(this.time_key, now);
                this.updateList();
            }
        };
        update_list.init();
        setInterval(() => {update_list.init();}, update_list.chack_interval);
    });

    // msg is plain text and is escaped; use showErrorHtml() for trusted markup built with escaped data
    function showError(id, msg, ms)
    {
        showErrorHtml(id, msg === undefined || msg === null ? msg : html_escape(msg), ms);
    }

    function showErrorHtml(id, html, ms)
    {
        let windError = $('#' + id);
        windError.html(html);
        if (windError.prop('classList').contains('d-none')) {
            windError.prop('classList').remove('d-none');
            if (ms != 0)
                setTimeout(() => {
                    if (!windError.prop('classList').contains('d-none'))
                        windError.prop('classList').add('d-none');
                }, ms);
        }
    }
</script>
</body>
</html>
