<?php

use app\core\ErrorHandler;
use app\core\helpers\HTTP;
use app\core\Router;

require __DIR__ . "/app/boot.php";

try {
    $router = new Router(HTTP::getHost(), strtok(HTTP::getRequestUri(), '?'));
    echo $router->run();
} catch (Exception $e) {
    ErrorHandler::handleErrors($e);
}
