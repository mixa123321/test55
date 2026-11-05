<?php

namespace app\core\models\accounts;

use app\core\entities\AccountHistory;
use app\core\entities\User;
use app\core\interfaces\AccessObjectEntity;

class UserHistories extends AccountHistories
{
    public static function create(User $user, $person_id, $create_user_id): void
    {
        $additional = ['user' => $user->toArray()];
        if (!empty($person_id))
            $additional['person_id'] = $person_id;
        self::storeCreate($user->getId(), AccessObjectEntity::OBJECT_USER, $additional, $create_user_id);
    }

    public static function update(User $user, User $old_user, $update_user_id, $field_to_save = [])
    {
        $except = ['id', 'pass', 'role_id', 'cart_id', 'access_scope'];
        if (!empty($field_to_save)) {
            $all_fields = array_keys($user->toArray());
            foreach ($all_fields as $field)
                if (!in_array($field, $field_to_save) && !in_array($field, $except))
                    $except[] = $field;
        }
        $diff = self::handleDifference($user, $old_user, $except);
        if (empty($diff))
            return;
        self::storeUpdate($user->getId(), AccessObjectEntity::OBJECT_USER, $diff, $update_user_id);
    }

    public static function addToWarehouse($user_id, $warehouse_id, $update_user_id)
    {
        self::store(self::initData($user_id, AccessObjectEntity::OBJECT_USER, AccountHistory::EVENT_ADD,
            ['warehouse_id' => $warehouse_id], $update_user_id));
    }

    public static function removeFromWarehouse($user_id, $warehouse_id, $update_user_id)
    {
        self::store(self::initData($user_id, AccessObjectEntity::OBJECT_USER, AccountHistory::EVENT_DELETE,
            ['warehouse_id' => $warehouse_id], $update_user_id));
    }

    public static function addToGroup($user_id, $user_group_id, $update_user_id)
    {
        self::store(self::initData($user_id, AccessObjectEntity::OBJECT_USER, AccountHistory::EVENT_ADD,
            ['user_group_id' => $user_group_id], $update_user_id));
    }

    public static function removeFromGroup($user_id, $user_group_id, $update_user_id)
    {
        self::store(self::initData($user_id, AccessObjectEntity::OBJECT_USER, AccountHistory::EVENT_DELETE,
            ['user_group_id' => $user_group_id], $update_user_id));
    }
}