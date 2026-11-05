<?php

namespace app\api;

use app\api\models\ErrorCodes;
use app\api\models\Users;
use app\core\entities\User;
use app\core\helpers\Request;
use app\core\helpers\Str;

class API
{
    private static $user;
    private static $request_data;
    private static $original_request_data;

    /** @return User */
    public static function getUser()
    {
        return self::$user;
    }

    /** @throws APIException */
    public static function standardActionProcessing($action)
    {
        $errors = $action->validateFields();
        if (!empty($errors))
            throw new APIException(400, $errors);
        $result = $action->process();
        if (!empty($result['errors'])) {
            $http_code = 400;
            if (!empty($result['errors'][0]['code']) && $result['errors'][0]['code'] === ErrorCodes::INTERNAL_ERROR)
                $http_code = 500;
            throw new APIException($http_code, $result['errors']);
        }
        return $result;
    }

    /** @throws APIException */
    public static function subtaskActionProcessing($action, $method)
    {
        $name_method = "{$method}ValidateFields";
        $errors = $action->$name_method();
        if (!empty($errors))
            throw new APIException(400, $errors);
        $result = $action->$method();
        if (!empty($result['errors'])) {
            $http_code = 400;
            if (!empty($result['errors'][0]['code']) && $result['errors'][0]['code'] === ErrorCodes::INTERNAL_ERROR)
                $http_code = 500;
            throw new APIException($http_code, $result['errors']);
        }
        return $result;
    }

    public static function authorization()
    {
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
        $user_obj = Users::getByLogin($login);
        if (!$user_obj
            || !$user_obj->isApiKeyValid($api_key)
            || $user_obj->isBlocked()) {
            throw new APIException(401, [['code' => ErrorCodes::ACCESS_DENIED, 'message' => 'Access denied']]);
        }
        self::$user = $user_obj;
    }

    public static function getInternalError()
    {
        return [
            'code' => ErrorCodes::INTERNAL_ERROR,
            'message' => ErrorCodes::$ALL[ErrorCodes::INTERNAL_ERROR]
        ];
    }

    public static function initRequestData()
    {
        if (Request::getRequestMethod() !== "GET") {
            $decoded_request = json_decode(Request::getRawBody(), true);
            if (!$decoded_request) {
                throw new APIException(406, [
                    [
                        'code' => 'incorrect_request_format',
                        'message' => 'Incorrect request format. JSON is required'
                    ]
                ]);
            }
            self::$request_data = $decoded_request;
        } else {
            self::$request_data = $_GET;
        }
        self::$original_request_data = self::$request_data;
        array_walk_recursive(self::$request_data, function (&$val) {
            $val = Str::removeEmoji($val);
            $val = Str::clearWhitespace($val);
        });
    }

    public static function getRequestData()
    {
        return self::$request_data;
    }

    public static function getOriginalRequestData()
    {
        return self::$original_request_data;
    }
}
