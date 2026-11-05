<?php

namespace app\backend\controllers;

use app\core\exceptions\RedirectException;

class Tests extends AuthController
{
    /** @throws */
    public function index()
    {
        throw new RedirectException(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/');
    }

    /** @throws */
    public function test()
    {
        throw new RedirectException(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/');
    }
}
