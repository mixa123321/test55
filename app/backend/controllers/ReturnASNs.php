<?php

namespace app\backend\controllers;

use app\backend\models\ASNHistories;
use app\backend\models\ASNs;
use app\backend\models\ASNSKUs;
use app\backend\models\ASNTemporaries;
use app\backend\models\OrdersOriginalDespatched;
use app\backend\models\ShopInventoryQueues;
use app\core\dbal\SafeMysql;
use app\core\entities\ASN;
use app\core\entities\ASNSKU;
use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\ReturnASNs as ReturnASNsModel;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\backend\models\Orders;
use app\backend\models\GoodsIn;
use app\core\models\ASNCompletes;
use app\backend\models\SKUs as SKUsModel;
use app\core\Queue;
use app\backend\models\Locations;
use app\backend\models\SKUBarcodes;
use app\core\models\Warehouses;

class ReturnASNs extends AuthController
{
    public function index()
    {
        $_SESSION['return_asn_filters'] = http_build_query($_GET);
        $suppliers = $this->getSuppliers(['warehouse_id' => $this->warehouse_id]);
        $filters = $this->createFilters($_GET, $suppliers);
        $rasn_list = ReturnASNsModel::getAll($filters);
        $current_filters = $filters;
        if(isset($current_filters))
            unset($current_filters['c']);
        $data = [
            'suppliers' => (count($suppliers) > 1 ? ['all'] : []) + $suppliers,
            'rasn_list' => $rasn_list['records'],
            'status_list' => ['all' => 'all'] + ASN::$statusNameReturn,
            'total_count' => $rasn_list['total'],
            'total_pages' => ceil($rasn_list['total'] / ReturnASNsModel::LIMIT_PER_PAGE),
            'current_page' => $rasn_list['current_page'],
            'current_filters' => http_build_query($current_filters),
        ];
        $this->render($data, 'returns/rasns');
    }

