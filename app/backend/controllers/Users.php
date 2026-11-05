<?php

namespace app\backend\controllers;

use app\backend\models\Locations;
use app\backend\models\Persons as PersonsModel;
use app\backend\models\Roles;
use app\backend\models\SKULocations;
use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\UserGroups;
use app\backend\models\Users as UsersModel;
use app\backend\models\UserWarehouses;
use app\core\entities\User;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\perms\AccessScope;
use app\core\models\Warehouses;

class Users extends AuthController
{
    public function admins()
    {
        $filters = $_GET;
        $role_id = Roles::SUPPLIER_ADMIN;
        $user_warehouses = [];
        $_SESSION[UsersModel::FILTER_PARAM] = http_build_query($filters);
        $warehouses = Arr::map(Warehouses::getAll($this->logistic_company->getId()), 'id', 'name');
        $user_list = UsersModel::getUsersAll($this->logistic_company->getId(), $filters, $role_id);
        $current_filters = $filters;
        if(isset($current_filters))
            unset($current_filters['c']);
        foreach ($user_list['records'] as $admin) {
            /** @var $admin User */
            $user_warehouses[$admin->getId()] = array_map(function($w) use ($admin, $warehouses) {
                return $w == $admin->getWarehouseId() ? '<b><b>' . View::toHtml($warehouses[$w]) . '</b></b>' : View::toHtml($warehouses[$w]);
            }, UsersModel::getAllWarehouseAdmin($admin->getId()));
        }
        $data = [
            'total' => $user_list['total'],
            'page_title' => 'Admins',
            'role' => Roles::getById($role_id),
            'users_list' => $user_list['records'],
            'parents' => UsersModel::getParents($user_list['records']),
            'user_warehouses' => $user_warehouses,
            'user_groups' => UserGroups::getGroupNamesByUsers($user_list['records']),
            'access_scopes' => AccessScope::getAccessScopeForSupplierAdmin(),
            'total_pages' => ceil($user_list['total'] / UsersModel::LIMIT_PER_PAGE),
            'current_page' => $user_list['current_page'],
            'current_filters' => http_build_query($current_filters),
            'create' => true,
        ];
        $this->render($data, 'accounts/list');
    }

    private function getUserRoute(): string
    {
        $filters = [];
        if (!empty($_SESSION[UsersModel::FILTER_PARAM]))
            parse_str(urldecode($_SESSION[UsersModel::FILTER_PARAM]), $filters);
        return $this->route('admins', null, $filters);
    }

    public function managers()
    {
        $filters = $_GET;
        $role_id = Roles::MANAGER;
        $_SESSION[UsersModel::FILTER_PARAM] = http_build_query($filters);
        $user_warehouses = [];
        $user_list = UsersModel::getUsersAll($this->logistic_company->getId(), $filters, $role_id);
        $current_filters = $filters;
        if(isset($current_filters))
            unset($current_filters['c']);
        foreach ($user_list['records'] as $manager) {
            /** @var User $manager */
            $manager_warehouses = UserWarehouses::getAvailableWarehouses($manager->getId());
            $user_warehouses[$manager->getId()] = array_map(function($w) use ($manager) {
                return $w['id'] == $manager->getWarehouseId() ? '<b><b>' . View::toHtml($w['name']) . '</b></b>' : View::toHtml($w['name']);
            }, $manager_warehouses);
        }
        $data = [
            'total' => $user_list['total'],
            'page_title' => 'Managers',
            'role' => Roles::getById($role_id),
            'users_list' => $user_list['records'],
            'parents' => UsersModel::getParents($user_list['records']),
            'persons' => PersonsModel::getByUsers($user_list['records']),
            'user_warehouses' => $user_warehouses,
            'access_scopes' => AccessScope::getAccessScopeForManager(),
            'warehouses_arr' => Arr::map(Warehouses::getAll($this->logistic_company->getId()), 'id', 'name'),
            'user_groups' => UserGroups::getGroupNamesByUsers($user_list['records']),
            'total_pages' => ceil($user_list['total'] /UsersModel::LIMIT_PER_PAGE),
            'current_page' => $user_list['current_page'],
            'current_filters' => http_build_query($current_filters),
            'create' => true,
        ];
        $this->render($data, 'accounts/list');
    }

