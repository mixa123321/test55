<?php

namespace app\core;

use app\api\models\ErrorCodes;
use app\core\controllers\BaseController;
use app\core\exceptions\FFException;
use app\core\exceptions\RedirectException;
use app\core\helpers\HTTP;
use app\core\helpers\Request;
use app\core\helpers\Str;
use app\core\helpers\View;
use app\core\statical\WithDate;

class Router
{
    public static $app;
    private $controller;
    private $action;
    private $url;
    private static $time_start;

    public function __construct($domain, $url)
    {
        $this->url = $url;
        $uriPath = explode("/", strtok($this->url, "?"));
        $this->controller = ucfirst(Str::toCamelCase(isset($uriPath[1]) ? $uriPath[1] : "", true));
        $this->action = Str::toCamelCase(isset($uriPath[2]) ? $uriPath[2] : "");
        list($suffix, $host) = HTTP::separateHost();
        $domainParts = explode(".", $domain, 2);
        switch ($domainParts[0]) {
            case HTTP::API:
                Environment::setApi();
                self::$app = HTTP::APP[HTTP::API];
                $this->action = strtolower(Request::getRequestMethod()) . ucfirst($this->action);
                break;
            case HTTP::ADMIN:
                Environment::setAdminPanel();
                self::$app = HTTP::APP[HTTP::ADMIN];
                break;
            default:
                Environment::setFrontend();
                self::$app = HTTP::APP[HTTP::WWW];
        }
        if (empty($this->controller))
            $this->controller = "Main";
        if (empty($this->action))
            $this->action = "index";
    }

    public static function getTimeStart()
    {
        return self::$time_start;
    }

    public function run()
    {
        self::$time_start = date(WithDate::DATE_SQL_FORMAT);
        if (Environment::isApi())
            header("Content-type: application/json; charset=utf-8");
        $controller = "\\app\\" . self::$app . "\\controllers\\{$this->controller}";
        if (!class_exists($controller))
            return $this->notFound();
        /** @var BaseController $controller */
        $controller = new $controller();
        if (!method_exists($controller, $this->action))
            return $this->notFound();
        try {
            if (!in_array($this->url, HTTP::COMMON_URLS))
                $controller->beforeAction($this->action);
            $controller->{$this->action}();
        } catch (RedirectException $e) {
            $controller->setHttpCode($e->getHttpCode());
        } catch (FFException $e) {
            $controller->setHttpCode($e->getHttpCode());
            $controller->setResponse($e->getCodesMessage());
        }
        if (!in_array($this->url, HTTP::COMMON_URLS))
            $controller->afterAction();
        HTTP::setResponseCode($controller->getHttpCode());
        return $controller->getResponse();
    }

    private function notFound()
    {
        HTTP::setResponseCode(404);
        if (Environment::isApi()) {
            $result = [
                "errors" => [
                    ["code" => ErrorCodes::UNKNOWN_ENDPOINT, "message" => "Unknown API endpoint"]
                ]
            ];
            return json_encode($result);
        }
        if (Environment::isFrontend())
            HTTP::redirect("/", 302);
        return View::render([], 'page_404', 'simple');
    }

    public static function internalError()
    {
//        HTTP::setResponseCode(500);
        if (Environment::isApi()) {
            $result = ['errors' => ''/*[API::getInternalError()]*/];
//            Logger::keep($result, 500, new MysqlLogKeeper());
            echo json_encode($result);
            return;
        }
        if (Environment::isRobot()) {
            echo "Internal error\n";
            return;
        }
        if (Environment::isFrontend()) {
            echo json_encode([
                "errors" => [
                    ["code" => "internal-error", "message" => "Internal Server Error"]
                ]
            ]);
            return;
        }
        echo View::render([], 'page_500', 'simple');
    }

    public static function parseUrl($url, $app = HTTP::APP[HTTP::ADMIN])
    {
        $url = explode("/", $url);
        $controller = ucfirst(Str::toCamelCase($url[0] ?? "Main", true));
        $controller = "\\app\\" . $app . "\\controllers\\{$controller}";
        $action = Str::toCamelCase($url[1] ?? "index");
        return [$controller, $action];
    }

    public static function hasHandler($url, $app = HTTP::APP[HTTP::ADMIN])
    {
        list($controller, $action) = self::parseUrl($url, $app);
        return class_exists($controller) && method_exists($controller, $action);
    }
}