    /** @throws */
    public function csvReport()
    {
        $suppliers = $this->getSuppliers(['warehouse_id' => $this->warehouse_id]);
        $filters = $this->createFilters($_GET, $suppliers);
        $limit = ReturnASNsModel::LIMIT_FLASH_GENERATE_REPORT;
        $count = !empty($_GET['count']) ? intval($_GET['count']) : 0;
        unset($_GET['count']);
        if ($count <= $limit)
            $count = ReturnASNsModel::getTotalCountReportCSV($filters);
        if ($count <= $limit) {
            set_time_limit(0);
            header('Content-Encoding: UTF-8');
            header('Content-Type: application/excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . ReturnASNsModel::getReportName() . '"');
            ReturnASNsModel::getAllAsCSV($filters);
        } else {
            $user_job_id = Queue::addJobToGenerateReportCSV([
                'user_id' => $this->user->getId(),
                'filters' => $_GET,
                'addition' => $filters['warehouse_id'],
                'count' => $count
            ], self::class);
            if ($user_job_id) {
                $_SESSION['user_queue'] = true;
                $_SESSION['user_queue_flash'] = true;
                View::addFlash('upload_file', 'warning', 'Quantity of Return ASNs more than ' . $limit .
                    '. The report will be processed in the queue. Job #' . $user_job_id);
            } else {
                View::addFlash('upload_file', 'danger', "Server error");
            }
            throw new RedirectException($_SERVER['HTTP_REFERER'] ?? '/return_ASNs');
        }
    }

    /** @throws */
    public function cancelRASN()
    {
        $id = Request::getPositiveId($_POST);
        $RASN = ASNs::getById($id);
        $back = '/return_ASNs?' . ($_SESSION['return_asn_filters'] ?? '');
        if (!$RASN || !ASNs::isAccess($RASN, $this->user)) {
            View::addFlash('Return ASNs', 'danger', 'Return ASN is not found or access denied');
            throw new RedirectException($back);
        }
        if ($RASN->cancelled()) {
            View::addFlash('Return ASNs', 'warning', 'Return ASN is already cancelled');
            throw new RedirectException($back);
        }
        if ($RASN->completed()) {
            View::addFlash('Return ASNs', 'danger', 'Return ASN cannot be cancelled');
            throw new RedirectException($back);
        }
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $errors = ASNs::cancelReturn($RASN, $this->user->getId());
        if ($errors === true) {
            $mysql->commit();
            View::addFlash('Return ASNs', 'success', "Done (Return ASN $id)!");
        } else {
            $mysql->rollback();
            View::addFlash('Return ASNs', 'danger', $errors);
        }
        throw new RedirectException($back);
    }

    /** @throws */
    public function viewRASN()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        $RASN = ASNs::getById($id);
        $supplier = SuppliersModel::getById($RASN->getSupplierId());
        $back = '/return_ASNs?' . ($_SESSION['return_asn_filters'] ?? '');
        if (!$RASN || !ASNs::isAccess($RASN, $this->user) || $RASN->getWarehouseId() != $this->warehouse_id || !$RASN->getOrderId()) {
            View::addFlash('Return ASNs', 'danger', 'Return ASN is not found or access denied');
            throw new RedirectException($back);
        }
        if (!empty($this->time_diff))
            if (!empty($RASN->getCompletedDateInt()))
                $RASN->setCompletedDate($this->handleTimezone($RASN->getCompletedDate()));
        $SKUs = ASNSKUs::getByRASNId($id);
        $comments = ASNHistories::getByASN($id, $RASN->getSupplierId());
        foreach ($comments as &$comment) {
            if (empty($comment['supplier_id']))
                $comment['supplier_id'] = $comment['id'];
            unset($comment['id']);
            $supplier_comment = SuppliersModel::getById($comment['supplier_id']);
            $comment['logo'] = empty($supplier_comment) ? '' : $supplier_comment->getLogo();
            if (!empty($this->time_diff))
                $comment['create_date'] = $this->handleTimezone($comment['create_date']);
        } unset($comment);
        $order = Orders::getById($RASN->getOrderId());
        if (empty($order)) {
            View::addFlash('Return ASNs', 'danger', 'Order is not found');
            throw new RedirectException($back);
        }
        $delivery = OrdersOriginalDespatched::getHandledDelivery($order);
        if ($order->getIsHash()) {
            $delivery->setContact('<span class="badge badge-danger">Hashed</span>');
            $delivery->setEmail('<span class="badge badge-danger">Hashed</span>');
        }
        $timezone = '';
        if ($warehouse = Warehouses::getById($this->warehouse_id)) {
            $timezone = $warehouse->getTimezone();
        }
        $data = [
            'RASN' => $RASN,
            'SKUs' => $SKUs,
            'comments' => $comments,
            'order' => $order,
            'action' => $supplier->getNoActionReturns(),
            'delivery' => $delivery,
            'back' => $back,
            'timezone' => $timezone,
        ];
        $this->render($data, 'returns/view_rasn');
    }

    /** @throws */
    public function manifest()
    {
        $id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
        $RASN = ASNs::getById($id);
        if (!$id || !$RASN || !ASNs::isAccess($RASN, $this->user) || !$RASN->getOrderId()) {
            View::addFlash('Return ASNs', 'danger', 'Return ASN is not found or access denied');
            throw new RedirectException('/');
        }
        GoodsIn::createPdfManifest($RASN, ASNSKUs::getManifestRASN($id));
    }

    /** @throws */
    public function updateLocation()
    {
        $RASN_id = Request::getPositiveId($_POST);
        $RASN = ASNs::getById($RASN_id);
        $back = '/return_ASNs?' . ($_SESSION['return_asn_filters'] ?? '');
        if (!$RASN || !ASNs::isAccess($RASN, $this->user) || !$RASN->getOrderId()) {
            View::addFlash('Return ASNs', 'danger', 'Return ASN is not found or access denied');
            throw new RedirectException($back);
        }
        if (ASNs::updateLocation($RASN, $_POST['location'], $this->user->getId()))
            View::addFlash('Return ASNs', 'success', 'Done!');
        else
            View::addFlash('Return ASNs', 'danger', 'Invalid information');
        throw new RedirectException("/return_ASNs/view_rasn/?id=$RASN_id");
    }

    /** @throws */
    public function addComment()
    {
        $id = Request::getPositiveId($_POST);
        $RASN = ASNs::getById($id);
        $back = '/return_ASNs?' . ($_SESSION['return_asn_filters'] ?? '');
        if (!$RASN || !ASNs::isAccess($RASN, $this->user) || !$RASN->getOrderId()) {
            View::addFlash('Return ASNs', 'danger', 'Return ASN is not found or access denied');
            throw new RedirectException($back);
        }
        $errors = ASNHistories::commentASN($id, $this->user->getId(), $_POST['comment']);
        if ($errors === true)
            View::addFlash('Return ASNs', 'success', "Done (return ASN #$id)!");
        elseif (is_array($errors) && !empty($errors))
            View::addFlash('Return ASNs', 'danger', Arr::column($errors, 'message'));
        throw new RedirectException("/return_ASNs/view_rasn/?id=$id");
    }

