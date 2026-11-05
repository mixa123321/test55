<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Route;

class Routes
{
    static $exception_redirect = [
        'BarcodeCreator' => 'housekeeping/barcode_creator',
        'goods_in/unexpected_noASN' => 'goods_in/unexpected_no_ASN',
        'cart/unexpected_noASN' => 'cart/unexpected_no_ASN',
        'goods_in/unexpected_noASN_end' => 'goods_in/unexpected_no_ASN_end',
        'goods_in/unexpected_noASN_again' => 'goods_in/unexpected_no_ASN_again',
        'locations/print_bar_codes' => 'locations/print_barcodes',
    ];

    public static function create(string $name, bool $editable = false, int $parent_id = null): ?Route
    {
        $fields = ['name'];
        if (empty($name))
            return null;
        $route = new Route();
        $route->setName(strtolower($name));
        if ($editable) {
            $fields[] = 'editable';
            $route->setEditable(true);
        }
        if (!is_null($parent_id)) {
            $fields[] = 'parent_id';
            $route->setParentId($parent_id);
        }
        $route->save($fields);
        RouteHistories::create($route);
        return $route;
    }

    public static function get($id): ?Route
    {
        $data = (new SafeMysql())->fetchOne('SELECT * FROM routes WHERE id = :id', ['id' => (int)$id]);
        return !empty($data) ? new Route($data) : null;
    }

    public static function getChildren($route_id): array
    {
        $routes = (new SafeMysql())->fetchAll('SELECT * FROM routes WHERE parent_id = :parent_id', ['parent_id' => (int)$route_id]);
        return array_map(function ($route) {
            return new Route($route);
        }, $routes);
    }

    public static function lock(int $id)
    {
        (new SafeMysql())->execute('UPDATE routes SET is_active = 0 WHERE id = :id', ['id' => $id]);
        RouteHistories::lock($id);
    }

    public static function unlock(int $id)
    {
        (new SafeMysql())->execute('UPDATE routes SET is_active = 1 WHERE id = :id', ['id' => $id]);
        RouteHistories::unlock($id);
    }

    public static function find($name): ?Route
    {
        static $routes = [];
        if (array_key_exists($name, $routes))
            return $routes[$name];
        $data = (new SafeMysql())->fetchOne("SELECT * FROM routes WHERE name = :name", ['name' => strtolower($name)]);
        return !empty($data) ? ($routes[$name] = new Route($data)) : null;
    }

    public static function findByMain($main): array
    {
        $lower = strtolower($main);
        $data = (new SafeMysql())->fetchAll("SELECT * FROM routes WHERE name like :pattern OR name = :name", ['pattern' => $lower . '/%', 'name' => $lower]);
        if (empty($data))
            return [];
        $res = [];
        foreach ($data as $item)
            $res[$item['id']] = new Route($item);
        return $res;
    }

    public static function destroySettings($route_id)
    {
        $route_id = (int)$route_id;
        $mysql = new SafeMysql();
        $mysql->execute('DELETE FROM route_role_accesses WHERE route_id = :route_id', ['route_id' => $route_id]);
        $mysql->execute('DELETE FROM route_user_group_accesses WHERE route_id = :route_id', ['route_id' => $route_id]);
        $mysql->execute('DELETE FROM route_user_accesses WHERE route_id = :route_id', ['route_id' => $route_id]);
    }

    public static function getHeadRoute(Route $route): Route
    {
        if (empty($route->getParentId()))
            return $route;
        $parent_route = self::get($route->getParentId());
        if (empty($parent_route))
            return $route;
        return self::getHeadRoute($parent_route);
    }

    public static function getHeadsRoute(Route $route, &$arr = []): array
    {
        $arr[] = $route->getId();
        if (empty($route->getParentId()))
            return $arr;
        $parent_route = self::get($route->getParentId());
        if (empty($parent_route))
            return $arr;
        return self::getHeadsRoute($parent_route, $arr);
    }
}