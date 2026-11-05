<?php

namespace app\backend\controllers;

use app\core\entities\Bundle;
use app\core\entities\SKU;
use app\core\exceptions\RedirectException;
use app\backend\models\Suppliers as SuppliersModel;
use app\core\helpers\Arr;
use app\core\helpers\Debug;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\backend\models\Bundles as BundlesModel;
use app\backend\models\SKUBarcodes;
use app\backend\models\SKUs as SKUsModel;
use app\core\statical\Currencies;
use app\core\statical\WithDate;
use app\core\Queue;

class Bundles extends AuthController
{
    /** @throws */
    public function index()
    {
        $suppliers = $this->getSuppliers();
        $filters = $_GET;
        if (!empty($filters['supplier_id'])) {
            if (empty($suppliers[$filters['supplier_id']])) {
                View::addFlash('bundles', 'danger', 'Access denied');
                throw new RedirectException('/bundles');
            }
        } else
            $filters['supplier_ids'] = array_keys($suppliers);
        $_SESSION[BundlesModel::FILTER_PARAM] = http_build_query($_GET);
        if (empty($filters['supplier_id']) && empty($filters['supplier_ids']))
            $raw_data = ['records' => [], 'total' => 0, 'current_page' => 0];
        else
            $raw_data = BundlesModel::getAll($this->warehouse_id, $filters, true);
        $records = $raw_data['records'];
        array_walk($records, function(&$item) {
            // escape barcodes, then restore the <del> marks added by the model as styled spans
            $item['sku_list'] = View::toHtml($item['sku_list']);
            $item['sku_list'] = str_replace('&lt;del&gt;', '<span class="text text-danger">', $item['sku_list']);
            $item['sku_list'] = str_replace('&lt;/del&gt;', '</span>', $item['sku_list']);
        });
        $current_filters = $filters;
        unset($current_filters['c'], $current_filters['supplier_ids']);
        $vars = [
            'bundles_with_problem' => BundlesModel::getBundlesWithProblemCurrency(array_column($records, 'id')),
            'bundles' => $records,
            'total_count' => $raw_data['total'],
            'total_pages' => ceil($raw_data['total'] / 10),
            'current_page' => $raw_data['current_page'],
            'suppliers' => $suppliers,
            'current_filters' => http_build_query($current_filters),
        ];
        $this->render($vars, 'bundles/bundles');
    }

