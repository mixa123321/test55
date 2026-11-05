<?php

namespace app\api\controllers;

use app\api\API;
use app\api\models\actions\ActionASNDelete;
use app\api\models\actions\ActionASNGet;
use app\api\models\actions\ActionASNPost;

class ASNs extends AuthApiController
{
    public function post()
    {
        $this->response = API::standardActionProcessing(new ActionASNPost(API::getRequestData()));
    }

    public function delete()
    {
        $this->response = API::standardActionProcessing(new ActionASNDelete(API::getRequestData()));
    }

    public function get()
    {
        $this->response = API::standardActionProcessing(new ActionASNGet(API::getRequestData()));
    }
}
