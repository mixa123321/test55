<?php

namespace app\core\models;

use app\backend\models\Pick;
use app\core\dbal\SafeMysql;
use app\core\dbal\SafeQueryBuilder;
use app\backend\models\Suppliers;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\models\perms\AccessScope;
use app\core\models\perms\Roles;
use app\core\models\Warehouses;

class Users
{
    public static $allScopes = [
        AccessScope::ACCESS_USERS => 'Manage users',
        AccessScope::ACCESS_CONTROL_PANEL => 'Access to control panel',
        AccessScope::ACCESS_ORDERS => 'Access to orders',
        AccessScope::ACCESS_STOCK => 'Access to stock',
        AccessScope::ACCESS_API => 'Send API requests',
        AccessScope::ACCESS_WORKERS => 'Manage workers',
        AccessScope::ACCESS_RECEPTION => 'Reception of goods in',
        AccessScope::ACCESS_MOVEMENT => 'Movement goods',
        AccessScope::ACCESS_PICKING => 'Picking parcels',
        AccessScope::ACCESS_PACKING => 'Packing parcels',
        AccessScope::ACCESS_INVENTORY => 'Inventory to stock',
        AccessScope::ACCESS_RETURNS => 'Return ASNs',
        AccessScope::ACCESS_ASNS => 'Create ASNs',
        AccessScope::ACCESS_SUPERADMIN => 'System superadmin',
    ];

    public static function getByLogin(string $login)
    {
        $login = addslashes($login);
        $user_info = (new SafeMysql())->fetchOne(
            "SELECT * FROM users WHERE login = :login LIMIT 1",
            ['login' => $login]
        );
        if (!empty($user_info))
            return new User($user_info);
        return null;
    }

    public static function getAll(Warehouse $warehouse, array $filter = [])
    {
        $qb = (new SafeQueryBuilder())
            ->select(['u.*'])
            ->from('users u')
            ->innerJoin('persons p', 'u.id = p.user_id')
            ->where('p.lc_id', '=', (int) $warehouse->getLCId())
            ->where('u.warehouse_id', '=', (int) $warehouse->getId());

        if (!empty($filter['parent_id'])) {
            $qb->where('u.parent_id', '=', (int) $filter['parent_id']);
        }

        if (!empty($filter['user_group_id'])) {
            $userGroupId = (int) $filter['user_group_id'];

            $qb->whereRaw(
                '(u.id = :user_group_id OR u.parent_id = :user_group_id)',
                ['user_group_id' => $userGroupId]
            );
        }

        if (
            isset($filter['status'])
            && in_array(
                (int) $filter['status'],
                [User::USER_STATUS_BLOCKED, User::USER_STATUS_ACTIVE],
                true
            )
        ) {
            $qb->where('u.status', '=', (int) $filter['status']);
        }

        if (!empty($filter['ids']) && is_array($filter['ids'])) {
            $ids = array_values(array_map('intval', $filter['ids']));

            if ($ids !== []) {
                $qb->whereIn('u.id', $ids);
            }
        }

        return (new SafeMysql())->fetchAssocWithKeyQuery($qb->build(), 'id');
    }

    public static function getWorkersPicking(Warehouse $warehouse, $picking_time, $type_id, $mezzanine)
    {
        $users_data = self::getAll($warehouse);
        if (empty($users_data))
            return [];
        $picking_time = (int)$picking_time;
        $res = [];
        foreach ($users_data as $user_data) {
            $user = new User($user_data);
            if (!$user->isRoleWorker())
                continue;
            if (empty($user->getPickingData()))
                continue;
            $picking_data = $user->getPickingData();
            if (isset($picking_data['mezzanine']) && $picking_data['mezzanine'] != $mezzanine)
                continue;
            if (empty($picking_data['type_id']) || $picking_data['type_id'] != $type_id)
                continue;
            $minutes = ($picking_time - $picking_data['date'])/60;
            if ($minutes > Pick::PICKING_TIME_DELAY)
                continue;
            $res[$picking_data['zone']][] = $user->getLogin();
        }
        return $res;
    }

    public static function getAllByParentId($warehouse_id, $parent_id)
    {
        return (new SafeMysql())->fetchArrayOneField(
            "SELECT id FROM users WHERE parent_id = :parent_id AND warehouse_id = :warehouse_id", 
            ['warehouse_id' => (int)$warehouse_id, 'parent_id' => (int)$parent_id]
        );
    }

