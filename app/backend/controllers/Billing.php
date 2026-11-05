<?php

namespace app\backend\controllers;

use app\backend\models\Billing as BillingModel;
use app\backend\models\ChargeASNBookings;
use app\backend\models\ChargeGroupsPackagings;
use app\backend\models\ChargeOrderPackagings;
use app\backend\models\ChargeOrderPicks;
use app\backend\models\ChargesHistory;
use app\backend\models\ChargesRecurring;
use app\backend\models\ChargesRecurringBilling;
use app\backend\models\Consumables;
use app\backend\models\InvoiceBillingSuppliers;
use app\backend\models\InvoicesBilling;
use app\backend\models\Charges;
use app\backend\models\ChargesBilling;
use app\backend\models\ChargesComplex;
use app\backend\models\Pagination;
use app\backend\models\Suppliers;
use app\core\entities\Charge;
use app\core\entities\ChargeGroup;
use app\core\entities\SupplierWarehouse;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\File;
use app\core\helpers\FileWrapper;
use app\core\helpers\Request;
use app\core\helpers\status\ChargeType;
use app\core\helpers\View;
use app\core\models\ChargeGroups;
use app\core\statical\WithDate;

class Billing extends AuthController
{
    public function charges()
    {
        $data = [
            'records' => Charges::getAll()
        ];
        $this->render($data, 'billing/charges');
    }

