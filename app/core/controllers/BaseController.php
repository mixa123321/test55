<?php

namespace app\core\controllers;

abstract class BaseController
{
    protected $httpCode = 200;
    protected $response;

    public function beforeAction($action)
    {
    }

    public function afterAction()
    {
        if (is_array($this->response)) {
            $this->response = json_encode($this->response);
        } elseif (!is_string($this->response)) {
            $this->response = (string) $this->response;
        }
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setResponse($response)
    {
        $this->response = $response;
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }

    public function setHttpCode($httpCode)
    {
        $this->httpCode = $httpCode;
    }
}