    /** @throws */
    public function workers()
    {
        $filters = $_GET;
        $this->disableWarehouseChange();
        $_SESSION[UsersModel::FILTER_PARAM] = http_build_query($filters);
        $workers_list = UsersModel::getWarehouseWorkerList($filters, $this->warehouse_id,
            $this->user->getRoleId() === Roles::MANAGER ? $this->user->getId() : null);
        $create = in_array($this->user->getRoleId(), [Roles::SA, Roles::MANAGER]);
        $existManager = true;
        if ($this->user->getRoleId() === Roles::SA) {
            $managers = Roles::getUsersByRole($this->logistic_company->getId(), Roles::MANAGER);
            if (empty($managers)) {
                View::addFlash('worker', 'danger', 'Create active manager firstly');
                throw new RedirectException($this->route('managers'));
            }
            $existManager = false;
            foreach ($managers as $manager) {
                /** @var User $manager */
                $manager_warehouses = Arr::column(UserWarehouses::getAvailableWarehouses($manager->getId()), 'id');
                if (!in_array($this->warehouse_id, $manager_warehouses) || $manager->isBlocked())
                    continue;
                $existManager = true;
                break;
            }
        }
        $current_filters = $filters;
        if(isset($current_filters))
            unset($current_filters['c']);
        $worker_data = [];
        $carts = Locations::getByIds(array_filter(array_unique(array_map(function(User $worker) {
            if (!is_null($worker->getCartId()))
                return $worker->getCartId();
            return null;
        }, $workers_list['records']))));
        $weight = SKULocations::getWeight($this->warehouse_id, array_keys($carts));
        $places = [1 => '', 21 => ' (picking single)', 22 => ' (picking multi)', 31 => ' (packing)', 3 => ' (dropping)'];
        foreach ($weight as $W)
            if (!empty($carts[$W['id']]))
                $carts[$W['id']]['weight'][$W['type']] = $W['weight'];
        foreach ($workers_list['records'] as $worker) {
            /** @var $worker User */
            if (!is_null(!is_null($worker->getCartId()))) {
                $worker_data[$worker->getId()]['location'] = $carts[$worker->getCartId()]['name'] ?? '';
                if (!empty($carts[$worker->getCartId()]['weight'])) {
                    $res = [];
                    foreach ($carts[$worker->getCartId()]['weight'] as $key => $val2)
                        $res[] = sprintf("%.2fkg%s", $val2, $places[$key] ?? '');
                    $worker_data[$worker->getId()]['weight'] = implode(';<br/>', $res);
                }
            }
        }
        $data = [
            'total' => $workers_list['total'],
            'page_title' => 'Workers',
            'role' => Roles::getById(Roles::WORKER),
            'users_list' => $workers_list['records'],
            'persons' => PersonsModel::getByUsers($workers_list['records']),
            'parents' => $this->user->getRoleId() === Roles::SA ? UsersModel::getParents($workers_list['records']) : [],
            'existManager' => $existManager,
            'worker_data' => $worker_data,
            'user_groups' => UserGroups::getGroupNamesByUsers($workers_list['records']),
            'access_scopes' => AccessScope::getAccessScopeForWorker(),
            'create' => $existManager && $create,
            'total_pages' => ceil($workers_list['total'] / UsersModel::LIMIT_PER_PAGE),
            'current_page' => $workers_list['current_page'],
            'current_filters' => http_build_query($current_filters),
        ];
        $this->render($data, 'accounts/list');
    }

    /** @throws */
    public function supplierUsers()
    {
        $this->disableWarehouseChange();
        $supplier = SuppliersModel::getById(Request::getPositiveId(null, 'supplier_id'));
        if (empty($supplier)) {
            View::addFlash('supplier', 'danger', 'Supplier not found');
            throw new RedirectException($this->route('index', Suppliers::class));
        }
        $role = Roles::getById(Roles::SUPPLIER_USER);
        $warehouses = Arr::map(Warehouses::getAll($this->logistic_company->getId()), 'id', 'name');
        $user_warehouses = [];
        $user_list = array_map(function($user) use ($warehouses, &$user_warehouses) {
            $user_warehouses[$user['id']] = View::toHtml($warehouses[$user['warehouse_id']] ?? '');
            return new User($user);
        }, UsersModel::getUserBySupplierId($supplier->getId()));
        $admin = UsersModel::getById($supplier->getUserId());
        $data = [
            'is_pagination' => false,
            'total' => count($user_list),
            'page_title' => $supplier->getName() . ' users',
            'role' => $role,
            'supplier' => $supplier,
            'admin' => $admin,
            'users_list' => $user_list,
            'persons' => PersonsModel::getByUsers($user_list),
            'parents' => UsersModel::getParents($user_list),
            'user_warehouses' => $user_warehouses,
            'user_groups' => UserGroups::getGroupNamesByUsers($user_list),
            'access_scopes' => AccessScope::getAccessScopeForSupplierUser(),
            'create' => !empty($supplier->getWarehouses()) && in_array($this->warehouse_id, $supplier->getWarehouses()) && $admin->isActive(),
            'back' => $this->route('settings', Suppliers::class, ['id' => $supplier->getId()])
        ];
        $this->render($data, 'accounts/list');
    }
}
