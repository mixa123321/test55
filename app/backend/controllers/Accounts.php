<?php

namespace app\backend\controllers;

use app\backend\models\Persons as PersonsModel;
use app\backend\models\Roles;
use app\backend\models\UserGroupRoles;
use app\backend\models\UserGroups;
use app\backend\models\Users as UsersModel;
use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\UserWarehouses;
use app\core\dbal\SafeMysql;
use app\core\entities\Person;
use app\core\entities\Role;
use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\entities\UserGroup;
use app\core\entities\UserGroupRole;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\backend\models\Routes;
use app\core\models\accounts\PersonHistories;
use app\core\models\accounts\RoleHistories;
use app\core\models\accounts\UserGroupHistories;
use app\core\models\accounts\UserHistories;
use app\core\models\Warehouses;
use app\core\services\crypto\StoredSecret;
use app\backend\models\Sizes as SizesModel;
use app\backend\models\Locations as LocationsModel;

class Accounts extends AuthController
{
    public function advanced()
    {
        $data = [];
        if ($this->user->isRoleSA()) {
            if (empty($_GET)) {
                $filters = $_SESSION['advanced_routes'] ?? [];
            } else {
                $filters = $_GET;
                $_SESSION['advanced_routes'] = $filters;
            }
            $data['dictionary'] = Routes::getAll($filters, $this->user->hasDevAccess());
            $data['filters'] = $filters;
            $data['pagination_path'] = $this->route('advanced', null, [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'editable' => !empty($filters['editable']) ? 'on' : '',
                'virtual' => !empty($filters['virtual']) ? 'on' : '',
            ]) . '&';
        }
        $this->render($data, 'accounts/advanced');
    }

