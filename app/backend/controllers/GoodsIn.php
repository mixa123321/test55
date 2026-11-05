<?php

namespace app\backend\controllers;

use app\backend\models\GoodsIn as GoodsInModel;
use app\backend\models\ASNHistories;
use app\backend\models\ASNs;
use app\backend\models\ASNSKUs;
use app\backend\models\ASNTemporaries;
use app\backend\models\InventorySync;
use app\backend\models\Locations;
use app\backend\models\Movements_;
use app\backend\models\Orders as OrdersModel;
use app\backend\models\OrdersOriginalDespatched;
use app\backend\models\ShopInventoryQueues;
use app\backend\models\SKUHistories;
use app\backend\models\SKULocations;
use app\backend\models\SKUs as SKUsModel;
use app\backend\models\Cart;
use app\backend\models\SKUBarcodes;
use app\backend\models\WebhookHistories;
use app\core\dbal\SafeMysql;
use app\core\entities\ASN;
use app\core\entities\ASNComplete;
use app\core\entities\ASNSKU;
use app\core\entities\Location;
use app\core\entities\SKU;
use app\core\exceptions\BatchException;
use app\core\exceptions\RedirectException;
use app\core\exceptions\VirtualException;
use app\core\helpers\Arr;
use app\core\helpers\Filter;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\ASNCompletes;
use app\core\models\Batches;
use app\core\models\Suppliers as SuppliersModel;
use app\core\models\Warehouses;
use app\core\Queue;
use app\core\statical\Currencies;
use app\core\statical\Months;
use app\core\statical\WithDate;
use app\core\traits\CommonAjaxRequests;