    /** @throws */
    public function csvReport()
    {
        $count = !empty($_GET['count']) ? intval($_GET['count']) : 0;
        unset($_GET['count']);
        $limit = BundlesModel::LIMIT_FLASH_GENERATE_REPORT;
        $suppliers = $this->getSuppliers();
        $filters = $_GET;
        $filters['warehouse_id'] = $this->warehouse_id;
        if (!empty($filters['supplier_id'])) {
            if (empty($suppliers[$filters['supplier_id']])) {
                View::addFlash('bundles', 'danger', 'Access denied');
                throw new RedirectException("/bundles/?" . (!empty($_SESSION[BundlesModel::FILTER_PARAM]) ? $_SESSION[BundlesModel::FILTER_PARAM] : ''));
            }
        } else
            $filters['supplier_ids'] = array_keys($suppliers);
        if ($count <= $limit) {
            $count = BundlesModel::getTotalCountReportCSV($filters);
        }
        if ($count <= $limit) {
            set_time_limit(0);
            header('Content-Encoding: UTF-8');
            header('Content-Type: application/excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . BundlesModel::getReportName() . '"');
            BundlesModel::getAllAsCSV($this->warehouse_id, $filters, $this->user->getId());
            return;
        }
        $user_job_id = Queue::addJobToGenerateReportCSV([
            'user_id' => $this->user->getId(),
            'filters' => $filters,
            'addition' => $this->warehouse_id,
            'count' => $count
        ], self::class);
        if ($user_job_id) {
            $_SESSION['user_queue'] = true;
            $_SESSION['user_queue_flash'] = true;
            View::addFlash('upload_file', 'warning', 'Quantity of bundles more than ' . $limit .
                '. The report will be processed in the queue. Job #' . $user_job_id);
        } else
            View::addFlash('upload_file', 'danger', "Server error");
        throw new RedirectException("/bundles/?" . (!empty($_SESSION[BundlesModel::FILTER_PARAM]) ? $_SESSION[BundlesModel::FILTER_PARAM] : ''));
    }

    /** @throws */
    public function bundlesAdd()
    {
        $back = "/bundles?" . ($_SESSION[BundlesModel::FILTER_PARAM] ?? '');
        $cost_value = round(abs((float)($_GET['cost_value'] ?? 0)), 2);
        $this->disableWarehouseChange();
        $suppliers = $this->getSuppliers();
        $supplier_id = Request::getPositiveId(null, 'supplier_id');
        if (empty($suppliers[$supplier_id])) {
            View::addFlash('bundles', 'danger', 'Access denied');
            throw new RedirectException($back);
        }
        $SKUs_detail = $this->getSKUsDetails(null, $supplier_id, $_GET['SKUs'] ?? []);
        if (empty($SKUs_detail['has_SKUs'])) {
            View::addFlash('bundles', 'danger', 'Not found SKUs' . (!$this->user->isRoleSupplierUser() ? ' for this supplier' : ''));
            throw new RedirectException($back);
        }
        unset($SKUs_detail['has_SKUs']);
        $bundle = new Bundle(['name' => !empty($_GET['bundle_name']) ? $_GET['bundle_name'] : '','supplier_id' => $supplier_id, 'cost_value' => $cost_value, 'currency_id' => $this->warehouse->getCurrencyId()]);
        $data = array_merge([
            'bundle' => $bundle,
            'currency' => $bundle->getCurrencyId() ? Arr::map(Currencies::getAll(), 'id', 'currency')[$bundle->getCurrencyId()] : '',
            'back' => '/bundles?' . ($_SESSION[BundlesModel::FILTER_PARAM] ?? ''),
        ], $SKUs_detail);
        $this->render($data, 'bundles/bundle');
    }

    /** @throws */
    public function bundlesEdit()
    {
        $this->disableWarehouseChange();
        $back = "/bundles?" . ($_SESSION[BundlesModel::FILTER_PARAM] ?? '');
        $bundle = BundlesModel::getById(Request::getPositiveId());
        if (empty($bundle)) {
            View::addFlash('bundles', 'danger', 'Bundle not found');
            throw new RedirectException($back);
        }
        if (!empty($_GET['bundle_name'])) {
            $bundle->setName($_GET['bundle_name']);
        }
        if (!empty($_GET['cost_value'])) {
            $bundle->setCostValue((float)$_GET['cost_value']);
        }
        $suppliers = $this->getSuppliers();
        if (empty($suppliers[$bundle->getSupplierId()])) {
            View::addFlash('bundles', 'danger', 'Access denied');
            throw new RedirectException($back);
        }
        $SKUs_detail = $this->getSKUsDetails($bundle->getId(), $bundle->getSupplierId(), $_GET['SKUs'] ?? []);
        if (!$SKUs_detail['has_SKUs']) {
            View::addFlash('bundles', 'danger', 'Not found SKUs' . (!$this->user->isRoleSupplierUser() ? ' for this supplier' : ''));
            throw new RedirectException($back);
        }
        unset($SKUs_detail['has_SKUs']);
        $data = array_merge([
            'bundle' => $bundle,
            'has_orders' => BundlesModel::hasOrders($bundle->getId()),
            'currency' => $bundle->getCurrencyId() ? Arr::map(Currencies::getAll(), 'id', 'currency')[$bundle->getCurrencyId()] : '',
            'back' => '/bundles?' . ($_SESSION[BundlesModel::FILTER_PARAM] ?? ''),
        ], $SKUs_detail);
        $this->render($data, 'bundles/bundle');
    }

    private function getSKUsDetails($bundle_id, $supplier_id, $reSKUs): array
    {
        $SKUs = SKUsModel::getBySupplier($supplier_id, [SKU::LOCKED_NUM, SKU::ACTIVE_NUM]);
        if (empty($SKUs))
            return [];
        $skus_to_search = [];
        $skus_info = [];
        foreach ($SKUs as $SKU) {
            $skus_to_search[] = [
                'value' => $SKU['barcode'],
                'label' => $SKU['barcode'] . " / " . $SKU['name'],
                'active' => $SKU['state'],
            ];
            $skus_info[$SKU['barcode']] = $SKU;
        }
        $bundle_SKUs = empty($bundle_id) ? [] : BundlesModel::getBundleSKUs($bundle_id);
        if (!empty($reSKUs)) {
            $bundle_SKUs = [];
            $reSKUs_info = Arr::maps($SKUs, 'id', ['barcode', 'name']);
            foreach ($reSKUs as $reSKU_id => $reSKU_qty) {
                if (!array_key_exists($reSKU_id, $reSKUs_info))
                    continue;
                $bundle_SKUs[$reSKUs_info[$reSKU_id]['barcode']] = [
                    'name' => $reSKUs_info[$reSKU_id]['name'],
                    'barcode' => $reSKUs_info[$reSKU_id]['barcode'],
                    'quantity' => $reSKU_qty,
                ];
            }
        }
        return [
            'has_SKUs' => !empty($SKUs),
            'bundle_SKUs' => View::toJs(Arr::maps($bundle_SKUs, 'barcode', ['name', 'barcode', 'quantity'])),
            'available_skus' => View::toJs($skus_to_search),
            'skus_info' => View::toJs($skus_info),
        ];
    }

    /** @throws */
    public function bundlesProcess()
    {
        $redirect = "/bundles?" . ($_SESSION[BundlesModel::FILTER_PARAM] ?? '');
        $suppliers = $this->getSuppliers();
        $bundle = BundlesModel::getById(Request::getPositiveId($_POST, 'bundle_id'));
        $supplier_id = empty($bundle) ? Request::getPositiveId($_POST, 'supplier_id') : $bundle->getSupplierId();
        $cost_value = round(abs((float)($_POST['cost_value'] ?? 0)), 2);
        $bundle_name = trim(addslashes($_POST['bundle_name'] ?? ''));
        $currency = Request::getVariable($_POST, 'currency');
        $back = "/bundles/" . (empty($bundle) ? "bundles_add?supplier_id={$supplier_id}" : 'bundles_edit?id=' . $bundle->getId()) . "&bundle_name={$bundle_name}&cost_value=$cost_value";
        if (empty($supplier_id) || empty($suppliers[$supplier_id])) {
            View::addFlash('bundles', 'danger', 'Access denied');
            throw new RedirectException($redirect);
        }
        if (!$this->validateName(true))
            throw new RedirectException($back);

        if ($errors = BundlesModel::validateCostValue($cost_value)) {
            View::addFlash('bundles', 'danger', $errors[0]['message']);
            throw new RedirectException($back);
        }
        $SKUs = $_POST['quantity'] ?? [];
        if (empty($SKUs)) {
            View::addFlash('bundles', 'danger', 'Not selected SKUs');
            throw new RedirectException($back);
        }
        $back .= '&' . http_build_query(['SKUs' => $SKUs]);
        $SKUs_currencies = [];
        foreach ($SKUs as $sku_id => $sku_quantity) {
            $sku_obj = SKUsModel::getById($sku_id);
            if ($sku_obj === null) {
                View::addFlash('bundles', 'danger', 'One of the added SKUs doesn\'t exist');
                throw new RedirectException($back);
            }
            if ($sku_obj->getSupplierId() != $supplier_id) {
                View::addFlash('bundles', 'danger', 'One of the selected SKUs does not belong to this account');
                throw new RedirectException($back);
            }
            if ($sku_obj->locked())
                View::addFlash('bundles', 'warning', 'SKU ' . SKUBarcodes::getBarcodesBySKUIdString($sku_obj->getId()) . ' is locked');
            if ((int)$sku_quantity < 1) {
                View::addFlash('bundles', 'danger', 'SKU quantity must equal or greater than 1');
                throw new RedirectException($back);
            }
            $SKUs_currencies[$sku_obj->getId()] = $sku_obj->getMarketCurrency();
            $SKUs[$sku_id] = ['quantity' => $sku_quantity, 'price' => $sku_obj->getMarketValue()];
        }
        $SKUs_currencies_unique = array_unique($SKUs_currencies);
        if (count($SKUs_currencies_unique) !== count(array_filter($SKUs_currencies_unique))) {
            View::addFlash('bundles', 'danger', 'Empty currency in SKU');
            Debug::sendDebugInfo('Bundle empty currencies', json_encode(['bundle_id' => $bundle ? $bundle->getId() : 0, 'SKUs' => $SKUs_currencies]), Debug::TEST);
            throw new RedirectException($back);
        }
        if (count($SKUs_currencies_unique) > 1) {
            View::addFlash('bundles', 'danger', 'Different currencies in SKUs');
            Debug::sendDebugInfo('Bundle diff currencies', json_encode(['bundle_id' => $bundle ? $bundle->getId() : 0, 'SKUs' => $SKUs_currencies]), Debug::TEST);
            throw new RedirectException($back);
        }
        if (array_shift($SKUs_currencies_unique) !== $currency) {
            View::addFlash('bundles', 'danger', 'Wrong currency ' . $currency);
            Debug::sendDebugInfo('Bundle wrong currency ' . $currency, json_encode(['bundle_id' => $bundle ? $bundle->getId() : 0, 'SKUs' => $SKUs_currencies]), Debug::TEST);
        }
        $currency_id = Currencies::getId($currency);
        if (!$currency_id) {
            View::addFlash('bundles', 'danger', 'Wrong currency ' . $currency);
            Debug::sendDebugInfo('Does not have currency ' . $currency, json_encode(['bundle_id' => $bundle ? $bundle->getId() : 0, 'SKUs' => $SKUs_currencies]), Debug::TEST);
            throw new RedirectException($back);
        }
        if (empty($bundle)) {
            BundlesModel::add($bundle_name, $supplier_id, $SKUs, $this->user->getId(), $currency_id, $cost_value);
            View::addFlash('bundles', 'success', "Bundle {$bundle_name} added successfully");
        } else {
            $bundle->setName($bundle_name);
            $bundle->setCostValue($cost_value);
            $bundle->setCurrencyId($currency_id);
            $res = BundlesModel::edit($bundle, $SKUs, $this->user->getId());
            View::addFlash('bundles', $res[0] ? 'success' : 'danger', $res[1]);
        }
        throw new RedirectException($redirect);
    }

    public function bundlesUploadCsv()
    {
        $this->disableWarehouseChange();
        $suppliers = $this->getSuppliers();
        $vars = [
            'suppliers' => $suppliers,
        ];
        $this->render($vars, 'bundles/bundles_upload_csv');
    }

    /** @throws */
    public function bundlesUploadCsvProcess()
    {
        set_time_limit(0);
        $suppliers = $this->getSuppliers();
        $supplier_id = $_POST['supplier_id'];
        if (empty($suppliers[$supplier_id])) {
            View::addFlash('bundles','danger', 'Access denied');
            throw new RedirectException('/bundles/bundles_upload_csv');
        }
        if ($_FILES['upload']['error'] != UPLOAD_ERR_OK) {
            View::addFlash('file_saved', 'danger', "Upload failed with code " . $_FILES['upload']['error']);
            throw new RedirectException("/bundles/bundles_upload_csv");
        }
        $supplier_id = $this->user->isRoleSA()
                            ? $_POST['supplier_id']
                            : array_keys(SuppliersModel::getSuppliersBySupplierSide($this->warehouse_id, $this->user->isRoleSupplierAdmin(), $this->user->getId()))[0];
        $result = BundlesModel::uploadCsv($_FILES['upload']['tmp_name'], $supplier_id, $this->user->getId());
        if (!empty($result['error'])) {
            View::addFlash('file_saved', 'danger', $result['error']);
        } elseif (!empty($result['success'])) {
            View::addFlash('file_saved', 'success', $result['success']);
        }
        throw new RedirectException("/bundles/bundles_upload_csv");
    }

    /** @throws */
    public function bundlesDelete()
    {
        $back = "/bundles?" . $_SESSION[BundlesModel::FILTER_PARAM] ?? '';

        $bundle = BundlesModel::getById(Request::getPositiveId($_POST));

        if (empty($bundle)) {
            View::addFlash('bundles', 'danger', 'Bundle not found');
            throw new RedirectException($back);
        }

        $suppliers = $this->getSuppliers();
        if (empty($suppliers[$bundle->getSupplierId()])) {
            View::addFlash('bundles', 'danger', 'Access denied');
            throw new RedirectException($back);
        }
        if (BundlesModel::hasOrders($bundle->getId())) {
            View::addFlash('bundles', 'danger', "Bundle exists in orders");
            throw new RedirectException($back);
        }
        BundlesModel::delete($bundle->getId(), $this->user->getId());
        View::addFlash('bundles', 'success', "Bundle {$bundle->getName()} successfully deleted");
        throw new RedirectException($back);
    }

    public function getBundleOrders()
    {
        $this->response = json_encode(['error']);
        $id = (int)$_POST['id'];
        if ($id > 0) {
            list($orders, $stat) = BundlesModel::getBundleOrders($id, $this->warehouse_id);
            if (!empty($orders))
                array_walk($orders, function (&$order) {
                    if (!empty($this->time_diff))
                        $order['create_date'] = $this->handleTimezone($order['create_date']);
                    $order['create_date'] = date(WithDate::DATE_FRONT_FORMAT, strtotime($order['create_date']));
                });
            $this->response = json_encode(['orders' => $orders, 'stat' => $stat]);
            return;
        }
        $this->response = json_encode(['error']);
    }

    public function validateName($retry = false)
    {
        if (($ajax = Request::isAjax()) || $retry) {
            $errors = [];
            $bundle_name = trim(addslashes($_POST['bundle_name'] ?? ''));
            if (empty($bundle_name)) {
                $error = 'Bundle Name/SKU cannot be empty';
                $errors[] = $error;
                if ($retry)
                    View::addFlash('bundles', 'danger', $error);
            }
            $bundle = BundlesModel::getById(Request::getPositiveId($_POST, 'bundle_id'));
            $supplier_id = empty($bundle) ? Request::getPositiveId($_POST, 'supplier_id') : $bundle->getSupplierId();
            if (!empty($bundle) && $bundle->getName() == $bundle_name)
                $errors = [];
            elseif (BundlesModel::isBundleExists($supplier_id, $bundle_name)) {
                $error = "Bundle {$bundle_name} already exists";
                $errors[] = $error;
                if ($retry)
                    View::addFlash('bundles', 'danger', $error);
            }
            $result = empty($errors);
            if ($ajax)
                return $this->response = [
                    'result' => $result ? 'success' : 'error',
                    'errors' => $errors
                ];
            else
                return $result;
        }
        return false;
    }

    public function checkSKU()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty(SKUsModel::getById($SKU_id = Request::getPositiveId($_POST, 'SKU_id'))))
            return $this->response = ['error'];
        return $this->response = BundlesModel::getBySKU($SKU_id);
    }
}