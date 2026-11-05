<?php
/**
 * @var $authUser User
 * @var $permission Permission
 * @var $view string
 */

use app\core\entities\User;
use app\core\helpers\headers\SystemSideBar;
use app\core\helpers\View;
use app\core\Permission;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <title>System Control Panel</title>
    <link href="<?= VENDOR_PATH . '/fontawesome-free/css/all.min.css'?>" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="<?= COMMON_CSS . '/sb-admin-2.min.css'?>" rel="stylesheet">
    <link href="<?= COMMON_CSS . '/font-roboto.css' ?>" rel="stylesheet">
    <?= View::include_css(BACKEND_CSS, DIRECTORY_SEPARATOR . 'main.css')?>
    <script src="<?= VENDOR_PATH . '/jquery/jquery.min.js'?>"></script>
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
            <?= (new SystemSideBar($permission))->put(); ?>
        </ul>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php if ($authUser) { ?>
                    <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                        <button id="sidebarToggleTop" class="btn btn-link d-sm-none rounded-circle mr-3" style="background-color: inherit !important;">
                            <i class="fa fa-bars"></i>
                        </button>
                        <ul class="navbar-nav ml-auto">
                            <li class="nav-item dropdown no-arrow">
                                <a class="nav-link dropdown-toggle" href="/tests/test">
                                    <span class="mr-2 d-none d-lg-inline text-gray-600 small"><i class="fas fa-tasks"></i> <b>Test</b></span>
                                </a>
                            </li>
                            <div class="topbar-divider d-none d-sm-block"></div>
                            <li class="nav-item dropdown no-arrow">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <span class="mr-2 d-lg-inline text-gray-600 small">
                                        <i class="fas fa-user"></i> <b><?= View::toHtml($authUser->getLogin()); ?></b>
                                    </span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                    <button class="dropdown-item" onclick="changePass()">
                                        <i class="fas fa-key fa-sm fa-fw mr-2 text-gray-400"></i>
                                        Change password
                                    </button>
                                    <script>
                                        function changePass() {
                                            const openModal = () => {
                                                setTimeout(() => {
                                                    $('.pass_input').val('');
                                                    $('#change_pass_modal').modal('show');
                                                }, 0);
                                            };
                                            openModal();
                                        }
                                    </script>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                        <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                        Logout
                                    </a>
                                </div>
                            </li>
                        </ul>
                    </nav>
                <?php } ?>
                <?php View::outputAllFlash(); ?>
                <?php require_once View::getBackend("$view.php"); ?>
            </div>
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; Fulfillment <?= date('Y') ?></span>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>
    <div id="change_pass_block">
        <?php require_once BACKEND_VIEW_PATH . DIRECTORY_SEPARATOR . 'change_pass_template.php'; ?>
    </div>
    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <form action="/auth/logout" method="POST" class="navbar-form navbar-right">
                        <input type="hidden" name="logout" value="1">
                        <button class="btn btn-outline-orange" type="button" data-dismiss="modal">Cancel</button>
                        <button class="btn btn-orange">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= COMMON_JS . '/jquery-ui.min.js'; ?>"></script>
    <script src="<?= VENDOR_PATH . '/bootstrap/js/bootstrap.bundle.min.js'?>"></script>
    <script src="<?= VENDOR_PATH . '/jquery-easing/jquery.easing.min.js'?>"></script>
    <script src="<?= COMMON_JS . '/sb-admin-2.min.js'; ?>"></script>
    <script>
        function showError(id, msg, ms)
        {
            showErrorHtml(id, msg === undefined || msg === null ? msg : $('<div>').text(msg).html(), ms);
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

        function hideError(id)
        {
            let windError = $('#' + id);
            if (!windError.prop('classList').contains('d-none'))
                windError.prop('classList').add('d-none');
        }
    </script>
</body>
</html>
