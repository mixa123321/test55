<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\UserGroupRole;

class UserGroupRoles
{

    public static function checkUnique(UserGroupRole $userGroupRole): bool
    {
        return !!(new SafeMysql())->fetchScalar(
            'SELECT 1 FROM user_group_roles WHERE user_group_id = :user_group_id AND role_id = :role_id',
            ['user_group_id' => $userGroupRole->getUserGroupId(), 'role_id' => $userGroupRole->getRoleId()]
        );
    }

    public static function create($user_group_id, $role_id): ?UserGroupRole
    {
        $userGroupRole = new UserGroupRole;
        $userGroupRole->setUserGroupId($user_group_id);
        $userGroupRole->setRoleId($role_id);
        if (self::checkUnique($userGroupRole))
            return null;
        $userGroupRole->save(['user_group_id', 'role_id']);
        return $userGroupRole;
    }

    public static function delete(UserGroupRole $userGroupRole): void
    {
        (new SafeMysql())->execute('DELETE FROM user_group_roles WHERE user_group_id = :user_group_id AND role_id = :role_id', [
            'user_group_id' => $userGroupRole->getUserGroupId(),
            'role_id' => $userGroupRole->getRoleId(),
        ]);
    }

}