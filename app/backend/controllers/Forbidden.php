<?php

namespace app\backend\controllers;

class Forbidden extends AuthController
{
    public function index()
    {
        $this->render([
            'back' => $_SERVER['HTTP_REFERER'] ?? '/'
        ], 'forbidden');
    }
}