    public function users()
    {
        $role_id = Request::getPositiveId(null, 'role_filter');
        $roles = Roles::getList(true);
        $role_data = [];
        /** @var $role Role */
        foreach ($roles as $role)
            $role_data[$role->getId()] = $role;
        $suppliers = SuppliersModel::getByFilters($this->logistic_company->getId(), ['active' => true, 'warehouse_id' => $this->warehouse_id]);
        foreach ($suppliers as $s_key => $supplier) {
            $admin = UsersModel::getById($supplier['user_id']);
            if (empty($admin) || $admin->isBlocked())
                unset($suppliers[$s_key]);
        }
        $suppliers = Arr::map($suppliers, 'id', 'name');
        if (!empty($role_id) && !array_key_exists($role_id, $role_data)) {
            $role_id = 0;
            View::addFlash('roles', 'warning', 'Error data');
        }
        $warehouses = Arr::map(Warehouses::getAll($this->logistic_company->getId()), 'id', 'name');
        $user_warehouses = [];
        $only_active = !empty($_GET['only_active']);
        $for_warehouse_id = !empty($_GET['for_warehouse']) ? $this->warehouse_id : null;
        $users = array_filter(
            empty($role_id)
                ? UsersModel::getAllUsers($this->logistic_company->getId())
                : Roles::getUsersByRole($this->logistic_company->getId(), $role_id),
            function($user) use (&$user_warehouses, $warehouses, $role_data, $only_active, $for_warehouse_id) {
                /** @var $user User */
                if ($only_active && $user->isBlocked())
                    return false;
                $filtered = true;
                $user->setLogin('<b>' . $user->getLogin() . '</b>');
                if (in_array($user->getRoleId(), [Roles::SUPPLIER_ADMIN, Roles::SUPPLIER_USER])) {
                    if ($user->isRoleSupplierUser()) {
                        $supplier = SuppliersModel::getInstanceByUser($user->getId());
                        if (!empty($supplier))
                            $user->setLogin($user->getLogin() . '<br>' . ' (' . $supplier->getName() . ')');
                    }
                    if ($user->isRoleSupplierAdmin()) {
                        $suppliers = SuppliersModel::getAllSuppliersByAdmin($user->getId());
                        if (!empty($suppliers))
                            $user->setLogin($user->getLogin() . '<br>'
                                . ' (' . implode(', ', array_map(function($supplier) {
                                    /** @var $supplier Supplier */
                                    return $supplier->getName();
                                }, $suppliers)) . ')');
                    }
                }
                $user_role = $role_data[$user->getRoleId()] ?? null;
                if ($user_role instanceof Role) {
                    if ($user_role->isSA()) {
                        foreach ($warehouses as $w_id => $warehouse)
                            $user_warehouses[$user->getId()][] = $w_id == $user->getWarehouseId() ? '<b><b>' . View::toHtml($warehouse) . '</b></b>' : View::toHtml($warehouse);
                    } elseif ($user_role->isSupplierAdmin() || $user_role->isMultipleWarehouses()) {
                        $multi_warehouses = $user_role->isSupplierAdmin()
                            ? UsersModel::getAllWarehouseAdmin($user->getId())
                            : Arr::column(UserWarehouses::getAvailableWarehouses($user->getId()), 'id');
                        $user_warehouses[$user->getId()] = array_map(function($w) use ($user, $warehouses) {
                            if (empty($warehouses[$w]))
                                return '';
                            return $w == $user->getWarehouseId() ? '<b><b>' . View::toHtml($warehouses[$w]) . '</b></b>' : View::toHtml($warehouses[$w]);
                        }, $multi_warehouses);
                        $filtered = empty($for_warehouse_id) || in_array($for_warehouse_id, $multi_warehouses);
                    } else {
                        $user_warehouses[$user->getId()] = ['<b><b>' . View::toHtml($warehouses[$user->getWarehouseId()] ?? '') . '</b></b>'];
                        $filtered = empty($for_warehouse_id) || $user->getWarehouseId() == $for_warehouse_id;
                    }
                }
                return $filtered;
            });
        $c = empty($_GET['c']) ? 1 : (int)$_GET['c'];
        $c = $c < 1 ? 1 : $c;
        $total_count = count($users);
        $total_pages = ceil($total_count / Roles::LIMIT_PER_PAGE);
        if ($c > $total_pages)
            $c = 1;
        $users = array_slice($users, Roles::LIMIT_PER_PAGE * ($c - 1), Roles::LIMIT_PER_PAGE, true);
        $this->render([
            'users' => $users,
            'roles' => $role_data,
            'suppliers' => $suppliers,
            'user_warehouses' => $user_warehouses,
            'create_link' => $this->route('createAccount'),
            'paginate_link' => $this->route('users'),
            'filters' => ['role_filter' => $role_id, 'only_active' => $only_active ? 'on' : '', 'for_warehouse' => $for_warehouse_id ? 'on' : ''],
            'total_count' => $total_count,
            'total_pages' => $total_pages,
            'current_page' => $c,
            'user_groups' => UserGroups::getGroupNamesByUsers($users),
            'back' => $this->route('advanced'),
        ], 'accounts/users');
    }

    public function roles()
    {
        $this->render([
            'roles' => Roles::getList(),
            'back' => $this->route('advanced'),
        ], 'accounts/roles');
    }

