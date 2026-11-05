<?php

namespace app\backend\controllers;

use app\backend\models\LocationHistoryReport;
use app\backend\models\Reports as ReportsModel;
use app\backend\models\Locations;
use app\backend\models\SKUHistories;
use app\backend\models\Suppliers;
use app\backend\models\Users;
use app\backend\models\WebhookHistories;
use app\core\exceptions\RedirectException;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\perms\Roles;
use app\backend\models\SKUs;
use app\core\entities\SKU;
use app\core\Queue;
use app\core\traits\CommonAjaxRequests;

class Reports extends AuthController
{
    use CommonAjaxRequests;

    public function index()
    {
        $report_by = ReportsModel::setReportMode(intval($_GET['report_by'] ?? ReportsModel::REPORT_DEFAULT));
        $suppliers = $this->getSuppliers(['warehouse_id' => $this->warehouse_id]);

        $event_categories = ReportsModel::SKU_EVENT_CATEGORIES;
        $event_names = SKUHistories::getEventDisplayNames();
        $all_names = [];
        foreach ($event_categories as $category => $events)
            foreach ($events as $event)
                if (isset($event_names[$event]))
                    $all_names[$event] = $event_names[$event];

        $data = [
            'report_by' => $report_by,
            'reports' => ReportsModel::REPORTS,
            'suppliers' => $suppliers,
            'intervals' => ReportsModel::INTERVALS,
            'workers' => ['all'] + Users::getAllWorkers($this->warehouse_id),
            'in_date' => $this->getInDate(),
            'has_sku_report_permission' => $this->hasAccessToRoute('reports/csv_report_skus'),
            'has_location_history_report_permission' => $this->hasAccessToRoute('reports/csv_report_location_history'),
            'all_event_names' => $all_names,
            'sku_event_categories' => $event_categories,
        ];
        $this->render($data, 'reports/reports');
    }

