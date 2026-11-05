<?php

namespace app\backend\controllers;

use app\backend\models\Dashboard as DashboardModel;
use app\backend\models\Orders;
use app\backend\models\Roles;
use app\backend\models\Suppliers;
use app\backend\models\Warehouses;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\HTTP;
use app\core\helpers\View;
use app\core\models\Langs;
use app\core\models\TokensTranslations;
use app\core\statical\Countries;
use app\backend\models\Users;
use app\core\statical\Timezones;
use app\core\statical\WithDate;

class Dashboard extends AuthController
{
    /** @throws \Exception */
    public function index()
    {
        if ($this->user->isRoleWorker())
            throw new RedirectException("/cart/set_cart/?id={$this->user->getId()}&back=/");
        $available_users = Arr::map(
            $this->getUsers($this->available_users, $this->warehouse_id),
            'id',
            'login'
        );
        if (empty($available_users) && $this->user->isRoleSupplierAdmin()) {
            View::addFlash('dashboard', 'warning', 'Please, create users first');
            throw new RedirectException('/suppliers');
        }
        $statuses = Orders::getStatuses($this->warehouse_id, $this->models_tokens);
        $filters = $_GET;
        $filters_sup['warehouse_id'] = $filters['warehouse_id'] = $this->warehouse_id;
        switch ($this->user->getRoleId()) {
            case Roles::SA:
                break;
            case Roles::SUPPLIER_ADMIN:
                $filters_sup['admin_ids'] = [$this->user->getId()];
                break;
            case Roles::SUPPLIER_USER:
                $filters_sup['user_ids'] = [$this->user->getId()];
                $filters['supplier_id'] = Suppliers::getSupplierByUser($this->warehouse_id, $this->user->getId())['id'];
                break;
        }
        $suppliers = Arr::map(Suppliers::getByFilters($this->logistic_company->getId(), $filters_sup), 'id', 'name');
        $_GET['warehouse_id'] = $this->warehouse_id;
        $date_to = date(WithDate::DATE_SIMPLE_FORMAT);
        if (WithDate::isNextDay(Timezones::getDateInZone($this->warehouse->getTimezone())))
            $date_to = date(WithDate::DATE_SIMPLE_FORMAT, strtotime($this->handleTimezone(date(WithDate::DATE_SIMPLE_FORMAT) . ' 23:59:59')));
        $aggregatedParams = DashboardModel::resolveAggregateParams($_GET, [
            'date_to' => $date_to,
            'date_from' => date(WithDate::DATE_SIMPLE_FORMAT, time() - 6*24*60*60),
        ]);
        if (empty($aggregatedParams['supplier_id']))
            $aggregatedParams['supplier_id'] = array_keys($suppliers);
        $all = TokensTranslations::getTranslate($this->models_tokens, 'all');
        $data = [
            'date_types' => Orders::dateTypes($this->models_tokens),
            'statuses' => $statuses,
            'countries' => [$all] + Countries::getAll(),
            'suppliers' => (count($suppliers) > 1 ? [$all] : []) + $suppliers,
            'group_by_list' => DashboardModel::getGroupByList($this->models_tokens, $this->user->isRoleSupplierUser()),
            'data' => DashboardModel::getAggregated($this->warehouse, $this->models_tokens, $aggregatedParams),
            'total' => 0,
            'date_to' => $aggregatedParams['date_to'],
            'date_from' => $aggregatedParams['date_from'],
        ];
        $this->render($data, 'dashboard');
    }

    /** @throws */
    public function reloadWarehouse()
    {
        if (empty($_POST) || empty($_POST['warehouse_id']) || !$this->multiple_warehouses)
            $this->errorAccess();
        $warehouse = Warehouses::getById($_POST['warehouse_id']);
        if (empty($warehouse) || $warehouse->getLCId() != $this->logistic_company->getId())
            $this->errorAccess();
        $is_warehouse = Users::isWarehouse($this->user, $warehouse);
        if (!$is_warehouse)
            $this->errorAccess();
        Users::changeWarehouse($this->user, $warehouse->getId());
        throw new RedirectException(isset($_SERVER['HTTP_REFERER']) ? HTTP::setParams($_SERVER['HTTP_REFERER'], 'c', 1) : '/');
    }

    /** @throws */
    public function reloadLang()
    {
        if (empty($_POST) || empty($_POST['lang_id']))
            $this->errorAccess();
        $lang_id = Langs::getIdByCode($_POST['lang_id']);
        if (!$lang_id)
            $this->errorAccess();
        Users::changeLang($this->user, $lang_id);
        throw new RedirectException(isset($_SERVER['HTTP_REFERER']) ? HTTP::setParams($_SERVER['HTTP_REFERER'], 'c', 1) : '/');
    }

    private function errorAccess()
    {
        View::addFlash('warehouse_access', 'danger', 'Access denied. Please contact a system administrator');
        throw new RedirectException("/auth/login");
    }
}
