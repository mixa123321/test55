<?php

namespace app\backend\controllers;

use app\backend\models\Users;
use app\core\helpers\Request;
use app\core\models\UsersLogged;
use app\core\exceptions\RedirectException;
use app\core\helpers\View;

class Auth extends BackendController
{
    public function login()
    {
        if ($_POST) {
            if ($user = Users::authControlPanel($_POST['login'] ?? '', $_POST['pass'] ?? '')) {
                $url = $_SESSION['authUrl'] ?? '/';
                $headers = Request::getRequestHeaders();
                $_SESSION['user_logged_id'] = UsersLogged::addUser($user->getId(), Request::getIp(), $headers['GEOIP_COUNTRY_CODE'] ?? $_SERVER['GEOIP_COUNTRY_CODE'] ?? '');
                unset($_SESSION['authUrl']);
                throw new RedirectException($url);
            }
            View::addFlash("login-error", "danger", "Incorrect login or password");
        }
        $this->response = View::render([], "", "login");
    }

    public function logout()
    {
        if ($_POST) {
            session_destroy();
            UsersLogged::removeUser($_SESSION['user_logged_id'] ?? 0);
            throw new RedirectException("/auth/login");
        }
        throw new RedirectException($_SERVER['HTTP_REFERER'] ?? '/');
    }
}
