<?php

namespace app\core\models\accounts;

use app\core\entities\UserGroup;
use app\core\interfaces\AccessObjectEntity;

class UserGroupHistories extends AccountHistories
{
    public static function create(UserGroup $userGroup, $user_id, $roles)
    {
        $values = $userGroup->toArray();
        foreach ($values as $field => $value) {
            if (empty($value) || $field === 'access_scope')
                unset($values[$field]);
        }
        self::storeCreate($userGroup->getId(), AccessObjectEntity::OBJECT_USER_GROUP, ['user_group' => $values, 'roles' => $roles], $user_id);
    }

    public static function update(UserGroup $userGroup, UserGroup $old_userGroup, $user_id, $add_roles = [], $remove_roles = [])
    {
        $diff = self::handleDifference($userGroup, $old_userGroup, ['access_scope']);
        if (!empty($add_roles))
            $diff['add_roles'] = $add_roles;
        if (!empty($remove_roles))
            $diff['remove_roles'] = $remove_roles;
        if (empty($diff))
            return;
        self::storeUpdate($userGroup->getId(), AccessObjectEntity::OBJECT_USER_GROUP, $diff, $user_id);
    }
}