    /** @throws */
    public function getCharge()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_GET))
            return $this->response = ['error' => 'Error data'];
        $charge = Charges::getById($_GET['charge_id']);
        if (!$charge)
            $charge = new Charge();
        $charge = $charge->toArray();
        $charge['type_name'] = ChargeType::get($charge['type']);
        $charge['standard'] = Charge::isStandard((int)$charge['type']);
        return $this->response = ['data' => $charge];
    }

    /** @throws */
    public function setCharge()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST))
            return $this->response = ['error' => 'Error data'];
        if ($id = (int)$_POST['id'])
            return $this->response = BillingModel::updateCharge($_POST, $id);
        else
            return $this->response = BillingModel::createCharge($_POST);
    }

    public function companies()
    {
        $pagination = new Pagination($_GET['c'] ?? 1);
        $data = [
            'records' => Suppliers::getSuppliersBilling(
                $this->logistic_company->getId(),
                $this->warehouse_id,
                array_keys(Charge::TYPE_NAMES),
                $pagination),
            'total_count' => $pagination->getTotalCount(),
            'total_pages' => $pagination->getPageCount(),
            'current_page' => $pagination->getCurrentPage(),
        ];
        $this->render($data, 'billing/companies');
    }

    /** @throws */
    public function lockBilling()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST) || empty($_POST['sw_id']))
            return $this->response = ['error' => 'Error data'];
        $supplier_warehouse = new SupplierWarehouse([
            'id' => (int)$_POST['sw_id'],
            'is_billing' => 1 - (int)$_POST['mode']
        ]);
        $supplier_warehouse->save(['id', 'is_billing']);
        return $this->response = ['success' => true];
    }

    /** @throws */
    public function getCharges()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_GET) || empty($_GET['sw_id']))
            return $this->response = ['error' => 'Error data'];
        $charges = Charges::getChargesPrepare($_GET['sw_id'], array_keys(Charge::TYPE_NAMES));
        if (!$charges)
            return $this->response = ['error' => 'Charges are not found'];
        return $this->response = ['data' => $charges];
    }

    /** @throws */
    public function getRecurring()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_GET) || empty($_GET['sw_id']))
            return $this->response = ['error' => 'Error data'];
        $charges = Charges::getChargesRecurring($_GET['sw_id']);
        if (!$charges)
            return $this->response = ['error' => 'Recurring charges are not found'];
        return $this->response = ['data' => $charges];
    }

    /** @throws */
    public function setCharges()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST['sw_id']))
            return $this->response = ['error' => 'Error data'];
        $charges = $_POST['usage'] ?? [];
        $charges_exist = Arr::maps(Charges::getChargesComplex($_POST['sw_id'], array_keys(Charge::TYPE_NAMES)), 'id', ['type', 'is_usage']);
        $ins_charges = $del_charges = [];
        foreach ($charges_exist as $charge_id => $charge) {
            if ($charge['is_usage'] && isset($charges[$charge['type']]) && $charges[$charge['type']] === 'on')
                continue;
            if (!$charge['is_usage'] && !isset($charges[$charge['type']]))
                continue;
            if ($charge['is_usage'])
                $del_charges[$charge_id] = 1;
            else
                $ins_charges[$charge_id] = 1;
        }
        if ($ins_charges)
            ChargesComplex::insertBySupplier($_POST['sw_id'], array_keys($ins_charges));
        if ($del_charges)
            ChargesComplex::deleteBySupplier($_POST['sw_id'], array_keys($del_charges));
        return $this->response = ['data' => $charges];
    }

    /** @throws */
    public function setRecurring()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST['sw_id']))
            return $this->response = ['error' => 'Error data'];
        $charges = $_POST['usage'] ?? [];
        $charges_exist = Charges::getChargesRecurring($_POST['sw_id']);
        $ins_charges = $del_charges = [];
        foreach ($charges_exist as $charge_id => $charge) {
            if ($charge['is_usage'] && isset($charges[$charge_id]) && $charges[$charge_id] === 'on')
                continue;
            if (!$charge['is_usage'] && !isset($charges[$charge_id]))
                continue;
            if ($charge['is_usage'])
                $del_charges[$charge_id] = 1;
            else
                $ins_charges[$charge_id] = 1;
        }
        if ($ins_charges)
            ChargesRecurring::insertBySupplier($_POST['sw_id'], array_keys($ins_charges));
        if ($del_charges)
            ChargesRecurring::deleteBySupplier($_POST['sw_id'], array_keys($del_charges));
        return $this->response = ['data' => $charges];
    }

    /** @throws */
    public function getTariffs()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_GET) || empty($_GET['sw_id']))
            return $this->response = ['error' => 'Error data'];
        $rates_valid = ($_GET['rates_valid'] ?? '') ?: date(WithDate::DATE_SQL_FORMAT);
        switch ($_GET['type_charge']) {
            case BillingModel::CHARGE_TAB:
                $tariffs = ChargesBilling::getChargesBilling((int)$_GET['sw_id'], $rates_valid);
                break;
            case BillingModel::RECURRING_TAB:
                $tariffs = ChargesRecurringBilling::getRecurringBilling((int)$_GET['sw_id'], $rates_valid);
                break;
            default:
                return $this->response = ['error' => 'Error data'];
        }
        if (empty($tariffs))
            return $this->response = ['data' => ['rates_valid' => $rates_valid, 'error' => 'Tariffs are not found']];
        return $this->response = ['data' => ['rates_valid' => $rates_valid, 'tariffs' => $tariffs]];
    }

    /** @throws */
    public function import()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST) || empty($_POST['sw_id']) || empty($_FILES['import_file']))
            return $this->response = ['error' => 'Error data'];
        $sw_id = (int)$_POST['sw_id'];
        if ($_FILES['import_file']['error'] != UPLOAD_ERR_OK)
            return $this->response = ['error' => 'Upload failed with code ' . $_FILES['import_file']['error']];
        switch ($_POST['type_charge']) {
            case BillingModel::CHARGE_TAB:
                $res = ChargesBilling::getFile($_FILES['import_file']['tmp_name']);
                if (!is_array($res))
                    return $this->response = ['error' => $res];
                [$header, $tariffs] = $res;
                [$nums, $errors] = BillingModel::setTariffs($sw_id, $header, $tariffs, $this->user);
                break;
            case BillingModel::RECURRING_TAB:
                $res = ChargesRecurringBilling::getFile($_FILES['import_file']['tmp_name']);
                if (!is_array($res))
                    return $this->response = ['error' => $res];
                [$header, $tariffs] = $res;
                [$nums, $errors] = BillingModel::setRecurringTariffs($sw_id, $header, $tariffs, $this->user);
                break;
            default:
                return $this->response = ['error' => 'Error data'];
        }
        return $this->response = ['success' => $nums, 'errors' => $errors ? ('Import<hr class="my-1"/>' . implode('<br/>', array_map([View::class, 'toHtml'], $errors))) : ''];
    }

    /** @throws */
    public function log()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_GET) || empty($_GET['sw_id']) || empty($_GET['type_charge']))
            return $this->response = ['error' => 'Error data'];
        $data = ChargesHistory::getLog((int)$_GET['sw_id'], ChargesHistory::TYPE_HISTORY_CHARGES[$_GET['type_charge']] ?? []);
        return $this->response = ['data' => $data];
    }

    /** @throws */
    public function download()
    {
        $file = !empty($_GET['id']) ? urldecode($_GET['id'] . '.csv') : '';
        $filename = ChargesBilling::BILLING_IMPORT . $file;
        if (!File::isPathWithin($filename, ChargesBilling::BILLING_IMPORT))
            throw new RedirectException('/forbidden');
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($filename)));
        if (!$file_wrapper->isFile($filename))
            throw new RedirectException('/forbidden');
        header('Content-Encoding: UTF-8');
        header('Content-Type: application/txt; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $file);
        echo stream_get_contents($file_wrapper->getStream($filename));
    }

    /** @throws */
    public function export()
    {
        if (!$this->user->isRoleSA() || empty($_GET) || empty($_GET['sw_id']) || empty($_GET['rates_valid']) || empty($_GET['type_charge']))
            throw new RedirectException('/forbidden');
        switch ($_GET['type_charge']) {
            case BillingModel::CHARGE_TAB:
                [$filename, $header, $data] = ChargesBilling::createDataExport((int)$_GET['sw_id'], $_GET['rates_valid']);
                break;
            case BillingModel::RECURRING_TAB:
                [$filename, $header, $data] = ChargesRecurringBilling::createDataExport((int)$_GET['sw_id'], $_GET['rates_valid']);
                break;
            default:
                return $this->response = ['error' => 'Error data'];
        }
        $temp = tmpfile();
        fputcsv($temp, $header);
        foreach ($data as $item)
            fputcsv($temp, $item);
        fseek($temp, 0);
        header('Content-Encoding: UTF-8');
        header('Content-Type: application/txt; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename);
        echo stream_get_contents($temp);
    }

    public function invoices()
    {
        $data = [
            'invoices' => InvoicesBilling::getAll($this->warehouse_id)
        ];
        $this->render($data, 'billing/invoices');
    }

    /** @throws */
    public function getInvoice()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_GET) || empty($_GET['id']))
            return $this->response = ['error' => 'Error data'];
        $data = BillingModel::getFiles($this->warehouse_id, (int)$_GET['id']);
        if (!$data)
            return $this->response = ['error' => 'Data is not found'];
        return $this->response = ['data' => $data];
    }

    /** @throws */
    public function setInvoice()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST) || empty($_POST['id']))
            return $this->response = ['error' => 'Error data'];
        $errors = BillingModel::createInvoice($this->warehouse, (int)$_POST['id'], $this->user->getId());
        if (is_string($errors))
            return $this->response = ['error' => $errors];
        elseif ($errors)
            View::addFlash('billing', 'danger', $errors);
        else
            View::addFlash('billing', 'success', 'The invoice has been created');
        return $this->response = ['data' => 'success'];
    }

    /** @throws */
    public function getInvoices()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_GET) || empty($_GET['id']))
            return $this->response = ['error' => 'Error data'];
        $id = (int)$_GET['id'];
        $invoice = InvoicesBilling::getById($id);
        if (!$invoice)
            return $this->response = ['error' => 'Invoice is not found'];
        $data = InvoiceBillingSuppliers::getInvoices($this->warehouse_id, $invoice['id']);
        if (!$data)
            return $this->response = ['error' => 'Invoices are not found'];
        return $this->response = ['data' => ['invoice' => $invoice, 'documents' => $data]];
    }

    /** @throws */
    public function savePdf()
    {
        if (!$this->user->isRoleSA() || empty($_GET) || empty($_GET['id']))
            throw new RedirectException('/forbidden');
        BillingModel::createInvoicePDF($_GET['id']);
    }

    /** @throws */
    public function saveCsv()
    {
        if (!$this->user->isRoleSA() || empty($_GET) || empty($_GET['id']))
            throw new RedirectException('/forbidden');
        $id = (int)$_GET['id'];
        $type = (int)$_GET['csv'];
        if (in_array($type, [Charge::TYPE_PICK, Charge::TYPE_CULLING_PICK, Charge::TYPE_BUNDLING_PICK]))
            [$filename, $header, $data] = ChargeOrderPicks::createDataExport($this->warehouse_id, $id, $type);
        elseif (in_array($type, [Charge::TYPE_PACKAGING, Charge::TYPE_CULLING_PACKAGING, Charge::TYPE_BUNDLING_PACKAGING]))
            [$filename, $header, $data] = ChargeOrderPackagings::createDataExport($this->warehouse_id, $id, $type);
        elseif (in_array($type, [Charge::TYPE_BOOKING_IN]))
            [$filename, $header, $data] = ChargeASNBookings::createDataExport($this->warehouse_id, $id);
        elseif (!$type)
            [$filename, $header, $data] = ChargesRecurring::createDataExport($this->warehouse_id, $id);
        else
            throw new RedirectException('/forbidden');
        $temp = tmpfile();
        fputcsv($temp, $header);
        foreach ($data as $item)
            fputcsv($temp, $item);
        fseek($temp, 0);
        header('Content-Encoding: UTF-8');
        header('Content-Type: application/txt; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename);
        echo stream_get_contents($temp);
    }

    public function recurring()
    {
        $data = [
            'records' => Charges::getAll(false)
        ];
        $this->render($data, 'billing/recurring');
    }

    /** @throws */
    public function lockRecurring()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST) || empty($_POST['id']))
            return $this->response = ['error' => 'Error data'];
        $charge = new Charge([
            'id' => (int)$_POST['id'],
            'status' => 1 - (int)$_POST['mode']
        ]);
        $charge->save(['id', 'status']);
        return $this->response = ['success' => true];
    }

    /** @throws */
    public function getChargeData()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_GET) || empty($_GET['id']) || empty($_GET['type_charge']))
            return $this->response = ['error' => 'Error data'];
        $id = (int)$_GET['id'];
        $cb_id = (int)$_GET['cb_id'];
        $type_charge = $_GET['type_charge'];
        $data = [
            'id' => $id,
            'cb_id' => $cb_id,
            'type_charge' => $type_charge
        ];
        if (!$cb_id)
            return $this->response = ['data' => $data + ($type_charge === BillingModel::RECURRING_TAB ? ['quantity' => 0] : []) + [
                'value' => 0,
                'valid_from' => ChargesBilling::BILLING_VALID_START,
                'valid_to' => null,
            ]];
        switch ($type_charge) {
            case BillingModel::CHARGE_TAB:
                return $this->response = ['data' => $data + ChargesBilling::getChargeById($cb_id, $id)];
            case BillingModel::RECURRING_TAB:
                return $this->response = ['data' => $data + ChargesRecurringBilling::getChargeById($cb_id, $id)];
            default:
                return $this->response = ['error' => 'Error data'];
        }
    }

    /** @throws */
    public function setChargeData()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST) || empty($_POST['sw_id']) || empty($_POST['id']) || empty($_POST['type_charge']))
            return $this->response = ['error' => 'Error data'];
        return $this->response = BillingModel::editCharge($_POST, $this->user->getId());
    }

    /** @throws */
    public function setUpdateRanges()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST) || empty($_POST['sw_id']) || empty($_POST['ranges']) || empty($_POST['type_charge']))
            return $this->response = ['error' => 'Error data'];
        if ($_POST['type_charge'] == BillingModel::CHARGE_TAB)
            $name_id = 'charge_complex_id';
        elseif ($_POST['type_charge'] == BillingModel::RECURRING_TAB)
            $name_id = 'charge_recurring_id';
        else
            return $this->response = ['error' => 'Error data'];
        $id = array_values($_POST['ranges'])[0][$name_id];
        $insert_data = $update_data = $delete_data = [];
        foreach ($_POST['ranges'] as $range)
            switch ($range['action']) {
                case 'insert':
                    $insert_data[] = $range;
                    break;
                case 'update':
                    $update_data[] = $range;
                    break;
                case 'delete':
                    $delete_data[] = $range;
                    break;
            }
        return $this->response = BillingModel::updateRange((int)$_POST['sw_id'], $_POST['type_charge'], $id, $this->user->getId(), $delete_data, $insert_data, $update_data);
    }

    /** @throws */
    public function getRangeData()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_GET) || empty($_GET['id']) || empty($_GET['type_charge']))
            return $this->response = ['error' => 'Error data'];
        $id = (int)$_GET['id'];
        $type_charge = $_GET['type_charge'];
        switch ($type_charge) {
            case BillingModel::CHARGE_TAB:
                return $this->response = ['data' => ['code' => ChargesComplex::getCodeById($id), 'ranges' => ChargesBilling::getRanges($id)]];
            case BillingModel::RECURRING_TAB:
                return $this->response = ['data' => ['code' => ChargesRecurring::getCodeById($id), 'ranges' => ChargesRecurringBilling::getRanges($id)]];
            default:
                return $this->response = ['error' => 'Error data'];
        }
    }

    /** @throws */
    public function deleteRange()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST) || empty($_POST['id']) || empty($_POST['type_charge']))
            return $this->response = ['error' => 'Error data'];
        $id = (int)$_POST['id'];
        $type_charge = $_POST['type_charge'];
        switch ($type_charge) {
            case BillingModel::CHARGE_TAB:
                return $this->response = ['success' => ChargesBilling::deleteRange($id)];
            case BillingModel::RECURRING_TAB:
                return $this->response = ['success' => ChargesRecurringBilling::deleteRange($id)];
            default:
                return $this->response = ['error' => 'Error data'];
        }
    }

    public function tariffGroups()
    {
        $data = [
            'records' => ChargeGroups::getAll($this->warehouse_id)
        ];
        $this->render($data, 'billing/tariff_groups');
    }

    /** @throws */
    public function getGroup()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_GET))
            return $this->response = ['error' => 'Error data'];
        $group = ChargeGroups::getById($_GET['group_id']);
        if (!$group)
            $group = new ChargeGroup();
        return $this->response = ['data' => $group->toArray()];
    }

    /** @throws */
    public function setGroup()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST))
            return $this->response = ['error' => 'Error data'];
        if ($id = (int)$_POST['id'])
            return $this->response = BillingModel::updateGroup($_POST, $id, $this->warehouse_id);
        else
            return $this->response = BillingModel::createGroup($_POST, $this->warehouse_id);
    }

    /** @throws */
    public function lockGroup()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST) || empty($_POST['id']))
            return $this->response = ['error' => 'Error data'];
        $group = new ChargeGroup([
            'id' => (int)$_POST['id'],
            'status' => 1 - (int)$_POST['mode']
        ]);
        $group->save(['id', 'status']);
        return $this->response = ['success' => true];
    }

    public function packagings()
    {
        $_SESSION[Consumables::FILTER_BILLING_PARAM] = http_build_query($_GET);
        $filters = $_GET;
        $records = Consumables::getPackagings($this->logistic_company->getId(), $this->warehouse_id, $filters);
        $data = [
            'records' => $records['records'],
            'total' => $records['total'],
            'current_page' => $records['current_page'],
            'pages' => $records['pages']
        ];
        $this->render($data, 'billing/packagings');
    }

    /** @throws */
    public function getGroups()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_GET))
            return $this->response = ['error' => 'Error data'];
        $groups = ChargeGroups::getAll($this->warehouse_id);
        if (!$groups)
            return $this->response = ['error' => 'Groups are not found'];
        $res = [];
        foreach ($groups as $group)
            $res[$group->getId()] = "{$group->getName()} ({$group->getCode()})";
        $packaging = ChargeGroupsPackagings::getPackaging($this->warehouse_id, $_GET['packaging_id']);
        $current = (!$packaging || !in_array($packaging['charge_group_id'], array_keys($res))) ? 0 : (int)$packaging['charge_group_id'];
        return $this->response = ['data' => ['current' => $current, 'groups' => $res]];
    }

    /** @throws */
    public function setPackagingGroup()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST))
            return $this->response = ['error' => 'Error data'];
        if (!($packaging = Consumables::getById($_POST['id'])))
            return $this->response = ['error' => 'Packaging is not found'];
        $group_id = (int)$_POST['group_id'];
        if ($group_id)
            ChargeGroupsPackagings::add($group_id, $packaging->getId());
        else
            ChargeGroupsPackagings::delete($this->warehouse_id, $packaging->getId());
        return $this->response = ['success' => true];
    }

    /** @throws */
    public function setDefaultPackaging()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        if (empty($_POST))
            return $this->response = ['error' => 'Error data'];
        $packaging = ChargeGroupsPackagings::getPackaging($this->warehouse_id, $_POST['id']);
        if (!$packaging)
            return $this->response = ['error' => 'Packaging is not found'];
        ChargeGroupsPackagings::default($packaging['packaging_id'], $packaging['charge_group_id']);
        return $this->response = ['success' => true];
    }
}