<?php

namespace app\api\controllers;

use app\api\API;
use app\api\models\actions\ActionSKUDelete;
use app\api\models\actions\ActionSKUGet;
use app\api\models\actions\ActionSKUPost;

class SKUs extends AuthApiController
{
    public function post()
    {
        $model = (new ActionSKUPost())->setRequestData(API::getRequestData())->validationRequest();
        if (empty($model->getValidRequestData())) {
            $this->response = ['created_SKUs' => [], 'error_SKUs' => $model->getValidationRequestErrors()];
            return;
        }
        $this->response = API::standardActionProcessing($model->init());
    }

    public function delete()
    {
        $this->response = API::standardActionProcessing(new ActionSKUDelete(API::getRequestData()));
    }

    public function get()
    {
        $this->response = API::standardActionProcessing(new ActionSKUGet(API::getRequestData()));
    }
}
