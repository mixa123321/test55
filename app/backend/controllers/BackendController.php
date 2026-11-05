<?php

namespace app\backend\controllers;

use app\core\controllers\BaseController;
use app\core\helpers\HTTP;
use app\core\helpers\Str;
use Exception;
use ReflectionClass;

abstract class BackendController extends BaseController
{
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        HTTP::securitySessionStart();
    }

    public function route(string $method, string $class = null, array $params = []): string
    {
        if (!is_null($class) && !class_exists($class))
            return '/';
        $class_obj = is_null($class) ? $this : new $class;
        if (!in_array($method, get_class_methods($class_obj)))
            return '/';
        try {
            if ($method === 'index')
                $method = '';
            return '/' . Str::camelCaseToSnakeCase((new ReflectionClass($class_obj))->getShortName())
                . (!empty($method) ? ('/' . Str::camelCaseToSnakeCase($method)) : '')
                . (!empty($params) ? ('?' . http_build_query($params)) : '');
        } catch (Exception $e) {
            return '/';
        }
    }

    public function getCurrentRoute(): string
    {
        return trim(strtok($_SERVER["REQUEST_URI"], "?") , "/" );
    }

    public function jsonResponse(array $response, $http_code = 200): array
    {
        $this->setHttpCode($http_code);
        header('Content-Type: application/json');
        return $this->response = $response;
    }
}
