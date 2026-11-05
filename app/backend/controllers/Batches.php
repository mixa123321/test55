<?php

namespace app\backend\controllers;

use app\backend\models\Batches as BatchesModel;
use app\backend\models\SKUHistories;
use app\backend\models\Suppliers;
use app\backend\models\Locations;
use app\core\dbal\SafeMysql;
use app\core\entities\Batch;
use app\core\entities\Location;
use app\core\exceptions\BatchException;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\View;
use app\core\models\SKULocations;
use app\core\Queue;
use app\core\statical\WithDate;

class Batches extends AuthController
{
    /** @throws RedirectException */
    public function expired()
    {
        $current = (int)($_GET['c'] ?? 1);
        unset($_GET['c']);
        if (!isset($_GET['days']) || (int)$_GET['days'] < 0)
            $_GET['days'] = Batch::MIN_EXPIRED;
        $filters = $_GET;
        $suppliers = [];
        if ($this->user->isRoleSupplierUser()) {
            $supplier = Suppliers::getSupplierByUser($this->warehouse_id, $this->user->getId());
            if ($supplier === null) {
                View::addFlash('company', 'danger', 'Company not found');
                throw new RedirectException('/');
            }
            $filters['supplier_id'] = $supplier['id'];
        } else {
            $suppliers = Suppliers::getByUserInstance($this->warehouse, $this->user);
            $supplier_ids = array_keys($suppliers);
            if (!empty($filters['supplier_id']) && !in_array($filters['supplier_id'], $supplier_ids)) {
                View::addFlash('housekeeping', 'danger', 'Supplier not found');
                unset($filters['supplier_id']);
            }
            if (empty($filters['supplier_id']) && !empty($suppliers)) {
                $filters['supplier_id'] = $supplier_ids;
            }
            if (!empty($suppliers))
                $suppliers = [0 => 'All'] + Arr::map($suppliers, 'id', 'name');
        }
        list($SKUs, $size) = BatchesModel::getSKUExpired($this->warehouse_id, $filters, $current);
        if ($SKUs === false) {
            View::addFlash('housekeeping', 'danger', "Location {$this->warehouse_label}-" . implode('-', Location::SPECIAL_LOCATION__EXPIRED) . ' not found. Please contact system administrator');
            throw new RedirectException('/');
        }
        $data = [
            'SKUs' => $SKUs,
            'suppliers' => $suppliers,
            'size' => $size,
            'pages' => (int)($size / RECORD_PAGE) + ($size % RECORD_PAGE > 0),
            'current' => $current,
        ];
        $this->render($data, 'inventory/expired');
    }

    /** @throws RedirectException */
    public function exportExpired()
    {
        $count = !empty($_GET['count']) ? intval($_GET['count']) : 0;
        unset($_GET['count']);
        if (!isset($_GET['days']) || (int)$_GET['days'] < 0)
            $_GET['days'] = Batch::MIN_EXPIRED;
        $limit = BatchesModel::LIMIT_FLASH_GENERATE_REPORT;
        $filters = $_GET;
        $filters['warehouse_id'] = $this->warehouse_id;
        if ($this->user->isRoleSupplierUser()) {
            $supplier = Suppliers::getSupplierByUser($this->warehouse_id, $this->user->getId());
            if ($supplier === null) {
                View::addFlash('company', 'danger', 'Company not found');
                throw new RedirectException('/');
            }
            $filters['supplier_id'] = $supplier['id'];
        } else {
            $suppliers = Suppliers::getByUserInstance($this->warehouse, $this->user);
            $supplier_ids = array_keys($suppliers);
            if (!empty($filters['supplier_id']) && !in_array($filters['supplier_id'], $supplier_ids))
                unset($filters['supplier_id']);
            if (empty($filters['supplier_id']) && !empty($suppliers))
                $filters['supplier_id'] = $supplier_ids;
        }
        if ($count <= $limit)
            $count = BatchesModel::getTotalCountReportCSV($filters);
        if ($count <= $limit) {
            set_time_limit(0);
            header('Content-Encoding: UTF-8');
            header('Content-Type: application/excel; charset=UTF-8');
            header(sprintf('Content-Disposition: attachment; filename="%s"', BatchesModel::getReportName()));
            BatchesModel::getAllAsCSV($this->warehouse_id, $filters, $this->user);
        } else {
            $user_job_id = Queue::addJobToGenerateReportCSV([
                'user_id' => $this->user->getId(),
                'filters' => $filters,
                'addition' => $this->warehouse_id,
                'count' => $count
            ], self::class);
            if ($user_job_id) {
                $_SESSION['user_queue'] = true;
                $_SESSION['user_queue_flash'] = true;
                View::addFlash('upload_file', 'warning', "Quantity of Expired batches more than $limit. The report will be processed in the queue. Job #$user_job_id");
            } else
                View::addFlash('upload_file', 'danger', "Server error");
            if (!empty($filters['supplier_id']) && is_array($filters['supplier_id']))
                unset($filters['supplier_id']);
            throw new RedirectException("/batches/expired/?" . http_build_query($filters));
        }
    }

