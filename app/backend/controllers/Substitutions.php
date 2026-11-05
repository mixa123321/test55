<?php

namespace app\backend\controllers;

use app\backend\models\SubstitutionConditions;
use app\core\entities\SKU;
use app\core\entities\SubstitutionCondition;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\backend\models\Substitutions as SubstitutionsModel;
use app\backend\models\SKUs as SKUsModel;
use app\core\models\SKUBarcodes;
use app\core\models\OrdersSubstitutions;
use app\core\statical\WithDate;

class Substitutions extends AuthController
{
    /** @throws RedirectException */
    public function index()
    {
        $suppliers = $this->getSuppliers();
        $filters = $_GET;
        if (!empty($filters['supplier_id'])) {
            if (empty($suppliers[$filters['supplier_id']])) {
                View::addFlash('substitutions', 'danger', 'Access denied');
                throw new RedirectException('/bundles');
            }
        } else
            $filters['supplier_ids'] = array_keys($suppliers);
        $_SESSION['substitution_filters'] = http_build_query($_GET);
        if (empty($filters['supplier_id']) && empty($filters['supplier_ids']))
            $raw_data = ['records' => [], 'total' => 0, 'current_page' => 0];
        else
            $raw_data = SubstitutionsModel::getAll($this->warehouse_id, $filters);
        foreach ($raw_data['records'] as $key => $SKU) {
            $raw_data['records'][$key]['check'] = SubstitutionsModel::checkSubstitutions($SKU['substitution_id']);
            $raw_data['records'][$key]['conditions'] = ['quantity' => $SKU['is_quantity']];
        }
        $current_filters = $filters;
        if (isset($current_filters))
            unset($current_filters['c']);
        $data = [
            'substitutions' => $raw_data['records'],
            'total_count' => $raw_data['total'],
            'total_pages' => ceil($raw_data['total'] / 10),
            'current_page' => $raw_data['current_page'],
            'suppliers' => $suppliers,
            'current_filters' => http_build_query($current_filters),
        ];
        $this->render($data, 'substitutions/substitutions');
    }

    /** @throws RedirectException */
    public function substitutionsAdd()
    {
        $back = '/substitutions?' . ($_SESSION['substitution_filters'] ?? '');
        $this->disableWarehouseChange();
        $suppliers = $this->getSuppliers();
        $supplier_id = Request::getPositiveId(null, 'supplier_id');
        if (empty($suppliers[$supplier_id])) {
            View::addFlash('substitutions', 'danger', 'Access denied');
            throw new RedirectException($back);
        }
        $SKUs = SKUsModel::getBySupplier($supplier_id, SKU::ACTIVE_NUM);
        if (empty($SKUs)) {
            View::addFlash('substitutions', 'danger', 'Not found SKUs' . (!$this->user->isRoleSupplierUser() ? ' for this supplier' : ''));
            throw new RedirectException($back);
        }
        if (count($SKUs) == 1) {
            View::addFlash('substitutions', 'danger', 'SKU for this supplier is alone');
            throw new RedirectException($back);
        }
        $skus_to_search = [];
        $skus_info = [];
        foreach ($SKUs as $SKU) {
            $skus_to_search[] = [
                'active' => $SKU['state'],
                'value' => $SKU['barcode'],
                'label' => $SKU['barcode'] . " / " . $SKU['name']
            ];
            $skus_info[$SKU['barcode']] = $SKU;
        }
        $substitution_SKUs = [];
        if (!empty($_GET['quantity'])) {
            $SKUs_adding = json_decode($_GET['quantity'], true);
            $substitution_SKUs = SubstitutionsModel::validateSKUs($SKUs_adding, $supplier_id);
            if (is_string($substitution_SKUs)) {
                View::addFlash('substitutions', 'danger', $substitution_SKUs);
                throw new RedirectException($back);
            }
            $SKU = SKUsModel::getById($_GET['id']);
            if (empty($SKU)) {
                View::addFlash('substitutions', 'danger', 'SKU is not found');
                throw new RedirectException($back);
            }
            $conditions = new SubstitutionCondition(['substitution_id' => $SKU->getId()] + json_decode($_GET['conditions'], true));
            $barcode = SKUBarcodes::getBarcodesBySKUIdString($SKU->getId());
        } else
            $conditions = new SubstitutionCondition();
        $data = [
            'available_SKUs' => View::toJs($skus_to_search),
            'SKUs_info' => View::toJs($skus_info),
            'back' => $back,
            'supplier_id' => $supplier_id,
            'substitution_SKUs' => View::toJs($substitution_SKUs),
            'supplier_name' => $suppliers[$supplier_id],
            'conditions' => $conditions,
            'barcode' => $barcode ?? '',
        ];
        $this->render($data, 'substitutions/substitution');
    }