    public static function getAllRoles(int $lc_id, int $role_id, $person = false)
    {
        $select = ['u.*'];

        if ($person) {
            $select[] = 'p.email';
            $select[] = 'p.phone';
        }

        $query = (new SafeQueryBuilder())
            ->select($select)
            ->from('users u')
            ->innerJoin('persons p', 'u.id = p.user_id')
            ->where('p.lc_id', '=', $lc_id)
            ->where('u.role_id', '=', $role_id)
            ->build();

        return (new SafeMysql())->fetchAssocWithKeyQuery($query, 'id');
    }

    public static function getAllAdmins(int $lc_id)
    {
        $admins = self::getAllRoles($lc_id, Roles::SUPPLIER_ADMIN);
        return Arr::maps($admins, 'id', ['id', 'login', 'status']);
    }

    public static function getAllWorkers($warehouse_id): array
    {
        $users = [];
        $all_users = (new SafeMysql())->fetchAssocWithKey(
            'SELECT * FROM users WHERE status = :status AND warehouse_id = :warehouse_id',
            [
                'status' => User::USER_STATUS_ACTIVE,
                'warehouse_id' => (int) $warehouse_id,
            ],
            'id'
        );
        if (empty($all_users))
            return $users;
        foreach ($all_users as $user_id => $user) {
            if ((new User($user))->isRoleWorker())
                $users[$user_id] = $user['login'];
        }
        return $users;
    }

    public static function getById($id) : ?User
    {
        $id = (int)$id;
        static $users = [];
        if (!array_key_exists($id, $users)) {
            $data = (new SafeMysql())->fetchOne(
                'SELECT * FROM users WHERE id = :id',
                [
                    'id' => (int) $id,
                ]
            );
            $users[$id] = $data ? new User($data) : null;
        }
        return $users[$id];
    }

    public static function getByIds(array $ids)
    {
        $res = [];
        if (!empty($ids)) {
            $query = (new SafeQueryBuilder())
                ->select(['id', 'login'])
                ->from('users')
                ->whereIn('id', array_map('intval', $ids))
                ->build();

            $users = (new SafeMysql())->fetchAssocWithKeyQuery($query, 'id');
            foreach ($ids as $id)
                $res[$id] = empty($users[$id]) ? 'System' : $users[$id]['login'];
        }
        return $res;
    }

    public static function getUserBySupplierId($supplier_id, $only_active = false)
    {
        $sql = '
            SELECT u.*, u2.login AS login_parent, u2.status AS active_parent
            FROM users u
            JOIN supplier_users su ON u.id = su.user_id AND su.supplier_id = :supplier_id
            LEFT JOIN users u2 ON u.parent_id = u2.id
        ';

        $params = [
            'supplier_id' => (int) $supplier_id,
        ];

        if ($only_active) {
            $sql .= ' WHERE u.status = :user_status_active AND u2.status = :parent_status_active';
            $params['user_status_active'] = User::USER_STATUS_ACTIVE;
            $params['parent_status_active'] = User::USER_STATUS_ACTIVE;
        }

        $user_info = (new SafeMysql())->fetchAll($sql, $params);

        if (!empty($user_info)) {
            $users = [];
            foreach ($user_info as $user)
                $users[$user['id']] = $user;
            return $users;
        }
        return [];
    }

