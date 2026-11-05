<?php

namespace app\backend\controllers;

use app\backend\models\Persons;
use app\backend\models\Persons as PersonsModel;
use app\backend\models\Roles;
use app\backend\models\SupplierEmails;
use app\backend\models\SupplierNotificationHistories;
use app\backend\models\SupplierNotifications;
use app\backend\models\SupplierNotificationTypeMap;
use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\SupplierWarehouses;
use app\backend\models\Users as UsersModel;
use app\core\entities\Supplier;
use app\core\entities\SupplierEmail;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\TokensTranslations;
use app\core\models\Warehouses;
use app\core\statical\Countries;
use app\core\exceptions\RedirectException;

class Account extends AuthController
{
    /** @throws */
    public function index()
    {
        $supplier = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId());
        if ($supplier === null) {
            View::addFlash('company', 'danger', 'Company not found');
            throw new RedirectException('/');
        }
        $supplier = new Supplier($supplier);
        $address = SupplierWarehouses::getAddress($this->user->getWarehouseId(), $supplier->getId());
        $billing_address = SupplierWarehouses::getBillingAddress($this->user->getWarehouseId(), $supplier->getId());
        $data = [
            "supplier" => $supplier,
            "user_page" => true,
            "warehouse_list" => [[
                'name' => '',
                'address' => $address->toString(),
                'billing_address' => $billing_address->toString(),
            ]]
        ];
        $this->render($data, 'companies/supplier');
    }

    /** @throws */
    public function editUser()
    {
        $user = UsersModel::getById($this->user->getId());
        $back = $this->route('index');
        if ($user === null) {
            View::addFlash('users', 'danger', 'User not found');
            throw new RedirectException($back);
        }
        if ($user->getRoleId() !== Roles::SUPPLIER_USER) {
            View::addFlash('users', 'danger', 'Access denied');
            throw new RedirectException($back);
        }
        $role = Roles::getById(Roles::SUPPLIER_USER);
        $supplier = SuppliersModel::getInstanceByUser($user->getId());
        if (empty($supplier)) {
            View::addFlash('supplier', 'danger', 'Access denied');
            throw new RedirectException($back);
        }
        $warehouse_list = Arr::map(Warehouses::getByIds($supplier->getWarehouses()), 'id', 'name');
        $data = [
            'user' => $user,
            'role' => $role,
            'userPerson' => PersonsModel::getByUserId($user->getId()),
            'parents' => [UsersModel::getById($user->getParentId())],
            'access_scopes' => Roles::getAccessScopeHeaders($role->getId()),
            'persons' => [],
            'warehouse_list' => $warehouse_list,
            'back' => $back,
        ];
        $this->render($data, 'accounts/account_form');
    }

    /** @throws */
    public function saveSupplier()
    {
        $id = Request::getPositiveId();
        $supplier = SuppliersModel::getById($id);
        if ($supplier === null) {
            View::addFlash('companies', 'danger', 'Company not found');
            throw new RedirectException('/account');
        }
        $data = [
            "supplier" => $supplier,
            "user_page" => true,
            "countries" => Countries::getAll(),
        ];
        $this->render($data, 'companies/edit_supplier');
    }

    /** @throws */
    public function addingSupplier()
    {
        $redirect_url = "/account/save_supplier/?id=".$_POST['supplier_data']['id'];
        if (!$_POST)
            throw new RedirectException($redirect_url);
        list(, $errors) = SuppliersModel::addSupplier($_POST['supplier_data'], $_FILES,
            empty($_POST['null_logo']) ? null : $_POST['null_logo'], $this->user, $this->warehouse, 'user');
        if (!empty($errors)) {
            View::addFlash('supplier_saved', 'danger', Arr::column($errors, 'message'));
            throw new RedirectException($redirect_url);
        }
        throw new RedirectException('/account');
    }

    /** @throws */
    public function notifications()
    {
        $id = Request::getPositiveId();
        $supplier = SuppliersModel::getById($id);
        if (empty($supplier)) {
            View::addFlash('supplier_error', 'danger', 'Supplier not found');
            throw new RedirectException('/');
        }
        $this->disableWarehouseChange();
        $types = SupplierNotificationTypeMap::getTypesBySupplier($id);
        $data = [
            'supplier' => $supplier,
            'types' => $types,
            'supplier_emails' => SupplierEmails::getListBySupplierId($id),
            'is_account' => !empty($_GET['account'])
        ];
        $this->render($data, 'companies/notifications');
    }

    /** @throws */
    public function changeActiveTypes()
    {
        if (Request::isAjax()) {
            $supplier_id = Request::getPositiveId($_POST, 'supplier_id');
            $types = Arr::map(SupplierNotificationTypeMap::getTypesBySupplier($supplier_id), 'id', 'is_active');
            $to_add = [];
            $to_remove = [];
            $new_types = !empty($_POST['types']) ? array_keys($_POST['types']) : [];
            foreach ($types as $type_id => $is_active) {
                if (in_array($type_id, $new_types) === empty($is_active)) {
                    if (empty($is_active)) {
                        $to_add[] = $type_id;
                    } else {
                        $to_remove[] = $type_id;
                    }
                }
            }
            $user_id = $this->user->getId();
            SupplierNotificationTypeMap::unsetActiveListForSupplier($supplier_id, $to_remove, $user_id);
            SupplierNotificationTypeMap::setActiveListForSupplier($supplier_id, $to_add, $user_id);
            return $this->response = ['success'];
        }
        throw new RedirectException('/');
    }

    /** @throws */
    public function getSupplierEmails()
    {
        if (Request::isAjax()) {
            $supplier_id = Request::getPositiveId($_POST, 'supplier_id');
            $type_id = Request::getPositiveId($_POST, 'type_id');
            if (!$supplier_id) {
                return $this->response = ['result' => 'error'];
            }
            $email_types = $type_id ? SupplierEmails::getBySupplierIdAndType($supplier_id, $type_id)
                : SupplierEmails::getBySupplierId($supplier_id);
            return $this->response = [
                'result' => 'success',
                'emails' => Arr::map($email_types, 'id', 'is_active')
            ];
        }
        throw new RedirectException('/');
    }

    /** @throws */
    public function storeActiveList()
    {
        $supplier_id = Request::getPositiveId($_POST, 'supplier_id');
        $type_id = Request::getPositiveId($_POST, 'type_id');
        if (!$supplier_id) {
            View::addFlash('supplier_error', 'danger', 'Supplier not found');
            throw  new RedirectException('/');
        }
        $to_activate = [];
        $to_deactivate = [];
        $new_active_list = !empty($_POST['supplier_emails']) ? array_keys($_POST['supplier_emails']) : [];
        $email_types = $type_id ? SupplierEmails::getBySupplierIdAndType($supplier_id, $type_id)
            : SupplierEmails::getBySupplierId($supplier_id);
        $email_map = array_map(function ($m) {
            return !empty($m);
        }, Arr::map($email_types, 'id', 'is_active'));
        foreach ($email_map as $e_id => $activate) {
            if (in_array($e_id, $new_active_list) !== (bool) $activate) {
                if (!$activate) {
                    $to_activate[] = $e_id;
                } else {
                    $to_deactivate[] = $e_id;
                }
            }
        }
        $user_id = $this->user->getId();
        if (!$type_id) {
            SupplierEmails::changeActiveList($to_deactivate, false, $user_id);
            SupplierEmails::changeActiveList($to_activate, true, $user_id);
        } elseif ($type_id > 0) {
            SupplierNotifications::unsetActiveList($type_id, $to_deactivate, $user_id);
            SupplierNotifications::setActiveList($type_id, $to_activate, $user_id);
        }
        View::addFlash('supplier_active list', 'success', 'Supplier Notifications Updated');
        throw new RedirectException('/account/notifications/?id='.$supplier_id.(!empty($_POST['account']) ? '&account=1' : ''));
    }

    /** @throws */
    public function addEmail()
    {
        if (Request::isAjax()) {
            $data = $_POST;
            $data['is_active'] = true;
            $supplier_email = new SupplierEmail($data);
            $errors = SupplierEmails::validate($supplier_email);
            if (!empty($errors)) {
                return $this->response = [
                    'result' => 'error', 'errors' => array_map(function ($error) {
                        return $error['message'];
                    }, $errors)
                ];
            }
            $supplier_email->save(array_keys($data));
            SupplierNotificationHistories::addSupplierEmail($supplier_email, $this->user->getId());
            return $this->response = ['result' => 'success'];
        }
        throw new RedirectException('/');
    }

    /** @throws */
    public function removeEmail()
    {
        $supplier_id = Request::getPositiveId($_POST, 'supplier_id');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || empty($supplier_id)) {
            $supplier_email_id = Request::getPositiveId($_POST, 'supplier_email_id');
            $supplier_email = SupplierEmails::getById($supplier_email_id);
            if (empty($supplier_email) || (int) $supplier_email->getSupplierId() !== (int) $supplier_id) {
                View::addFlash('error_supplier_email', 'danger', 'Email not found');
            } else {
                SupplierEmails::remove($supplier_email, $this->user->getId());
                View::addFlash('deleted_supplier_email', 'warning', "Supplier email #{$supplier_email_id} removed!");
            }
        } else {
            View::addFlash('server_error', 'danger', 'Server error');
        }

        throw new RedirectException('/account/notifications/?id='.$supplier_id.(!empty($_POST['account']) ? '&account=1' : ''));
    }

    public function getPassTemplate()
    {
        View::setTemplateTokens(TokensTranslations::getPassTokens($this->warehouse_lang));
        return require_once BACKEND_VIEW_PATH . DIRECTORY_SEPARATOR . 'change_pass_template.php';
    }

    /** @throws */
    public function changePass()
    {
        $old_pass = $_POST['old_pass'] ?? '';
        $new_pass = $_POST['new_pass'] ?? '';
        $confirm_pass = $_POST['confirm_pass'] ?? '';
        if (!password_verify($old_pass, $this->user->getPass())) {
            View::addFlash('password', 'danger', 'Password is incorrect');
            throw new RedirectException($_SERVER['HTTP_REFERER'] ?? '/');
        }
        if (empty($old_pass) || empty($new_pass) || $new_pass !== $confirm_pass || strlen($new_pass) < 3 || strlen($new_pass) > 255) {
            View::addFlash('password', 'danger', 'Error data');
            throw new RedirectException($_SERVER['HTTP_REFERER'] ?? '/');
        }
        if (password_verify($new_pass, $this->user->getPass())) {
            View::addFlash('password', 'danger', 'The new password is the same as the old one');
            throw new RedirectException($_SERVER['HTTP_REFERER'] ?? '/');
        }
        $this->user->setPass($new_pass);
        $this->user->save(['id', 'pass']);
        session_destroy();
        throw new RedirectException("/auth/login");
    }
}
