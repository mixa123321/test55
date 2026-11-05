<?php

namespace app\backend\controllers;

use app\core\Environment;
use app\core\helpers\HTTP;
use app\core\Router;
use app\frontend\controllers\Tracking AS FrontTracking;

class Tracking extends FrontTracking
{
    function __construct() {
        Environment::setFrontend();
        Router::$app = HTTP::APP[HTTP::WWW];
    }
}