    /** @throws */
    public function storeRole()
    {
        if (empty($_POST)) {
            View::addFlash('role_fail', 'danger', 'Access denied');
            throw new RedirectException($this->route('roles'));
        }
        $create = true;
        if (isset($_POST['role_id']) && !empty($_POST['role_id'])) {
            // edit
            $exist_role = Roles::getById($_POST['role_id']);

            if (empty($exist_role)) {
                View::addFlash('role_fail', 'danger', 'Role not found');
                throw new RedirectException($this->route('roles'));
            } else {
                if (Roles::existConstantRoleId($exist_role->getId())) {
                    View::addFlash('role_fail', 'danger', 'Constant roles can`t be updated');
                    throw new RedirectException('/accounts/roles');
                }
                $create = false;
                $role = clone $exist_role;
                $fields = ['id', 'name', 'is_active'];
                $role->setName($_POST['name'] ?? '');
                if ($role->isWarehouseSide()) {
                    $fields[] = 'multiple_warehouses';
                    $multiple_warehouses = !empty($_POST['multiple_warehouses']);
                    if ($role->isMultipleWarehouses() !== $multiple_warehouses)
                        Roles::changeMultipleWarehouses($this->logistic_company->getId(), $role->getId(), $multiple_warehouses, $this->user->getId());
                    $role->setMultipleWarehouses($multiple_warehouses);
                }
                $is_active = !empty($_POST['is_active']);
                if ($role->isActive() && !$is_active && Roles::existUsers($this->logistic_company->getId(), $role->getId())) {
                    View::addFlash('role_fail', 'danger', 'Exist users with this role');
                    throw new RedirectException($this->route('roles'));
                }
                $role->setIsActive($is_active);
            }
        } else {
            //create
            $fields = ['name', 'constant', 'side', 'multiple_warehouses', 'is_active', 'sub_role'];
            $role = new Role();
            $role->setName($_POST['name'] ?? '');
            $role->setConstant(false);
            $role->setSide(Roles::SIDE_WAREHOUSE);
            $role->setMultipleWarehouses(!empty($_POST['multiple_warehouses']));
            $role->setIsActive(true);
            $role->setSubRole(false);
        }
        $errors = Roles::validate($role);
        if (!empty($errors)) {
            View::addFlash('role_fail', 'danger', Arr::column($errors, 'message'));
        } else {
            $role->save($fields);
            if ($create)
                RoleHistories::create($role, $this->getAuthUserId());
            else
                RoleHistories::update($role, $exist_role ?? new Role, $this->getAuthUserId());
            View::addFlash('role_stored', 'success', 'Role saved');
        }
        throw new RedirectException($this->route('roles'));
    }

    public function userGroups()
    {
        $groups = UserGroups::getList();
        $roles = [];
        foreach (Roles::getList() as $role) {
            /** @var $role Role */
            if ($role->hasUserGroups())
                $roles[$role->getId()] = $role;
        }
        $group_roles = [];
        $users = [];
        foreach ($groups as $group) {
            /** @var $group UserGroup */
            $users[$group->getId()] = !empty(UserGroups::getUsersByGroups($group->getId()));
            $role_ids = UserGroupRoles::getRolesIdsByGroup($group->getId());
            $group_roles[$group->getId()] = [
                'ids' => $role_ids,
                'roles' => array_filter($roles, function($role) use ($role_ids) {
                    /** @var $role Role */
                    return in_array($role->getId(), $role_ids);
                }),
            ];
        }
        $data = [
            'groups' => $groups,
            'users' => $users,
            'roles' => $roles,
            'group_roles' => $group_roles,
            'back' => $this->route('advanced'),
        ];
        $this->render($data, 'accounts/user_groups');
    }

    /** @throws */
    public function getUsersByGroup()
    {
        $group = UserGroups::getById($_POST['group_id'] ?? null);
        if (empty($group))
            return $this->response = [
                'result' => 'error'
            ];
        $users = UserGroups::getUsersByGroups($group->getId());
        $warehouse_users = [];
        /** @var $user User */
        foreach ($users as $user) {
            $user_role = Roles::getById($user->getRoleId());
            if (empty($user_role))
                continue;
            if ($user_role->isSA()
                || ($user_role->isSupplierAdmin() && in_array($this->warehouse_id, UsersModel::getAllWarehouseAdmin($user->getId())))
                || ($user_role->isMultipleWarehouses() && UserWarehouses::hasUserInWarehouse($user->getId(), $this->warehouse_id))
                || (!$user_role->isMultipleWarehouses() && $user->getWarehouseId() == $this->warehouse_id))
                $warehouse_users[] = $user->getId();
        }
        $roles = Arr::map(array_map(function(Role $role) { return $role->toArray(); }, Roles::getList()), 'id', 'name');
        $userData = [];
        foreach ($users as $user) {
            /** @var $user User */
            $userData[$user->getId()] = [
                'id' => $user->getId(),
                'login' => $user->getLogin(),
                'role' => $roles[$user->getRoleId()]
            ];
        }
        return $this->response = [
            'result' => 'success',
            'users' => $userData,
            'warehouse_users' => $warehouse_users
        ];
    }