    /** @throws */
    public function nextStatus()
    {
        $id = Request::getPositiveId($_POST);
        $RASN = ASNs::getById($id);
        $back = '/return_ASNs?' . ($_SESSION['return_asn_filters'] ?? '');
        if (!$RASN || !ASNs::isAccess($RASN, $this->user) || !$RASN->getOrderId()) {
            View::addFlash('Return ASNs', 'danger', 'Return ASN is not found or access denied');
            throw new RedirectException($back);
        }
        $ASNSKUs = ASNSKUs::getByRASNId($RASN->getId());
        switch ($RASN->getStatus()) {
            case ASN::NEW:
                ReturnASNsModel::updateActions($ASNSKUs, $_POST, $this->user->getId());
                break;
            case ASN::ACTION:
                if (empty($_POST['action'])) {
                    View::addFlash('Return ASNs', 'warning', 'Button was pressed twice!');
                    throw new RedirectException("/return_ASNs/view_rasn/?id=$id");
                }
                ReturnASNsModel::updateWarehouses($ASNSKUs, $_POST, $this->user->getId());
                break;
            case ASN::WAREHOUSE:
                if ($this->user->isClientSide() || !empty($_POST['action'])) {
                    View::addFlash('Return ASNs', 'warning', 'Button was pressed twice!');
                    throw new RedirectException("/return_ASNs/view_rasn/?id=$id");
                }
                break;
        }
        $RASN->setNextStatus();
        $RASN->save(['id', 'status']);
        ASNHistories::returnStatusASN($id, $RASN->getStatus(), $this->user->getId());
        View::addFlash('Return ASNs', 'success', "Done (return ASN #$id)!");
        throw new RedirectException("/return_ASNs/view_rasn/?id=$id");
    }

