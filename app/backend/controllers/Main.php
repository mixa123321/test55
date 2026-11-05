<?php

namespace app\backend\controllers;

use app\backend\models\UsersLogged;
use app\core\exceptions\RedirectException;
use app\core\helpers\Html;
use app\core\helpers\Request;
use app\frontend\models\PrintLabel;

class Main extends AuthController
{
    protected $is_loggable = false;

    /** @throws */
    public function index()
    {
        throw new RedirectException("/dashboard");
    }

    public function close()
    {
        $this->render([], 'utility/close');
    }

    public function getListOfUsers()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $users = UsersLogged::getUsers();
        foreach ($users as &$user) {
            $user['last_access'] = $this->handleTimezone($user['last_access']);
            if (empty($user['country_id']) && $user['IPv4'] === '127.0.0.1')
                $user['country_id'] = 'local';
            else {
                $filename = COMMON_IMG . DIRECTORY_SEPARATOR . 'flags' . DIRECTORY_SEPARATOR . strtolower($user['country_id']) . '.png';
                if (is_file(ROOT_PATH . $filename))
                    $user['country_id'] = Html::img($filename, ['title' => $user['country_id'], 'style' => 'width: 30px']);
            }
        }
        unset($user);
        return $this->response = json_encode($users);
    }

    public function updateList()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $headers = Request::getRequestHeaders();
        if (!empty($_SESSION['user_logged_id']))
            UsersLogged::updateList($_SESSION['user_logged_id'], $this->user->getId(), Request::getIp(), $headers['GEOIP_COUNTRY_CODE'] ?? $_SERVER['GEOIP_COUNTRY_CODE'] ?? '');
        else
            $_SESSION['user_logged_id'] = UsersLogged::addUser($this->user->getId(), Request::getIp(), $headers['GEOIP_COUNTRY_CODE'] ?? $_SERVER['GEOIP_COUNTRY_CODE'] ?? '');
    }

    public function printLabel()
    {
        $shipment_id = Request::getPositiveId($_GET, 'shipment_id');
        if ($shipment_id)
            if (PrintLabel::printShippingLabel($shipment_id, $_GET['hash'] ?? '', $_GET['viewMode'] ?? ''))
                return;
        $return_id = Request::getPositiveId($_GET, 'return_id');
        if ($return_id)
            if (PrintLabel::printReturnLabel($return_id, $_GET['hash'] ?? '', $_GET['viewMode'] ?? ''))
                return;
        $bulk_queue_id = Request::getPositiveId($_GET, 'bulk_queue_id');
        if ($bulk_queue_id)
            if (PrintLabel::printBulk($bulk_queue_id, $_GET['hash'] ?? '', $_GET['viewMode'] ?? ''))
                return;
        die('Requested label not found');
    }
}