    /** @throws RedirectException */
    public function expiredTool()
    {
        $current = 1;
        if (isset($_GET['c']))
            $current = (int)$_GET['c'];
        unset($_GET['c']);
        if (!isset($_GET['supplier_id']))
            $_GET['supplier_id'] = 0;
        $filters = $_GET;
        $suppliers = [];
        if ($this->user->isRoleSupplierAdmin()) {
            $suppliers = Suppliers::getByUserInstance($this->warehouse, $this->user);
            $supplier_ids = array_keys($suppliers);
            if (!$suppliers) {
                View::addFlash('expired', 'danger', 'Companies not found');
                throw new RedirectException('/');
            }
            if (!empty($filters['supplier_id']) && !in_array($filters['supplier_id'], $supplier_ids)) {
                View::addFlash('expired', 'danger', 'Supplier not found');
                $filters['supplier_id'] = $_GET['supplier_id'] = 0;
            }
            $filters['supplier_id'] = $supplier_ids;
            if (!empty($suppliers))
                $suppliers = [0 => 'All'] + Arr::map($suppliers, 'id', 'name');
        } elseif ($this->user->isRoleSupplierUser()) {
            $supplier = Suppliers::getSupplierByUser($this->warehouse_id, $this->user->getId());
            if ($supplier === null) {
                View::addFlash('expired', 'danger', 'Company not found');
                throw new RedirectException('/');
            }
            $filters['supplier_id'] = $supplier['id'];
        } elseif ($this->user->isRoleSA() || $this->user->isRoleManager()) {
            $suppliers = Suppliers::getByUserInstance($this->warehouse, $this->user);
            $supplier_ids = array_keys($suppliers);
            if (!empty($filters['supplier_id']) && !in_array($filters['supplier_id'], $supplier_ids)) {
                View::addFlash('expired', 'danger', 'Supplier not found');
                $filters['supplier_id'] = $_GET['supplier_id'] = 0;
            }
            if (!empty($suppliers))
                $suppliers = [0 => 'All'] + Arr::map($suppliers, 'id', 'name');
        } else {
            View::addFlash('bundles', 'danger', 'Access denied');
            throw new RedirectException('/');
        }
        list($SKUs, $size) = BatchesModel::getExpiredGoodsOut($this->warehouse_id, $filters, $current);
        if ($SKUs === false) {
            View::addFlash('expired', 'danger', "Location {$this->warehouse_label}-" . implode('-', Location::SPECIAL_LOCATION__EXPIRED) . ' not found. Please contact system administrator');
            throw new RedirectException('/');
        }
        $data = [
            'SKUs' => $SKUs,
            'size' => $size,
            'suppliers' => $suppliers,
            'pages' => (int)($size / RECORD_PAGE) + ($size % RECORD_PAGE > 0),
            'current' => $current,
            'current_filters' => http_build_query($filters),
        ];
        $this->render($data, 'inventory/expired_tool');
    }

    /** @throws RedirectException */
    public function destroyExpired()
    {
        if (empty($_GET) || empty($_GET['SKU_id']) || empty($_GET['batch_id']) || empty($_GET['quantity'])) {
            View::addFlash('expired', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        /** @var array $loc_exp */
        $loc_exp = Locations::getByExpiredLocation($this->warehouse_id);
        if (empty($loc_exp)) {
            View::addFlash('expired', 'danger', 'Expired location for warehouse is absent');
            throw new RedirectException('/');
        }
        $loc_exp = $loc_exp[0];
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $recs = SKULocations::destroyExpired($loc_exp->getId(), (int)$_GET['SKU_id'], (int)$_GET['batch_id']);
        View::addFlash('expired', 'success', "$recs row(s) was deleted");
        SKUHistories::destroyExpired(
            (int)$_GET['SKU_id'],
            (int)$_GET['quantity'],
            ['location_id' => $loc_exp->getId(), 'batch_id' => (int)$_GET['batch_id']],
            !empty($_GET['order']) && (int)$_GET['order'] === 1 ? SKUHistories::EVENT_EXPIRED_ORDER : SKUHistories::EVENT_DESTROY_EXPIRED,
            $this->warehouse_id,
            $this->user->getId());
        $mysql->commit();
        unset($_GET['SKU_id'], $_GET['batch_id'], $_GET['quantity']);
        throw new RedirectException('/batches/expired_tool/?' . http_build_query($_GET));
    }

    /** @throws RedirectException | BatchException */
    public function manifest()
    {
        if (empty($_GET) || empty($_GET['id']) || empty($_GET['batch_id'])) {
            View::addFlash('expired', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $batches = BatchesModel::getBatchesBySKU($_GET['id']);
        if (empty($batches) || empty($batches[$_GET['batch_id']])) {
            View::addFlash('expired', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        /** @var array $loc_exp */
        $loc_exp = Locations::getByExpiredLocation($this->warehouse_id);
        if (empty($loc_exp)) {
            View::addFlash('expired', 'danger', 'Expired location for warehouse is absent');
            throw new RedirectException('/');
        }
        BatchesModel::createPdfManifest($_GET['id'], $_GET['batch_id'], $loc_exp[0]->getId());
    }

    public function getHistoryDestroy()
    {
        if (!empty($_POST))
            return null;
        $from_date = date(WithDate::DATE_SIMPLE_FORMAT, strtotime('-1 months'));
        $destroy = SKUHistories::getDestroyed($from_date, SKUHistories::EVENT_DESTROY_EXPIRED, $this->warehouse_id);
        $orders = SKUHistories::getDestroyed($from_date, SKUHistories::EVENT_EXPIRED_ORDER, $this->warehouse_id);
        $handleDate = function (&$item)  {
          if (!empty($this->time_diff))
              $item['event_date'] = $this->handleTimezone($item['event_date']);
            $item['event_date'] = WithDate::toFrontFormat($item['event_date']);
        };
        if (!empty($destroy))
            array_walk($destroy, $handleDate);
        if (!empty($orders))
            array_walk($orders, $handleDate);
        return $this->response = ['destroy' => json_encode($destroy), 'order' => json_encode($orders)];
    }
}
