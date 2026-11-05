<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\db\QueryBuilder;
use app\core\entities\Person;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\Validator;
use app\core\models\Warehouses;

class Users extends \app\core\models\Users
{
    const LIMIT_PER_PAGE = 10;
    const FILTER_PARAM = 'users_filters';

    public static function isWarehouse(User $user, Warehouse $warehouse)
    {
        if (empty($user))
            return null;
        if ($user->isRoleSA())
            return in_array($warehouse->getId(), Warehouses::getAllId($warehouse->getLCId()));
        elseif ($user->isRoleSupplierAdmin())
            return in_array($warehouse->getId(), self::getAllWarehouseAdmin($user->getId()));
        if ($user->hasMultipleWarehouse())
            return in_array($warehouse->getId(), Arr::column(UserWarehouses::getAvailableWarehouses($user->getId()), 'id'));
        return $user->getWarehouseId() == $warehouse->getId();
    }

    public static function getAllWarehouseAdmin($user_id)
    {
        $user_id = (int)$user_id;
        $admin_role_id = Roles::SUPPLIER_ADMIN;
        $sql = "SELECT DISTINCT sup.warehouses
                FROM users u
                    JOIN suppliers sup ON (u.id = sup.user_id OR u.parent_id = sup.user_id)
                WHERE u.id = :user_id AND u.role_id = :admin_role_id";
        $warehouses = (new SafeMysql())->fetchArrayOneField($sql, ['user_id' => $user_id, 'admin_role_id' => $admin_role_id]);
        $res = [];
        foreach ($warehouses as $warehouse)
            if (!empty(($tmps = json_decode($warehouse))))
                foreach ($tmps as $tmp)
                    if (!in_array($tmp, $res))
                        $res[] = $tmp;
        return $res;
    }

    public static function authControlPanel($login, $pass)
    {
        $user = self::getByLogin($login);
        if ($user === null || !password_verify($pass, $user->getPass()) || !$user->isActive())
            return false;
        $_SESSION['authLogin'] = $user->getLogin();
        $_SESSION['authPass'] = $user->getPass();
        return $user;
    }

