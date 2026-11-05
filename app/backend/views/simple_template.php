<?php
/**
 * @var $view string
 * @var $logistic_company LogisticCompany
 */

use app\core\entities\LogisticCompany;
use app\core\helpers\headers\SimpleSideBar;
use app\core\helpers\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <title>Fulfillment Control Panel</title>
    <link href="<?= VENDOR_PATH . '/fontawesome-free/css/all.min.css' ?>" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
          rel="stylesheet">
    <link href="<?= COMMON_CSS . '/sb-admin-2.min.css' ?>" rel="stylesheet">
    <link href="<?= COMMON_CSS . '/font-roboto.css' ?>" rel="stylesheet">
    <?= View::include_css(BACKEND_CSS, DIRECTORY_SEPARATOR . (!empty($logistic_company) ? $logistic_company->getCss() : 'main.css')); ?>
    <script src="<?= VENDOR_PATH . '/jquery/jquery.min.js' ?>"></script>
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
            <?= (new SimpleSideBar())->put(); ?>
        </ul>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-sm-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ml-auto">
                    </ul>
                </nav>
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
    <!-- Bootstrap core JavaScript-->
    <script src="<?= VENDOR_PATH . '/bootstrap/js/bootstrap.bundle.min.js' ?>"></script>

    <!-- Core plugin JavaScript-->
    <script src="<?= VENDOR_PATH . '/jquery-easing/jquery.easing.min.js' ?>"></script>

    <!-- Custom scripts for all pages-->
    <script src="<?= COMMON_JS . '/sb-admin-2.min.js'; ?>"></script>
</body>
</html>