    /** @throws RedirectException */
    public function substitutionsEdit()
    {
        $back = '/substitutions?' . ($_SESSION['substitution_filters'] ?? '');
        $this->disableWarehouseChange();
        $substitution_id = Request::getPositiveId();
        $substitutions = SubstitutionsModel::getById($substitution_id);
        if (empty($substitutions)) {
            View::addFlash('substitutions', 'danger', 'Rule is not found');
            throw new RedirectException($back);
        }
        $SKU = SKUsModel::getById($substitution_id);
        if (empty($SKU)) {
            View::addFlash('substitutions', 'danger', 'Origin SKU is not found');
            throw new RedirectException($back);
        }
        $suppliers = $this->getSuppliers();
        if (empty($suppliers[$SKU->getSupplierId()])) {
            View::addFlash('substitutions', 'danger', 'Access denied');
            throw new RedirectException($back);
        }
        $SKUs = SKUsModel::getBySupplier($SKU->getSupplierId(), SKU::ACTIVE_NUM);
        if (empty($SKUs)) {
            View::addFlash('substitutions', 'danger', 'Not found SKUs' . (!$this->user->isRoleSupplierUser() ? ' for this supplier' : ''));
            throw new RedirectException($back);
        }
        if (count($SKUs) == 1) {
            View::addFlash('substitutions', 'danger', 'SKU for this supplier is alone');
            throw new RedirectException($back);
        }
        if (!empty($_GET['quantity'])) {
            $SKUs_adding = json_decode($_GET['quantity'], true);
            $substitution_SKUs = SubstitutionsModel::validateSKUs($SKUs_adding, $SKU->getSupplierId());
            if (is_string($substitution_SKUs)) {
                View::addFlash('substitutions', 'danger', $substitution_SKUs);
                throw new RedirectException($back);
            }
            $conditions = new SubstitutionCondition(['substitution_id' => $SKU->getId()] + json_decode($_GET['conditions'], true));
        } else {
            $substitution_SKUs = Arr::maps($substitutions, 'barcode', ['name', 'barcode', 'quantity']);
            $conditions = SubstitutionConditions::getById($SKU->getId());
            if (empty($conditions))
                $conditions = new SubstitutionCondition();
        }
        $skus_to_search = [];
        $skus_info = [];
        foreach ($SKUs as $SKU_data) {
            $skus_to_search[] = [
                'active' => $SKU_data['state'],
                'value' => $SKU_data['barcode'],
                'label' => $SKU_data['barcode'] . " / " . $SKU_data['name']
            ];
            $skus_info[$SKU_data['barcode']] = $SKU_data;
        }
        $data = [
            'available_SKUs' => View::toJs($skus_to_search),
            'SKUs_info' => View::toJs($skus_info),
            'back' => $back,
            'supplier_id' => $SKU->getSupplierId(),
            'barcode' => SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()),
            'substitution_SKUs' => View::toJs($substitution_SKUs),
            'edit_substitution' => true,
            'has_orders' => OrdersSubstitutions::hasOrders($substitution_id),
            'supplier_name' => $suppliers[$SKU->getSupplierId()],
            'conditions' => $conditions,
        ];
        $this->render($data, 'substitutions/substitution');
    }

    /** @throws RedirectException */
    public function substitutionsProcess()
    {
        $redirect = '/substitutions?' . ($_SESSION['substitution_filters'] ?? '');
        if (empty($_POST) || empty($_POST['supplier_id']) || empty($_POST['substitution_id']) || empty($_POST['quantity'])) {
            View::addFlash('substitutions', 'danger', 'Access denied');
            throw new RedirectException($redirect);
        }
        $edit_substitution = Request::getPositiveId($_POST, 'edit_substitution');
        $substitution_id = Request::getPositiveId($_POST, 'substitution_id');
        $substitutions = SubstitutionsModel::getById($substitution_id);
        $supplier_id = Request::getPositiveId($_POST, 'supplier_id');
        $_POST['conditions']['is_quantity'] = (int)!empty($_POST['conditions']['is_quantity']);
        $conditions = new SubstitutionCondition(['substitution_id' => $substitution_id] + $_POST['conditions']);
        $back = '/substitutions/substitutions_' . (!$edit_substitution ? "add?supplier_id=$supplier_id&" : "edit?") . "id=$substitution_id";
        $back_qty = "$back&quantity=" . json_encode($_POST['quantity']) . '&conditions=' . json_encode($_POST['conditions']);
        if (!empty($substitutions) && !$edit_substitution) {
            View::addFlash('substitutions', 'danger', 'Rule is already exists');
            throw new RedirectException($back_qty);
        }
        $SKUs = $_POST['quantity'];
        if (empty($SKUs)) {
            View::addFlash('substitutions', 'danger', 'Not selected SKUs');
            throw new RedirectException($back);
        }
        $errors = SubstitutionsModel::validateSKUs($SKUs, $supplier_id);
        if (is_string($errors)) {
            View::addFlash('substitutions', 'danger', $errors);
            throw new RedirectException($back_qty);
        }
        $errors = SubstitutionConditions::validate($conditions);
        if (!empty($errors)) {
            View::addFlash('substitutions', 'danger', Arr::column($errors, 'message'));
            throw new RedirectException($back_qty);
        }
        View::addFlash(
            'substitutions',
            'success',
            'Rule ' . (SubstitutionsModel::substitutionsProcess($substitutions, $SKUs, $conditions, $edit_substitution, $this->user->getId()) ? 'updated' : 'added') . ' successfully');
        throw new RedirectException($redirect);
    }

    /** @throws RedirectException */
    public function substitutionsDelete()
    {
        $back = '/substitutions?' . ($_SESSION['substitution_filters'] ?? '');
        $substitution_id = Request::getPositiveId($_POST);
        $substitutions = SubstitutionsModel::getById($substitution_id);
        if (empty($substitutions)) {
            View::addFlash('substitutions', 'danger', 'Rule is not found');
            throw new RedirectException($back);
        }
        $SKU = SKUsModel::getById($substitution_id);
        if (empty($SKU)) {
            View::addFlash('substitutions', 'danger', 'Origin SKU is not found');
            throw new RedirectException($back);
        }
        $suppliers = $this->getSuppliers();
        if (empty($suppliers[$SKU->getSupplierId()])) {
            View::addFlash('substitutions', 'danger', 'Access denied');
            throw new RedirectException($back);
        }
        if (OrdersSubstitutions::hasOrders($substitution_id)) {
            View::addFlash('substitutions', 'danger', "Substitution exists in orders");
            throw new RedirectException($back);
        }
        SubstitutionsModel::delete($substitution_id, Arr::map($substitutions, 'id', 'quantity'), $this->user->getId());
        SubstitutionConditions::delete($substitution_id);
        View::addFlash('substitutions', 'success', "Sunctitution for " . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()) . " (#$substitution_id) successfully deleted");
        throw new RedirectException($back);
    }

    /** @throws RedirectException */
    public function substitutionsLock()
    {
        $back = '/substitutions?' . ($_SESSION['substitution_filters'] ?? '');
        if (empty($_POST) || empty($_POST['substitution_id'])) {
            View::addFlash('substitutions', 'danger', 'Access denied');
            throw new RedirectException($back);
        }
        $substitution_id = Request::getPositiveId($_POST, 'substitution_id');
        $substitutions = SubstitutionsModel::getById($substitution_id);
        if (empty($substitutions)) {
            View::addFlash('substitutions', 'danger', 'Rule is not found');
            throw new RedirectException($back);
        }
        $SKU = SKUsModel::getById($substitution_id);
        if (empty($SKU)) {
            View::addFlash('substitutions', 'danger', 'Origin SKU is not found');
            throw new RedirectException($back);
        }
        $suppliers = $this->getSuppliers();
        if (empty($suppliers[$SKU->getSupplierId()])) {
            View::addFlash('substitutions', 'danger', 'Access denied');
            throw new RedirectException($back);
        }
        $newStatus = SubstitutionConditions::changeStatus($substitution_id);
        if ($newStatus === null) {
            View::addFlash('substitutions', 'danger', 'Change status error');
            throw new RedirectException($back);
        }
        View::addFlash('substitutions', 'success', "Substitution for " . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()) . " (#$substitution_id) successfully " . ($newStatus ? 'active' : 'locked'));
        throw new RedirectException($back);
    }

    public function getSubstitutionOrders()
    {
        if (empty($_POST) || empty($_POST['id']))
            return $this->response = json_encode(['error']);
        list($orders, $stat) = SubstitutionsModel::getSubstitutionOrders((int)$_POST['id'], $this->warehouse_id);
        if (!empty($orders))
            array_walk($orders, function(&$order) {
                if (!empty($this->time_diff))
                    $order['create_date'] = $this->handleTimezone($order['create_date']);
                $order['create_date'] = WithDate::toFrontFormat($order['create_date']);
            });
        return $this->response = json_encode(['orders' => $orders, 'stat' => $stat]);
    }
}