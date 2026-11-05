<?php

namespace app\api\controllers;

use app\api\API;
use app\api\models\actions\ActionOrderDelete;
use app\api\models\actions\ActionOrderGet;
use app\api\models\actions\ActionOrderPost;

class Orders extends AuthApiController
{
    public function post()
    {
        $model = (new ActionOrderPost())->setRequestData(API::getRequestData())->validationRequest();
        if (empty($model->getValidRequestData())) {
            $this->response = ['created_orders' => [], 'error_orders' => $model->getValidationRequestErrors()];
            return;
        }
        $model->setOriginalRequest(API::getOriginalRequestData());
        $this->response = API::standardActionProcessing($model->init());
    }

    public function delete()
    {
        $this->response = API::standardActionProcessing(new ActionOrderDelete(API::getRequestData()));
    }

    public function get()
    {
        $this->response = API::standardActionProcessing(new ActionOrderGet(API::getRequestData()));
    }
}
