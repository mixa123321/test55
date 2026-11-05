<?php

namespace app\frontend\controllers;

use app\core\controllers\BaseController;
use app\core\exceptions\RedirectException;

class Main extends BaseController
{
    public function index()
    {
        throw new RedirectException(BASE_PROTOCOL . "://admin." . BASE_DOMAIN);
    }
}
