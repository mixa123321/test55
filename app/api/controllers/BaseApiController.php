<?php

namespace app\api\controllers;

use app\api\API;
use app\api\APIException;
use app\backend\models\WebhookHistories;
use app\core\controllers\BaseController;
use app\core\entities\WebhookHistory;

abstract class BaseApiController extends BaseController
{
    /** @throws APIException */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        API::initRequestData();
    }

    public function afterAction()
    {
        parent::afterAction();
    }

    protected function callAction($name, $action_class, $name_method = null)
    {
        $action_class = "app\\api\\models\\actions\\" . $action_class;
        $fields_to_save = ['id', 'response', 'json_data'];
        $webhook_history = WebhookHistories::createInternalHistory($name);
        try {
            $action = new $action_class(API::getRequestData());
            $webhook_history->setUserId($action->getUser()->getId());
            $fields_to_save[] = 'user_id';
            $this->response = empty($name_method) ? API::standardActionProcessing($action)
                : API::subtaskActionProcessing($action, $name_method);
        } catch (APIException $e) {
            $webhook_history->setStatus(WebhookHistory::STATUS_ERROR);
            $fields_to_save[] = 'status';
            $webhook_history->setCreateDate(date(DATE_ATOM, time()));
            $fields_to_save[] = 'create_date';
            $this->response = $e->getCodesMessage();
        } finally {
            $webhook_history->setJsonData($action->getData());
            $webhook_history->setResponse($this->getResponse());
            $webhook_history->save($fields_to_save);
        }
    }
}