    public function removeUserFromGroup()
    {
        $group = UserGroups::getById($_POST['group_id'] ?? null);
        $user = UsersModel::getById($_POST['user_id'] ?? null);
        if (empty($group) || empty($user))
            return $this->response = [
                'result' => 'error'
            ];

        UserGroups::removeUserFromGroup($user, $group->getId(), $this->getAuthUserId());
        return $this->response = [
            'result' => 'success',
            'message' => $user->getLogin() . ' removed from group ' . $group->getName()
        ];
    }

    /** @throws */
    public function storeUserGroup()
    {
        if (empty($_POST)) {
            View::addFlash('user_groups_fail', 'danger', 'Access denied');
            throw new RedirectException('/accounts/user_groups');
        }
        
        $group_roles = [];
        foreach ($_POST['group_roles'] ?? [] as $role_id => $item) {
            if (empty(Roles::getById($role_id)))
                continue;
            $group_roles[] = (int)$role_id;
        }
        $create = true;
        if (isset($_POST['user_group_id']) && !empty($_POST['user_group_id'])) {
            //edit
            $exist_user_group = UserGroups::getById($_POST['user_group_id']);
            if (empty($exist_user_group)) {
                View::addFlash('user_groups_fail', 'danger', 'Group not found');
                throw new RedirectException('/accounts/user_groups');
            } else {
                $create = false;
                $user_group = clone $exist_user_group;
                $fields = ['id', 'name'];
                $user_group->setName($_POST['name'] ?? '');
            }
        } else {
            //create
            $fields = ['name', 'is_active'];
            $user_group = new UserGroup();
            $user_group->setName($_POST['name'] ?? '');
            $user_group->setIsActive(true);
        }

        $errors = UserGroups::validate($user_group);
        if (empty($group_roles))
            $errors[] = ['message' => 'At least one role must be selected'];

        if (!empty($errors)) {
            View::addFlash('user_groups_fail', 'danger', Arr::column($errors, 'message'));
        } else {
            $user_group->save($fields);
            $role_ids = UserGroupRoles::getRolesIdsByGroup($user_group->getId());

            $create_roles = array_diff($group_roles, $role_ids);
            foreach ($create_roles as $role_id)
                UserGroupRoles::create($user_group->getId(), $role_id);
            $del_roles = array_diff($role_ids, $group_roles);
            foreach ($del_roles as $role_id)
                UserGroupRoles::delete(new UserGroupRole(['user_group_id' => $user_group->getId(), 'role_id' => $role_id]));

            if ($create)
                UserGroupHistories::create($user_group, $this->getAuthUserId(), $create_roles);
            else
                UserGroupHistories::update($user_group, $exist_user_group ?? new UserGroup, $this->getAuthUserId(), $create_roles, array_values($del_roles));
            View::addFlash('user_groups_stored', 'success', 'Group saved');
        }
        throw new RedirectException('/accounts/user_groups');
    }

    /** @throws */
    public function lockUserGroup()
    {
        if (empty($_POST)) {
            View::addFlash('user_groups_fail', 'danger', 'Access denied');
            throw new RedirectException('/accounts/user_groups');
        }
        $exist_user_group = UserGroups::getById($_POST['group_id']);
        if (empty($exist_user_group))
            View::addFlash('user_groups_fail', 'danger', 'Group not found');
        $user_group = clone $exist_user_group;
        $is_active = !empty($_POST['lock']);
        $user_group->setIsActive($is_active);
        $user_group->save(['id', 'is_active']);
        UserGroupHistories::update($user_group, $exist_user_group, $this->getAuthUserId());
        View::addFlash('user_groups_stored', $is_active ? 'success' : 'danger', 'Group ' . $user_group->getName()
                                                                    . ' ' . ($is_active ? 'unlocked' : 'locked'));
        throw new RedirectException('/accounts/user_groups');
    }

