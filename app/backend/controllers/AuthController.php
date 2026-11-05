<?php

namespace app\backend\controllers;

use app\backend\models\Cart as CartModel;
use app\backend\models\Roles;
use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\Users;
use app\backend\models\UserWarehouses;
use app\core\entities\LogisticCompany;
use app\core\entities\Route;
use app\core\entities\User;
use app\core\entities\Queue;
use app\core\Environment;
use app\core\entities\Warehouse;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\Langs;
use app\core\models\Logger;
use app\core\models\LogisticCompanies;
use app\core\models\Notifiers;
use app\core\models\Routes;
use app\core\models\TokensTranslations;
use app\core\models\Warehouses;
use app\core\models\Queues;
use app\backend\models\UserJobs;
use app\core\Permission;
use app\core\services\shipping\Shipping;
use app\core\statical\Timezones;
use app\core\statical\WithDate;

abstract class AuthController extends BackendController
{
    /** @var User */
    protected $user;
    /** @var Route */
    protected $route;
    /** @var Permission */
    protected $permission;
    protected $bulk_queue;
    /** @var User[] */
    protected $available_users;
    /** @var LogisticCompany */
    protected $logistic_company;
    /** @var Warehouse */
    protected $warehouse;
    protected $warehouse_id;
    protected $multiple_warehouses;
    protected $warehouse_label;
    protected $warehouse_virtual;
    protected $warehouse_lang;
    protected $models_tokens = [];
    protected $change_warehouse = true;
    public $available_suppliers;
    public $supplier_id;
    protected $time_diff = 0;
    protected $mobile = false;
    protected $only_read = false;
    protected $user_queue;
    /** @var $shipping array */
    protected $shipping;

