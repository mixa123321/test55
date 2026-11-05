<?php


namespace app\core\models;

use app\core\entities\Role;
use app\core\helpers\Str;
use app\core\models\perms\Accesses;
use app\core\models\perms\Roles;

class RefinementActionLine
{

    private const CSV_DOMAIN = 'admin.' . BASE_DOMAIN;

    public const ACCESS_Y = 'Y';
    public const ACCESS_N = 'N';
    public const ACCESS_S = 'S';
    public const ACCESS_R = 'R';
    public const ACCESS_EXCEPT = 'E';

    public const ACCESSES = [
        self::ACCESS_Y => Accesses::PERM_ACCESS,
        self::ACCESS_N => Accesses::PERM_NONE,
        self::ACCESS_S => Accesses::PERM_USER_SELECT_FOR_ROLE,
        self::ACCESS_R => Accesses::PERM_READ,
        self::ACCESS_EXCEPT => Accesses::PERM_REJECT
    ];

    private $name;
    private $route;
    private $controller;
    private $method;

    public $role_perms = [];

    private static $head = [];
    private const ROW_INTERFACE = 'Interface';
    private const ROW_EP = 'URL';
    private const ROW_ROLE = 'Role';
    private static $required_fields = [
        self::ROW_INTERFACE,
        self::ROW_EP,
        self::ROW_ROLE,
    ];


    public static function handleHeader($head)
    {
        if (empty($head))
            return;
        foreach ($head as $key => $value) {
            if (($item_key = array_search($value, self::$required_fields)) !== false) {
                unset(self::$required_fields[$item_key]);
                self::$head[$value] = $key;
            } else {
                $role = Roles::getByName($value);
                if ($role instanceof Role) {
                    $role_key = array_search('Role', self::$required_fields);
                    unset(self::$required_fields[$role_key]);
                    self::$head[self::ROW_ROLE][$role->getId()] = $key;
                }
            }
        }
        if (!empty(self::$required_fields))
            throw new \Exception('Not found ' . implode(', ', self::$required_fields));
    }

    /**
     * @throws \Exception
     */
    public function __construct($row)
    {
        if (empty(self::$head))
            throw new \Exception('Empty header');
        $this->name = $row[self::$head[self::ROW_INTERFACE]] ?? '';
        $this->handleEndPoint($row[self::$head[self::ROW_EP]] ?? '');
        foreach (self::$head[self::ROW_ROLE] as $role_id => $role_key)
            $this->role_perms[$role_id] = $this->handlePerm($row[$role_key] ?? '');
    }

    private function handleEndPoint($end_point): void
    {
        $endPointArr = explode('/', $end_point);
        $method = array_pop($endPointArr);
        $controller = array_pop($endPointArr);

        if ($controller === self::CSV_DOMAIN) {
            $controller = $method;
            $method = 'index';
        }

        $methodGetArr = explode('?', $method);
        if (count($methodGetArr) > 1) {
            $method = array_shift($methodGetArr);
        }

        if (!empty($controller) && !empty($method)) {
            $this->setController(ucfirst(Str::toCamelCase($controller)));
            $this->setMethod(Str::toCamelCase($method));
            $this->setRoute($controller, $method);
        }
    }

    private function handlePerm($field)
    {
        return array_key_exists($field, self::ACCESSES)
            ? self::ACCESSES[$field]
            : self::ACCESSES[self::ACCESS_EXCEPT];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getController()
    {
        return $this->controller;
    }

    public function setController($controller): void
    {
        $this->controller = $controller;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function setMethod($method): void
    {
        $this->method = $method;
    }

    /**
     * @return array
     */
    public function getRolePerms(): array
    {
        return $this->role_perms;
    }

    public function getRoute()
    {
        return $this->route;
    }

    public function setRoute($part1, $part2): void
    {
        $this->route = strtolower($part1) . ($part2 !== 'index' ? '/' . strtolower($part2) : '');
    }


}