<?php

namespace app\backend\controllers;

use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\SuppliersHistory;
use app\backend\models\SupplierWarehouses;
use app\backend\models\Users as UsersModel;
use app\core\channels\shopify\ShopifyDataProcessor;
use app\core\dbal\SafeMysql;
use app\core\entities\Address;
use app\core\entities\RetainMeCredentials;
use app\core\entities\ReturnCarrier;
use app\core\entities\Supplier;
use app\core\entities\SupplierWarehouse;
use app\core\entities\User;
use app\core\entities\SuppliersConfig;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\PackingSlips;
use app\core\models\ReturnCarriers;
use app\core\models\SuppliersWarehouses;
use app\core\models\Warehouses;
use app\core\statical\Countries;
use app\core\exceptions\RedirectException;
use app\core\virtual\BaseVirtualCredentials;
use app\core\models\accounts\UserHistories;
use app\core\helpers\File;

class Suppliers extends AuthController
{
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        parent::checkSupplierAccess();
    }

    public function index()
    {
        $_GET['search_name'] = empty($_GET['search_name']) ? '' : trim($_GET['search_name']);
        $_SESSION[SuppliersModel::FILTER_PARAM] = http_build_query($_GET);
        $count_supplier = SuppliersModel::getSuppliersAll($this->logistic_company->getId(), 0, $this->user->isRoleSA() ? 0 : $this->user->getId(), $_GET ?: []);
        $warehouses = Arr::map(Warehouses::getAll($this->logistic_company->getId()), 'id', 'name');
        foreach ($count_supplier['records'] as $supplier_id => $supplier) {
            $supplier_warehouses = json_decode($supplier['warehouses']);
            if (empty($supplier_warehouses)) {
                $count_supplier['records'][$supplier_id]['warehouses'] = [];
                $count_supplier['records'][$supplier_id]['is_virtual'] = $count_supplier['records'][$supplier_id]['is_visible'] = false;
            } else {
                $count_supplier['records'][$supplier_id]['warehouses'] = array_map(function ($elem) use ($warehouses) {
                    return $elem == $this->warehouse_id ? '<b><b>' . View::toHtml($warehouses[$elem]) . '</b></b>' : View::toHtml($warehouses[$elem]);
                }, $supplier_warehouses);
                $count_supplier['records'][$supplier_id]['is_visible'] = in_array($this->warehouse_id, $supplier_warehouses);
                $count_supplier['records'][$supplier_id]['is_virtual'] = $this->warehouse_virtual && $count_supplier['records'][$supplier_id]['is_visible'];
            }
        }
        $admins = UsersModel::getAllAdmins($this->logistic_company->getId());
        $current_filters = $_GET;
        if(isset($current_filters))
            unset($current_filters['c']);
        $exist_admin = false;
        foreach ($admins as $admin)
            if ((int)$admin['status'] === User::USER_STATUS_ACTIVE) {
                $exist_admin = true;
                break;
            }
        $active_suppliers_count = 0;
        foreach ($count_supplier['records'] as $id => $supplier) {
            if (!$supplier['del'])
                $active_suppliers_count++;
            $count_supplier['records'][$id]['setup'] = $supplier['is_visible'] ? SuppliersModel::getSetUp($this->warehouse_id, $id) : '';
            $supplierAdmin = new User($admins[$supplier['user_id']] ?? []);
            if (empty($supplierAdmin->getId())) {
                $supplierAdmin->setLogin(' not found ');
                $supplierAdmin->setStatus(User::USER_STATUS_BLOCKED);
            }
            $count_supplier['records'][$id]['admin'] = $supplierAdmin;
        }
        $users_admins = UsersModel::getUsersForBulkLock($this->logistic_company->getId(), array_keys($count_supplier['records']));
        $data = [
            'suppliers_list' => $count_supplier['records'],
            'exist_admin' => $exist_admin,
            'suppliers_active' => $users_admins['suppliers_active'] ?? [],
            'active' => $count_supplier['total'],
            'statuses' => [-1 => 'All'] + Supplier::STATUSES,
            'admins' => [-1 => 'All'] + Arr::map($admins, 'id', 'login'),
            'total_pages' => ceil($count_supplier['total'] / SuppliersModel::LIMIT_PER_PAGE),
            'current_page' => $count_supplier['current_page'],
            'current_filters' => http_build_query($current_filters),
        ];
        $this->render($data, 'companies/suppliers');
    }

    /** @throws */
    public function delete()
    {
        $id = Request::getPositiveId();
        $back = $_SESSION[SuppliersModel::FILTER_PARAM] ?? '';
        $lock = $_GET['lock'] ?? 0;
        if (!$id) {
            View::addFlash('suppliers', 'danger', 'Supplier not found');
        } else {
            $supplier = SuppliersModel::getById($id);
            if ($supplier === null) {
                View::addFlash('suppliers', 'danger', 'Supplier not found');
                throw new RedirectException('/suppliers');
            }
            if (!$this->user->hasUserAccess($supplier->getId(), $supplier->getUserId())) {
                View::addFlash('suppliers', 'danger', 'Access denied');
                throw new RedirectException('/suppliers');
            }
            $supplier->setDel($lock);
            $supplier->save(["id", "del"]);
            SuppliersHistory::lock($supplier->getId(), $lock, $this->user->getId());
        }
        throw new RedirectException('/suppliers?'. $back);
    }

    public function deleteAllUsers()
    {
        if (!$this->user->isRoleSA()) {
            View::addFlash('Users lock', 'danger', 'Access denied');
            throw new RedirectException('/suppliers');
        }
        $supplier_id = Request::getPositiveId($_POST, 'supplier_id');
        $warehouse_id = Request::getPositiveId($_POST, 'warehouse_id');

        $suppliers = SuppliersModel::getSuppliers($this->logistic_company->getId(), 0, 0);
        $users_admins = UsersModel::getUsersForBulkLock($this->logistic_company->getId(), array_keys($suppliers));
        $users = $users_admins['users'];
        $suppliers_admins = $users_admins['admins'];
        if (!isset($users[$warehouse_id][$supplier_id])) {
            View::addFlash('Users lock', 'danger', 'Access denied');
            throw new RedirectException('/suppliers');
        }
        $ids = [];
        foreach ($users[$warehouse_id][$supplier_id] ?? [] as $user_id)
            $ids[$user_id] = $user_id;
        if (isset($suppliers_admins[$supplier_id]))
            $ids[$suppliers_admins[$supplier_id]] = $suppliers_admins[$supplier_id];

        foreach ($ids as $id) {
            $user = UsersModel::getById($id);
            if (!$user)
                continue;
            $new_user = clone $user;
            $new_user->setStatus(0);
            $mysql = new SafeMysql();
            $mysql->beginTransaction();
            $new_user->save(['id', 'status']);
            UserHistories::update($new_user, $user, $this->getAuthUserId(), ['status']);
            $mysql->commit();
        }
        throw new RedirectException('/suppliers');
    }

    /** @throws */
    public function saveSupplier()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        $supplier_data = $_GET;
        unset($supplier_data['id']);
        if (empty($id)/* || (!empty($supplier_data))*/)
            $supplier = new Supplier($supplier_data);
        else {
            $supplier = SuppliersModel::getById($id);
            if ($supplier === null) {
                View::addFlash('suppliers', 'danger', 'Supplier not found');
                throw new RedirectException('/suppliers');
            }
            if (!$this->user->hasUserAccess($supplier->getId(), $supplier->getUserId())) {
                View::addFlash('suppliers', 'danger', 'Access denied');
                throw new RedirectException('/suppliers');
            }
        }
        $admins = UsersModel::getAllAdmins($this->logistic_company->getId());
        array_walk($admins, function($admin) use (&$admins, $supplier) {
            $blocked = (int)$admin['status'] === User::USER_STATUS_BLOCKED;
            $is_new = empty($supplier->getId());
            if (($is_new && $blocked) || (!$is_new && (int)$supplier->getUserId() !== (int)$admin['id'] && $blocked))
                unset($admins[$admin['id']]);
        });
        $data = [
            'supplier' => $supplier,
            'admins' => Arr::map($admins, 'id', 'login'),
            'settings' => empty($id) ? [] : SuppliersConfig::readConfigsBySupplierId((int)$id),
            'back' => $_SESSION[SuppliersModel::FILTER_PARAM] ?? '',
        ];
        $this->render($data, 'companies/edit_supplier');
    }

    /** @throws */
    public function addingSupplier()
    {
        if (!$_POST)
            throw new RedirectException('/suppliers/save_supplier/');
        if (empty($_POST['supplier_data']['id']))
            unset($_POST['supplier_data']['id']);
        $supplier_data = $_POST['supplier_data'];
        $supplier_data['order_inserts'] = (int)!empty($_POST['supplier_data']['order_inserts']);
        $supplier_data['shopify_locations'] = (int)!empty($_POST['supplier_data']['shopify_locations']);
        $supplier_data['product_price'] = (int)!empty($_POST['supplier_data']['product_price']);
        $offer_returns = !empty($_POST['supplier_data']['offer_returns']);
        $supplier_data['offer_returns'] = $offer_returns;
        $supplier_data['packing_slip_batch_expired'] = !empty($_POST['supplier_data']['packing_slip_batch_expired']);
        $supplier_data['offer_exchanges'] = !empty($_POST['supplier_data']['offer_exchanges']);
        $supplier_data['custom_return'] = !empty($_POST['supplier_data']['custom_return']);
        if (!$offer_returns)
            unset($supplier_data['offer_exchanges'], $supplier_data['custom_return'], $_FILES['custom-return-doc']);
        $has_weeks_before_expiry = !empty($_POST['supplier_data']['has_weeks_before_expiry']);
        $supplier_data['has_weeks_before_expiry'] = $has_weeks_before_expiry;
        if ($has_weeks_before_expiry)
            $supplier_data['weeks_before_expiry'] = (int)($_POST['supplier_data']['weeks_before_expiry'] ?? 0);
        $utilise_despatch_date = !empty($_POST['supplier_data']['utilise_despatch_date']);
        $supplier_data['utilise_despatch_date'] = $utilise_despatch_date;
        if ($utilise_despatch_date)
            $supplier_data['days_before_despatch'] = (int)($_POST['supplier_data']['days_before_despatch'] ?? 0);

        $supplier_data['clear_custom_return_doc'] = !empty($_POST['clear_custom_return_doc']);
        $supplier_data['no_action_returns'] = (int)!empty($_POST['supplier_data']['no_action_returns']);

        $configs = [
            (new SuppliersConfig())->setType(SuppliersConfig::TYPE_SHOPIFY_SPLIT_ORDER)->setValueBool((bool)($_POST['shopify_split_orders'] ?? false)),
            (new SuppliersConfig())->setType(SuppliersConfig::TYPE_SHOPIFY_NS_PID_ATTRIBUTE)->setValueString(trim($_POST['shopify_ns_pid_attribute'] ?? '')),
            (new SuppliersConfig())->setType(SuppliersConfig::TYPE_WOOCOMMERCE_NS_PID_ATTRIBUTE)->setValueString(trim($_POST['woocommerce_ns_pid_attribute'] ?? '')),
            (new SuppliersConfig())->setType(SuppliersConfig::TYPE_MAGENTO_NS_PID_ATTRIBUTE)->setValueString(trim($_POST['magento_ns_pid_attribute'] ?? '')),
            (new SuppliersConfig())->setType(SuppliersConfig::TYPE_MAGENTO_S_PID_ATTRIBUTE)->setValueString(trim($_POST['magento_s_pid_attribute'] ?? '')),
        ];

        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        list($supplier, $errors) = SuppliersModel::addSupplier($supplier_data, $_FILES, empty($_POST['null_logo']) ? null : $_POST['null_logo'], $this->user, $this->warehouse);
        if (!empty($errors)) {
            View::addFlash('supplier_saved', 'danger', Arr::column($errors, 'message'));
            $mysql->rollback();
            throw new RedirectException('/suppliers/save_supplier/?' . http_build_query($supplier->toArray()));
        }
        foreach ($configs as $config)
            $config->setSupplierId($supplier->getId());
        SuppliersConfig::saveConfigs($configs);
        $mysql->commit();
        throw new RedirectException('/suppliers' . ($supplier->getId() > 0 ? "/settings/?id={$supplier->getId()}" : ''));
    }

    /** @throws */
    public function settings()
    {
        $supplier = $this->checkSupplierRequest();
        $supplierWarehouses = SupplierWarehouses::getWarehouses($supplier->getId());
        $accessed_warehouses = $supplier->getWarehouses() ?? [];
        $warehouses = [];
        foreach ($supplierWarehouses as $supplierWarehouse) {
            if (in_array($supplierWarehouse['warehouse_id'], $accessed_warehouses ?? [])) {
                $address_data = json_decode($supplierWarehouse['address'] ?? '');
                $address = new Address(!empty($address_data) ? $address_data : []);
                $billing_address_data = json_decode($supplierWarehouse['billing_address'] ?? '');
                $billing_address = new Address(!empty($billing_address_data) ? $billing_address_data : []);
                $warehouses[] = [
                  'name' => (Warehouses::getById($supplierWarehouse['warehouse_id']))->getName(),
                  'address' => $address->toString(),
                  'billing_address' => $billing_address->toString()
                ];
            }
        }
        $data = [
            "supplier" => $supplier,
            "warehouse_list" => $warehouses,
            "admin" => UsersModel::getById($supplier->getUserId())->getLogin(),
            "settings" => SuppliersConfig::readConfigsBySupplierId((int)$supplier->getId()),
            "back" => $_SESSION[SuppliersModel::FILTER_PARAM] ?? '',
        ];
        $this->render($data, 'companies/supplier');
    }

    /** @throws */
    public function warehouses()
    {
        $supplier = $this->checkSupplierRequest();
        $warehouses = Arr::maps(Warehouses::getAll($this->logistic_company->getId()), 'id', ['id', 'name', 'country_id', 'is_virtual', 'picking_history']);
        $supplier_warehouses = SuppliersWarehouses::getWarehouses($supplier->getId());
        array_walk($warehouses, static function(&$warehouse) use ($supplier, $supplier_warehouses) {
            $supplier_warehouse = $supplier_warehouses[$warehouse['id']] ?? [];
            $warehouse['access'] = in_array($warehouse['id'], $supplier->getWarehouses());
            $warehouse['VAT'] = $supplier_warehouse['VAT'] ?? '';
            $warehouse['EORI'] = $supplier_warehouse['EORI'] ?? '';
            $warehouse['sage_id'] = $supplier_warehouse['sage_id'] ?? '';
            $warehouse['shipping_account_number'] = $supplier_warehouse['shipping_account_number'] ?? '';
            $warehouse['return_label'] = !empty($supplier_warehouse['return_label']);
            $return_carrier = ReturnCarriers::getBySupplierWarehouseId($supplier_warehouse['id'] ?? 0);
            $warehouse['return_carrier_id'] = !empty($return_carrier) ? (string)$return_carrier->getId() : null;
            $warehouse['automatical_assignment'] = $supplier_warehouse['automatical_assignment'] ?? '';
            $warehouse['packing_slip_type'] = $supplier_warehouse['packing_slip_type'] ?? '';
            $warehouse['reg_number'] = $supplier_warehouse['reg_number'] ?? '';
            $warehouse['address'] = json_decode($supplier_warehouse['address'] ?? '') ?? array_map(function($a) { return (string)$a; }, (new Address())->toArray());
            $warehouse['billing_address'] = json_decode($supplier_warehouse['billing_address'] ?? '') ?? array_map(function($a) { return (string)$a; }, (new Address())->toArray());
            if ($warehouse['is_virtual']) {
                $warehouse['picking_history'] = json_decode($warehouse['picking_history'] ?? '', true) ?? [];
                $class_name = BaseVirtualCredentials::getClassName($warehouse['picking_history']['class']);
                if (empty($warehouse['picking_history']['class']) || !class_exists($class_name)) {
                    View::addFlash('suppliers', 'danger', "Virtual warehouse '{$warehouse['name']}' is not defined");
                    throw new RedirectException('/suppliers/settings/?id=' . $supplier->getId());
                }
                $warehouse['virtual_credentials'] = (new $class_name($supplier_warehouse['virtual_credentials'] ?? ''))->toArray();
            }
            unset($warehouse['picking_history']);
            $warehouse = self::withoutSecrets($warehouse, $supplier_warehouse);
        });
        $return_carriers_data = [];
        $return_carriers = ReturnCarriers::getAll($this->logistic_company->getId());
        /** @var ReturnCarrier $return_carrier */
        foreach ($return_carriers as $return_carrier)
            $return_carriers_data[$return_carrier->getId()] = $return_carrier->getName();
        $data = [
            'supplier' => $supplier,
            'packing_slip_types' => PackingSlips::getTypes(),
            'countries' => ['' => ''] + Countries::getAll(),
            'return_carriers' => [0 => 'None'] + $return_carriers_data,
            'warehouses_list' => $warehouses,
        ];
        $this->render($data, 'companies/warehouses');
    }

    /** @throws */
    public function updateWarehouses()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $warehouse_id = (int)($_POST['warehouse_id'] ?? 0);
        $supplier_id = (int)($_POST['_supplier_id'] ?? 0);
        $supplier = SuppliersModel::getById($supplier_id);
        if ($warehouse_id <= 0 || empty($supplier))
            return $this->response = ['result' => false, 'errors' => ['Data error']];
        $accessed_warehouses = !empty($supplier->getWarehouses()) ? $supplier->getWarehouses() : [];
        $access = $_POST['access'] === 'true';
        if (!$access && count($accessed_warehouses) <= 1 && in_array($warehouse_id, $accessed_warehouses))
            return $this->response = [
                'result' => false,
                'errors' => ['Access not changed. There must be at least one available warehouse']
            ];
        $response = [];
        $supplier_warehouse_data = $_POST;
        unset($supplier_warehouse_data['access']);
        $return_label = $_POST['return_label'] === 'true';
        $automatical_assignment = $_POST['automatical_assignment'] === 'true';
        if ($return_label && !SuppliersWarehouses::checkReturnCarrier($_POST['return_carrier_id']))
            return $this->response = [
                'result' => false,
                'errors' => ['If checked return label, must be selected return carrier']
            ];
        $supplier_warehouse_data['return_label'] = $return_label;
        $supplier_warehouse_data['automatical_assignment'] = $automatical_assignment;
        $exist_supplier_warehouse = SuppliersWarehouses::getSupplierWarehouse($warehouse_id, $supplier_id);
        if (empty($exist_supplier_warehouse)) {
            unset($supplier_warehouse_data['id']);
        } else {
            $supplier_warehouse_data['id'] = $exist_supplier_warehouse['id'];
            $old_return_carrier = ReturnCarriers::getBySupplierWarehouseId($exist_supplier_warehouse['id']);
            $exist_supplier_warehouse['return_carrier_id'] = !empty($old_return_carrier) ? $old_return_carrier->getId() : null;
        }
        // Secrets are not sent to the page (ADR-002 Phase 2 step 4c): an empty field keeps the stored value
        $shipping_api_key = trim((string)($supplier_warehouse_data['shipping_api_key'] ?? ''));
        if (($supplier_warehouse_data['shipping_api_key_clear'] ?? '') === 'true')
            $shipping_api_key = '';
        elseif ($shipping_api_key === '')
            $shipping_api_key = (string)($exist_supplier_warehouse['shipping_api_key'] ?? '');
        $supplier_warehouse_data['shipping_api_key'] = $shipping_api_key;
        unset($supplier_warehouse_data['shipping_api_key_clear']);
        $retainme_credentials = null;
        if (($supplier_warehouse_data['packing_slip_type'] ?? '') === PackingSlips::TYPE_RETAIN_ME) {
            $new_retainme_credentials = (array)($supplier_warehouse_data['retainme_credentials'] ?? []);
            $old_retainme_credentials = json_decode($exist_supplier_warehouse['retainme_credentials'] ?? '', true) ?: [];
            foreach ([RetainMeCredentials::VAR_ORDERS_PWD, RetainMeCredentials::VAR_PRINT_PWD] as $pwd)
                if (trim((string)($new_retainme_credentials[$pwd] ?? '')) === '')
                    $new_retainme_credentials[$pwd] = $old_retainme_credentials[$pwd] ?? '';
            $retainme_credentials = (new RetainMeCredentials($new_retainme_credentials))->toJson();
        }
        $supplier_warehouse_data['retainme_credentials'] = $retainme_credentials;
        $supplier_warehouse_data['address'] = (new Address($supplier_warehouse_data['address'] ?? []))->toSupplierAddressJson();
        $supplier_warehouse_data['billing_address'] = (new Address($supplier_warehouse_data['billing_address'] ?? []))->toBillingAddressJson();
        $old_virtual_credentials = json_decode($exist_supplier_warehouse['virtual_credentials'] ?? '', true) ?: [];
        $new_virtual_credentials = (array)($supplier_warehouse_data['virtual_credentials'] ?? []);
        if (trim((string)($new_virtual_credentials['pass'] ?? '')) === '' && isset($old_virtual_credentials['pass']))
            $new_virtual_credentials['pass'] = $old_virtual_credentials['pass'];
        $supplier_warehouse_data['virtual_credentials'] = json_encode($new_virtual_credentials);
        $supplier_warehouse = new SupplierWarehouse($supplier_warehouse_data);
        if ($access) {
            $errors = SupplierWarehouses::validate($supplier_warehouse);
            if (!empty($errors))
                return $this->response = [
                    'result' => false,
                    'errors' => array_map(function($error) {
                        return $error['message'];
                    }, $errors)
                ];
        }
        list($access_updated, $access_message) = SuppliersModel::updateWarehouseAccess($supplier_id, $warehouse_id, $access, $this->user->getId());
        if ($access_message)
            $response[$access_updated ? 'messages' : 'errors'][] = $access_message;
        // unchanged virtual credentials are not saved: the column also caches the virtual API access token
        $virtual_credentials = $supplier_warehouse_data['virtual_credentials'];
        if (($old_virtual_credentials['user_name'] ?? '') == ($new_virtual_credentials['user_name'] ?? '') &&
            ($old_virtual_credentials['pass'] ?? '') == ($new_virtual_credentials['pass'] ?? '')) {
            $virtual_credentials = $exist_supplier_warehouse['virtual_credentials'] ?? '';
            unset($supplier_warehouse_data['virtual_credentials']);
        }
        $supplier_warehouse->save(array_merge(array_keys($supplier_warehouse_data), ['supplier_id']));
        SuppliersWarehouses::updateReturnCarrier($supplier_warehouse, $supplier_warehouse_data['return_carrier_id']);
        if ($supplier_warehouse->getPackingSlipType() !== PackingSlips::TYPE_RETAIN_ME)
            unset($supplier_warehouse_data['retainme_credentials']);
        if (SupplierWarehouses::hasDifferences($supplier_id, $supplier_warehouse_data, $exist_supplier_warehouse, $this->user->getId()))
            $response['messages'][] = 'Setting changes saved';
        $supplier_warehouse_data['access'] = $access_updated ? $access : null;
        $response['result'] = self::withoutSecrets($supplier_warehouse_data, [
            'shipping_api_key' => $shipping_api_key,
            'retainme_credentials' => $retainme_credentials,
            'virtual_credentials' => $virtual_credentials,
        ]);
        return $this->response = $response;
    }

    /**
     * Secrets of a supplier warehouse are not sent to the page (ADR-002 Phase 2 step 4c): only the logins and whether
     * each secret is set. $stored holds the decrypted stored values, the credentials as JSON.
     */
    private static function withoutSecrets(array $warehouse, array $stored): array
    {
        $retainme_credentials = json_decode($stored['retainme_credentials'] ?? '', true) ?: [];
        $virtual_credentials = json_decode($stored['virtual_credentials'] ?? '', true) ?: [];
        unset($warehouse['shipping_api_key']);
        $warehouse['retainme_credentials'] = $retainme_credentials ? [
            RetainMeCredentials::VAR_ORDERS_LOGIN => (string)($retainme_credentials[RetainMeCredentials::VAR_ORDERS_LOGIN] ?? ''),
            RetainMeCredentials::VAR_PRINT_LOGIN => (string)($retainme_credentials[RetainMeCredentials::VAR_PRINT_LOGIN] ?? ''),
        ] : [];
        if (!empty($warehouse['is_virtual']))
            $warehouse['virtual_credentials'] = ['user_name' => (string)($virtual_credentials['user_name'] ?? '')];
        else
            unset($warehouse['virtual_credentials']);
        $warehouse['secrets'] = [
            'shipping_api_key' => !empty($stored['shipping_api_key']),
            RetainMeCredentials::VAR_ORDERS_PWD => !empty($retainme_credentials[RetainMeCredentials::VAR_ORDERS_PWD]),
            RetainMeCredentials::VAR_PRINT_PWD => !empty($retainme_credentials[RetainMeCredentials::VAR_PRINT_PWD]),
            'virtual_pass' => !empty($virtual_credentials['pass']),
        ];
        return $warehouse;
    }

    /** @throws RedirectException */
    private function checkSupplierRequest(): Supplier
    {
        $id = Request::getPositiveId();
        if (!$id) {
            View::addFlash('suppliers', 'danger', 'Supplier not found');
            throw new RedirectException('/suppliers');
        }
        $supplier = SuppliersModel::getById($id);
        if (empty($supplier)) {
            View::addFlash('suppliers', 'danger', 'Supplier not found');
            throw new RedirectException('/suppliers');
        }
        if (!$this->user->hasUserAccess($supplier->getId(), $supplier->getUserId())) {
            View::addFlash('suppliers', 'danger', 'Access denied');
            throw new RedirectException('/suppliers');
        }
        $suppliers = SuppliersModel::getSuppliers($this->logistic_company->getId(), 0, $this->user->isRoleSA() ? 0 : $this->user->getId());
        if (!in_array($id, array_keys($suppliers))) {
            View::addFlash('suppliers', 'danger', 'Company not found for this warehouse');
            throw new RedirectException('/suppliers');
        }
        $this->disableWarehouseChange();
        return $supplier;
    }

    /** @throws */
    public function getCustomReturnDocFile()
    {
        $supplier_id = (int)$_GET['id'] ?? 0;
        if (!empty($supplier = SuppliersModel::getById($supplier_id))) {
            if ($supplier->getCustomReturnDoc() && $supplier->existCustomReturnDocFile()) {
                File::outputStoredDocument($supplier->getCustomReturnDocPath(), (string)$supplier->getCustomReturnDoc());
            }
            return;
        }
        throw new RedirectException($_SERVER['HTTP_REFERER']);
    }
}