    /** @throws */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        if ($form = Request::fixConfirmFormResubmissionBack()) {
            echo $form;
            exit();
        }
        $this->user = Users::getAuthUser();
        if ($this->user === null) {
            if (!Request::isAjax())
                $_SESSION["authUrl"] = $_SERVER["REQUEST_URI"];
            throw new RedirectException("/auth/login");
        }
        if (!$this->user->isRoleSystem()) {
            if (!isset($_SESSION['user_queue'])) {
                $has_user_queue = UserJobs::hasUserQueue($this->user->getId());
                $_SESSION['user_queue'] = $has_user_queue;
                $this->user_queue = [$has_user_queue];
            } else
                $this->user_queue = [!empty($_SESSION['user_queue'])];
            if ($this->user_queue[0]) {
                $this->user_queue[] = isset($_SESSION['user_queue_flash']);
                unset($_SESSION['user_queue_flash']);
            }
            $this->setWarehouse();
            $this->time_diff = Timezones::getDiff($this->warehouse->getTimezone());
            $this->warehouse_lang = Langs::getCode($this->user->getLangId() ?: $this->warehouse->getLangId());
            $this->models_tokens = TokensTranslations::getModelsTokens($this->warehouse_lang);
            $this->warehouse_virtual = $this->warehouse->isVirtual();
            if ($this->user->isRoleWorker() && $this->warehouse_virtual) {
                View::addFlash('warehouse_access', 'danger', 'Unknown worker. Please contact a system administrator');
                throw new RedirectException("/auth/login");
            }
            $this->permission = new Permission($this->user, $this->warehouse_virtual);
            $this->warehouse_label = Warehouses::getLabel($this->warehouse_id);
            $this->available_users = Users::getAvailableUsers($this->warehouse, $this->user);
            $this->available_suppliers = $this->user->hasManagerAccess()
                ? SuppliersModel::getSuppliers($this->logistic_company->getId(), 0)
                : SuppliersModel::getSuppliersIdByUsers($this->warehouse_id, array_keys($this->available_users));
            $this->shipping = Shipping::createShippingMethods($this->logistic_company);
            $this->bulk_queue = $this->user->isRoleWorker() && Queues::checkExistQueue(Queue::TYPE_BULK_PRINT, $this->user->getId(), $this->warehouse_id);
        }
        $this->handleUrlAccess();
        // check moving pallet
        if (!Request::isAjax() && $this->user->isRoleWorker()) {
            $cart = CartModel::getCart($this->user);
            if ($cart && $cart->isDetached()) {
                $info = CartModel::getCartInfo($cart);
                $return = true;
                foreach ($info as $mode => $_data) {
                    switch ($mode) {
                        case CartModel::MODE_NONE:
                        case CartModel::MODE_MOVE:
                        case CartModel::MODE_EXPIRED:
                            break;
                        case CartModel::MODE_LARGE:
                        case CartModel::MODE_MULTI:
                        case CartModel::MODE_SINGLE:
                            if ($this instanceof Drop) {
                                $return = false;
                                break;
                            }
                            View::addFlash('cart', 'danger', 'You should drop SKUs from the pallet');
                            throw new RedirectException('/drop/dropping_' . substr($mode, 0, 1));
                        case CartModel::MODE_PACK:
                            // what to do here?...
                            // looks like someone unhooked pallet in a wrong way
                            break;
                    }
                }
                if ($return && (!($this instanceof Cart) || $action != 'returnPallet')) {
                    View::addFlash('cart', 'danger', 'You should return the pallet');
                    throw new RedirectException('/cart/return_pallet');
                }
            }
        }
    }

    /** @throws */
    private function setWarehouse()
    {
        $this->warehouse_id = $this->user->getWarehouseId();
        $role = Roles::getById($this->user->getRoleId());
        if (empty($role)) {
            View::addFlash('warehouse_access', 'danger', 'User error. Please contact a system administrator');
            throw new RedirectException("/auth/login");
        }
        $this->multiple_warehouses = $role->isMultipleWarehouses();
        if (empty($this->warehouse_id) || empty($this->warehouse = Warehouses::getById($this->warehouse_id))) {
            View::addFlash('warehouse_access', 'danger', 'Unknown warehouse or company not found. Please contact a system administrator');
            throw new RedirectException("/auth/login");
        }
        $this->logistic_company = LogisticCompanies::getById($this->warehouse->getLCId(), LogisticCompany::ACTIVE);
        if (empty($this->logistic_company)) {
            View::addFlash('warehouse_access', 'danger', 'Unknown warehouse or company not found. Please contact a system administrator');
            throw new RedirectException("/auth/login");
        }
        Notifiers::init($this->logistic_company);
        if ($this->user->isRoleSupplierAdmin()) {
            if (empty($whs = Users::getAllWarehouseAdmin($this->user->getId()))) {
                View::addFlash('warehouse_access', 'danger', 'Unknown warehouse. Please contact a system administrator');
                throw new RedirectException("/auth/login");
            }
            if (!in_array($this->warehouse_id, $whs)) {
                View::addFlash('warehouse_access', 'danger', 'Unknown warehouse. Please contact a system administrator');
                throw new RedirectException("/auth/login");
            }
        } elseif ($this->user->isRoleSupplierUser()) {
            $supplier = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId());
            if (empty($supplier)) {
                View::addFlash('warehouse_access', 'danger', 'Unknown warehouse. Please contact a system administrator');
                throw new RedirectException("/auth/login");
            }
        }
        if (!$this->user->isRoleAdmins() && $this->multiple_warehouses) {
            $user_warehouses = Arr::column(UserWarehouses::getAvailableWarehouses($this->user->getId()), 'id');
            if (empty($user_warehouses) || !in_array($this->warehouse_id, $user_warehouses)) {
                View::addFlash('warehouse_access', 'danger', 'Unknown warehouse. Please contact a system administrator');
                throw new RedirectException("/auth/login");
            }
        }
    }

    /** @throws */
    private function handleUrlAccess()
    {
        if ($this->user->isRoleSystem())
            throw new RedirectException('/system_settings');
        if (in_array(static::class, [Forbidden::class, Dashboard::class, Main::class]))
            return;
        if (in_array(static::class, [Tests::class, Cron::class])) {
            if (!Environment::isDev() && (!Environment::isProduction() || !$this->user->hasDevAccess()))
                throw new RedirectException('/forbidden');
            return;
        }
        if (static::class === RouteAccesses::class) {
            if (!$this->user->isRoleSA())
                throw new RedirectException('/forbidden');
            return;
        }
        if (empty($route_name = $this->getCurrentRoute()))
            return;
        if (array_key_exists($route_name, Routes::$exception_redirect))
            $route_name = Routes::$exception_redirect[$route_name];
        if (empty($this->route = Routes::find($route_name)))
            throw new RedirectException('/forbidden');
        if ($this->warehouse_virtual && !$this->route->isVirtualAccess())
            throw new RedirectException('/');
        $access = $this->permission->get($this->route);
        if ($access->getReject()) {
            View::addFlash('reject_access', 'danger', 'Access rejected');
            throw new RedirectException("/");
        }
        $this->only_read = $access->getOnlyRead();
        if (!$access->getAccess() && !$access->getOnlyRead())
            throw new RedirectException('/forbidden');
        if ($this->only_read && !$access->getAccess())
            View::addFlash('only_read', 'info', 'Only read access');
    }

    /** @throws */
    public function afterAction()
    {
        parent::afterAction();
        if (Environment::isLoggable())
            Logger::addRecord(isset($this->user) ? $this->user->getId() : 0, '', $this->httpCode);
        if (!empty($this->user) && $this->user->isRoleSystem())
            throw new RedirectException('/system_settings');
    }

    protected function render($vars = [], $view = '', $template = 'default')
    {
        $vars['authUser'] = $this->user;
        $vars['logistic_company'] = $this->logistic_company;
        $vars['warehouses'] = $this->getWarehouses();
        $vars['langs'] = Langs::getAllShort();
        $vars['warehouse_id'] = $this->warehouse_id;
        $vars['multiple_warehouses'] = $this->multiple_warehouses;
        $vars['warehouse_label'] = $this->warehouse_label;
        $vars['warehouse_lang'] = $this->warehouse_lang;
        $vars['warehouse_virtual'] = $this->warehouse_virtual;
        $vars['change_warehouse'] = $this->change_warehouse;
        $vars['user_queue'] = $this->user_queue;
        $vars['mobile'] = $this->mobile;
        $vars['referer'] = $this->getReferer();
        $vars['title'] = !empty($this->route) ? $this->route->getAlias() : '';
        $vars['permission'] = $this->permission;
        $vars['bulk_queue'] = $this->bulk_queue;
        $vars['controller'] = $this;
        $this->response = View::render($vars, $view, $template);
    }

    private function getReferer()
    {
        $referer = str_replace('https://' . $_SERVER['HTTP_HOST'], '', $_SERVER['HTTP_REFERER'] ?? '');
        if (in_array($referer, ['/auth/login', '/forbidden']) || strpos($referer, '/forbidden/mobile') !== false)
            return '/';
        return $referer;
    }

    private function getWarehouses(): array
    {
        switch ($this->user->getRoleId()) {
            case Roles::SA:
                return Warehouses::getAll($this->logistic_company->getId());
            case Roles::SUPPLIER_ADMIN:
                return Warehouses::getByIds(Users::getAllWarehouseAdmin($this->user->getId()));
            default:
                if ($this->multiple_warehouses)
                    return UserWarehouses::getAvailableWarehouses($this->user->getId());
                return [$this->warehouse_id => Warehouses::getById($this->warehouse_id)->toArray()];
        }
    }

    protected function getUsers($users, $warehouse_id = 0) : array
    {
        $result = [];
        foreach ($users as $key => $user_data) {
            $user = new User($user_data);
            if ($user->hasAdminsAccess()) {
                switch ($this->user->getRoleId()) {
                    case Roles::SA:
                        $result[$key] = $user_data;
                        break;
                    case Roles::SUPPLIER_ADMIN:
                        if (!empty($warehouse_id) && in_array($user->getWarehouseId(), Users::getAllWarehouseAdmin($user->getId()))) {
                            $result[$key] = $user_data;
                            break;
                        }
                    case Roles::SUPPLIER_USER:
                    case Roles::MANAGER:
                    case Roles::WORKER:
                        if ($user->getWarehouseId() == $warehouse_id)
                            $result[$key] = $user_data;
                        break;
                }
            }
        }
        return $result;
    }

    /** @throws */
    public function checkSupplierAccess()
    {
        $this->supplier_id = Request::getSupplierId();
        if ($this->supplier_id && $this->supplier_id != 'all' && !$this->user->isRoleSA()) {
            if (empty($this->available_suppliers[$this->supplier_id])) {
                View::addFlash('supplier_access', 'danger', 'Access denied');
                throw new RedirectException('/');
            }
        } else
            $this->supplier_id = array_keys($this->available_suppliers);
    }

    public function getSuppliers($filters_sup = []) : array
    {
        switch ($this->user->getRoleId()) {
            case Roles::SUPPLIER_ADMIN:
                $filters_sup['admin_ids'] = [$this->user->getId()];
                break;
            case Roles::SUPPLIER_USER:
                $filters_sup['user_ids'] = [$this->user->getId()];
                break;
        }
        return Arr::map(SuppliersModel::getByFilters($this->logistic_company->getId(), $filters_sup), 'id', 'name');
    }

    protected function handleTimezone($date)
    {
        return Timezones::getDiff($this->warehouse->getTimezone(), $date);
    }

    protected function getInDate()
    {
        return date(WithDate::DATE_SIMPLE_FORMAT, empty($this->time_diff) ? time() : strtotime($this->handleTimezone(date(WithDate::DATE_SQL_FORMAT))));
    }

    public function getAuthUserId(): ?int
    {
        if ($this->user instanceof User)
            return (int)$this->user->getId();
        return null;
    }

    public function hasAccessToRoute($route_name)
    {
        $route = Routes::find($route_name);
        $access = $this->permission->get($route);

        return !$access->getReject()
                && ($access->getAccess() || $access->getOnlyRead())
                && (!$this->warehouse_virtual || $route->isVirtualAccess());
    }

    public function disableWarehouseChange(): void
    {
        $this->change_warehouse = false;
    }
}
