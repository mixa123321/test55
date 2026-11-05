<?php

namespace app\core\models\perms;

use app\core\dbal\SafeMysql;
use app\core\entities\AccountHistory;
use app\core\entities\UserGroup;
use app\core\interfaces\AccessObjectEntity;
use app\core\models\accounts\AccountHistories;

class UserGroups
{

    public static function getById($id): ?UserGroup
    {
        $res = (new SafeMysql())->fetchOne(
            'SELECT * FROM user_groups WHERE id = :id LIMIT 1',
            [
                'id' => (int) $id,
            ]
        );
        return !empty($res) ? new UserGroup($res) : null;
    }

    public static function create($name, $accesses = []): int
    {
        $fields = ['name'];
        $user_group = new UserGroup(['name' => $name]);
        if (!empty($accesses)) {
            $fields[] = 'access_scope';
            $user_group->setAccessScope($accesses);
        }
        $user_group->save($fields);

        return (int)$user_group->getId();
    }

    public static function remove(UserGroup $user_group, $user_id): bool
    {
        $id = (int) $user_group->getId();
        (new SafeMysql())->execute(
            'DELETE FROM user_groups WHERE id = :id',
            [
                'id' => $id,
            ]
        );
        AccountHistories::delete($id, AccessObjectEntity::OBJECT_USER_GROUP, $user_group, $user_id);
        return true;
    }

    public static function hasUsers($id): bool
    {
        return (bool) (new SafeMysql())->fetchScalar(
            'SELECT 1 FROM user_groups_map WHERE group_id = :group_id LIMIT 1',
            [
                'group_id' => (int) $id,
            ]
        );
    }

    public static function getActiveGroupIdsByUserId($user_id): array
    {
        return (new SafeMysql())->fetchArrayOneField(
            '
                SELECT ugm.group_id
                FROM user_groups_map ugm
                JOIN user_groups ug ON ug.id = ugm.group_id
                WHERE ugm.user_id = :user_id
                AND ug.is_active
            ',
            [
                'user_id' => (int) $user_id,
            ]
        );
    }
}
