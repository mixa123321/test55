<?php

namespace app\api\controllers;

use app\api\API;
use app\api\APIException;
use app\api\models\ErrorCodes;
use app\api\models\VisibleCallback;
use app\backend\models\Users;
use app\core\entities\WebhookHistory;
use app\core\exceptions\VirtualException;
use app\core\helpers\Request;
use app\core\models\Warehouses;

class Visible extends BaseApiController
{
    private $login = 'US_Fulfilment';
    private $user;
    private $data;

    public function beforeAction($action)
    {
        parent::beforeAction($action);
        $this->user = Users::getByLogin($this->login);
        if (empty($this->user) || $this->user->isBlocked())
            return false;
        $warehouse_id = $this->user->getWarehouseId();
        $key = Warehouses::getVirtualAPIKey($warehouse_id);
        $this->data = API::getRequestData();
        $headers = Request::getRequestHeaders();
        if (empty($key) || empty($headers[$key['name']]) || !hash_equals((string)$key['key'], (string)$headers[$key['name']]))
            throw new APIException(401, [['code' => ErrorCodes::ACCESS_DENIED, 'message' => 'Access denied']]);
        unset($this->data[$key['name']]);
    }

    private function postWebhook($callback_function)
    {
        $webhook_history = new WebhookHistory();
        try {
            $visible = new VisibleCallback($this->user, $this->data);
            $this->response = ['success' => $visible->$callback_function()];
            $webhook_history->setStatus(WebhookHistory::STATUS_SUCCESS);
        }
        catch (VirtualException $e) {
            $this->response = ['error' => $e->getMessage()];
            $webhook_history->setStatus(WebhookHistory::STATUS_ERROR);
        }
        $webhook_history->setId($visible->getWebhookHistoryId());
        $webhook_history->setResponse($this->response);
        $webhook_history->setCreateDate(date(DATE_ATOM, time()));
        $webhook_history->save(['id', 'status', 'response', 'create_date']);
    }

    public function postInventoryReceived()
    {
        $this->postWebhook('InventoryReceived');
    }

    public function postOrderStatusChange()
    {
        $this->postWebhook('OrderStatusChange');
    }

    public function postShipConfirm()
    {
        $this->postWebhook('ShipConfirm');
    }
}
