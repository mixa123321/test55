<?php

namespace app\api\controllers;

use app\api\API;
use app\api\APIException;
use app\api\models\ErrorCodes;
use app\core\helpers\Debug;

class TestCallback extends BaseApiController
{
    private $login = 'Fulfilment';
    private $pwd = '***';
    private $data;

    public function beforeAction($action)
    {
        parent::beforeAction($action);
        if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
            throw new APIException(401, [
                [
                    'code' => ErrorCodes::ACCESS_DENIED,
                    'message' => 'Login and API key must be provided. Authorization method is HTTP Basic'
                ]
            ]);
        }
        $login = trim($_SERVER['PHP_AUTH_USER']);
        $api_key = trim($_SERVER['PHP_AUTH_PW']);
        if ($login != $this->login || $api_key != $this->pwd)
            throw new APIException(401, [['code' => ErrorCodes::ACCESS_DENIED, 'message' => 'Access denied']]);
        $this->data = API::getRequestData();
    }

    public function postOrderDespatch()
    {
        Debug::sendDebugInfo('Callback OrderDespatch', json_encode($this->data), Debug::TEST);
        $this->response = 'OrderDespatch';
    }

    public function postOrderStatusChange()
    {
        Debug::sendDebugInfo('Callback OrderDespatch', json_encode($this->data), Debug::TEST);
        $this->response = 'OrderStatusChange';
    }
}
