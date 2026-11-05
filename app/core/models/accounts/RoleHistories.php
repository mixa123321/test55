<?php

namespace app\core\models\accounts;

use app\core\entities\Role;
use app\core\interfaces\AccessObjectEntity;

class RoleHistories extends AccountHistories
{
    public static function create(Role $role, $user_id)
    {
        $values = $role->toArray();
        foreach ($values as $field => $value) {
            if (empty($value))
                unset($values[$field]);
        }
        self::storeCreate($role->getId(), AccessObjectEntity::OBJECT_ROLE, ['role' => $values], $user_id);
    }

    public static function update(Role $role, Role $old_role, $user_id)
    {
        $diff = self::handleDifference($role, $old_role);
        if (empty($diff))
            return;
        self::storeUpdate($role->getId(), AccessObjectEntity::OBJECT_ROLE, $diff, $user_id);
    }
}