    public function getLocationsForSearch()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $term = (string)($_GET['term'] ?? '');
        $term = mb_ereg('^\s+$', $term) ? ' ' : trim($term);
        if ($term === '')
            return $this->response = ['data' => [], 'errors' => []];
        return $this->response = [
            'data' => LocationHistoryReport::getLocationsForSearch($this->warehouse_id, $term),
            'errors' => []
        ];
    }

    public function getAsyncData()
    {
        $filters = $_GET;
        $report_by = ReportsModel::setReportMode(intval($_GET['report_by'] ?? ReportsModel::REPORT_DEFAULT));
        $suppliers = array_map(function($s) { return $s['name']; }, Suppliers::getByFilters($this->logistic_company->getId(), ['warehouse_id' => $this->warehouse_id]));
//        ReportsModel::clearFiles($this->user->getId());
        $errors = ReportsModel::validate($this->warehouse_id, $filters, $suppliers);
        if ($report_by && empty($errors))
            list($records, $total, $filename) = ReportsModel::getReportChartData($this->warehouse, $filters, $this->user->getId(), $suppliers);
        return $this->response = [
            'records' => $records ?? [],
            'errors' => $errors,
            'total' => $total ?? 0,
            'filename' => $filename ?? ''
        ];
    }

    /** @throws */
    public function csvReport()
    {
        $filename = $_GET['filename'] ?? false;
        if (!ReportsModel::saveCSV(CSV_STORAGE, $filename, $this->user->getId() . DIRECTORY_SEPARATOR . ReportsModel::PATH . DIRECTORY_SEPARATOR)){
            View::addFlash('reports', 'danger', 'Report fail');
            throw new RedirectException('/reports');
        }
        return;
    }

    /** @throws */
    public function csvReportLocations()
    {
        if (empty($_GET) || empty($_GET['supplier_id'])) {
            View::addFlash('upload_file', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        if ($_GET['supplier_id'] === ReportsModel::LOCATIONS_REPORT_ALL) {
            // all clients' data: only for roles that see the Locations report
            if (!in_array($this->user->getRoleId(), Roles::MANAGERS)) {
                View::addFlash('upload_file', 'danger', 'Access denied');
                throw new RedirectException('/');
            }
            $supplier_id = ReportsModel::LOCATIONS_REPORT_ALL;
            $name = 'All';
        } else {
            // only clients available to the current user in this warehouse
            $suppliers = $this->getSuppliers(['warehouse_id' => $this->warehouse_id]);
            $supplier_id = (int)$_GET['supplier_id'];
            if (!isset($suppliers[$supplier_id])) {
                View::addFlash('upload_file', 'danger', 'Supplier is not found');
                throw new RedirectException('/');
            }
            $name = substr(implode('', preg_grep("/\w/", str_split($suppliers[$supplier_id]))), 0, 8);
        }
        $filename = ReportsModel::LocationsHeld($this->warehouse_id, $supplier_id, $name, !empty($_GET['empty']));
        if (!ReportsModel::saveCSV(REPORT_STORAGE, $filename)){
            View::addFlash('reports', 'danger', 'Report fail');
            throw new RedirectException('/reports');
        }
        return;
    }

    /** @throws */
    public function csvReportCTDaily()
    {
        if (empty($_GET) || empty($_GET['date_for'])) {
            View::addFlash('reports', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $filename = ReportsModel::CollectionTimeDaily($this->warehouse, $_GET['date_for'] ?? date('Y-m-d'));
        if (!ReportsModel::saveCSV(REPORT_STORAGE, $filename)){
            View::addFlash('reports', 'danger', 'Report fail');
            throw new RedirectException('/reports');
        }
        return;
    }

    /** @throws */
    public function csvReportCTInterval()
    {
        if (empty($_GET) || empty($_GET['date_from']) || empty($_GET['date_to'])) {
            View::addFlash('reports', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        if (!empty($_GET['supplier_id'])) {
            $supplier = Suppliers::getById($_GET['supplier_id']);
            if (empty($supplier)) {
                View::addFlash('upload_file', 'danger', 'Supplier is not found');
                throw new RedirectException('/');
            }
        }
        $filename = ReportsModel::CollectionTimeInterval($this->warehouse, $_GET['date_from'], $_GET['date_to'], $_GET['supplier_id']);
        if (!ReportsModel::saveCSV(REPORT_STORAGE, $filename)){
            View::addFlash('reports', 'danger', 'Report fail');
            throw new RedirectException('/reports');
        }
        return;
    }

    /** @throws */
    public function viewWebhook()
    {
        $id = Request::getPositiveId();
        if (empty($id) || !$this->user->isRoleSA() || !$this->warehouse_virtual) {
            View::addFlash('webhook', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $webhook = WebhookHistories::getById($id);
        if (empty($webhook)) {
            View::addFlash('webhook', 'danger', 'Webhook is not found');
            throw new RedirectException('/');
        }
        $data = [
            'webhook' => $webhook,
        ];
        $this->render($data, 'reports/webhook');
    }

    public function csvReportSkus()
    {
        $sku_id = isset($_GET['sku_id']) ? (int)$_GET['sku_id'] : 0;
        $supplier_id = isset($_GET['supplier_id']) && !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
        $events = isset($_GET['events']) ? json_decode($_GET['events'], true) : null;

        if (empty($sku_id)) {
            View::addFlash('reports', 'danger', 'Invalid SKU ID');
            throw new RedirectException('/reports');
        }

        if (empty($supplier_id)) {
            View::addFlash('reports', 'danger', 'Supplier must be selected');
            throw new RedirectException('/reports');
        }

        if (empty($events)) {
            View::addFlash('reports', 'danger', 'At least one event type must be selected');
            throw new RedirectException('/reports');
        }

        $sku = SKUs::getById($sku_id);
        if (empty($sku) || $sku->getSupplierId() != $supplier_id) {
            View::addFlash('reports', 'danger', 'SKU not found for selected supplier');
            throw new RedirectException('/reports');
        }

        $result = ReportsModel::generateSKUHistoryReport($sku->getId(), $this->warehouse, $events);
        if (!empty($result['error'])) {
            View::addFlash('reports', 'danger', $result['error']);
            throw new RedirectException('/reports');
        }

        $filename = $result['filename'];

        if (!ReportsModel::saveCSV(REPORT_STORAGE, $filename)) {
            View::addFlash('reports', 'danger', 'Report fail');
            throw new RedirectException('/reports');
        }

        return;
    }

    public function csvReportLocationHistory()
    {
        $location_id = Request::getPositiveId($_GET, 'location_id');
        $location = Locations::getById($location_id);
        if (empty($location) || (int)$location->getWarehouse() !== (int)$this->warehouse_id) {
            View::addFlash('reports', 'danger', 'Location is not found');
            throw new RedirectException('/reports');
        }

        $filters = [
            'warehouse_id' => $this->warehouse_id,
            'location_id' => $location_id,
        ];
        $count = LocationHistoryReport::getTotalCountReportCSV($filters);
        if ($count <= 0) {
            View::addFlash('reports', 'warning', 'No location history found');
            throw new RedirectException('/reports');
        }

        $user_job_id = Queue::addJobToGenerateReportCSV([
            'user_id' => $this->user->getId(),
            'filters' => $filters,
            'addition' => [
                'warehouse_id' => $this->warehouse_id,
                'location_id' => $location_id,
            ],
            'count' => $count
        ], LocationHistoryReport::class);

        if ($user_job_id) {
            $_SESSION['user_queue'] = true;
            $_SESSION['user_queue_flash'] = true;
            View::addFlash('reports', 'success', 'The report will be processed in the queue. Job #' . $user_job_id);
        } else {
            View::addFlash('reports', 'danger', 'Server error');
        }
        throw new RedirectException('/reports');
    }
}
