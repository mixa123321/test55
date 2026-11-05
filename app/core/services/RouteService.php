<?php

namespace app\core\services;

use app\core\dbal\SafeMysql;
use app\core\entities\Route;
use app\core\helpers\Debug;
use app\core\helpers\Str;
use app\core\models\Routes;
use Exception;
use ReflectionClass;

class RouteService
{
    private $exist_routes = [];
    private $handled_routes = [];
    private $controllers = [];

    private $exceptFiles = [
        '.',
        '..',
        'Auth.php',
        'AuthController.php',
        'BackendController.php',
        'Tests.php',
        'Cron.php',
        'RouteAccesses.php',
        'SystemController.php',
        'Tracking.php'
    ];

    public function execute()
    {
        $this->initControllers()->handleControllers();
        $this->handleScanRemovedControllers();
    }
    private function handleScanRemovedControllers()
    {
        $sql = 'SELECT id, name FROM routes WHERE is_active';
        $params = [];
        if (!empty($this->handled_routes)) {
            $sql .= ' AND id NOT IN (:ids)';
            $params['ids'] = array_keys($this->handled_routes);
        }
        $routes = (new SafeMysql())->fetchAll($sql, $params);
        foreach ($routes as $routeData)
            $this->checkActiveController($routeData['id'], $routeData['name']);
    }

    private function checkActiveController($route_id, $routeName)
    {
        $main = explode('/', $routeName);
        $controller = ucfirst(Str::toCamelCase($main[0]));
        $controllerClass = $this->handleControllerPath($controller);
        if (!class_exists($controllerClass)) {
            Routes::lock((int)$route_id);
            self::output($routeName . ' locked');
        }
    }

    private static function output($message)
    {
//        if (Environment::isRobot())
//            echo $message . PHP_EOL;
        Debug::sendDebugInfo('RouteHandler', $message, Debug::TEST);
    }

    /** @return RouteService */
    public function initControllers(): RouteService
    {
        $controllers = scandir(BACKEND_PATH . DIRECTORY_SEPARATOR . 'controllers');
        foreach ($controllers as $controller) {
            if (in_array($controller, $this->exceptFiles))
                continue;
            $this->addController(str_replace('.php', '', $controller));
        }
        return $this;
    }

    private function addController($controller)
    {
        $this->controllers[] = $controller;
    }

    public function getControllers(): array
    {
        return $this->controllers;
    }

    public function handleControllers()
    {
        foreach ($this->getControllers() as $controller)
            try {
                $this->handleControllerMethods($controller);
            } catch (Exception $e) {
                echo $e->getMessage() . PHP_EOL;
            }
        $this->handleRemoved();
    }

    /** @throws Exception */
    private function handleControllerMethods($controller)
    {
        $controllerPath = $this->handleControllerPath($controller);
        if (!class_exists($controllerPath))
            throw new Exception('Class ' . $controller . ' not found');
        $this->getExistRoutes($controller);
        $controllerObj = new $controllerPath;
        try {
            $f = new ReflectionClass($controllerObj);
            foreach ($f->getMethods() as $method)
                if ($method->class == get_class($controllerObj)
                    && $method->isPublic()
                    && !in_array($method->name, ['getAccess', 'beforeAction', '__construct']))
                        $this->handleRoute($controller, $method->getName());
        } catch (\ReflectionException $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    private function getExistRoutes($controller): void
    {
        $f_route = Str::camelCaseToSnakeCase($controller);
        foreach (Routes::findByMain($f_route) as $route_id => $r) {
            /** @var $r Route */
            $this->exist_routes[$f_route][$r->getName()] = [
                'id' => $route_id,
                'active' => $r->isActive()
            ];
        }
    }

    private function handleRoute($controller, $method)
    {
        $route = Str::camelCaseToSnakeCase($controller);
        $f_route = $route;
        $exist_routes = $this->exist_routes[$f_route] ?? null;
        if ($method !== 'index')
            $route .= '/' . Str::camelCaseToSnakeCase($method);
        if (empty($exist_routes) || !array_key_exists($route, $exist_routes)) {
            if (!empty($routeObj = Routes::create($route)))
                $this->handled_routes[$routeObj->getId()] = true;
            self::output($route . ' created');
        } else {
            if (empty($exist_routes[$route]['active'])) {
                $route_id = $exist_routes[$route]['id'];
                Routes::unlock($route_id);
                $this->handled_routes[$route_id] = true;
                self::output($route . ' unlocked');
            }
            unset($this->exist_routes[$f_route][$route]);
        }
    }

    /**
     * @param string $controller
     * @return string
     */
    public static function handleControllerPath(string $controller): string
    {
        return "\\app\\backend\\controllers\\$controller";
    }

    private function handleRemoved()
    {
        foreach ($this->exist_routes as $routes)
            if (!empty($routes))
                foreach ($routes as $route => $item) {
                    if ($item['active']) {
                        Routes::lock((int)$item['id']);
                        $this->handled_routes[$item['id']] = true;
                        self::output($route . ' locked');
                    }
                }
    }
}