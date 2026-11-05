<?php

namespace app\backend\controllers;

use app\backend\models\Roles;
use app\backend\models\Suppliers as SuppliersModel;
use app\core\dbal\SafeMysql;
use app\core\entities\Consumable;
use app\core\entities\ConsumableHistory;
use app\core\entities\Supplier;
use app\core\exceptions\RedirectException;
use app\backend\models\Consumables as ConsumablesModel;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\ConsumableHistories;
use app\core\models\ConsumablesCount;
use app\backend\models\Packages;

class Consumables extends AuthController
{
    /** @throws RedirectException */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        parent::checkSupplierAccess();
    }

    /** @throws RedirectException */
    public function index()
    {
        $_SESSION[ConsumablesModel::FILTER_PARAM] = http_build_query($_GET);
        $filters = $_GET;
        if ($this->user->isRoleSupplierUser()){
            $supplier = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId());
            if (empty($supplier)) {
                View::addFlash('consumables', 'danger', 'Company is not found');
                throw new RedirectException('/');
            }
            $filters['supplier_id'] = $supplier['id'];
        }
        $suppliers = !$this->user->isRoleManager()
            ? SuppliersModel::getSuppliers4SKUs($this->warehouse, $this->user)
            : SuppliersModel::getByFilters($this->logistic_company->getId(), ['warehouse_id' => $this->warehouse_id]);
        $roles = Roles::getById($this->user->getRoleId());
        $filters['side'] = $roles->getSide();
        if (!isset($filters['supplier_id']) || $filters['supplier_id'] < 0)
            $filters['supplier_id'] = array_keys($suppliers);
        if (empty($filters['supplier_id'])) {
            View::addFlash('consumables', 'danger', 'Company is not found');
            $raw_data = ['records' => [], 'total' => 0, 'current_page' => 0, 'pages' => 0];
        } else
            $raw_data = ConsumablesModel::getAvailableByFilter($this->logistic_company->getId(), $this->warehouse_id, $filters);
        unset($filters['c']);
        $data = [
            'consumables' => $raw_data['records'],
            'total_count' => $raw_data['total'],
            'total_pages' => ceil($raw_data['total'] / ConsumablesModel::LIMIT_PER_PAGE),
            'current_page' => $raw_data['current_page'],
            'current_filters' => http_build_query($_GET),
            'suppliers' => $suppliers,
            'side' => $roles->getSide(),
            'lc_id' => $this->logistic_company->getId(),
        ];
        $this->render($data, 'consumables/consumables');
    }

    /** @throws RedirectException */
    public function consumableAdd()
    {
        if (!empty($_GET['type']) && !in_array($_GET['type'], array_keys(Consumable::TYPES))) {
            View::addFlash('consumables', 'danger', 'Type of consumable is not found');
            throw new RedirectException('/consumables');
        }
        $this->disableWarehouseChange();
        $consumable_id = $_GET['consumable_id'] ?? 0;
        $supplier = null;
        if (empty($consumable_id)) {
            if (empty($_GET['type'])) {
                View::addFlash('consumables', 'danger', 'Type of consumable is absent');
                throw new RedirectException('/consumables');
            }
            $consumable = new Consumable(['type' => $_GET['type']]);
            if ($this->user->isRoleSupplierUser())
                $suppliers = [SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId())];
            elseif ($this->user->isRoleManager())
                $suppliers = SuppliersModel::getByFilters($this->logistic_company->getId(), ['warehouse_id' => $this->warehouse_id]);
            else
                $suppliers = SuppliersModel::getSuppliers4SKUs($this->warehouse, $this->user);
            if (empty($suppliers)) {
                View::addFlash('consumables', 'danger', 'Companies are not found');
                throw new RedirectException('/consumables');
            }
            if ($this->user->isWarehouseSide())
                $suppliers = [-1 => ['id' => -$this->logistic_company->getId(), 'name' => 'All']] + $suppliers;
            $min_stock_level_data = ConsumablesCount::getMinStockLevels($this->logistic_company->getId(), $consumable_id, $suppliers);
        } else {
            $consumable = ConsumablesModel::getById($consumable_id);
            if (empty($consumable)) {
                View::addFlash('consumables', 'danger', 'Comsumable is absent');
                throw new RedirectException('/consumables');
            }
            $suppliers = [];
            if ($consumable->getSupplierId() > 0) {
                $supplier = SuppliersModel::getById($consumable->getSupplierId());
                if (empty($supplier)) {
                    View::addFlash('consumables', 'danger', 'Company is absent');
                    throw new RedirectException('/consumables');
                }
            } else
                $supplier = new Supplier(['id' => -$this->logistic_company->getId()]);
            $min_stock_level_data = ConsumablesCount::getMinStockLevels($this->logistic_company->getId(), $consumable_id, [$supplier->toArray()]);
        }
        ConsumablesModel::changeMinStockLevel($min_stock_level_data, $this->user->getRoleId(), $this->warehouse_id);
        $data = [
            'type' => $consumable->getType(),
            'consumable' => $consumable,
            'suppliers' => $suppliers,
            'supplier' => $supplier,
            'back' => "/consumables/?".(!empty($_SESSION[ConsumablesModel::FILTER_PARAM]) ? $_SESSION[ConsumablesModel::FILTER_PARAM] : ''),
            'min_stock_level_data' => json_encode($min_stock_level_data),
        ];
        $this->render($data, 'consumables/add_consumable');
    }

    /** @throws RedirectException */
    private function consumableAddAgain(Consumable $consumable, $min_stock_level)
    {
        $this->disableWarehouseChange();
        $supplier = null;
        if (empty($consumable->getId())) {
            $suppliers = $this->user->isRoleSupplierUser()
                ? [SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId())]
                : (($this->user->isRoleSupplierAdmin()
                        ? []
                        : [-1 => ['id' => 0, 'name' => 'All']]) + SuppliersModel::getByUserInstance($this->warehouse, $this->user));
            if (empty($suppliers)) {
                View::addFlash('consumables', 'danger', 'Companies are not found');
                throw new RedirectException('/consumables');
            }
            $min_stock_level_data = ConsumablesCount::getMinStockLevels($this->logistic_company->getId(), $consumable->getId(), $suppliers);
        } else {
            $suppliers = [];
            if (!empty($consumable->getSupplierId())) {
                $supplier = SuppliersModel::getById($consumable->getSupplierId());
                if (empty($supplier)) {
                    View::addFlash('consumables', 'danger', 'Company is absent');
                    throw new RedirectException('/consumables');
                }
            } else
                $supplier = new Supplier(['id' => 0]);
            $min_stock_level_data = ConsumablesCount::getMinStockLevels($this->logistic_company->getId(), $consumable->getId(), [$supplier->toArray()]);
        }
        ConsumablesModel::changeMinStockLevel($min_stock_level_data, $this->user->getRoleId(), $this->warehouse_id);
        foreach ($min_stock_level_data[$consumable->getSupplierId()] as $key => $datum)
            $min_stock_level_data[$consumable->getSupplierId()][$key]['value'] = (int)($min_stock_level[$datum['warehouse_id']] ?? 0);
        $data = [
            'type' => $consumable->getType(),
            'consumable' => $consumable,
            'suppliers' => $suppliers,
            'supplier' => $supplier,
            'back' => "/consumables/?".(!empty($_SESSION[ConsumablesModel::FILTER_PARAM]) ? $_SESSION[ConsumablesModel::FILTER_PARAM] : ''),
            'min_stock_level_data' => json_encode($min_stock_level_data),
        ];
        $this->render($data, 'consumables/add_consumable');
    }

    /** @throws RedirectException */
    public function addingConsumable()
    {
        $back = '/consumables/?' . (!empty($_SESSION[ConsumablesModel::FILTER_PARAM]) ? $_SESSION[ConsumablesModel::FILTER_PARAM] : '');
        $_POST['consumable']['reuse'] = (int)!empty($_POST['consumable']['reuse']);
        $consumable = new Consumable($_POST['consumable']);
        $errors = ConsumablesModel::validate($consumable);
        $min_stock_levels = $_POST['min_stock_level'] ?? [];
        if (!empty($min_stock_levels))
            foreach ($min_stock_levels as $min_stock_level_value)
                if (intval($min_stock_level_value) < 0) {
                    $errors[] = ['message' => 'Min stock level error'];
                    break;
                }
        if (!empty($errors)) {
            View::addFlash('consumables', 'danger', Arr::column($errors, 'message'));
            return self::consumableAddAgain($consumable, $min_stock_levels);
        }
        $update = true;
        $update_data = $consumable->toArray();
        $old_consumable = ConsumablesModel::getById($consumable->getId());
        if (empty($old_consumable)) {
            $update = false;
            array_shift($update_data);
        } else {
            $res = [];
            foreach ($old_consumable->toArray() as $field => $value)
                if ($value != $update_data[$field])
                    $res[$field] = $update_data[$field];
            $update_data = $res;
        }
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        if (!empty($update_data)) {
            $fields = array_keys($consumable->toArray());
            if (empty($consumable->getId())) {
                $update = false;
                array_shift($fields);
            }
            $consumable->save($fields);
            ConsumableHistories::saveConsumables($consumable->getId(), $update_data, $this->user->getId(), $update ? ConsumableHistory::EVENT_UPDATE : ConsumableHistory::EVENT_CREATE);
        }
        $update_stock = false;
        if (!empty($min_stock_levels)) {
            foreach ($min_stock_levels as $w_id => $min_stock_level) {
                $min_stock_level = (int)$min_stock_level;
                $old_min_stock_level = ConsumablesCount::getMinStockLevel($consumable->getId(), $w_id);
                if ($old_min_stock_level !== $min_stock_level) {
                    ConsumablesCount::setMinStockLevel($min_stock_level, $consumable->getId(), $w_id);
                    $update_stock = true;
                }
            }
            ConsumableHistories::saveMinStockLevel($consumable->getId(), $min_stock_levels, $this->user->getId());
        }
        $mysql->commit();
        if (empty($update_data) && !$update_stock)
            View::addFlash('consumables', 'warning', 'Nothing to change');
        else
            View::addFlash('consumables', 'success', 'Consumable was ' . ($update ? 'changed' : 'created'));
        throw new RedirectException($back);
    }

    /** @throws RedirectException */
    public function consumableLock()
    {
        if (empty($_GET) || empty($_GET['id'])) {
            View::addFlash('consumables', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $id = Request::getPositiveId();
        $lock = (int)($_GET['lock'] ?? 0);
        $back = '/consumables/?' . (!empty($_SESSION[ConsumablesModel::FILTER_PARAM]) ? $_SESSION[ConsumablesModel::FILTER_PARAM] : '');
        /** @var Consumable $consumable */
        $consumable = ConsumablesModel::getById($id);
        if (empty($consumable)) {
            View::addFlash('consumables', 'danger', 'Consumable is not found');
            throw new RedirectException($back);
        }
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $consumable->setStatus($lock);
        $consumable->save(['id', 'status']);
        ConsumableHistories::changeStatus($consumable->getId(), $lock, $this->user->getId());
        if ($consumable->getStatus() == Consumable::STATUS_LOCKED && $consumable->getType() == Consumable::TYPE_PACKAGING) {
            $packages = Packages::getPackagesByPackagingId($consumable->getId());
            $nums = Packages::bulkLockPackages(Arr::column($packages, 'id'));
            if ($nums !== false && $nums > 0)
                View::addFlash('consumables', 'warning', 'Packages ' . implode(', ', Arr::column($packages, 'name')) . ' was locked');
        }
        $mysql->commit();
        View::addFlash('consumables', 'success', 'Operation is done');
        throw new RedirectException($back);
    }

    /** @throws RedirectException */
    public function consumableCount()
    {
        if (empty($_GET) || empty($_GET['id'])) {
            View::addFlash('consumables', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $id = Request::getPositiveId();
        $quantity = (int)($_GET['q'] ?? 0);
        $back = '/consumables/?' . (!empty($_SESSION[ConsumablesModel::FILTER_PARAM]) ? $_SESSION[ConsumablesModel::FILTER_PARAM] : '');
        ConsumablesCount::setQuantity($quantity, $id, $this->warehouse_id);
        ConsumableHistories::adjustment($id, $quantity, $this->warehouse_id, $this->user->getId());
        View::addFlash('consumables', 'success', 'Operation is done');
        throw new RedirectException($back);
    }

    public function getHistoryStock()
    {
        if (empty($_POST) || empty($_POST['consumable_id']))
            return [];
        $from_date = date('Y-m-d', strtotime('-1 months'));
        $stocks = ConsumablesModel::getStock($_POST['consumable_id'], $this->warehouse_id, $from_date);
        $counts = ConsumableHistories::getAdjustment($_POST['consumable_id'], $this->warehouse_id, $from_date);
        if (!empty($this->time_diff))
            array_walk($counts, function(&$item) {
                $item['event_date'] = $this->handleTimezone($item['event_date']);
            });
        return $this->response = ['stock' => json_encode($stocks), 'count' => json_encode($counts)];
    }
}