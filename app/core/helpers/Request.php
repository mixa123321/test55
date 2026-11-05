<?php

namespace app\core\helpers;

class Request
{
    const URI_RESUBMISSION = [
        '/pick/picking_get',
        '/pick/picking_bar'
    ];

    public static $php_input = false;

    public static function getVariable($data, $field = null, $default_value = null, array $ignore_fields = [])
    {
        if (!empty($ignore_fields))
            $data = Arr::filterKeysRecursive($data, $ignore_fields);
        if ($field === null)
            return $data;
        if (!array_key_exists($field, $data))
            return $default_value;
        return $data[$field];
    }

    public static function getPositiveId($data = null, $field = 'id')
    {
        if ($data === null)
            $data = $_GET;
        $val = (int)($data[$field] ?? 0);
        return $val < 0 ? 0 : $val;
    }

    public static function getPage(?array $data = null, ?int $current_page = null): int
    {
        if (is_null($current_page))
            $current_page = !empty($data['c']) ? $data['c'] : 1;
        return (int)$current_page > 0 ? $current_page : 1;
    }

    public static function get($field = null)
    {
        $data = array_merge($_GET, $_POST);
        if (isset($data[$field])) return $data[$field];
    }

    public static function getError($data = null)
    {
        if ($data === null)
            $data = $_GET;
        $val = (int)(isset($data['err']) ? $data['err'] : 0);
        return $val != 0;
    }

    public static function getUser($data = null)
    {
        if ($data === null)
            $data = $_GET;
        return (isset($data["user"]) ? $data["user"] : "");
    }

    public static function getBarcode($data = null, $name = 'sku')
    {
        if ($data === null)
            $data = $_GET;
        return (isset($data[$name]) ? View::toHtml($data[$name]) : "");
    }

    /**
     * @deprecated use getPositiveId
     * @return mixed|string
     */
    public static function getSupplierId($data = null)
    {
        if ($data === null)
            $data = array_merge($_GET, $_POST);
        $oldReturn = (isset($data["supplier"]) ? $data["supplier"] : "");
        return (isset($data["supplier_id"]) ? $data["supplier_id"] : $oldReturn);
    }

    public static function getPersonId($data = null)
    {
        if ($data === null)
            $data = $_GET;
        return (isset($data["person"]) ? $data["person"] : "");
    }

    public static function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public static function getAllowedRequestMethods()
    {
        return [
            'GET',
            'POST',
            'PUT',
            'DELETE'
        ];
    }

    public static function isRequestMethodAllowed()
    {
        $method = self::getRequestMethod();
        return in_array($method, self::getAllowedRequestMethods());
    }

    public static function getRawBody()
    {
        if (self::$php_input === false)
            self::$php_input = file_get_contents("php://input");
        return self::$php_input;
    }

    public static function getIp()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $a = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($a[0]);
        }
        return empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
    }

    public static function getRequestHeaders()
    {
        $result = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                $result = $headers;
                if (isset($result["Authorization"]))
                    unset($result["Authorization"]);
            }
        }
        return $result;
    }

    public static function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public static function getData()
    {
        if($_SERVER['REQUEST_METHOD'] === "GET" || $_SERVER['REQUEST_METHOD'] === "POST")
            return $_REQUEST;
        else
            parse_str(file_get_contents("php://input"), $var);
            return $var;
    }

    /**
     * Решение проблемы `Подтвердите повторную отправку формы`
     * На стороне js подменяем URL на такой же URL
     * Но из-за этого POST запрос в истории браузера превращается в GET запрос
     * Подменять URL нужно в любом случае, иначе браузер не отправляет запрос на сторону php и ничего сделать нельзя
     *
     * @return string
     */
    public static function fixConfirmFormResubmissionFront(): string
    {
        if (!self::isAjax() && self::isPost() && in_array(parse_url(HTTP::getRequestUri(), PHP_URL_PATH), self::URI_RESUBMISSION))
            return 'window.history.replaceState(null, null, location.pathname + location.search);';
        return '';
    }

    /**
     * Решение проблемы `Подтвердите повторную отправку формы`
     * 1. На стороне php запоминаем POST запросы. 5 последних POST запросов держим в сессии, чтобы не засорять
     * 2. На стороне php проверяем был ли POST запрос с таким же URL как текущий GET запрос, если да
     * то отправляем POST запрос с параметрами из сессии
     *
     * @return string
     */
    public static function fixConfirmFormResubmissionBack(): string
    {
        if (Request::isAjax() || !in_array(parse_url(HTTP::getRequestUri(), PHP_URL_PATH), self::URI_RESUBMISSION))
            return '';
        $uri = HTTP::getRequestUri();
        $md5 = md5($uri);
        if (Request::isPost()) {
            $_SESSION['postHistory'][$md5] = $_POST;
            if (count($_SESSION['postHistory']) > 5)
                array_shift($_SESSION['postHistory']);
        } elseif (Request::isGet() && !empty($_SERVER['HTTP_REFERER']) && isset($_SESSION['postHistory'][$md5])) {
            $form = "<form id='form' action='" . View::toHtml($uri) . "' method='post'>";
            foreach ($_SESSION['postHistory'][$md5] as $key => $value)
                $form .= '<input type="hidden" name="'.htmlentities($key).'" value="'.htmlentities($value).'">';
            $form .= "</form>";
            $form .= '<script type="text/javascript">document.getElementById("form").submit();</script>';
            return $form;
        }
        return '';
    }

    public static function isPost()
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === "POST";
    }

    public static function isGet()
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === "GET";
    }
}