    /** @throws */
    public function deleteUserGroup()
    {
        $back = '/accounts/user_groups';
        if (empty($_POST)) {
            View::addFlash('user_groups_fail', 'danger', 'Access denied');
            throw new RedirectException($back);
        }
        $user_group = UserGroups::getById($_POST['group_id']);
        if (empty($user_group)) {
            View::addFlash('user_groups_fail', 'danger', 'Group not found');
            throw new RedirectException($back);
        }

        if (UserGroups::hasUsers($user_group->getId()))
            $message = 'Operation failed. Group has users';
        else {
            $res = UserGroups::remove($user_group, $this->getAuthUserId());
            $message = $res ? ('Group ' . $user_group->getName() . ' removed') : 'Operation failed';
        }
        View::addFlash('user_groups_stored', 'danger', $message);
        throw new RedirectException($back);
    }

    /** @throws */
    public function createAccount()
    {
        $this->disableWarehouseChange();
        $role = Roles::find($_GET['role'] ?? null);
        if (empty($role)
            || $role->isSA()
            || $role->isBlocked()) {
            View::addFlash('create_account', 'danger', 'Access denied');
            throw new RedirectException('/');
        }
        $supplier_id = $_GET['supplier_id'] ?? null;
        $advanced = !empty($_GET['advanced']);
        $roleFilter = Request::getPositiveId(null, 'role_filter');
        $back = $this->getBack($advanced, $roleFilter, $role);
        $this->checkAccountAccess($role, null, $supplier_id, false, $advanced ? $back : '');
        $user = new User();
        $user->setRoleId($role->getId());
        $user->setWarehouseId($this->warehouse_id);
        $user->setStatus(User::USER_STATUS_ACTIVE);
        $warehouse_list = [];
        if ($role->isMultipleWarehouses())
            $warehouse_list = Arr::map(Warehouses::getAll($this->logistic_company->getId()), 'id', 'name');
        $parents = [$this->user];
        if ($role->isWorker() && $this->user->getRoleId() !== Roles::MANAGER)
            $parents = UsersModel::getWarehouseManagers($this->warehouse_id);
        $supplier = null;
        if ($role->isSupplierUser()) {
            $supplier = SuppliersModel::getById($supplier_id);
            if (empty($supplier)) {
                View::addFlash('create_account', 'danger', 'Access denied');
                throw new RedirectException('/');
            }
            if (empty($_GET['advanced']))
                $back = $this->route('supplierUsers', Users::class, ['supplier_id' => $supplier->getId()]);
            $warehouse_list = Arr::map(Warehouses::getByIds( $supplier->getWarehouses()), 'id', 'name');
            $admin = UsersModel::getById($supplier->getUserId());
            if (empty($admin)) {
                View::addFlash('create_account', 'danger', 'Supplier admin not found');
                throw new RedirectException($back);
            }
            $parents = [$admin];
        }
        $userPerson = !empty($_SESSION['create_person']) && !empty($_SESSION['flash_messages']['role_fail'])
            ? new Person($_SESSION['create_person']) : null;
        $user_groups = [];
        if ($role->hasUserGroups()) {
            $user_groups = UserGroups::getList(true, $role->getId());
            if (empty($user_groups))
                View::addFlash('create_account', 'warning', 'User group not found for role ' . $role->getName());
        }
        $data = [
            'user' => new User,
            'role' => $role,
            'userPerson' => $userPerson,
            'parents' => $parents,
            'access_scopes' => Roles::getAccessScopeHeaders($role->getId()),
            'persons' => $role->hasPerson() ? PersonsModel::getEmptyUser($this->logistic_company->getId()) : [],
            'user_warehouses' => [],
            'warehouse_list' => $warehouse_list,
            'user_groups' => $user_groups,
            'supplier' => $supplier,
            'back' => $back === '/' ? '/accounts/advanced' : ($back . '?&' . ($_SESSION[UsersModel::FILTER_PARAM] ?? '')),
        ];
        $this->render($data, 'accounts/account_form');
    }