    public static function getAuthUser()
    {
        if (empty($_SESSION['authLogin']) || empty($_SESSION['authPass']))
            return null;
        $data = (new SafeMysql())->fetchOne("
            SELECT *
            FROM users
            WHERE login = :login AND
                  pass = :pass AND
                  status = :status
            LIMIT 1", [
            'login' => $_SESSION['authLogin'],
            'pass' => $_SESSION['authPass'],
            'status' => User::USER_STATUS_ACTIVE
        ]);
        if ($data)
            return new User($data);
        unset($_SESSION['authLogin']);
        unset($_SESSION['authPass']);
        return null;
    }

    public static function getUsersAll(int $lc_id, array $filters, int $role_id)
    {
        $query = new QueryBuilder();
        $query->setSelectFrom('SELECT SQL_CALC_FOUND_ROWS u.* FROM users u');
        $query->addJoin("JOIN persons p ON u.id = p.user_id AND p.lc_id = :lc_id");
        $query->addWhere("u.role_id = :role_id");
        $query->setGroupBy("id");
        $query->setOrderBy("id");
        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);
        $query->setLimit("{$offset}, " . self::LIMIT_PER_PAGE);
        $records_raw = (new SafeMysql())->fetchAll($query->build(), [
            'role_id' => $role_id,
            'lc_id' => $lc_id
        ]);
        if (empty($records_raw))
            return ['records' => [], 'total' => 0, 'current_page' => 1];
        $records = array_map(function($user) {
            return new User($user);
        }, $records_raw);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        return ['records' => $records, 'total' => $total, 'current_page' => $current_page];
    }

    public static function validate(User $user, User $authUser)
    {
        $rules = [
            'login' => [
                'val' => $user->getLogin(),
                'rules' => [Validator::TYPE_STRING => [4, 255]]
            ],
            'api_key' => [
                'val' => $user->getApiKey(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ]
        ];
        if ($user->getId() !== $authUser->getId())
            $rules['login']['rules'][Validator::REQUIRED] = 1;
        if (empty($user->getId()))
            $rules['pass'] = [
                'val' => $user->getPass(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [3, 255]]
            ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        if (!empty($user->getId())) {
            $user_by_id = Users::getById($user->getId());
            if ($user_by_id === null)
                $validator->addError("user_not_found", "User not found");
            if (!$authUser->isRoleSystem()) {
                if (!$authUser->hasUserAccess($user_by_id->getId(), $user_by_id->getParentId()))
                    $validator->addError("access_denied", "Access to the edit this user denied");
                if ($authUser->getParentId() === $user_by_id->getId())
                    $validator->addError("access_denied", "Access to the edit parent user denied");
                //Do not allow to change parent_id for parent users
                if ($user_by_id->getParentId() === 0 && $user->getParentId() > 0)
                    $validator->addError("operation_prohibited", "Cannot change account type");
            }
        } elseif (!$authUser->isRoleSystem() && !$authUser->hasUserAccess(0, $user->getParentId()))
                $validator->addError("access_denied", "Access denied");
        $user_by_login = Users::getByLogin($user->getLogin());
        if (!empty($user_by_login)) {
            $not_uniq_login = false;
            if (empty($user->getId()))
                $not_uniq_login = true;
            if (!empty($user->getId()) && $user->getId() !== $user_by_login->getId())
                $not_uniq_login = true;
            if ($not_uniq_login)
                $validator->addError("not_unique", "This login is already used.", 'login');
        }
        return $validator->getErrors();
    }

    public static function getStatusText($status)
    {
        return User::$STATUSES[$status] ?? User::$STATUSES[User::USER_STATUS_BLOCKED];
    }

    public static function getWarehouseManagers(int $warehouse_id): array
    {
        $res = (new SafeMysql())->fetchAll("
            SELECT u.*
            FROM users u
                JOIN user_warehouses uw ON uw.user_id = u.id AND uw.warehouse_id = :warehouse_id
            WHERE u.role_id = :role_id AND u.status = :status", [
            'warehouse_id' => $warehouse_id,
            'role_id' => Roles::MANAGER,
            'status' => User::USER_STATUS_ACTIVE
        ]);
        return array_map(function($item) {
            return new User($item);
        }, $res);
    }

    public static function getParents(array $users): array
    {
        $user_ids = array_filter(array_map('intval', array_unique(array_map(function($user) {
            if ($user instanceof User)
                return $user->getParentId();
            return null;
        }, $users))));
        if (empty($user_ids))
            return [];
        $parents = [];
        $parent_data = array_map(function($item) {
            return new User($item);
        }, (new SafeMysql())->fetchAll("SELECT * FROM users WHERE id IN (:user_ids)", ['user_ids' => $user_ids]));
        /** @var $parent User */
        foreach ($parent_data as $parent)
            $parents[$parent->getId()] = $parent;
        return $parents;
    }

    public static function getWarehouseWorkerList($filters, int $warehouse_id, ?int $manager_id = null): array
    {
        $query = new QueryBuilder();
        $query->setSelectFrom('SELECT SQL_CALC_FOUND_ROWS * FROM users');
        $query->addWhere("warehouse_id = :warehouse_id");
        $query->addWhere('role_id = :role_id');
        if (!is_null($manager_id))
            $query->addWhere("parent_id = :parent_id");
        $query->setGroupBy("id");
        $query->setOrderBy("id");
        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);
        $query->setLimit("{$offset}, " . self::LIMIT_PER_PAGE);
        $records_raw = (new SafeMysql())->fetchAll($query->build(), [
            'warehouse_id' => $warehouse_id,
            'parent_id' => $manager_id,
            'role_id' => Roles::WORKER
        ]);
        $records = array_map(function($user) {
            return new User($user);
        }, $records_raw);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        return ['records' => $records, 'total' => $total, 'current_page' => $current_page];
    }

    public static function createSA(array $data, User $authUser)
    {
        $user = new User($data);
        $errors = Users::validate($user, $authUser);
        if (!empty($user->getId())) {
            $person_sa = Persons::getByUserId($user->getId());
            if (!empty($person_sa))
                $id = $person_sa->getId();
        }
        $person = new Person([
            'id' => $id ?? 0,
            'email' => $data['email'] ?? '',
            'name' => $data['login'] ?? '',
            'phone' => $data['phone'] ?? '',
            'lc_id' => $data['lc_id'] ?? 0,
        ]);
        $errors = array_merge($errors, Persons::validate($person));
        if (empty($data['warehouse_id']))
            $errors[] = ['code' => 'warehouse_empty', 'message' => 'Warehouse is empty', 'field' => 'warehouse_id'];
        if (!empty($errors))
            return $errors;
        $user->setRoleId(Roles::SA);
        $fields_to_save = ['login', 'warehouse_id', 'role_id', 'status'];
        if (!empty($user->getPass()))
            $fields_to_save[] = 'pass';
        if (!empty($user->getId()))
            $fields_to_save[] = 'id';
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $user->save($fields_to_save);
        $person->setUserId($user->getId());
        $fields_to_save = array_keys($person->toArray());
        if (empty($person->getId()))
            unset($fields_to_save[array_search('id', $fields_to_save)]);
        $person->save($fields_to_save);
        $mysql->commit();
        return true;
    }
}