class GoodsIn extends AuthController
{
    use CommonAjaxRequests;
    /** @throws RedirectException */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        $this->mobile = $this->user->isRoleWorker();
    }

    public function index()
    {
        if ($this->user->isRoleSupplierUser()) {
            $this->all();
            return;
        }
        $time = empty($this->time_diff) ? time() : strtotime($this->handleTimezone(date(WithDate::DATE_SQL_FORMAT)));
        $yearToday = date('Y', $time);
        $month =  !empty($_POST) ? $_POST['month'] : (!empty($_GET) ? $_GET['month'] : date('m', $time));
        $year = !empty($_POST) ? $_POST['year'] : (!empty($_GET) ? $_GET['year'] : $yearToday);
        $filters_sup['warehouse_id'] = $filters['warehouse_id'] = $this->warehouse_id;
        if ($this->user->isRoleSupplierAdmin())
            $filters_sup['admin_ids'] = [$this->user->getId()];
        $suppliers = Arr::column(SuppliersModel::getByFilters($this->logistic_company->getId(), $filters_sup), 'id');
        $years = [$yearToday - 2, $yearToday + 2, (int)$year];
        $months = [1, 12, (int)$month];
        $today = explode('|', date('d|m|Y', $time));
        $data = [
            'months' => $months,
            'years' => $years,
            'week' => date('w', strtotime(sprintf("%04d-%02d-01", $years[2], $months[2]))),
            'days' => Months::getDaysInMonth($months[2], $years[2]),
            'today' => ($today[1] == $months[2] && $today[2] == $years[2]) ? $today[0] : -1,
            'ASNs' => ASNs::getByDate($this->warehouse_id, $suppliers, $month, $year),
        ];
        $this->render($data, 'ASNs/incoming');
    }

    /** @throws */
    public function viewAsn()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        $ASN = ASNs::getById($id);
        $back = "/goods_in/all?" . (!empty($_SESSION['asn_all_filters']) ? $_SESSION['asn_all_filters'] : '');
        if (!$ASN || !ASNs::isAccess($ASN, $this->user) || $ASN->getWarehouseId() != $this->warehouse_id || $ASN->getOrderId()) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException($back);
        }
        if (!empty($this->time_diff)) {
            if (!empty($ASN->getArrivedDateInt()))
                $ASN->setArrivedDate($this->handleTimezone($ASN->getArrivedDate()));
            if (!empty($ASN->getCompletedDateInt()))
                $ASN->setCompletedDate($this->handleTimezone($ASN->getCompletedDate()));
        }
        $SKUs = ASNSKUs::getByASNId($id);
        $comments = ASNHistories::getByASN($id, $ASN->getSupplierId());
        foreach ($comments as &$comment) {
            if (empty($comment['supplier_id']))
                $comment['supplier_id'] = $comment['id'];
            unset($comment['id']);
            $supplier = SuppliersModel::getById($comment['supplier_id'] ?? 0);
            $comment['logo'] = empty($supplier) ? '' : $supplier->getLogo();
            if (!empty($this->time_diff))
                $comment['create_date'] = $this->handleTimezone($comment['create_date']);
        } unset($comment);
        list($lastSKUs, $lastDo) = GoodsInModel::getLastDo($ASN, $SKUs, $comments);
        $data = [
            'containers' => ['' => 'Loose'] + ASNs::getContainerList(),
            'ASN' => $ASN,
            'SKUs' => $SKUs,
            'lastSKUs' => $lastSKUs,
            'lastDo' => $lastDo,
            'comments' => $comments,
            'order_ref' => empty($_GET['ref']) ? '' : (string)$_GET['ref'],
            'back' => $back,
        ];
        $this->render($data, 'ASNs/view_asn');
    }

    /** @throws */
    public function manifest()
    {
        $id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
        $ASN = ASNs::getById($id);
        if (!$id || !$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException('/');
        }
        GoodsInModel::createPdfManifest($ASN, ASNSKUs::getByASNId($id, true));
    }

    /** @throws */
    public function updateContainer()
    {
        $ASN_id = Request::getPositiveId($_POST);
        $ASN = ASNs::getById($ASN_id);
        $back = "/goods_in/all?" . (!empty($_SESSION['asn_all_filters']) ? $_SESSION['asn_all_filters'] : '');
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException($back);
        }
        if (ASNs::updateContainer($ASN, $_POST['container'], $this->user->getId()))
            View::addFlash('ASNs', 'success', 'Done!');
        else
            View::addFlash('ASNs', 'danger', 'Invalid information');
        throw new RedirectException('/goods_in/view_asn/?id=' . $ASN_id);
    }

    /** @throws */
    public function updateArrival()
    {
        $ASN_id = Request::getPositiveId($_POST);
        $ASN = ASNs::getById($ASN_id);
        $back = "/goods_in/all?" . (!empty($_SESSION['asn_all_filters']) ? $_SESSION['asn_all_filters'] : '');
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException($back);
        }
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        try {
            if (ASNs::updateArrival($ASN, $_POST['arrival_date'], $this->warehouse_virtual, $this->user->getId()))
                View::addFlash('ASNs', 'success', 'Done!');
            else
                View::addFlash('ASNs', 'danger', 'Invalid information');
            $mysql->commit();
        } catch (VirtualException $err) {
            $mysql->rollback();
            View::addFlash('ASNs', 'danger', $err->getMessage());
        }
        throw new RedirectException('/goods_in/view_asn/?id=' . $ASN_id);
    }

    /** When non-compliant ASN is resolved, its status becomes "Completed" (4) */
    /** @throws */
    public function resolveNonCompliant()
    {
        $ASN_id = Request::getPositiveId($_POST);
        $ASN = ASNs::getById($ASN_id);
        $back = "/goods_in/all?" . (!empty($_SESSION['asn_all_filters']) ? $_SESSION['asn_all_filters'] : '');
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException($back);
        }
        $errors = ASNs::resolveNonCompliant($ASN, $this->user->getId());
        if ($errors === true)
            View::addFlash('ASNs', 'success', "Done (ASN $ASN_id)!");
        else
            View::addFlash('ASNs', 'danger', $errors);
        throw new RedirectException('/goods_in/' . (empty($_POST['back']) ? ('view_asn/?id=' . $ASN_id) : $_POST['back']));
    }

    /** @throws */
    public function editSku()
    {
        $ASN_id = Request::getPositiveId($_POST);
        $ASN = ASNs::getById($ASN_id);
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException("/goods_in/all?" . (!empty($_SESSION['asn_all_filters']) ? $_SESSION['asn_all_filters'] : ''));
        }
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        list($res, $errors) = GoodsInModel::editSKUs($ASN, $_POST['quantity'], $this->warehouse_virtual, $this->user->getId());
        if (!$res) {
            $mysql->rollback();
            View::addFlash('ASNs', 'danger', $errors);
        } else {
            $mysql->commit();
            View::addFlash('ASNs', 'success', 'Done!');
        }
        throw new RedirectException('/goods_in/view_asn/?id=' . $ASN_id);
    }

    /** @throws */
    public function updateRef()
    {
        $ASN_id = Request::getPositiveId($_POST);
        $ASN = ASNs::getById($ASN_id);
        $back = "/goods_in/all?" . (!empty($_SESSION['asn_all_filters']) ? $_SESSION['asn_all_filters'] : '');
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException($back);
        }
        $errors = ASNs::updateAdditional($ASN, $_POST['ref'], $_POST['carrier'], $_POST['tracking_number'], $this->warehouse_virtual, $this->user->getId());
        if ($errors === true)
            View::addFlash('ASNs', 'success', 'Done!');
        else
            View::addFlash('ASNs', 'danger', $errors);
        throw new RedirectException('/goods_in/view_asn/?id=' . $ASN_id);
    }

    /** @throws */
    public function addComment()
    {
        $id = Request::getPositiveId($_POST);
        $ASN = ASNs::getById($id);
        $back = '/goods_in/' . (empty($_POST['back']) ? 'all' : (addslashes($_POST['back']) . 's')) . '?' . (!empty($_SESSION['asn_all_filters']) ? $_SESSION['asn_all_filters'] : '');
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException($back);
        }
        $errors = ASNHistories::commentASN($id, $this->user->getId(), $_POST['comment']);
        if ($errors === true)
            View::addFlash('ASNs', 'success', "Done (ASN $id)!");
        elseif (is_array($errors) && !empty($errors))
            View::addFlash('ASNs', 'danger', Arr::column($errors, 'message'));
        throw new RedirectException('/goods_in/view_' . (empty($_POST['back']) ? 'asn' : addslashes($_POST['back'])) . '/?id=' . $id);
    }

    /** @throws */
    public function charge()
    {
        /** @var ASN $ASN */
        $ASN = ASNs::getById($_POST['ASN_id']);
        if (!$ASN || !ASNs::isAccess($ASN, $this->user))
            throw new RedirectException("/goods_in/view_asn/?id={$_POST['ASN_id']}", 403, false);
        $ASN->setBillingDescriptionPallet($_POST['desc_pallet']);
        $ASN->setBillingChargePallet($_POST['pallet']);
        $ASN->setBillingDescriptionRework($_POST['desc_rework']);
        $ASN->setBillingChargeRework($_POST['rework']);
        $ASN->save(['id', 'billing_charge_pallet', 'billing_description_pallet', 'billing_charge_rework', 'billing_description_rework']);
    }

    public function all()
    {
        $filters = $_GET;
        $_SESSION['asn_all_filters'] = http_build_query($_GET);
        $filters_sup['warehouse_id'] = $filters['warehouse_id'] = $this->warehouse_id;
        if ($this->user->isRoleSupplierAdmin())
            $filters_sup['admin_ids'] = [$this->user->getId()];
        elseif ($this->user->isRoleSupplierUser()) {
            $filters_sup['user_ids'] = [$this->user->getId()];
            $filters['supplier_id'] = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId())['id'];
        }
        $suppliers = Arr::map(SuppliersModel::getByFilters($this->logistic_company->getId(), $filters_sup), 'id', 'name');
        if (empty($filters['supplier_id']) || (!empty($filters['supplier_id']) && !in_array($filters['supplier_id'], array_keys($suppliers))))
            $filters['supplier_id'] = array_keys($suppliers);
        $asn_list = ASNs::getAll($filters);
        foreach ($asn_list['records'] as &$asn) {
            $asn['assigned_skus'] = SKULocations::checkAssigned($this->warehouse_id, explode(",", $asn['sku_id_list']));
            $asn['sku_sizes_configured'] = SKUsModel::checkMeasuresConfigured($this->warehouse_id, explode(",", $asn['sku_id_list']));
            if (!empty($this->time_diff))
                $asn['arrived_date'] = !empty($asn['arrived_date']) ? $this->handleTimezone($asn['arrived_date']) : '';
        } unset($asn);
        $current_filters = $filters;
        if(isset($current_filters))
            unset($current_filters['c']);
        $data = [
            'suppliers' => (count($suppliers) > 1 ? ['all'] : []) + $suppliers,
            'asn_list' => $asn_list['records'],
            'status_list' => ['all' => 'all'] + ASN::$statusName,
            'containers' => ['' => 'Loose'] + ASNs::getContainerList(),
            'total_count' => $asn_list['total'],
            'total_pages' => ceil($asn_list['total'] / ASNs::LIMIT_PER_PAGE),
            'current_page' => $asn_list['current_page'],
            'current_filters' => http_build_query($current_filters),
        ];
        $this->render($data, 'ASNs/all_asn');
    }

    /** @throws */
    public function arrivedAsn()
    {
        $id = Request::getPositiveId($_POST);
        $ASN = ASNs::getById($id);
        $back = "/goods_in/all?" . (!empty($_SESSION['asn_all_filters']) ? $_SESSION['asn_all_filters'] : '');
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException($back);
        }
        $errors = ASNs::arrived($ASN, $this->user->getId(), $_POST['container'] ?? '');
        if ($errors !== true) {
            View::addFlash('ASNs', 'danger', $errors);
            throw new RedirectException($back);
        }
        $SKU_data = ASNSKUs::getByASNId($id);
        foreach ($SKU_data as $SKU) {
            $move_id = Movements_::setASN($SKU['id'], $SKU['quantity'], $id);
            SKUHistories::addASN($SKU['id'], $SKU['quantity'], $move_id, $id, $this->warehouse_id);
        }
        View::addFlash('ASNs', 'success', "Done (ASN #$id)!");
        throw new RedirectException($back);
    }

    /** @throws */
    public function cancelAsn()
    {
        $id = Request::getPositiveId($_POST);
        $ASN = ASNs::getById($id);
        $back = "/goods_in/all?" . (!empty($_SESSION['asn_all_filters']) ? $_SESSION['asn_all_filters'] : '');
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException($back);
        }
        if ($ASN->cancelled()) {
            View::addFlash('ASNs', 'warning', 'ASN is already cancelled');
            throw new RedirectException($back);
        }
        if (!$ASN->new()) {
            View::addFlash('ASNs', 'danger', 'ASN cannot be cancelled');
            throw new RedirectException($back);
        }
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        try {
            $errors = ASNs::cancel($ASN, $this->warehouse_virtual, $this->user->getId());
            if ($errors === true) {
                $mysql->commit();
                View::addFlash('ASNs', 'success', "Done (ASN $id)!");
            } else {
                $mysql->rollback();
                View::addFlash('ASNs', 'danger', $errors);
            }
        } catch (VirtualException $err) {
            $mysql->rollback();
            View::addFlash('ASNs', 'danger', $err->getMessage());
        }
        throw new RedirectException($back);
    }

    /** @throws */
    public function revertAsn()
    {
        $id = Request::getPositiveId($_POST);
        $ASN = ASNs::getById($id);
        $back = "/goods_in/all?" . (!empty($_SESSION['asn_all_filters']) ? $_SESSION['asn_all_filters'] : '');
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException($back);
        }
        $errors = ASNs::revert($ASN, $this->user->getId());
        if ($errors !== true) {
            View::addFlash('ASNs', 'danger', Arr::column($errors, 'message'));
            throw new RedirectException($back);
        }
        $SKU_data = ASNSKUs::getByASNId($id);
        foreach ($SKU_data as $SKU) {
            $move_id = Movements_::delASN($SKU['id'], $SKU['quantity'], $id);
            SKUHistories::delASN($SKU['id'], $SKU['quantity'], $move_id, $id, $this->warehouse_id);
        }
        View::addFlash('ASNs', 'success', 'Done!');
        throw new RedirectException($back);
    }

    /** @throws */
    public function noVerify()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        $ASN = ASNs::getById($id);
        $back = !$this->user->isRoleWorker() ? ("/goods_in/all?" . (!empty($_SESSION['asn_all_filters']) ? $_SESSION['asn_all_filters'] : '')) : '/goods_in/arrival';
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException($back);
        }
        $SKUs = ASNSKUs::getByASNId($id);
        $data = [
            'ASN' => $ASN,
            'SKUs' => $SKUs,
            'back' => $back,
        ];
        $this->render($data, 'ASNs/view_asn_noverify');
    }

    /** @throws */
    public function noVerified()
    {
        $id = Request::getPositiveId($_POST, 'asn_id');
        $ASN = ASNs::getById($id);
        $back = !$this->user->isRoleWorker() ? ("/goods_in/all?" . (!empty($_SESSION['asn_all_filters']) ? $_SESSION['asn_all_filters'] : '')) : '/goods_in/arrival';
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('NoVerify', 'danger', 'ASN not found or access denied');
            throw new RedirectException($back);
        }
        $location = empty($_POST['location'])
            ? ($this->warehouse_label . '-' . implode('-', Location::SPECIAL_LOCATION__NOVERIFY))
            : addslashes($_POST['location']);
        $l = Locations::getByScan($this->warehouse_id, $location);
        $error_back = "/goods_in/no_verify/?id=$id";
        if (is_null($l) || !$l->isActive()) {
            View::addFlash('NoVerify', 'danger', "Location $location invalid. Contact system administrator");
            throw new RedirectException($error_back);
        }
        if (!$l->PA() && !$l->ST()) {
            View::addFlash('NoVerify', 'danger', "Location should be either in storage or in PA zone");
            throw new RedirectException($error_back);
        }
        $errors = GoodsInModel::noVerify($ASN, $l, $this->user->getId(), $this->warehouse_label);
        if ($errors !== true) {
            View::addFlash('NoVerify', 'danger', Arr::column($errors, 'message'));
            throw new RedirectException($error_back);
        }

        View::addFlash('NoVerify', 'success', "Done!");
        throw new RedirectException($back);
    }

    /** @throws */
    public function complete()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        $ASN = ASNs::getById($id);
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('complete', 'danger', 'ASN not found or access denied');
            throw new RedirectException('/goods_in/arrival');
        }
        $back = '/goods_in/' . (empty($_GET['p']) ? 'all' : addslashes($_GET['p']));
        $search = empty($_GET['s']) ? '' : addslashes($_GET['s']);
        $current = empty($_GET['c']) ? 0 : (int)$_GET['c'];
        $SKUs_ASN = ASNSKUs::getByASNId($id);
        $SKUs_tmp = ASNTemporaries::getPutawayQuantity($id);
        $SKUs_cmp = ASNCompletes::getSKUsLocations($id);
        foreach ($SKUs_ASN as $key => $SKU) {
            if (!empty($SKUs_tmp[$SKU['id']])) {
                $SKUs_ASN[$key]['qty'] = $SKUs_tmp[$SKU['id']]['summa'];
                unset($SKUs_tmp[$SKU['id']]);
            } else
                $SKUs_ASN[$key]['qty'] = 0;
        }
        foreach ($SKUs_tmp as $key => $SKU) {
            $SKU_user = SKUsModel::getById($key);
            if (empty($SKU_user)) {
                View::addFlash('complete', 'danger', "SKU (#$key) is not found");
                throw new RedirectException("$back?s=$search&c=$current");
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
            'page' => "s=$search&c=$current",
        ];
        $this->render($data, 'ASNs/complete_asn');
    }

    /** @throws */
    public function completed()
    {
        $ASN_id = Request::getPositiveId($_POST);
        $ASN = ASNs::getById($ASN_id);
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('Complete', 'danger', 'ASN not found or access denied');
            throw new RedirectException('/goods_in/arrival');
        }
        $back = empty($_POST['back']) ? '' : addslashes($_POST['back']);
        $page = empty($_POST['page']) ? '' : addslashes($_POST['page']);
        $successes = [];
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        foreach ($_POST['addSKUs'] ?? [] as $SKU_id => $new_sku) {
            $location = Locations::getById($new_sku['loc_id']);
            if ($location->getWarehouse() != $this->warehouse_id) {
                View::addFlash('Complete', 'danger', 'ASN not found in this warehouse');
                $mysql->rollback();
                throw new RedirectException('/goods_in/arrival');
            }
            $SKU = SKUsModel::getById($SKU_id);
            $data = [
                'ASN_id' => $ASN_id,
                'SKU_id' => $SKU_id,
                'quantity' => 0,
                'accepted_quantity' => $new_sku['qty'],
            ];
            $ASN_SKU = new ASNSKU($data);
            $ASN_SKU->save(array_keys($data));
            $errors = ASNHistories::deviationASN($ASN_id, $this->user->getId(), $SKU_id, $new_sku['qty'], $location->getLocation($this->warehouse_label));
            if ($errors !== true)
                View::addFlash('Deviation', 'danger', $errors);
            $successes[] = "ASN #{$ASN_id}: the SKU (" . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()) . ") in the amount of {$new_sku['qty']} arrived and placed in the location ({$location->getLocation($this->warehouse_label)})";
        }
        if (!empty($successes))
            View::addFlash('Complete', 'success', $successes);
        $isComplete = true;
        foreach ($_POST['SKUs'] as $SKU_id => $SKU_data) {
            $errors = ASNs::toComplete($ASN_id, $SKU_id, $SKU_data['qty'], $SKU_data['quantity'], $this->user->getId());
            if ($errors !== true)
                View::addFlash('Complete', 'danger', $errors);
            if ($SKU_data['quantity'] != $SKU_data['qty'])
                $isComplete = false;
        }
        if (count($_POST['addSKUs'] ?? []))
            $isComplete = false;
        $errors = ASNs::completed($ASN, !$isComplete ? ASN::NON_COMPLIANT : ASN::COMPLETED, $this->user->getId());
        if ($errors !== true) {
            $mysql->rollback();
            View::addFlash('Complete', 'danger', $errors);
            throw new RedirectException('/goods_in/arrival');
        }
