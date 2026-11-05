<?php

use app\core\helpers\View;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <title>Fulfilment Control Panel</title>
    <link href="<?= COMMON_CSS . '/sb-admin-2.min.css' ?>" rel="stylesheet">
    <link href="<?= COMMON_CSS . '/font-roboto.css' ?>" rel="stylesheet">
    <link href="<?= BACKEND_CSS . DIRECTORY_SEPARATOR . 'main.css' ?>" rel="stylesheet">
    <script src="<?= COMMON_JS . '/jquery-3.7.1.min.js' ?>"></script>
    <script src="<?= VENDOR_PATH . '/bootstrap/js/bootstrap.bundle.min.js' ?>"></script>
    <style>
        body {
            position: relative;
            background: url(<?= str_replace(DIRECTORY_SEPARATOR, '/', FRONTEND_IMG . DIRECTORY_SEPARATOR . 'login_bg.jpg'); ?>);
            background-size: cover;
            background-attachment: fixed;
        }
        .form-signin {
            max-width: 400px;
            padding: 24px;
            margin: 48px auto;
            background-color: rgba(255, 255, 255, 0.85);
        }
        .form-control {
            background-color: #fff;
        }
    </style>
</head>
<body>
    <div class="form-signin">
        <h2 class="mb-4 pb-2">Welcome to Fulfilment Control Panel</h2>
        <form method="POST">
            <?php View::outputAllFlash(); ?>
            <div class="form-group-element mb-1">
                <input id="login" class="form-control form-control-lg" type="text" name="login" placeholder=" " required autofocus>
                <label for="login">Login</label>
            </div>
            <div class="form-group-element mt-2">
                <input id="pass" class="form-control form-control-lg" type="password" name="pass" placeholder=" " autocomplete="off" required>
                <label for="pass">Password</label>
            </div>
            <div class="text-right py-2 mt-2 mb-0">
                <button class="btn btn-orange btn-lg" type="submit">Log in</button>
            </div>
        </form>
    </div>
</body>
</html>