    /** @throws */
    public function editAccount()
    {
        $this->disableWarehouseChange();
        $user = UsersModel::getById($_GET['id']);
        if (empty($user) || $user->getRoleId() === Roles::SA) {
            View::addFlash('edit_account', 'danger', 'Access denied');
            throw new RedirectException('/');
        }
        $role = Roles::getById($user->getRoleId());
        $roleFilter = Request::getPositiveId(null, 'role_filter');
        $advanced = !empty($_GET['advanced']);
        $back = $this->getBack($advanced, $roleFilter, $role);
        $this->checkAccountAccess($role, $user, null, false, $advanced ? $back : null);
        $parent = UsersModel::getById($user->getParentId());
        if (empty($parent)) {
            View::addFlash('create_account', 'danger', 'Parent not found');
            throw new RedirectException($back);
        }
        $warehouse_list = [];
        if ($role->isMultipleWarehouses())
            $warehouse_list = Arr::map(Warehouses::getAll($this->logistic_company->getId()), 'id', 'name');
        if ($role->isSupplierUser()) {
            $supplier = SuppliersModel::getInstanceByUser($user->getId());
            if (empty($supplier)) {
                View::addFlash('create_account', 'danger', 'Access denied');
                throw new RedirectException($back);
            }
            if (empty($_GET['advanced']))
                $back = $this->route('supplierUsers', Users::class, ['supplier_id' => $supplier->getId()]);
            $warehouse_list = Arr::map(Warehouses::getByIds($supplier->getWarehouses()), 'id', 'name');
        }
        $user_groups = [];
        if ($role->hasUserGroups()) {
            $user_groups = UserGroups::getList(true, $role->getId());
            if (empty($user_groups))
                View::addFlash('create_account', 'warning', 'User group not found for role ' . $role->getName());
        }
        $data = [
            'user' => $user,
            'role' => $role,
            'userPerson' => PersonsModel::getByUserId($user->getId()),
            'parents' => [$parent],
            'access_scopes' => Roles::getAccessScopeHeaders($role->getId()),
            'persons' => [],
            'user_warehouses' => $role->isMultipleWarehouses() ? Arr::map(UserWarehouses::getAvailableWarehouses($user->getId()), 'id', 'name') : [],
            'warehouse_list' => $warehouse_list,
            'user_groups' => $user_groups,
            'user_groups_exist' => $role->hasUserGroups() ? Arr::map(UserGroups::getActiveByUsers($user), 'id', 'name') : [],
            'back' => $back === '/' ? '/accounts/advanced' : ($back . '?&' . ($_SESSION[UsersModel::FILTER_PARAM] ?? '')),
        ];
        $this->render($data, 'accounts/account_form');
    }

