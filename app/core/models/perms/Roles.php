<?php

namespace app\core\models\perms;

use app\core\dbal\SafeMysql;
use app\core\entities\Role;

class Roles
{
    const SYSTEM = -1;
    const SA = 1;
    const MANAGER = 2;
    const WORKER = 3;
    const SUPPLIER_ADMIN = 4;
    const SUPPLIER_USER = 5;

    const CONSTANT_LIST = [
        self::SA => 'Super Admin',
        self::MANAGER => 'Manager',
        self::WORKER => 'Worker',
        self::SUPPLIER_ADMIN => 'Admin',
        self::SUPPLIER_USER => 'User',
    ];

    const SIDE_WAREHOUSE = 'warehouse';
    const SIDE_CLIENT = 'client';

    const SIDES = [
        self::SIDE_WAREHOUSE => [
            self::SA, self::MANAGER, self::WORKER
        ],
        self::SIDE_CLIENT => [
            self::SUPPLIER_ADMIN, self::SUPPLIER_USER
        ]
    ];

    const ADMINS = [
        self::SA,
        self::MANAGER,
        self::SUPPLIER_ADMIN,
    ];
    const MANAGERS = [
        self::SA,
        self::MANAGER,
    ];

    private static function inSide($role_id, $side): bool
    {
        if (!array_key_exists($side, self::SIDES))
            return false;
        if (in_array($role_id, self::SIDES[$side]))
            return true;
        $role = self::getById($role_id);
        if (empty($role))
            return false;

        return $role->getSide() === $side;
    }

    public static function inWarehouseSide($role_id):bool
    {
        return self::inSide($role_id, self::SIDE_WAREHOUSE);
    }

    public static function inClientSide($role_id):bool
    {
        return self::inSide($role_id, self::SIDE_CLIENT);
    }

    public static function getConstantRoleKeys(): array
    {
        return array_keys(self::CONSTANT_LIST);
    }

    public static function existConstantRoleId($role_id): bool
    {
        return in_array($role_id, self::getConstantRoleKeys());
    }

    public static function getConstantRoleName($role_id): string
    {
        return self::CONSTANT_LIST[$role_id] ?? '';
    }

    public static function getById($id): ?Role
    {
        $id = (int)$id;
        static $roles = [];
        if (!array_key_exists($id, $roles)) {
            $res = (new SafeMysql())->fetchOne(
                'SELECT * FROM roles WHERE id = :id',
                [
                    'id' => (int) $id,
                ]
            );
            $roles[$id] = !empty($res) ? new Role($res) : null;
        }
        return $roles[$id];
    }

    public static function getNameById($id): ?string
    {
        static $role_names = [];
        if (array_key_exists($id, $role_names))
            return $role_names[$id];
        $role = self::getById($id);
        if (empty($role))
            return '';
        $role_names[$id] = $role->getName();
        return $role_names[$id];
    }

    public static function getByName($role_name) : ?Role
    {
        $res = (new SafeMysql())->fetchOne(
            'SELECT * FROM roles WHERE name = :name',
            [
                'name' => trim($role_name),
            ]
        );
        return !empty($res) ? new Role($res) : null;
    }

    public static function isAdmin($role_id) : bool
    {
        return in_array($role_id, self::ADMINS);
    }

    public static function isManager($role_id) : bool
    {
        return in_array($role_id, self::MANAGERS);
    }
}