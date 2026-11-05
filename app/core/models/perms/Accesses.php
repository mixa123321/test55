<?php

namespace app\core\models\perms;

use app\core\dbal\SafeMysql;
use app\core\entities\RouteRoleAccess;
use app\core\entities\RouteUserAccess;
use app\core\entities\RouteUserGroupAccess;
use app\core\interfaces\AccessObjectEntity;

class Accesses
{
    const PERM_NONE = 0;
    const PERM_ACCESS = 1;
    const PERM_READ = 2;
    const PERM_USER_SELECT_FOR_ROLE = 5;
    const PERM_REJECT = 9;

    const LIST = [
        self::PERM_NONE => 'none',
        self::PERM_ACCESS => 'access',
        self::PERM_READ => 'read only',
        self::PERM_REJECT => 'reject',
        self::PERM_USER_SELECT_FOR_ROLE => 'user select',
    ];

    const COLOR_LIST = [
        self::PERM_NONE => 'secondary',
        self::PERM_ACCESS => 'success',
        self::PERM_READ => 'primary',
        self::PERM_REJECT => 'danger',
        self::PERM_USER_SELECT_FOR_ROLE => 'purple',
    ];

    public static function getListForRoles(): array
    {
        return array_keys(self::LIST);
    }

    public static function getListForUserGroups(): array
    {
        return self::getListForUsers();
    }

    public static function getListForUsers(): array
    {
        $list = self::LIST;
        unset($list[self::PERM_USER_SELECT_FOR_ROLE]);
        return array_keys($list);
    }

    public static function name($access): string
    {
        return self::LIST[$access] ?? '';
    }

    public static function color($access): string
    {
        return self::COLOR_LIST[$access] ?? '';
    }

    public static function getRoleAccess($route_id, $role_id): ?RouteRoleAccess
    {
        $item = (new SafeMysql())->fetchOne(
            '
                SELECT *
                FROM route_role_accesses
                WHERE role_id = :role_id
                AND route_id = :route_id
            ',
            [
                'role_id' => (int) $role_id,
                'route_id' => (int) $route_id,
            ]
        );
        return !empty($item) ? new RouteRoleAccess($item) : null;
    }

    public static function setRoleAccess($route_id, $role_id, $access, $create_user_id = -1, $addition = [])
    {
        $access = self::handleAccess($access);
        $item = self::getRoleAccess($route_id, $role_id);
        $old_access = null;
        if (!empty($item)) {
            $old_access = $item->getAccess();
            $item->setAccess($access);
            $item->save(['id', 'access']);
        } else {
            $data = [
                'route_id' => $route_id,
                'role_id' => $role_id,
                'access' => $access,
            ];
            $item = new RouteRoleAccess($data);
            $item->save(array_keys($data));
        }
        if (!is_null($old_access))
            $addition = array_merge(['old_access' => $old_access], $addition);
        RouteAccessHistories::set($route_id, AccessObjectEntity::OBJECT_ROLE,
                                    $role_id, $access, $create_user_id, $addition);
    }

    public static function handleAccess($access): int
    {
        if (!array_key_exists($access, self::LIST))
            $access = self::PERM_NONE;
        return (int)$access;
    }

    public static function getUserGroupAccess($route_id, $user_group_id): ?RouteUserGroupAccess
    {
        $item = (new SafeMysql())->fetchOne(
            '
                SELECT *
                FROM route_user_group_accesses
                WHERE user_group_id = :user_group_id
                AND route_id = :route_id
            ',
            [
                'user_group_id' => (int) $user_group_id,
                'route_id' => (int) $route_id,
            ]
        );
        return !empty($item) ? new RouteUserGroupAccess($item) : null;
    }

    public static function setUserGroupAccess($route_id, $user_group_id, $access, $create_user_id = -1, $addition = [])
    {
        $access = self::handleAccess($access);
        if ($access === self::PERM_USER_SELECT_FOR_ROLE)
            $access = self::PERM_NONE;
        $item = self::getUserGroupAccess($route_id, $user_group_id);
        $old_access = null;
        if (!empty($item)) {
            $old_access = $item->getAccess();
            $item->setAccess($access);
            $item->save(['id', 'access']);
        } else {
            $data = [
                'route_id' => $route_id,
                'user_group_id' => $user_group_id,
                'access' => $access,
            ];
            $item = new RouteUserGroupAccess($data);
            $item->save(array_keys($data));
        }
        if (!is_null($old_access))
            $addition = array_merge(['old_access' => $old_access], $addition);
        RouteAccessHistories::set($route_id, AccessObjectEntity::OBJECT_USER_GROUP,
            $user_group_id, $access, $create_user_id, $addition);
    }

    public static function getUserAccess($route_id, $user_id): ?RouteUserAccess
    {
        $item = (new SafeMysql())->fetchOne(
            '
                SELECT *
                FROM route_user_accesses
                WHERE user_id = :user_id
                AND route_id = :route_id
            ',
            [
                'user_id' => (int) $user_id,
                'route_id' => (int) $route_id,
            ]
        );
        return !empty($item) ? new RouteUserAccess($item) : null;
    }

    public static function setUserAccess($route_id, $user_id, $access, $create_user_id = -1, $addition = [])
    {
        $access = self::handleAccess($access);
        if ($access === self::PERM_USER_SELECT_FOR_ROLE)
            $access = self::PERM_REJECT;

        $item = self::getUserAccess($route_id, $user_id);
        $old_access = null;
        if (!empty($item)) {
            $old_access = $item->getAccess();
            $item->setAccess($access);
            $item->save(['id', 'access']);
            if (!is_null($old_access))
                $addition = array_merge(['old_access' => $old_access], $addition);
            RouteAccessHistories::set($route_id, AccessObjectEntity::OBJECT_USER,
                $user_id, $access, $create_user_id, $addition);
        } else {
            $data = [
                'route_id' => $route_id,
                'user_id' => $user_id,
                'access' => $access,
            ];
            $item = new RouteUserAccess($data);
            $item->save(array_keys($data));
            $addition = array_merge(['event' => 'addUser'], $addition);
            RouteAccessHistories::set($route_id, AccessObjectEntity::OBJECT_USER,
                $user_id, $access, $create_user_id, $addition);
        }
    }

    public static function removeRouteUserAccess($route_id, $user_id, $create_user_id)
    {
        $user_access = self::getUserAccess($route_id, $user_id);
        if (empty($user_access))
            return;
        RouteAccessHistories::set($user_access->getRouteId(), AccessObjectEntity::OBJECT_USER, $user_access->getUserId(),
            null, $create_user_id, [
                'event' => 'removeRouteUserAccess',
                'old_access' => $user_access->getAccess()
            ]
        );
        (new SafeMysql())->execute(
            '
                DELETE FROM route_user_accesses
                WHERE user_id = :user_id
                AND route_id = :route_id
            ',
            [
                'user_id' => (int) $user_access->getUserId(),
                'route_id' => (int) $user_access->getRouteId(),
            ]
        );
    }

    public static function isReject($access): bool
    {
        return $access == self::PERM_REJECT;
    }

    public static function isUserSelect($access): bool
    {
        return $access == self::PERM_USER_SELECT_FOR_ROLE;
    }
}