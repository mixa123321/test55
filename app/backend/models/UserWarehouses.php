<?php

namespace app\backend\models;

use app\core\db\Mysql;
use app\core\dbal\SafeMysql;
use app\core\models\accounts\UserHistories;

class UserWarehouses extends \app\core\models\UserWarehouses
{
    public static function getAvailableWarehouses(int $user_id): array
    {
        return (new SafeMysql())->fetchAll("
            SELECT w.*
            FROM warehouses w
                JOIN user_warehouses uw ON w.id = uw.warehouse_id AND uw.user_id = :user_id", ['user_id' => $user_id]);
    }

    public static function hasUserInWarehouse($user_id, $warehouse_id): bool
    {
        return !!(new SafeMysql())->fetchScalar('SELECT 1 FROM user_warehouses WHERE user_id = :user_id AND warehouse_id = :warehouse_id', [
            'user_id' => $user_id,
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addUserToWarehouse($user_id, $warehouse_id, $create_user_id)
    {
        if (self::hasUserInWarehouse($user_id, $warehouse_id))
            return;
        Mysql::saveRow('user_warehouses', [
            'user_id' => (int)$user_id,
            'warehouse_id' => (int)$warehouse_id
        ]);
        UserHistories::addToWarehouse($user_id, $warehouse_id, $create_user_id);
    }

    public static function removeUserFromWarehouse($user_id, $warehouse_id, $create_user_id): void
    {
        (new SafeMysql())->execute(
            'DELETE FROM user_warehouses WHERE user_id = :user_id AND warehouse_id = :warehouse_id',
            [
                'user_id' => (int)$user_id,
                'warehouse_id' => (int)$warehouse_id,
            ]
        );
        UserHistories::removeFromWarehouse($user_id, $warehouse_id, $create_user_id);
    }
}