    public function storeAccount()
    {
        $errors = [];
        $exist_user = null;
        if (isset($_POST['user']['id'])) {
            $exist_user = UsersModel::getById($_POST['user']['id']);
            if (empty($exist_user)) {
                $errors[] = 'Error. User not found';
                return $this->response = [
                    'result' => 'error',
                    'errors' => $errors
                ];
            }
            $role_id = $exist_user->getRoleId();
        } else
            $role_id = $_POST['role_id'] ?? null;
        $role = Roles::getById($role_id);
        if (empty($role) || $role->isSA())
            $errors[] = 'Access denied. Please reload page';
        $user_data = $_POST['user'] ?? [];
        $fields = array_keys($user_data);
        $user = new User($user_data);
        if (empty($user->getId())) {
            $fields[] = 'role_id';
        } else
            $user->setWarehouseId($exist_user->getWarehouseId());
        $user->setRoleId($role->getId());
        $create = empty($user->getId());
        if ($create) {
            $fields[] = 'status';
            $user->setStatus(User::USER_STATUS_ACTIVE);
        }
        // Write-only API key: an empty field keeps the stored key; only roles with an API key can set one
        $api_key_field = array_search('api_key', $fields, true);
        if ($api_key_field !== false) {
            $prefixed = StoredSecret::isEncrypted((string)$user_data['api_key']);
            if ($prefixed)
                $errors[] = 'Api key must not start with ' . StoredSecret::PREFIX;
            if ($prefixed || !$role->hasApiKey() || (!$create && !$user->hasApiKey())) {
                unset($fields[$api_key_field]);
                $user->setApiKey('');
            }
        }
        $supplier_id = (int)($_POST['supplier_id'] ?? null);
        if ($create && $role->isSupplierUser() && empty(SuppliersModel::getById($supplier_id)))
            $errors[] = 'Access denied. Supplier not found';
        $access_message = $this->checkAccountAccess($role, $exist_user, $supplier_id, true);
        if (!empty($access_message))
            $errors[] = $access_message;
        $user_warehouses = [];
        if ($role->isMultipleWarehouses() && !$role->isSupplierAdmin()) {
            $user_warehouses = array_keys($_POST['user_warehouses'] ?? []);
            if (empty($user_warehouses)) {
                $errors[] = 'Must be selected warehouse';
            } else {
                $fields[] = 'warehouse_id';
                $user->setWarehouseId(array_values($user_warehouses)[0]);
            }
        } else {
            if (empty($user->getWarehouseId())) {
                $fields[] = 'warehouse_id';
                $user->setWarehouseId($_POST['warehouse_id'] ?? $this->warehouse_id);
            }
            if (!empty($_POST['warehouse_id'])) {
                $fields[] = 'warehouse_id';
                $user->setWarehouseId($_POST['warehouse_id']);
            }
        }
        $user_groups = [];
        if ($role->hasUserGroups()) {
            $user_groups = UserGroups::handleGroups(array_keys($_POST['user_groups'] ?? []));
            if ($role->isWorker() && empty($user_groups))
                $errors[] = 'Must be selected user group';
        }
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $validate_errors = UsersModel::validate($user, $this->user);
        if (empty($errors) && empty($validate_errors)) {
//            if ($create) {
//                $user->setAccessScope(array_keys(Roles::getAccessScopeHeaders($user->getRoleId())));
//                $fields[] = 'access_scope';
//            }
            $user->save($fields);
            if ($create) {
                if ($role->hasPerson()) {
                    $person_id = $_POST['person_id'] ?? 0;
                    $exist_person = PersonsModel::getById($person_id);
                    if (!empty($exist_person->getUserId()))
                        $errors[] = 'Person is busy. Please reload page for select new person';
                    else {
                        $person = clone $exist_person;
                        $person->setUserId($user->getId());
                        $person->save(['id', 'user_id']);
                        PersonHistories::update($person, $exist_person, $this->getAuthUserId());
                    }
                }
                UserHistories::create($user, $person_id ?? null, $this->getAuthUserId());
            } else
                UserHistories::update($user, $exist_user ?? new User, $this->getAuthUserId(), $fields);
            if ($role->isMultipleWarehouses() && !$role->isSupplierAdmin()) {
                if (!$create) {
                    $exist_user_warehouse_ids = Arr::column(UserWarehouses::getAvailableWarehouses($user->getId()), 'id');
                    foreach ($exist_user_warehouse_ids as $exist_user_warehouse_id)
                        if (!in_array($exist_user_warehouse_id, $user_warehouses))
                            UserWarehouses::removeUserFromWarehouse($user->getId(), $exist_user_warehouse_id, $this->getAuthUserId());
                }
                foreach ($user_warehouses as $w_id)
                    if ($create || !in_array($w_id, $exist_user_warehouse_ids))
                        UserWarehouses::addUserToWarehouse($user->getId(), $w_id, $this->getAuthUserId());

                if (empty(UserWarehouses::getAvailableWarehouses($user->getId())))
                    $errors[] = 'Warehouses error';
            }
            if ($role->hasUserGroups()) {
                if (!$create) {
                    $exist_user_groups = Arr::column(UserGroups::getActiveByUsers($exist_user), 'id');
                    foreach ($exist_user_groups as $exist_user_group_id)
                        if (!in_array($exist_user_group_id, $user_groups))
                            UserGroups::removeUserFromGroup($user, $exist_user_group_id, $this->getAuthUserId());
                }
                foreach ($user_groups as $user_group_id)
                    if ($create || !in_array($user_group_id, $exist_user_groups))
                        UserGroups::addUserToGroup($user->getId(), $user_group_id, $this->getAuthUserId());
//                if ($role->isWorker())
//                    UserGroups::handleWorkerAccess($user);
            }
            if ($create && $role->isWarehouseSide()) {
                $size = SizesModel::getByName($this->warehouse_id, 'UNLIMITEDSIZE');
                LocationsModel::createVirtualTrolley($this->warehouse_id, $user->getId(), empty($size) ? -1 : $size->getId());
            }
            if ($create && $role->isSupplierUser())
                SuppliersModel::addUser($supplier_id, $user->getId(), $this->warehouse_id, $this->getAuthUserId());
        }
        if (!empty($validate_errors))
            $errors = array_merge(Arr::column($validate_errors, 'message'), $errors);
        if (!empty($errors))
            $mysql->rollback();
        else
            $mysql->commit();

        return $this->response = [
            'result' => empty($errors) ? 'success' : 'error',
            'errors' => $errors
        ];
    }

