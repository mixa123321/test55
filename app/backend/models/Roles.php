<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Role;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\Validator;
use app\core\models\perms\AccessScope;

class Roles extends \app\core\models\perms\Roles
{
    const LIMIT_PER_PAGE = 20;

    public static $accessScope = [
       Roles::SA => [
           AccessScope::ACCESS_SUPERADMIN
       ],
       Roles::MANAGER => [
           AccessScope::ACCESS_WORKERS,
           AccessScope::ACCESS_CONTROL_PANEL,
           AccessScope::ACCESS_RECEPTION,
           AccessScope::ACCESS_MOVEMENT,
           AccessScope::ACCESS_PICKING,
           AccessScope::ACCESS_PACKING,
           AccessScope::ACCESS_INVENTORY,
           AccessScope::ACCESS_RETURNS,
           AccessScope::ACCESS_ORDERS,
       ],
       Roles::WORKER => [
           AccessScope::ACCESS_RECEPTION,
           AccessScope::ACCESS_MOVEMENT,
           AccessScope::ACCESS_PICKING,
           AccessScope::ACCESS_PACKING,
           AccessScope::ACCESS_INVENTORY,
           AccessScope::ACCESS_RETURNS,
       ],
       Roles::SUPPLIER_ADMIN => [
           AccessScope::ACCESS_USERS,
           AccessScope::ACCESS_CONTROL_PANEL,
           AccessScope::ACCESS_ORDERS,
           AccessScope::ACCESS_STOCK,
           AccessScope::ACCESS_API
       ],
       Roles::SUPPLIER_USER => [
           AccessScope::ACCESS_ORDERS,
           AccessScope::ACCESS_STOCK,
           AccessScope::ACCESS_API
       ],
   ];

    public static function validate(Role $role): array
    {
        $rules = [
            'name' => [
                'val' => $role->getName(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [4, 60]]
            ],
            'side' => [
                'val' => $role->getSide(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [0, 20]]
            ]
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        $exist_role = self::getByName($role->getName());
        if (!empty($exist_role) && $exist_role->getId() != $role->getId())
            $validator->addError("no_uniq_name", "Name must be unique", 'name');
        if (!array_key_exists($role->getSide(), self::SIDES))
            $validator->addError("no_side", "Side must be warehouse or client", 'side');
        return $validator->getErrors();
    }

    public static function getList($only_active = false): array
    {
        $sql = 'SELECT * FROM roles WHERE id <> -1';
        if ($only_active)
            $sql .= ' AND is_active = 1';
        $res = (new SafeMysql())->fetchAll($sql);
        return array_map(function($item) {
            return new Role($item);
        }, $res);
    }

    public static function getUsersByRole(int $lc_id, int $role_id): array
    {
        $sql = "
            SELECT u.*
            FROM users u
                JOIN persons p ON u.id = p.user_id AND p.lc_id = :lc_id 
            WHERE u.role_id = :role_id";
        $users = (new SafeMysql())->fetchAll($sql, ['lc_id' => $lc_id, 'role_id' => $role_id]);
        return array_map(function($user) {
            return new User($user);
        }, $users);
    }

    public static function existUsers(int $lc_id, int $role_id): bool
    {
        $sql = "
            SELECT 1
            FROM users u
                JOIN persons p ON u.id = p.user_id AND p.lc_id = :lc_id
            WHERE u.role_id = :role_id
            LIMIT 1";
        return !!(new SafeMysql())->fetchScalar($sql, ['lc_id' => $lc_id, 'role_id' => $role_id]);
    }

    public static function hasMultipleWarehouse($role_id): bool
    {
        $role = self::getById($role_id);
        if (empty($role))
            return false;
        return $role->isMultipleWarehouses();
    }

    public static function find($query): ?Role
    {
        $role = self::getById($query);
        if (!empty($role))
            return $role;
        foreach (self::CONSTANT_LIST as $role_id => $name)
            if (strcasecmp($query, $name) === 0)
                return self::getById($role_id);
        return null;
    }

    /** @deprecated  */
    public static function getAccessScopeHeaders($role_id): array
    {
        $res = [];
        foreach (self::$accessScope[$role_id] ?? [] as $access)
            $res[$access] = Users::$allScopes[$access] ?? '';
        return $res;
    }

    public static function changeMultipleWarehouses($lc_id, $role_id, $value, $auth_user_id)
    {
        $users = self::getUsersByRole($lc_id, $role_id);
        if (empty($users))
            return;
        array_walk($users, function ($user) use ($value, $auth_user_id) {
            /** @var $user User */
            $user_warehouses = UserWarehouses::getAvailableWarehouses($user->getId());
            if ($value) {
                $user_warehouse_ids = Arr::column($user_warehouses, 'id');
                if (!empty($user->getWarehouseId()) && !in_array($user->getWarehouseId(), $user_warehouse_ids))
                    UserWarehouses::addUserToWarehouse($user->getId(), $user->getWarehouseId(), $auth_user_id);
            } elseif (empty($user->getWarehouseId()) && !empty($user_warehouses))
                Users::changeWarehouse($user, $user_warehouses[0]['id']);
        });
    }

    public static function getLinkCreate(Role $role, $supplier_id = null): string
    {
        $role_name = Roles::getConstantRoleName($role->getId());
        if (empty($role_name))
            $role_name = $role->getId();
        $link = '/accounts/create_account?role=' . $role_name;
        if ($role->isSupplierUser()) {
            if (empty($supplier_id))
                return '/';
            $link .= '&supplier_id=' . (int)$supplier_id;
        }
        return $link;
    }

    public static function getLinkEdit($user_id): string
    {
        return '/accounts/edit_account?id=' . (int)$user_id;
    }
}
