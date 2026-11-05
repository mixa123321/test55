<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\User;
use app\core\helpers\Arr;

class Accesses extends \app\core\models\perms\Accesses
{
    public static function getAccessesByRoles($route_id): array
    {
        $items = (new SafeMysql())->fetchAll(
            '
                SELECT *
                FROM route_role_accesses
                WHERE route_id = :route_id
            ',
            [
                'route_id' => (int) $route_id,
            ]
        );
        if (empty($items))
            return [];
        array_walk($items, function(&$item) {
            if (!in_array($item['access'], self::getListForRoles()))
                $item['access'] = self::PERM_NONE;
        });
        return Arr::map($items, 'role_id', 'access');
    }

    public static function getAccessesByUserGroups($route_id): array
    {
        $items = (new SafeMysql())->fetchAll(
            '
                SELECT *
                FROM route_user_group_accesses
                WHERE route_id = :route_id
            ',
            [
                'route_id' => (int) $route_id,
            ]
        );
        array_walk($items, function(&$item) {
            if (!in_array($item['access'], self::getListForUserGroups()))
                $item['access'] = self::PERM_NONE;
        });
        if (empty($items))
            return [];
        return Arr::map($items, 'user_group_id', 'access');
    }

    public static function getAccessesByUsers($route_id): array
    {
        $items = (new SafeMysql())->fetchAll(
            '
                SELECT *
                FROM route_user_accesses
                WHERE route_id = :route_id
            ',
            [
                'route_id' => (int) $route_id,
            ]
        );
        if (empty($items))
            return [];
        array_walk($items, function(&$item) {
            if (!in_array($item['access'], self::getListForUsers()))
                $item['access'] = self::PERM_NONE;
        });
        return Arr::map($items, 'user_id', 'access');
    }

    public static function getAccessedUsersForRoute($route_id)
    {
        $users = (new SafeMysql())->fetchAll(
            '
                SELECT u.*
                FROM route_user_accesses rua
                JOIN users u ON rua.user_id = u.id
                WHERE rua.route_id = :route_id
                ORDER BY rua.id
            ',
            [
                'route_id' => (int) $route_id,
            ]
        );
        return array_map(function ($user) {
            return new User($user);
        }, $users);
    }
}