    public function findAccount()
    {
        $query = $_POST['nameStartsWith'] ?? null;
        if (!empty($query) && strlen($query) > 1)
            return $this->response = Arr::maps(UsersModel::findUser($this->logistic_company->getId(), $query), 'id', ['login', 'role']);
        return $this->response = [];
    }

    private function checkAccountAccess(Role $role, User $user = null, $supplier_id = null, $async = false, $advanced_back = null)
    {
        $response = function($error, $url) use ($async, $advanced_back) {
            if ($async)
                return $error;
            else {
                View::addFlash('account', 'danger', $error);
                throw new RedirectException(empty($advanced_back) ? $url : $advanced_back);
            }
        };
        if (!Roles::existConstantRoleId($role->getId()) && $this->user->getRoleId() !== Roles::SA)
            return $response('Access denied', '/');
        if (($role->isManager() || $role->isSupplierAdmin()) && $this->user->getRoleId() !== Roles::SA)
            return $response('Access denied', '/');
        if ($role->isWorker()) {
            if (!in_array($this->user->getRoleId(), [Roles::SA, Roles::MANAGER]))
                return $response('Access denied', '/');
            if ($this->warehouse_virtual && empty($user->getId())) {
                return $response('Access denied', '/');
            }
            $workersRoute = $this->route('workers', Users::class);
            if (!empty($user)) {
                if ($this->user->getRoleId() === Roles::MANAGER && !empty($user->getId()) && $user->getParentId() != $this->getAuthUserId())
                    return $response('User not found', $workersRoute);
                if (empty($user->getId()) && $user->getWarehouseId() != $this->warehouse_id)
                    return $response('User warehouse error', $workersRoute);
            }
        }
        if ($role->isSupplierUser()) {
            if (!is_null($user) && $user->getId() === $this->getAuthUserId())
                return null;
            if (!in_array($this->user->getRoleId(), [Roles::SA, Roles::SUPPLIER_ADMIN]))
                return $response('Access denied', '/');
            $supplierRoute = $this->route('index', Suppliers::class);
            $supplier = is_null($user) ? SuppliersModel::getById($supplier_id) : SuppliersModel::getInstanceByUser($user->getId());
            if (empty($supplier))
                return $response('Supplier not found', $supplierRoute);
            $admin = UsersModel::getById($supplier->getUserId());
//            if (empty($admin) || $admin->isBlocked())
//                return $response("Admin not available", $supplierRoute);
            if (!empty($admin) && $this->user->isRoleSupplierAdmin() && $this->getAuthUserId() !== $admin->getId())
                return $response("Supplier `{$supplier->getName()}` not available", $supplierRoute);
        }
        return null;
    }

    /**
     * @param  bool  $advanced
     * @param  int  $roleFilter
     * @param  Role  $role
     * @return string
     */
    private function getBack(bool $advanced, int $roleFilter, Role $role): string
    {
        $filters = [];
        if ($advanced) {
            if (!empty($roleFilter)) {
                $filters['role_filter'] = $roleFilter;
            }
            if (!empty($_GET['only_active'])) {
                $filters['only_active'] = 'on';
            }
            if (!empty($_GET['for_warehouse'])) {
                $filters['for_warehouse'] = 'on';
            }
        }
        return $advanced
            ? $this->route('users', self::class, $filters)
            : $this->route(strtolower($role->getName().'s'), Users::class);
    }
}
