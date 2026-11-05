<?php

namespace app\backend\controllers;

use app\core\entities\User;
use app\core\exceptions\RedirectException;
use app\core\helpers\View;
use app\core\Permission;
use app\backend\models\Users;

abstract class SystemController extends BackendController
{
    /** @var User */
    protected $user;
    /** @var Permission */
    protected $permission;

    /** @throws */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        $this->user = Users::getAuthUser();
        if ($this->user === null) {
            $_SESSION["authUrl"] = $_SERVER["REQUEST_URI"];
            throw new RedirectException("/auth/login");
        }
        if (!$this->user->isRoleSystem())
            throw new RedirectException("/forbidden");
        $this->permission = new Permission($this->user);
    }

    protected function render($vars = [], $view = '')
    {
        $vars['authUser'] = $this->user;
        $vars['permission'] = $this->permission;
        $this->response = View::render($vars, $view, 'system');
    }
}