    /** @throws */
    public function complete()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        $RASN = ASNs::getById($id);
        if (!empty($_GET['p']))
            $back = "/goods_in/{$_GET['p']}?c={$_GET['c']}&s={$_GET['s']}";
        else
            $back = empty($_GET['view']) ? ('/return_ASNs?' . ($_SESSION['return_asn_filters'] ?? '')) : "/return_ASNs/view_rasn?id=$id";
        if (!$RASN || !ASNs::isAccess($RASN, $this->user)) {
            View::addFlash('complete', 'danger', 'Return ASN is not found or access denied');
            throw new RedirectException($back);
        }
        $SKUs_ASN = ASNSKUs::getByASNId($id);
        $SKUs_tmp = ASNTemporaries::getPutawayQuantity($id);
        $SKUs_cmp = ASNCompletes::getSKUsLocations($id);
        foreach ($SKUs_ASN as $key => $SKU)
            if (!empty($SKUs_tmp[$SKU['id']])) {
                $SKUs_ASN[$key]['qty'] = $SKUs_tmp[$SKU['id']]['summa'];
                unset($SKUs_tmp[$SKU['id']]);
            } else
                $SKUs_ASN[$key]['qty'] = 0;
        foreach ($SKUs_tmp as $key => $SKU) {
            $SKU_user = SKUsModel::getById($key);
            if (empty($SKU_user)) {
                View::addFlash('complete', 'danger', "SKU (#$key) is not found");
                throw new RedirectException($back);
            }
            $SKUs_ASN[] = [
                'id' => $SKU_user->getId(),
                'name' => $SKU_user->getName(),
                'barcode' => SKUBarcodes::getBarcodesBySKUIdString($SKU_user->getId()),
                'quantity' => 0,
                'photo' => $SKU_user->getPhoto(),
                'qty' => $SKU['summa'],
            ];
        }
        $data = [
            'ASN_id' => $id,
            'SKUs' => $SKUs_ASN,
            'addSKUs' => $SKUs_cmp,
            'back' => $back,
        ];
        $this->render($data, 'returns/complete_rasn');
    }

    /** @throws */
    public function completed()
    {
        $back = empty($_POST['back']) ? '/return_ASNs' : addslashes($_POST['back']);
        $RANS_id = Request::getPositiveId($_POST);
        $RASN = ASNs::getById($RANS_id);
        if (!$RASN || !ASNs::isAccess($RASN, $this->user)) {
            View::addFlash('Complete', 'danger', 'Return ASN is not found or access denied');
            throw new RedirectException($back);
        }
        if ($RASN->completed()) {
            View::addFlash('Complete', 'danger', 'Return ASN is already completed');
            throw new RedirectException('/return_ASNs');
        }
        $successes = [];
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        foreach ($_POST['addSKUs'] ?? [] as $SKU_id => $new_sku) {
            $location = Locations::getById($new_sku['loc_id']);
            if ($location->getWarehouse() != $this->warehouse_id) {
                View::addFlash('Complete', 'danger', 'Return ASN is not found in this warehouse');
                $mysql->rollback();
                throw new RedirectException('/return_ASNs');
            }
            $SKU = SKUsModel::getById($SKU_id);
            $data = [
                'ASN_id' => $RANS_id,
                'SKU_id' => $SKU_id,
                'quantity' => 0,
                'accepted_quantity' => $new_sku['qty'],
                'batch_number' => json_encode(['condition' => 4, 'reason' => 5, 'refund' => 3, 'action' => 1])
            ];
            $ASN_SKU = new ASNSKU($data);
            $ASN_SKU->save(array_keys($data));
            $errors = ASNHistories::deviationASN($RANS_id, $this->user->getId(), $SKU_id, $new_sku['qty'], $location->getLocation($this->warehouse_label));
            if ($errors !== true)
                View::addFlash('Deviation', 'danger', $errors);
            $successes[] = "Return ASN #{$RANS_id}: the SKU (" . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()) . ") in the amount of {$new_sku['qty']} arrived and placed in the location ({$location->getLocation($this->warehouse_label)})";
        }
        if (!empty($successes))
            View::addFlash('Complete', 'success', $successes);
        $isComplete = true;
        foreach ($_POST['SKUs'] as $SKU_id => $SKU_data) {
            $errors = ASNs::toComplete($RANS_id, $SKU_id, $SKU_data['qty'], $SKU_data['quantity'], $this->user->getId());
            if ($errors !== true)
                View::addFlash('Complete', 'danger', $errors);
            if ($SKU_data['quantity'] != $SKU_data['qty'])
                $isComplete = false;
        }
        if (count($_POST['addSKUs'] ?? []))
            $isComplete = false;
        $errors = ASNs::completed($RASN, ASN::COMPLETED, $this->user->getId());
        if ($errors !== true) {
            $mysql->rollback();
            View::addFlash('Complete', 'danger', $errors);
            throw new RedirectException('/return_ASNs');
        }
        if (!$isComplete)
            ASNHistories::notCompletedRASN($RASN, $this->user->getId());
//        Queue::addJobToSetInventoryLevel(['asn_id' => $RANS_id]);
        ShopInventoryQueues::addASN($RANS_id);
        ASNTemporaries::clear($RANS_id);
        ASNCompletes::clear($RANS_id);
        $mysql->commit();
        View::addFlash('Complete', 'success', "Return ASN #{$RANS_id} is completed!");
        throw new RedirectException($back);
    }

    /** @throws */
    public function actioned()
    {
        if (empty($_POST) || !isset($_POST['actioned']) || empty($_POST['ASN_id']))
            return $this->response = ['result' => 'error'];
        $RASN = ASNs::getById($_POST['ASN_id']);
        if (!$RASN || !ASNs::isAccess($RASN, $this->user)) {
            View::addFlash('Actioned', 'danger', 'Return ASN is not found or access denied');
            throw new RedirectException('/return_ASNs');
        }
        $RASN->setActioned($_POST['actioned']);
        $RASN->save(['id', 'actioned']);
        ASNHistories::returnActionedASN($RASN->getId(), $RASN->getActioned(), $this->user->getId());
        return $this->response = ['result' => 'success'];
    }

    protected function createFilters(array $filters, array $suppliers): array
    {
        $filters['warehouse_id'] = $this->warehouse_id;
        if ($this->user->isRoleSupplierUser()) {
            $filters['supplier_id'] = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId())['id'];
        }
        if (empty($filters['supplier_id']) || (!empty($filters['supplier_id']) && !in_array($filters['supplier_id'], array_keys($suppliers))))
            $filters['supplier_id'] = array_keys($suppliers);
        return $filters;
    }
}