//        Queue::addJobToSetInventoryLevel(['asn_id' => $ASN_id]);
        ShopInventoryQueues::addASN($ASN_id);
        ASNTemporaries::clear($ASN_id);
        ASNCompletes::clear($ASN_id);
        $mysql->commit();
        View::addFlash('Complete', 'success', "ASN #{$ASN_id} is completed!");
        throw new RedirectException("$back?$page");
    }

    public function ncrs()
    {
        $filters = $_GET;
        $filters['status'] = ASN::NON_COMPLIANT;
        $_SESSION['ncr_all_filters'] = http_build_query($_GET);
        $filters_sup['warehouse_id'] = $filters['warehouse_id'] = $this->warehouse_id;
        if ($this->user->isRoleSupplierAdmin())
            $filters_sup['admin_ids'] = [$this->user->getId()];
        elseif($this->user->isRoleSupplierUser()) {
            $filters_sup['user_ids'] = [$this->user->getId()];
            $filters['supplier_id'] = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId())['id'];
        }
        $suppliers = Arr::map(SuppliersModel::getByFilters($this->logistic_company->getId(), $filters_sup), 'id', 'name');
        if (empty($filters['supplier_id']) || (!empty($filters['supplier_id']) && !in_array($filters['supplier_id'], array_keys($suppliers))))
            $filters['supplier_id'] = array_keys($suppliers);
        $ncr_list = ASNs::getAllNCR($filters);
        $supplier_id = 0;
        if ($this->user->isClientSide()) {
            $suppliers = $this->user->isRoleSupplierAdmin() ? SuppliersModel::getSuppliersByAdmin($this->warehouse_id, $this->user->getId()) :
                SuppliersModel::getSuppliersByUser($this->warehouse_id, $this->user->getId());
            $suppliers = Arr::map($suppliers, 'id', 'name');
            $supplier_id = array_keys($suppliers)[0];
        }
        foreach ($ncr_list['records'] as &$ncr) {
            $tmp = ASNHistories::getLastComment($ncr['id']);
            if (empty($tmp['supplier_id']))
                $tmp['supplier_id'] = $tmp['id'];
            unset($tmp['id']);
            $tmp['supplier_id'] = $this->user->isWarehouseSide() ? (empty($tmp['supplier_id']) ? 1 : 2) :
                ($supplier_id == $tmp['supplier_id'] ? 1 : 2);
            if (!empty($this->time_diff)) {
                $ncr['arrived_date'] = !empty($ncr['arrived_date']) ? $this->handleTimezone($ncr['arrived_date']) : '';
                $tmp['last_date'] = $this->handleTimezone($tmp['last_date']);
            }
            $ncr['last_comment'] = $tmp;
        } unset($ncr);
        uasort($ncr_list['records'], function($el1, $el2) {
            if ($el1['last_comment']['supplier_id'] == $el2['last_comment']['supplier_id'])
                return $el2['id'] - $el1['id'];
            else
                return $el2['last_comment']['supplier_id'] - $el1['last_comment']['supplier_id'];
        });
        $ncr_list['total'] = count($ncr_list['records']);
        $current_page = Request::getPage($filters);
        $ncr_list['current_page'] = $current_page;
        $offset = ASNs::LIMIT_PER_PAGE * ($current_page - 1);
        $ncr_list['records'] = array_slice($ncr_list['records'], $offset, ASNs::LIMIT_PER_PAGE);
        $current_filters = $filters;
        if(isset($current_filters))
            unset($current_filters['c']);
        $data = [
            'suppliers' => (count($suppliers) > 1 ? ['all'] : []) + $suppliers,
            'ncr_list' => $ncr_list['records'],
            'containers' => ['' => 'Loose'] + ASNs::getContainerList(),
            'total_count' => $ncr_list['total'],
            'total_pages' => ceil($ncr_list['total'] / ASNs::LIMIT_PER_PAGE),
            'current_page' => $ncr_list['current_page'],
            'current_filters' => http_build_query($current_filters),
        ];
        $this->render($data, 'ASNs/ncrs');
    }

    /** @throws */
    public function viewNcr()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        $NCR = ASNs::getById($id);
        $back = "/goods_in/ncrs?" . (!empty($_SESSION['ncr_all_filters']) ? $_SESSION['ncr_all_filters'] : '');
        if (!$NCR || !$NCR->nonCompliant() || $NCR->getOrderId()) {
            View::addFlash('NCRs', 'danger', 'NCR not found or not Non-Compliant');
            throw new RedirectException($back);
        }
        $SKUs = ASNSKUs::getByASNId($id);
        $comments = ASNHistories::getByASN($id, $NCR->getSupplierId());
        foreach ($comments as &$comment) {
            if (empty($comment['supplier_id']))
                $comment['supplier_id'] = $comment['id'];
            unset($comment['id']);
            $supplier = SuppliersModel::getById($comment['supplier_id']);
            $comment['logo'] = empty($supplier) ? '' : $supplier->getLogo();
            if (!empty($this->time_diff))
                $comment['create_date'] = $this->handleTimezone($comment['create_date']);
        } unset($comment);
        list($lastSKUs, $lastDo) = GoodsInModel::getLastDo($NCR, $SKUs, $comments);
        $data = [
            'containers' => ['' => 'Loose'] + ASNs::getContainerList(),
            'NCR' => $NCR,
            'SKUs' => $SKUs,
            'lastSKUs' => $lastSKUs,
            'lastDo' => $lastDo,
            'comments' => $comments,
            'order_ref' => empty($_GET['ref']) ? '' : (string)$_GET['ref'],
            'back' => $back,
        ];
        $this->render($data, 'ASNs/view_ncr');
    }

    /** @throws */
    public function csvReport()
    {
        $count = !empty($_GET['count']) ? intval($_GET['count']) : 0;
        unset($_GET['count']);
        $limit = ASNs::LIMIT_FLASH_GENERATE_REPORT;
        $filters = $_GET;
        $filters_sup['warehouse_id'] = $filters['warehouse_id'] = $this->warehouse_id;
        if ($this->user->isRoleSupplierAdmin())
            $filters_sup['admin_ids'] = [$this->user->getId()];
        elseif ($this->user->isRoleSupplierUser()) {
            $filters_sup['user_ids'] = [$this->user->getId()];
            $filters['supplier_id'] = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId())['id'];
        }
        $suppliers = Arr::map(SuppliersModel::getByFilters($this->logistic_company->getId(), $filters_sup), 'id', 'name');
        if (empty($filters['supplier_id']))
            $filters['supplier_id'] = array_keys($suppliers);
        if ($count <= $limit) {
            $count = ASNs::getTotalCountReportCSV($filters);
        }
        if ($count <= $limit) {
            set_time_limit(0);
            header('Content-Encoding: UTF-8');
            header('Content-Type: application/excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . ASNs::getReportName() . '"');
            ASNs::getAllAsCSV($filters);
        } else {
            $user_job_id = Queue::addJobToGenerateReportCSV([
                'user_id' => $this->user->getId(),
                'filters' => $filters,
                'addition' => $filters['warehouse_id'],
                'count' => $count
            ], self::class);
            if ($user_job_id) {
                $_SESSION['user_queue'] = true;
                $_SESSION['user_queue_flash'] = true;
                View::addFlash('upload_file', 'warning', 'Quantity of ASNs more than ' . $limit .
                    '. The report will be processed in the queue. Job #' . $user_job_id);
            } else
                View::addFlash('upload_file', 'danger', "Server error");
            throw new RedirectException($_SERVER['HTTP_REFERER'] ?? '/goods_in/all');
        }
    }

    /** @throws RedirectException */
    public function createAsn()
    {
         $this->add();
    }

    /** @throws RedirectException */
    public function createReturnAsn()
    {
        $this->add(['return' => true]);
    }

    /** @throws */
    private function add($params = [])
    {
        $this->disableWarehouseChange();
        $filters_sup['warehouse_id'] = $this->warehouse_id;
        if ($this->user->isRoleSupplierAdmin())
            $filters_sup['admin_ids'] = [$this->user->getId()];
        elseif ($this->user->isRoleSupplierUser())
            $filters_sup['user_ids'] = [$this->user->getId()];
        $suppliers = SuppliersModel::getByFilters($this->logistic_company->getId(), $filters_sup);
        if (count($suppliers) == 0) {
            View::addFlash('ASN', 'danger', 'Supplier not found for this user');
            throw new RedirectException('/');
        }
        $data = [
            'is_return' => !empty($params['return']) || !empty($params['order_id']),
            'id' => $this->user->getId(),
            'suppliers' => $suppliers,
            'supplier_id' => $params['supplier_id'] ?? 0,
            'in_date' => empty($params['arrival_date'])
                ? (empty($this->time_diff) ? time() : strtotime($this->handleTimezone(date(WithDate::DATE_SQL_FORMAT))))
                : $params['arrival_date'],
            'order_id' => $params['order_id'] ?? 0,
            'unscheduled' => $params['unscheduled'] ?? -1,
        ];
        $this->render($data, 'ASNs/add_asn');
    }

    /** @throws RedirectException */
    public function addAsn($getASN = null, $SKUs_user = [])
    {
        $this->disableWarehouseChange();
        if (is_null($getASN)) {
            if (empty($_GET) && empty($_POST))
                throw new RedirectException("/goods_in/create_asn");
            if (empty($_GET)) {
                $_POST['ASN']['unscheduled'] = empty($_POST['ASN']['unscheduled']) ? 0 : 1;
                $_POST['ASN']['arrival_date'] = strtotime($_POST['ASN']['arrival_date']);
                if (!empty($_POST['ASN']['order_id'])) {
                    $order = OrdersModel::getDespatchedByIdOrReference($_POST['ASN']['supplier_id'], $_POST['ASN']['order_id'], $this->warehouse_id, !$_POST['ASN']['unscheduled']);
                    if (empty($order)) {
                        View::addFlash('ASN', 'danger', 'Order not found or not despatched');
                        $this->add([
                            'supplier_id' => $_POST['ASN']['supplier_id'],
                            'arrival_date' => $_POST['ASN']['arrival_date'],
                            'order_id' => $_POST['ASN']['order_id'],
                            'unscheduled' => $_POST['ASN']['unscheduled']
                        ]);
                        return;
                    }
                    $_POST['ASN']['order_id'] = $order->getId();
                    $_POST['ASN']['order_ref'] = $order->getRef();
                }
                $ASN = $_POST['ASN'];
            } else {
                $getData = json_decode(base64_decode($_GET['data']), TRUE);
                if (empty($getData['ASN'])) {
                    $this->addAsn();
                    return;
                }
                if (!empty($getData['ASN']['order_id'])) {
                    $order = OrdersModel::getDespatchedByIdOrReference($getData['ASN']['supplier_id'], $getData['ASN']['order_id'], $this->warehouse_id, !$getData['ASN']['unscheduled']);
                    if (empty($order)) {
                        View::addFlash('ASN', 'danger', 'Order not found or not despatched');
                        $this->add([
                            'supplier_id' => $getData['ASN']['supplier_id'],
                            'arrival_date' => $getData['ASN']['arrival_date'],
                            'order_id' => $getData['ASN']['order_id'],
                            'unscheduled' => $getData['ASN']['unscheduled']
                        ]);
                        return;
                    }
                }
                $ASN = $getData['ASN'];
                $quantities = $getData['quantities'] ?? [];
            }
        } else
            $ASN = $getASN;
        if (empty($ASN) || empty($ASN['supplier_id'])) {
            View::addFlash('ASN', 'danger', 'Information for ASN not found');
            throw new RedirectException("/goods_in/create_asn");
        }
        $users = !$this->user->isRoleSupplierUser() ? SuppliersModel::getUsers($this->warehouse_id, $ASN['supplier_id']) : null;
        if (empty($users) && !$this->user->isRoleSupplierUser()) {
            View::addFlash('ASN', 'danger', 'Client not found for this company');
            throw new RedirectException('/goods_in');
        }
        $SKUs = SKUsModel::getBySupplier($ASN['supplier_id'], SKU::ACTIVE_NUM);
        if (empty($SKUs)) {
            View::addFlash('ASN', 'danger', 'SKUs not found for this company');
            throw new RedirectException('/goods_in');
        }
        $SKUs_to_search = [];
        $SKUs_info = [];
        $SKUs_edit = [];
        foreach ($SKUs as $SKU) {
            $SKUs_to_search[] = [
                'value' => $SKU['barcode'],
                'label' => $SKU['barcode'] . " / " . $SKU['name'] . " / " . $SKU['customer_ref'],
                'active' => $SKU['state']
            ];
            $SKUs_info[$SKU['barcode']] = $SKU;
            if (!empty($SKUs_user[$SKU['id']])) {
                $SKUs_user[$SKU['id']]['name'] = $SKU['name'];
                $SKUs_edit[$SKU['barcode']] = $SKUs_user[$SKU['id']];
            }
        }
        if (empty($SKUs_edit)) {
            if (!empty($quantities)) {
                $SKUs = SKUsModel::getByIds(array_keys($quantities), $ASN['supplier_id'] ?? 0);
                foreach ($SKUs as $SKU_item) {
                    if (empty($SKUs_info[$SKU_item['barcode']])) {
                        View::addFlash('ASN', 'warning', 'Absent SKU barcode ' . $SKU_item['barcode']);
                        continue;
                    }
                    $SKUs_edit[$SKU_item['barcode']] = [
                        'name' => $SKU_item['name'],
                        'quantity' => $quantities[$SKU_item['id']],
                        'batch_number' => $getData['batch_number'][$SKU_item['id']] ?? '',
                        'batch_expired' => $getData['batch_expired'][$SKU_item['id']] ?? '',
                    ];
                }
            } else if (!empty($ASN['order_id'])) {
                $SKUs_desp = array_map(function ($item) {
                    $item['batch_id'] = (int)$item['batch_id'];
                    return $item;
                }, Arr::maps(OrdersOriginalDespatched::getHandledItems($order), 'SKU_id', ['quantity', 'batch_id']));
                $SKUs_batch = Arr::maps(Batches::getBatchesByIds(Arr::column($SKUs_desp, 'batch_id')), 'id', ['number', 'expired']);
                $SKUs_name = Arr::maps(SKUsModel::getByIds(array_keys($SKUs_desp)), 'id', ['name', 'barcode']);
                foreach ($SKUs_desp as $SKU_id => $SKU_data)
                    $SKUs_edit[$SKUs_name[$SKU_id]['barcode']] = [
                        'name' => $SKUs_name[$SKU_id]['name'],
                        'quantity' => $SKU_data['quantity'],
                        'batch_number' => $SKUs_batch[$SKU_data['batch_id']]['number'] ?? '',
                        'batch_expired' => empty($SKUs_batch[$SKU_data['batch_id']]['expired']) ? '' : date('Y-m-d', strtotime($SKUs_batch[$SKU_data['batch_id']]['expired'])),
                    ];
            }
        }
        $sup_data = SuppliersModel::getWeeksBeforeExpiryBySupplierId($ASN['supplier_id']);
        $date_from = date('Y-m-d', (!empty($sup_data['weeks_before_expiry']) ? strtotime("{$sup_data['weeks_before_expiry']} weeks") : time()) + 3600 * 24);
        $data = [
            'ASN' => $ASN,
            'containers_list' => ["" => 'Loose'] + ASNs::getContainerList(),
            'users' => $users,
            'available_skus' => View::toJs($SKUs_to_search),
            'skus_info' => View::toJs($SKUs_info),
            'skus_order' => View::toJs($SKUs_edit),
            'locations_list' => Warehouses::getLocations($this->warehouse_id),
            'in_date' => empty($this->time_diff) ? time() : strtotime($this->handleTimezone(date(WithDate::DATE_SQL_FORMAT))),
            'back' => '/goods_in/' . (!empty($ASN['is_return']) ? 'create_return_asn' : 'create_asn'),
            'date_from' => $date_from,
        ];
        $this->render($data, 'ASNs/add_asn_skus');
    }

    /** @throws */
    public function addAsnSave()
    {
        if (!empty($_GET)) {
            $_POST = json_decode(base64_decode($_GET['data']), true);
            $_POST['mode'] = 1;
        }
        if (empty($_POST)) {
            View::addFlash('ASN', 'danger', 'Information for ASN not found');
            throw new RedirectException("/goods_in/create_asn");
        }
        if ($_POST['mode'] == 2) {
            $post = base64_encode(json_encode($_POST));
            throw new RedirectException("/upload/SKUs_ASN/?back=/goods_in/add_asn&data=$post");
        }
        $return_ASN = !empty($_POST['ASN']['is_return']);
        $_POST['ASN']['status'] = ASN::NEW;
        $_POST['ASN']['warehouse_id'] = $this->warehouse_id;
        if (isset($_POST['ASN']['services']))
            foreach ($_POST['ASN']['services'] as $key => $val)
                $_POST['ASN']['services'][$key] = (int)($val === 'on');
        if (!empty($_POST['ASN']['service0'])) {
            $_POST['ASN']['services'][0] = $_POST['ASN']['service0'];
            unset($_POST['ASN']['service0']);
        }
        if (!empty($_POST['ASN']['services']))
            ksort($_POST['ASN']['services']);
        if (!empty($_POST['batch_expired']))
            foreach ($_POST['batch_expired'] as $key => $value)
                $_POST['batch_expired'][$key] = str_replace('/', '-', $value);
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $SKUs = $errors = [];
        if (!empty($_POST['quantity']))
            list($SKUs, $errors) = GoodsInModel::prepareSKUs($_POST['barcode'] ?? [], $_POST['batch_number'] ?? [], $_POST['batch_expired'] ?? [], $_POST['quantity']);
        else
            $errors[]['message'] = 'List of SKU is empty';
        list($done, $res) = ASNs::addASN($_POST['ASN'], $SKUs, $errors, $this->warehouse_virtual, $this->user->getId());
        if (!$done)
            $mysql->rollback();
        if ($done) {
            $mysql->commit();
            View::addFlash('ASN', 'success', 'Operation is done (' . ($return_ASN ? 'Return ' : '') . "ASN #$res)!");
            throw new RedirectException($return_ASN ? '/return_ASNs' : '/goods_in/all');
        } elseif (is_array($res)) {
            View::addFlash('ASN', 'danger', Arr::column($res, 'message'));
            $this->addAsn($_POST['ASN'], $SKUs);
            return;
        }
        View::addFlash('ASN', 'danger', $res);
        throw new RedirectException('/goods_in/create_asn');
    }

    ///////////////////////////////////////// Goods Arrival

    /** @throws */
    public function arrival()
    {
        $current = 1;
        if (isset($_GET['c']))
            $current = (int)$_GET['c'];
        $search = '';
        if (isset($_POST['search']))
            $search = $_POST['search'];
        elseif (isset($_GET['search']))
            $search = htmlentities($_GET['search']);
        $supplier_id = 0;
        if (isset($_POST['supplier_id']))
            $supplier_id = (int)$_POST['supplier_id'];
        elseif (isset($_GET['supplier_id']))
            $supplier_id = (int)$_GET['supplier_id'];
        $filter = array_merge($_GET, $_POST, [
            'supplier_id' => $supplier_id,
            'search' => $search,
        ]);
        if ($search && !$supplier_id) {
            $suppliers = SKUsModel::getSuppliersByBarcode($search, $this->warehouse_id);
            $supplier_id = $suppliers[0]['id'] ?? 0;
        }
        unset($filter['c']);
        if (isset($_POST['current_query']) && $current > 1 && $search != $_POST['current_query'])
            throw new RedirectException('/goods_in/arrival/?' . http_build_query($filter));
        $show_return_ASN = $this->permission->isAccessByUrl('return_asns');
        list($ASNs, $size) = ASNs::getArrival($this->warehouse_id, $filter, $current, $show_return_ASN);
        $msg = '';
        $SKUs = [];
        if (count($ASNs) && !empty($search)) {
            $SKUs = SKUsModel::getByBarcodes([$search], $supplier_id, Filter::getFilterTypeFromRequest($filter, 'search'));
            $msg = count($SKUs) == 1
                ? GoodsInModel::getArrivalMessageSKU($SKUs[0]['id'], $this->warehouse_id)
                : '';
        }
        $data = [
            'ASNs' => $ASNs,
            'size' => $size,
            'pages' => (int)($size / LIMIT_PER_TICKET_PAGE) + ($size % LIMIT_PER_TICKET_PAGE > 0),
            'search' => $search,
            'cont_arrival' => !empty($search) ? $msg : '',
            'supplier_id' => $supplier_id,
            'current' => $current,
            'SKUs' => $SKUs,
            'filter' => $filter,
        ];
        $this->render($data, 'arrival/arrival');
    }

    /** @throws */
    public function arrivalSKUs()
    {
        if (Request::isAjax()) {
            $id = Request::getPositiveId($_POST);
            if (!$id)
                return $this->jsonResponse(['error' => 'Invalid information']);
            $ASN = ASNs::getById($id);
            if (!$ASN || !ASNs::isAccess($ASN, $this->user))
                return $this->jsonResponse(['error' => 'ASN not found or access denied']);
            $barcode = $_POST['barcode'] ?? '';
            $filter_type = Filter::getFilterTypeFromRequest($_POST, 'barcode');
            return $this->jsonResponse([
                'SKUs' => ASNSKUs::getSKUsByBarcode($id, $barcode, $filter_type),
            ]);
        }
        $this->disableWarehouseChange();
        if (empty($_GET)) {
            View::addFlash('Arrival', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $id = (int)$_GET['id'];
        $ASN = ASNs::getById($id);
        if (empty($ASN)) {
            View::addFlash('Arrival', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $supplier_id = $ASN->getSupplierId();
        $barcode = !empty($_GET['sku']) ? $_GET['sku'] : null;
        if (empty($barcode)) {
            $SKUs_response = [];
            $SKUs_tmp = ASNTemporaries::getPutawayQuantity($id);
            $SKUs = ASNSKUs::getByASNId($id);
            $enough_SKU = true;
            foreach ($SKUs as $SKU) {
                $SKUs_response[$SKU['barcode']] = GoodsInModel::getArrivalMessageSKU($SKU['id'], $this->warehouse_id);
                $summa = $SKUs_tmp[$SKU['id']]['summa'] ?? 0;
                if (($SKU['quantity'] - $summa) > 0)
                    $enough_SKU = false;
            }

            $SKUs = array_merge($SKUs, SKUsModel::getLeftASN($supplier_id, Arr::columnAssoc($SKUs, 'id')));
            usort($SKUs, function($SKU1, $SKU2) {
                $delta = (int)$SKU1['id'] - (int)$SKU2['id'];
                return empty($delta) ? 0 : ($delta < 0 ? -1 : 1);
            });
            $data = [
                'ASN_id' => $id,
                'SKUs' => $SKUs,
                'SKUs_response' => $SKUs_response,
                'enough_SKU' => $enough_SKU
            ];
            $this->render($data, 'arrival/arrival_ASN');
            return;
        }
        $PA_id = Locations::getIdByStandard($this->warehouse_id, Location::SPECIAL_LOCATION__PA);
        if (!$PA_id) {
            View::addFlash('Arrival', 'danger', 'PA zone not defined. Contact system administrator');
            throw new RedirectException('/');
        }
        $cart = Cart::getCart($this->user);
        $modes = Cart::getCartInfo($cart);
        $weight = empty($modes['move']) ? 0 : $modes['move'];
        $unexpected = false;
        $SKU = SKUsModel::getByBarcodeAndSupplier($barcode, $supplier_id);
        if (empty($SKU)) {
            View::addFlash('Arrival', 'danger', "SKU ($barcode) is unexpected");
            throw new RedirectException('/goods_in/arrival');
        }
        $ASNSKUs = ASNSKUs::getByAsnIdAndSkuId($id, $SKU->getId());
        $quantity = 0;
        if (ASNSKUs::isExist($id, $SKU->getId()))
            $quantity = ASNSKUs::getQuantity($id, $SKU->getId())['quantity'] - ASNTemporaries::getQuantity($id, $SKU->getId());
        elseif ($ASN->getSupplierId() != $SKU->getSupplierId()) {
            View::addFlash('Arrival', 'danger', "SKU ($barcode) isn't in ASN #$id. You can add it as an unexpected item with a temporary barcode using \"ASN Complete\" interface");
            throw new RedirectException('/goods_in/arrival');
        } else
            $unexpected = true;
        $locs = SKULocations::getLocationsFree($this->warehouse_id, $SKU);
        $data = [
            'ASN_id' => $id,
            'barcode' => $barcode,
            'quantity' => $quantity,
            'locs' => $locs,
            'qty' => 1,
            'SKU' => $SKU,
            'barcodes' => SKUBarcodes::getBarcodesBySKUId($SKU->getId()),
            'ASN_SKUs' => $ASNSKUs,
            'cart' => $cart,
            'PA_id' => $PA_id,
            'weight' => $weight,
            'unexpected' => $unexpected,
        ];
        $this->render($data,'arrival/arrival_SKU');
    }

    /** @throws */
    public function arrivalEnd()
    {
        if (empty($_GET)) {
            View::addFlash('arrival', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $id = empty($_GET['id']) ? 0 : (int)$_GET['id'];
        $ASN = ASNs::getById($id);
        if (empty($ASN)) {
            View::addFlash('arrival', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $supplier_id = $ASN->getSupplierId();
        $barcode = empty($_GET['barcode']) ? '' : $_GET['barcode'];
        $qty = empty($_GET['qty']) ? 0 : (int)$_GET['qty'];
        $loc_id = empty($_GET['loc']) ? 0 : (int)$_GET['loc'];
        $location = Locations::getById($loc_id);
        if (!$id || empty($location) || empty($barcode)) {
            View::addFlash('arrival', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $SKU = SKUsModel::getByBarcodeAndSupplier($barcode, $supplier_id);
        if (empty($SKU)) {
            View::addFlash('arrival', 'danger', 'SKU not found');
            throw new RedirectException('/goods_in/arrival');
        }
        if (!$SKU->active() && !$location->PA()) {
            View::addFlash('arrival', 'danger', 'SKU has ' . SKU::getStateName($SKU->getState()) . ' status. Choose PA locations');
            $_GET['SKU_id'] = $SKU->getId();
            unset(
                $_GET['barcode'],
                $_GET['loc']
            );
            throw new RedirectException('/goods_in/arrival_skus_again/?' . http_build_query($_GET));
        }
        $asnSkus = ASNSKUs::getByASNId($id, true);
        $existedBatch = $asnSkus[$SKU->getId()] ?? ['batch_number' => 0];
        $batchNumber = empty($_GET['batch_number']) ? $existedBatch['batch_number'] : $_GET['batch_number'];
        if (!empty($batchNumber)) {
            $batch = json_decode($batchNumber, true);
            if (is_null($batch) || !is_array($batch)) {
                SKULocations::handleSKUBatchInLocation($SKU, $location, $batchNumber, static function () use ($SKU) {
                    View::addFlash('arrival', 'danger', 'SKU batches in this location cannot be mixed');
                    $_GET['SKU_id'] = $SKU->getId();
                    throw new RedirectException('/goods_in/arrival_skus_again/?' . http_build_query($_GET));
                }, [Location::ST]);
            } else
                $batchNumber = !empty($batch['batch']) ? Batches::getById($batch['batch'])->getNumber() : '';
        }
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        try {
            $batch_id = Batches::addBatch($batchNumber, $SKU->getId(), $_GET['batch_expired']);
        } catch (BatchException $e){
            View::addFlash('arrival', 'danger', $e->getMessage() . '. Try again');
            $mysql->rollback();
            $_GET['SKU_id'] = $SKU->getId();
            unset(
                $_GET['barcode'],
                $_GET['loc']
            );
            throw new RedirectException('/goods_in/arrival_skus_again/?' . http_build_query($_GET));
        }
        if ($location->storage()) {
            $checkLoc = true;
            if (empty(SKUsModel::getSize($SKU->getId(), $location->getSizeId()))) {
                View::addFlash('arrival', 'danger', 'SKU has not size for this location');
                $checkLoc = false;
            }
            $batches = SKULocations::getBatchesInLocation($SKU->getId(), $loc_id);
            if (!empty($batches)) {
                if (count($batches) != 1 || $batch_id != $batches[0]['batch_id']) {
                    View::addFlash('arrival', 'danger', 'SKU in this location cannot be mixed');
                    $checkLoc = false;
                }
            }
            if (!$checkLoc) {
                $mysql->rollback();
                $_GET['SKU_id'] = $SKU->getId();
                unset(
                    $_GET['barcode'],
                    $_GET['loc']
                );
                throw new RedirectException('/goods_in/arrival_skus_again/?' . http_build_query($_GET));
            }
        }
        $res = GoodsInModel::setPutaway($this->warehouse_id, $id, $SKU->getId(), $loc_id, $qty, $batch_id, $this->user->getId(), $location->PA());
        if ($res !== true) {
            View::addFlash('arrival', 'danger', $res === false ? 'Goods arrival is impossible' : $res);
            $mysql->rollback();
            throw new RedirectException('/goods_in/arrival');
        }
        $mysql->commit();
        $loc = Locations::getById($loc_id);
        View::addFlash('Arrival', 'success', "ASN #$id: the SKU (" . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()) . ") in the amount of $qty arrived and placed in the location ({$loc->getLocation($this->warehouse_label)})");
        throw new RedirectException("/goods_in/arrival_skus/?id=$id");
    }

    /** @throws */
    public function arrivalSKUsAgain()
    {
        $this->disableWarehouseChange();
        if (empty($_GET)) {
            View::addFlash('arrival', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $id = (int)$_GET['id'];
        $ASN = ASNs::getById($id);
        if (empty($ASN)) {
            View::addFlash('arrival', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $PA_id = Locations::getIdByStandard($this->warehouse_id, Location::SPECIAL_LOCATION__PA);
        if (!$PA_id) {
            View::addFlash('arrival', 'danger', 'PA zone not defined. Contact system administrator');
            throw new RedirectException('/');
        }
        $cart = Cart::getCart($this->user);
        $modes = Cart::getCartInfo($cart);
        $weight = empty($modes['move']) ? 0 : $modes['move'];
        $unexpected = false;
        $SKU = SKUsModel::getById($_GET['SKU_id']);
        if (empty($SKU)) {
            View::addFlash('arrival', 'danger', "SKU is unexpected");
            throw new RedirectException('/goods_in/arrival');
        }
        $quantity = 0;
        if (ASNSKUs::isExist($id, $SKU->getId()))
            $quantity = ASNSKUs::getQuantity($id, $SKU->getId())['quantity'] - ASNTemporaries::getQuantity($id, $SKU->getId());
        elseif ($ASN->getSupplierId() != $SKU->getSupplierId()) {
            View::addFlash('arrival', 'danger', "SKU (" . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()) . ") isn't in ASN #$id. You can add it as an unexpected item with a temporary barcode using \"ASN Complete\" interface");
            throw new RedirectException('/goods_in/arrival');
        } else
            $unexpected = true;
        $locs = SKULocations::getLocationsFree($this->warehouse_id, $SKU);
        $barcodes = SKUBarcodes::getBarcodesBySKUId($SKU->getId());
        $data = [
            'ASN_id' => $id,
            'barcode' => $barcodes[0],
            'SKU' => $SKU,
            'barcodes' => $barcodes,
            'PA_id' => $PA_id,
            'cart' => $cart,
            'weight' => $weight,
            'unexpected' => $unexpected,
            'ASN_SKUs' => ['batch_number' => addslashes($_GET['batch_number']), 'batch_expired' => addslashes($_GET['batch_expired'])],
            'quantity' => $quantity,
            'locs' => $locs,
            'qty' => (int) $_GET['qty'],
        ];
        $this->render($data,'arrival/arrival_SKU');
    }

    /** @throws */
    public function unexpectedNoASN()
    {
        $this->disableWarehouseChange();
        if (empty($_GET)) {
            View::addFlash('unexpected_noasn', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $supplier_id = empty($_GET['supplier_id']) ? 0 : (int)$_GET['supplier_id'];
        $barcodes = [];
        $locs = [];
        if (empty($supplier_id)) {
            $suppliers = SuppliersModel::getByFilters($this->logistic_company->getId(), [
                'active' => true,
                'warehouse_id' => $this->warehouse_id,
            ]);
            if (empty($suppliers)) {
                View::addFlash('unexpected_noasn', 'danger', 'Companies not found');
                throw new RedirectException('/goods_in/arrival');
            }
            $suppliers = [0 => 'none'] + Arr::map($suppliers,'id', 'name');
            $create_time = time();
            $name = !empty($_GET['name']) ? addslashes($_GET['name']) : "name_$create_time";
            $barcodes = ["UNXP$create_time"];
            $SKU = new SKU([
                'id' => 0,
                'name' => $name,
                'photo' => '',
                'supplier_id' => 0,
                'state' => SKU::UNEXPECTED_NUM,
            ]);
        } else {
            $supplier = SuppliersModel::getById($supplier_id);
            if (empty($_GET['name']) || empty($supplier)) {
                View::addFlash('unexpected_noasn', 'danger', 'Invalid information');
                throw new RedirectException('/');
            }
            $suppliers = $supplier_id;
            $barcode = addslashes($_GET['name']);
            $SKU = SKUsModel::getByBarcodeAndSupplier($barcode, $supplier_id);
            if (empty($SKU)) {
                $barcodes = ['UNXP' . time()];
                $SKU = new SKU([
                    'id' => 0,
                    'name' => $barcode,
                    'photo' => '',
                    'supplier_id' => $supplier_id,
                    'state' => SKU::UNEXPECTED_NUM,
                ]);
            } else {
                $locs = SKULocations::getLocationsFree($this->warehouse_id, $SKU);
                $barcodes = SKUBarcodes::getBarcodesBySKUId($SKU->getId());
            }
        }
        $PA_id = Locations::getIdByStandard($this->warehouse_id, Location::SPECIAL_LOCATION__PA);
        if (!$PA_id) {
            View::addFlash('unexpected_noasn', 'danger', 'PA zone not defined. Contact system administrator');
            throw new RedirectException('/');
        }
        $cart = Cart::getCart($this->user);
        $modes = Cart::getCartInfo($cart);
        $weight = empty($modes['move']) ? 0 : $modes['move'];
        $back = "p={$_GET['p']}" . (!empty($_GET['s']) ? "&s={$_GET['s']}" : '') . (!empty($_GET['c']) ? "&c={$_GET['c']}" : '');
        $data = [
            'suppliers' => $suppliers,
            'SKU' => $SKU,
            'barcodes' => $barcodes,
            'locs' => $locs,
            'qty' => 1,
            'cart' => $cart,
            'weight' => $weight,
            'PA_id' => $PA_id,
            'back' => $back,
        ];
        $this->render($data, 'arrival/unexpected_noASN_SKU');
    }

    /** @throws */
    public function unexpectedNoASNEnd()
    {
        $mysql = new SafeMysql();
        $go_again = function ($SKU, $message, $rollback = false) use ($mysql) {
            if ($rollback)
                $mysql->rollback();
            View::addFlash('unexpected_noasn', 'danger', $message);
            $data = $_POST;
            $data['SKU_id'] = $SKU->getId();
            unset(
                $data['barcode'],
                $data['name'],
                $data['cart_id'],
                $data['PA_id'],
                $data['location_id'],
            );
            return new RedirectException('/goods_in/unexpected_noASN_again/?' . http_build_query($data));
        };
        $this->disableWarehouseChange();
        if (empty($_POST)) {
            View::addFlash('unexpected_noasn', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : 0;
        $location = Locations::getById($location_id);
        if (empty($location)) {
            View::addFlash('arrival', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $upload_res = true;
        if (!empty($_FILES['upload_file']['name']))
            list($upload_res, $file_name) = GoodsInModel::loadFile($_FILES['upload_file']);
        if (!$upload_res) {
            View::addFlash('unexpected_noasn', 'danger', $file_name);
            $cart = Cart::getCart($_POST['cart_id']);
            $modes = Cart::getCartInfo($cart);
            $data = $_POST;
            $data['weight'] = empty($modes['move']) ? 0 : $modes['move'];
            $data['cart'] = $cart;
            $data['suppliers'] = $data['supplier_id'];
            $data['barcodes'] = $data['barcode'];
            $data['SKU'] = new SKU([
                'id' => 0,
                'name' => $data['name'],
                'photo' => '',
                'supplier_id' => 0,
                'state' => SKU::UNEXPECTED_NUM,
            ]);
            unset($data['location_id'], $data['supplier_id']);
            return $this->render($data, 'arrival/unexpected_noASN_SKU');
        } elseif(!empty($file_name))
            View::addFlash('unexpected_noasn', 'success', "The file '$file_name' is valid and has been successfully uploaded");
        $supplier_id = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : 0;
        $warehouse = Warehouses::getById($this->warehouse_id);
        $currency = Currencies::getCurrency($warehouse->getCurrencyId());
        if (empty($_POST['SKU_id'])) {
            $data = [
                'name' => $_POST['name'],
                'cost_value' => 0,
                'cost_currency' => $currency,
                'market_value' => 0,
                'market_currency' => $currency,
                'supplier_id' => $supplier_id,
                'state' => SKU::UNEXPECTED_NUM,
            ];
            if (!empty($file_name))
                $data['photo'] = $file_name;
            $res = SKUsModel::addSKU($data, [$_POST['barcode']], $this->warehouse_id, $this->user->getId());
            if (!$res[0]) {
                View::addFlash('unexpected_noasn', 'danger', json_encode($res[1]));
                throw new RedirectException('/goods_in/arrival');
            }
            $_POST['SKU_id'] = $res[1];
            View::addFlash('unexpected_noasn', 'success', "SKU '{$data['name']}' added to database");
        }
        $SKU = SKUsModel::getById((int)$_POST['SKU_id']);
        if (empty($SKU)) {
            View::addFlash('unexpected_noasn', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }

        if (!$SKU->active() && !$location->PA() && !$location->CA()) {
            throw $go_again($SKU, 'SKU has ' . SKU::getStateName($SKU->getState()) . ' status. Choose PA locations');
        }
        $batchNumber = addslashes($_POST['batch_number']);
        $mysql->beginTransaction();
        try {
            $batch_id = Batches::addBatch($batchNumber, $SKU->getId(), $_POST['batch_expired']);
        } catch (BatchException $e) {
            throw $go_again($SKU, $e->getMessage() . '. Try again', true);
        }
        if ($location->storage()) {
            if (empty(SKUsModel::getSize($SKU->getId(), $location->getSizeId())))
                throw $go_again($SKU, 'SKU has not size for this location', true);
            $batches = SKULocations::getBatchesInLocation($SKU->getId(), $location_id);
            if (!empty($batches) && (count($batches) != 1 || $batch_id != $batches[0]['batch_id']))
                throw $go_again($SKU, 'SKU in this location cannot be mixed', true);
        }
        if (!SKULocations::addPutaway($this->warehouse_id, $SKU->getId(), $location_id, $_POST['qty'], $batch_id)) {
            View::addFlash('unexpected_noasn', 'danger', 'Goods arrival is impossible');
            $mysql->rollback();
            throw new RedirectException('/goods_in/arrival');
        }
        $move_id = Movements_::setPartially($location_id, $SKU->getId(), $_POST['qty'], $this->user->getId());
        SKUHistories::addPartially($location_id, $SKU->getId(), $_POST['qty'], $move_id, $this->user->getId(), $this->warehouse_id);
        $id = ASNs::createUnexpected($this->warehouse_id, $this->warehouse_virtual, $SKU, $_POST['qty'], $batchNumber, $this->user);
        if (empty($id)) {
            View::addFlash('unexpected_noasn', 'danger', 'Unexpected ASN is not created');
            $mysql->rollback();
            throw new RedirectException('/goods_in/arrival');
        }
        ASNTemporaries::addPutaway($id, $SKU->getId(), $_POST['qty']);
        SKUHistories::changeStockOneLocation($location_id, $SKU->getId(), $this->warehouse_id);
        $mysql->commit();
        View::addFlash('unexpected_noasn', 'success', "ASN '$id' created");
        throw new RedirectException("/goods_in/arrival_skus/?id=$id");
    }

    /** @throws */
    public function unexpectedNoASNAgain()
    {
        $this->disableWarehouseChange();
        if (empty($_GET) || empty($_GET['supplier_id']) || empty($_GET['SKU_id']) || empty($_GET['back']) || empty($_GET['qty'])) {
            View::addFlash('unexpected_noasn', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $SKU = SKUsModel::getById((int) $_GET['SKU_id']);
        if (empty($SKU)) {
            View::addFlash('unexpected_noasn', 'danger', 'SKU not found');
            throw new RedirectException('/goods_in/arrival');
        }
        $suppliers = $SKU->getSupplierId();
        $PA_id = Locations::getIdByStandard($this->warehouse_id, Location::SPECIAL_LOCATION__PA);
        if (!$PA_id) {
            View::addFlash('unexpected_noasn', 'danger', 'PA zone not defined. Contact system administrator');
            throw new RedirectException('/');
        }
        $cart = Cart::getCart($this->user);
        $modes = Cart::getCartInfo($cart);
        $weight = empty($modes['move']) ? 0 : $modes['move'];
        $locs = SKULocations::getLocationsFree($this->warehouse_id, $SKU);
        $data = [
            'batch_number' => empty($_GET['batch_number']) ? '' : addslashes($_GET['batch_number']),
            'batch_expired' => empty($_GET['batch_expired']) ? '' : addslashes($_GET['batch_expired']),
            'suppliers' => $suppliers,
            'barcodes' => SKUBarcodes::getBarcodesBySKUId($SKU->getId()),
            'cart' => $cart,
            'weight' => $weight,
            'qty' => (int) $_GET['qty'],
            'SKU' => $SKU,
            'locs' => $locs,
            'PA_id' => $PA_id,
            'photo_src' => $SKU->getPhoto(),
            'back' => addslashes($_GET['back']),
        ];
        $this->render($data, 'arrival/unexpected_noASN_SKU');
    }

    /** @throws */
    public function printNewBarcode()
    {
        $id = Request::getPositiveId();
        $sku = Request::getBarcode($_GET,'barcode');
        $ASN = ASNs::getById($id);
        if (empty($sku)) {
            View::addFlash('ASNs', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        GoodsInModel::createPdfNewBarcode($ASN, $sku);
    }

    /** @throws */
    public function unexpected()
    {
        $this->disableWarehouseChange();
        if (empty($_GET)) {
            View::addFlash('unexpected', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $PA_id = Locations::getIdByStandard($this->warehouse_id, Location::SPECIAL_LOCATION__PA);
        if (!$PA_id) {
            View::addFlash('unexpected', 'danger', 'PA zone not defined. Contact system administrator');
            throw new RedirectException('/');
        }
        $ASN_id = Request::getPositiveId();
        $ASN = ASNs::getById($ASN_id);
        if (empty($ASN)) {
            View::addFlash('unexpected', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $create_time = time();
        $name = empty($_GET['name']) ? "name_$create_time" : $_GET['name'];
        $barcode = "UNXP$create_time";
        $cart = Cart::getCart($this->user);
        $modes = Cart::getCartInfo($cart);
        $weight = empty($modes['move']) ? 0 : $modes['move'];
        $data = [
            'ASN_id' => $ASN_id,
            'barcode' => $barcode,
        ];
        $complete = new ASNComplete($data);
        $complete->save(array_keys($data));
        $back = "p={$_GET['p']}" . (!empty($_GET['s']) ? "&s={$_GET['s']}" : '') . (!empty($_GET['c']) ? "&c={$_GET['c']}" : '');
        $data = [
            'ASN_id' => $ASN_id,
            'barcode' => $barcode,
            'name' => $name,
            'qty' => 1,
            'cart' => $cart,
            'weight' => $weight,
            'PA_id' => $PA_id,
            'back' => $back,
        ];
        $this->render($data, 'arrival/unexpected_SKU');
    }

    /** @throws */
    public function unexpectedEnd()
    {
        $this->disableWarehouseChange();
        if (empty($_POST)) {
            View::addFlash('unexpected', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $id = Request::getPositiveId($_POST, 'ASN_id');
        $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : 0;
        list($res, $name) = GoodsInModel::loadFile($_FILES['upload_file']);
        if (!$res) {
            View::addFlash('unexpected', 'danger', $name);
            unset($_POST['location_id']);
            $cart = Cart::getCart($_POST['cart_id']);
            $modes = Cart::getCartInfo($cart);
            $_POST['weight'] = empty($modes['move']) ? 0 : $modes['move'];
            $_POST['cart'] = $cart;
            $this->render($_POST, 'arrival/unexpected_SKU');
            return;
        } elseif(!empty($name))
            View::addFlash('unexpected', 'success', "The file '$name' is valid and has been successfully uploaded");
        /**
         * @var ASN $ASN
         */
        $ASN = ASNs::getById($id);
        if (empty($ASN)) {
            View::addFlash('unexpected', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $supplier_id = $ASN->getSupplierId();
        $warehouse = Warehouses::getById($this->warehouse_id);
        $currency = Currencies::getCurrency($warehouse->getCurrencyId());
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        if (empty($_POST['SKU_id'])) {
            $data = [
                'name' => $_POST['name'],
                'cost_value' => 0,
                'cost_currency' => $currency,
                'market_value' => 0,
                'market_currency' => $currency,
                'supplier_id' => $supplier_id,
                'state' => 2,
            ];
            if (!empty($name))
                $data['photo'] = $name;
            $res = SKUsModel::addSKU($data, [$_POST['barcode']], $this->warehouse_id, $this->user->getId());
            if (!$res[0]) {
                View::addFlash('unexpected', 'danger', json_encode($res[1]));
                throw new RedirectException('/goods_in/arrival');
            }
            $_POST['SKU_id'] = $res[1];
            View::addFlash('unexpected', 'success', "SKU '{$data['name']}' added to database");
        }
        $SKU = SKUsModel::getById($_POST['SKU_id']);
        if (empty($SKU)) {
            View::addFlash('unexpected', 'danger', 'Invalid information');
            $mysql->rollback();
            throw new RedirectException('/');
        }
        $batchNumber = addslashes($_POST['batch_number']);
        try {
            $batch_id = Batches::addBatch($batchNumber, $SKU->getId(), $_POST['batch_expired']);
        } catch (BatchException $e){
            View::addFlash('unexpected', 'danger', $e->getMessage() . '. Try again');
            $mysql->rollback();
            $_POST['SKU_id'] = $SKU->getId();
            unset(
                $_POST['barcode'],
                $_POST['name'],
                $_POST['cart_id'],
                $_POST['PA_id'],
                $_POST['location_id'],
            );
            throw new RedirectException('/goods_in/unexpected_again/?' . http_build_query($_POST));
        }
        if (!SKULocations::addPutaway($this->warehouse_id, $SKU->getId(), $location_id, $_POST['qty'], $batch_id)) {
            View::addFlash('unexpected', 'danger', 'Goods arrival is impossible');
            $mysql->rollback();
            throw new RedirectException('/goods_in/arrival');
        }
        $move_id = Movements_::setPartially($location_id, $SKU->getId(), $_POST['qty'], $this->user->getId());
        SKUHistories::addPartially($location_id, $SKU->getId(), $_POST['qty'], $move_id, $this->user->getId(), $this->warehouse_id);
        SKUHistories::changeStockOneLocation($location_id, $SKU->getId(), $this->warehouse_id);
        $complete = ASNCompletes::getComplete($id, $_POST['barcode'], $location_id);
        $fields = ['id', 'quantity'];
        $qty = $_POST['qty'];
        if (is_null($complete)) {
            $complete = ASNCompletes::getComplete($id, $_POST['barcode']);
            if ($complete->getLocationId()) {
                $data = [
                    'ASN_id' => $id,
                    'barcode' => $_POST['barcode'],
                    'quantity' => $_POST['qty'],
                ];
                $complete = new ASNComplete($data);
                $fields = array_keys($data);
            } else
                $complete->setQuantity($complete->getQuantity() + $qty);
            $complete->setLocationId($location_id);
            $fields[] = 'location_id';
        } else
            $complete->setQuantity($complete->getQuantity() + $qty);
        $complete->save($fields);
        if ($ASN->new() || $ASN->arrived()) {
            $fields = ['id', 'status'];
            if ($ASN->new()) {
                $ASN->setArrivedDate(time());
                $fields[] = 'arrived_date';
            }
            $ASN->setStatus(3);
            $ASN->save($fields);
        }
        $mysql->commit();
        View::addFlash('unexpected', 'success', "ASN #{$_POST['ASN_id']}: the SKU ({$_POST['barcode']}) in the amount of {$_POST['qty']} added to ASN");
        throw new RedirectException("/goods_in/arrival");
    }

    /** @throws */
    public function unexpectedAgain()
    {
        $this->disableWarehouseChange();
        if (empty($_GET) || empty($_GET['ASN_id']) || empty($_GET['SKU_id']) || empty($_GET['back']) || empty($_GET['qty'])) {
            View::addFlash('unexpected', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $SKU = SKUsModel::getById((int) $_GET['SKU_id']);
        if (empty($SKU)) {
            View::addFlash('unexpected_noasn', 'danger', 'SKU not found');
            throw new RedirectException('/goods_in/arrival');
        }
        $PA_id = Locations::getIdByStandard($this->warehouse_id, Location::SPECIAL_LOCATION__PA);
        if (!$PA_id) {
            View::addFlash('unexpected', 'danger', 'PA zone not defined. Contact system administrator');
            throw new RedirectException('/');
        }
        $ASN_id = Request::getPositiveId($_GET, 'ASN_id');
        $ASN = ASNs::getById($ASN_id);
        if (empty($ASN)) {
            View::addFlash('unexpected', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $cart = Cart::getCart($this->user);
        $modes = Cart::getCartInfo($cart);
        $weight = empty($modes['move']) ? 0 : $modes['move'];
        $data = [
            'ASN_id' => $ASN_id,
            'barcode' => SKUBarcodes::getOneBySKUId($SKU->getId()),
            'name' => $SKU->getName(),
            'photo_src' => $SKU->getPhoto(),
            'batch_number' => empty($_GET['batch_number']) ? '' : addslashes($_GET['batch_number']),
            'batch_expired' => empty($_GET['batch_expired']) ? '' : addslashes($_GET['batch_expired']),
            'qty' => (int) $_GET['qty'],
            'cart' => $cart,
            'SKU_id' => $SKU->getId(),
            'weight' => $weight,
            'PA_id' => $PA_id,
            'back' => addslashes($_GET['back']),
        ];
        $this->render($data, 'arrival/unexpected_SKU');
    }

    ///////////////////////////////////////// Putaway Queue

    public function putaway()
    {
        $SKUs = SKUsModel::getPutaway($this->warehouse_id);
        $data = [
            'SKUs' => $SKUs,
        ];
        $this->render($data, 'putaway/putaway');
    }

    public function printPutaway()
    {
        $SKUs = SKUsModel::getPutaway($this->warehouse_id);
        foreach ($SKUs as $key => $val)
            if (!(int)$val['qty'])
                unset($SKUs[$key]);
        GoodsInModel::createPdfPutaway($SKUs);
    }

    /** @throws */
    public function putawaySKU()
    {
        $this->disableWarehouseChange();
        if (empty($_POST)) {
            if (empty($_GET)) {
                View::addFlash('putaway', 'danger', 'Invalid information');
                throw new RedirectException('/');
            }
            $data = $_GET;
        } else
            $data = $_POST;
        $id = empty($data['SKU_id']) ? 0 : (int)$data['SKU_id'];
        $location_id = empty($data['location_id']) ? 0 : (int)$data['location_id'];
        $qty = empty($data['qty']) ? 0 : (int)$data['qty'];
        if (!$id || !$location_id || !$qty) {
            View::addFlash('putaway', 'danger', 'Invalid information');
            throw new RedirectException('/goods_in/putaway');
        }
        /**
         * @var SKU $SKU
         */
        $SKU = SKUsModel::getById($id);
        if (empty($SKU)) {
            View::addFlash('putaway', 'danger', 'Invalid information');
            throw new RedirectException('/goods_in/putaway');
        }
        if (!$SKU->active()) {
            View::addFlash('putaway', 'danger', 'SKU has ' . SKU::getStateName($SKU->getState()) . ' status');
            throw new RedirectException('/goods_in/putaway');
        }
        if (empty(SKUsModel::getSize($SKU->getId(), Locations::getSize($location_id)))) {
            View::addFlash('putaway', 'danger', "SKU " . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()) . " has not size for this location");
            throw new RedirectException('/goods_in/putaway');
        }
        $batch = Batches::getById($data['batch_id']);
        $locs = SKULocations::getLocationsFree($this->warehouse_id, $SKU, $data['batch_id']);
        if (!count($locs)) {
            View::addFlash('putaway', 'danger', "No locations available. Please ask a manager to assign location(s) for " . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()));
            throw new RedirectException('/goods_in/putaway');
        }
        $locs_processing = $this->prepareProcessingLocations(Locations::getNonCountedTypesLocations($this->warehouse_id));
        $cart = Cart::getCart($this->user);
        $modes = Cart::getCartInfo($cart);
        $weight = empty($modes['move']) ? 0 : $modes['move'];
        $data = [
            'SKU' => $SKU,
            'barcodes' => SKUBarcodes::getBarcodesBySKUId($SKU->getId()),
            'quantity' => $qty,
            'loc_id' => $location_id,
            'qty' => 1,
            'locs' => $locs,
            'cart' => $cart,
            'weight' => $weight,
            'batch' => $batch,
            'locs_processing' => $locs_processing
        ];
        $this->render($data, 'putaway/putaway_SKU');
    }

    /** @throws */
    public function putawayEnd()
    {
        if (empty($_GET)) {
            View::addFlash('putaway', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $id = Request::getPositiveId();
        $qty = empty($_GET['qty']) ? 0 : (int)$_GET['qty'];
        $loc_id = empty($_GET['loc']) ? 0 : (int)$_GET['loc'];
        $loc_PA = empty($_GET['PA_id']) ? 0 : (int)$_GET['PA_id'];
        $batch_id = empty($_GET['batch_id']) ? 0 : (int)$_GET['batch_id'];
        if (!$id || !$qty || !$loc_id || !$loc_PA) {
            View::addFlash('putaway', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $SKU = SKUsModel::getById($id);
        $loc = Locations::getById($loc_id);
        $locPA = Locations::getById($loc_PA);
        if (!$loc) {
            View::addFlash('putaway', 'danger', 'Destination location not found');
            throw new RedirectException("/goods_in/putaway/");
        }
        $batch_number = null;
        if ($batch_id != 0) {
            $batch = Batches::getById($batch_id);
            $batch_number = $batch->getNumber();
        }
        SKULocations::handleSKUBatchInLocation($SKU, $loc, $batch_number, static function () {
            View::addFlash('batch_putaway', 'danger', 'SKU batches in this location cannot be mixed');
            throw new RedirectException('/goods_in/putaway/');
        });
        $res = SKULocations::createMovement($locPA, $loc, $SKU, $batch_id, $qty, 'From PA zone', $this->user->getId());
        if (!$res[0])
            View::addFlash('putaway', 'danger', $res[1]);
        else {
            View::addFlash('putaway', 'success', "The SKU (" . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()) . ") in the amount of $qty arrived and placed in the location ({$loc->getLocation($this->warehouse_label)})");
//            Queue::addJobToSetInventoryLevelSKU(['SKU_id' => $SKU->getId(), 'warehouse_id' => $this->warehouse_id]);
            ShopInventoryQueues::addSKU($SKU->getId(), $this->warehouse_id, InventorySync::SYNC_PUTAWAY);
        }
        throw new RedirectException("/goods_in/putaway/");
    }

    ///////////////////////////////////////// Common functions

    /** @throws */
    public function printASN()
    {
        $id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
        $ASN = ASNs::getById($id);
        if (!$ASN || !ASNs::isAccess($ASN, $this->user)) {
            View::addFlash('ASNs', 'danger', 'ASN not found or access denied');
            throw new RedirectException('/');
        }
        GoodsInModel::createPdfASN($ASN, ASNSKUs::getByASNId($id));
    }

    public function getFromReality()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $ASN_id = Request::getPositiveId($_POST, 'ASN_id');
        if ($ASN_id <= 0)
            return $this->response = ['error' => 'ASN is not found'];
        $ASN = ASNs::getById($ASN_id);
        if (empty($ASN))
            return $this->response = ['error' => 'ASN is not found'];
        if (!$this->warehouse_virtual)
            return $this->response = ['error' => 'Warehouse is not virtual'];
        try {
            $processor = Warehouses::getVirtualProcessor($this->warehouse_id, $ASN->getSupplierId());
            if (empty($processor))
                return $this->response = ['error' => 'Supplier is not found in warehouse'];
            $ASN_reality = $processor->getASN(trim($ASN->getRef()));
            if (empty($ASN_reality))
                return $this->response = ['error' => 'ASN is not found in warehouse'];
        } catch (\Exception $e) {
            return $this->response = ['error' => $e->getMessage()];
        }
        return $this->response = ['success' => $ASN_reality];
    }

    public function getWebhookHistory()
    {
        $ASN_id = Request::getPositiveId($_POST, 'ASN_id');
        if ($ASN_id <= 0)
            return $this->response = ['error' => 'ASN is not found'];
        $ASN = ASNs::getById($ASN_id);
        if (empty($ASN))
            return $this->response = ['error' => 'ASN is not found'];
        if (!$this->warehouse_virtual)
            return $this->response = ['error' => 'Warehouse is not virtual'];
        $webhook_lst = Arr::maps(WebhookHistories::getExternalByASNId($ASN_id), 'id', ['name', 'status', 'create_date', 'login']);
        if (empty($webhook_lst))
            return $this->response = ['error' => 'Webhooks are not found'];
        array_walk($webhook_lst, function(&$webhook) {
            $webhook['status'] = WebhookHistories::badgeStatus($webhook['status']);
        });
        return $this->response = ['success' => $webhook_lst];
    }

    private function prepareProcessingLocations($locs_processing)
    {
        $result = [];
        foreach ($locs_processing as $val) {
            $result[$val['name']] = $val;
        }

        return $result;
    }
}