    public static function getUsersForBulkLock($lc_id, array $ids): array
    {
        if (!$ids)
            return [];
        $ids = array_values(array_map('intval', $ids));

        $query = (new SafeQueryBuilder())
            ->select(['u.id', 'u.status', 'u.warehouse_id', 'su.supplier_id'])
            ->from('users u')
            ->innerJoin('supplier_users su', 'u.id = su.user_id')
            ->whereIn('su.supplier_id', $ids)
            ->build();

        $users_items = (new SafeMysql())->fetchAllQuery($query);

        $query = (new SafeQueryBuilder())
            ->select(['s.user_id AS id', 'u.status', 'u.warehouse_id', 's.id AS supplier_id'])
            ->from('suppliers s')
            ->innerJoin('users u', 'u.id = s.user_id AND u.role_id = :role_id')
            ->whereIn('s.id', $ids)
            ->build()
            ->withParams([
                'role_id' => Roles::SUPPLIER_ADMIN,
            ]);

        $admin_items = (new SafeMysql())->fetchAllQuery($query);

        $warehouse_id_to_name = $users = $admins_suppliers = $suppliers_admins = $suppliers_active = [];
        foreach (Warehouses::getAll($lc_id) as $item)
            $warehouse_id_to_name[(int)$item['id']] = $item['name'];
        foreach ($users_items as $item) {
            $status = (int)$item['status'];
            $supplier_id = (int)$item['supplier_id'];
            $warehouse_id = (int)$item['warehouse_id'];
            if (!$warehouse_id || !$status) //добавляем только активных пользователей
                continue;
            $id = (int)$item['id'];
            if (!isset($users[$warehouse_id]))
                $users[$warehouse_id] = [];
            if (!isset($users[$warehouse_id][$supplier_id]))
                $users[$warehouse_id][$supplier_id] = [];
            $users[$warehouse_id][$supplier_id][$id] = $id;
            if (!isset($suppliers_active[$supplier_id]))
                $suppliers_active[$supplier_id] = [];
            $suppliers_active[$supplier_id][$warehouse_id] = $warehouse_id_to_name[$warehouse_id];
        }
        foreach ($admin_items as $item) {
            $status = (int)$item['status'];
            $supplier_id = (int)$item['supplier_id'];
            $warehouse_id = (int)$item['warehouse_id'];
            $id = (int)$item['id'];
            if (!$warehouse_id || !$status) //добавляем только активных админов
                continue;
            if (!isset($suppliers_active[$supplier_id])) //добавляем только админов с активными пользователями
                continue;
            if (count($suppliers_active[$supplier_id]) > 1) //добавляем если у админа есть активные пользователи, только на одном складе
                continue;
            if (!isset($admins_suppliers[$id]))
                $admins_suppliers[$id] = [];
            $admins_suppliers[$id][] = $supplier_id;
        }
        foreach ($admins_suppliers as $id => $suppliers) {
            if (count($suppliers) > 1) //добавляем админа только если активные пользователи у одного supplier
                continue;
            $suppliers_admins[$suppliers[0]] = $id;
        }
        return ['users' => $users, 'admins' => $suppliers_admins, 'suppliers_active' => $suppliers_active];
    }

    public static function getWarehouseIdById($id)
    {
        return (new SafeMysql())->fetchScalar(
            'SELECT warehouse_id FROM users WHERE id = :id LIMIT 1',
            [
                'id' => (int) $id,
            ]
        );
    }

    public static function getAvailableUsers(Warehouse $warehouse, $user)
    {
        /** @var User $user */
        if (!$user)
            return [];
        $filters = [];
        switch ($user->getRoleId()) {
            case Roles::SUPPLIER_ADMIN:
            case Roles::MANAGER:
                $filters['user_group_id'] = $user->getId();
                break;
            case Roles::SUPPLIER_USER:
            case Roles::WORKER:
                $filters['ids'][] = $user->getId();
                break;
        }
        return self::getAll($warehouse, $filters);
    }

    public static function getAllUsers(int $lc_id): array
    {
        $users = (new SafeMysql())->fetchAll(
            '
                SELECT u.*
                FROM users u
                    JOIN persons p ON u.id = p.user_id AND p.lc_id = :lc_id
            ',
            [
                'lc_id' => (int) $lc_id,
            ]
        );
        if (empty($users))
            return [];
        return array_map(function ($user) {
            return new User($user);
        }, $users);
    }

    public static function getLogoForUser(int $user_id)
    {
        $supplierRes = Suppliers::getSuppliersIdByUsers(0, $user_id);
        if (empty($supplierRes))
            return '';
        $supplier_id = array_values($supplierRes)[0]['id'];
        $logo = (new SafeMysql())->fetchArrayOneField(
            'SELECT logo FROM suppliers WHERE id = :supplier_id',
            [
                'supplier_id' => (int) $supplier_id,
            ]
        );
        if (!empty($logo) && $logo[0] != '')
            return Suppliers::showLogoSrc($logo[0]);
    }

    public static function findUser(int $lc_id, $query): array
    {
        return (new SafeMysql())->fetchAll(
            '
                SELECT u.*, r.name AS role
                FROM users u
                    JOIN persons p ON u.id = p.user_id AND p.lc_id = :lc_id
                    LEFT JOIN roles r ON r.id = u.role_id
                WHERE u.login LIKE :login_prefix
                AND u.role_id != :role_id
            ',
            [
                'lc_id' => (int) $lc_id,
                'login_prefix' => $query . '%',
                'role_id' => Roles::SA,
            ]
        );
    }

    public static function changeWarehouse(User $user, int $warehouse_id)
    {
        $user->setWarehouseId($warehouse_id);
        $user->save(['id', 'warehouse_id']);
    }

    public static function changeLang(User $user, int $lang_id)
    {
        $user->setLangId($lang_id);
        $user->save(['id', 'lang_id']);
    }

    public static function system(): User
    {
        return new User(['id' => User::ID__SYSTEM]);
    }
}
