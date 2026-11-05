<?php

namespace app\core\services\upload;

use app\core\entities\Warehouse;
use app\core\entities\User;
use app\backend\models\Suppliers;
use app\core\helpers\Barcode128Rule;
use app\backend\models\SKUs;
use app\backend\models\SKUBarcodes;
use app\core\interfaces\SFTPUpload;

class SKUsUploadService implements SFTPUpload
{
    private static $fields_SKU = [
        'name',
        'barcode',
        'alternate_codes',
        'gs1',
        'cost_value',
        'cost_currency',
        'market_value',
        'market_currency',
        'width',
        'height',
        'depth',
        'weight',
        'hs_code',
        'season',
        'customer_ref',
        'customer_ref2',
        'photo_src',
        'unique_customer_ref',
        'DDS',
        'exemption_TARIC',
        'ns_pid',
        's_pid',
    ];

    private $messages = [];
    private $rows = [];

    public function execute($head, $data, $form, Warehouse $warehouse, User $user): array
    {
        // ABF-308 https://airbusiness.atlassian.net/jira/software/c/projects/WMSP/boards/53?selectedIssue=WMSP-219
        if (($pos = array_search('length', $head)) !== false) {
            if (($pos_depth = array_search('depth', $head)) !== false)
                $head[$pos_depth] = 'depth_old';
            $head[$pos] = 'depth';
        }
        // ========================================================================================================
        $this->validateHeader($head)
            ->validateSupplier($form, $warehouse, $user)
            ->validateData($form, $head, $data);
        foreach ($this->rows as $SKU_data) {
            list($res, $errors) = SKUs::addSKU($SKU_data, $SKU_data['barcode'], $warehouse->getId(), $user->getId());
            if (!$res)
                $this->messages[] = json_encode($errors);
        }
        if (!empty($this->messages))
            return [[false, ['danger' => $this->messages]], null];
        return [[true, ['danger' => [], 'success' => 'Successfully']], -1];
    }

    private function validateHeader($head): self
    {
        if (!count($this->messages)) {
            $not_exist = [];
            foreach (['name', 'barcode', 'customer_ref'] as $needle)
                if (!in_array($needle, $head, true))
                    $not_exist[] = $needle;
            if (!empty($not_exist))
                $this->messages['header'] = [
                    'Absent header of CSV. The following columns need to be added: ' . implode(',', $not_exist)
                ];
        }
        return $this;
    }

    private function validateSupplier(&$form, Warehouse $warehouse, User $user): self
    {
        if (!count($this->messages)) {
            if (empty($form['supplier'])) {
                $supplier = Suppliers::getSupplierByUser($warehouse->getId(), $user->getId());
                $form['supplier'] = $supplier['id'] ?? -1;
            }
            if (!array_key_exists($form['supplier'], $this->getSuppliers($warehouse, $user)))
                $this->messages['supplier'] = ['Company not found'];
        }
        return $this;
    }

    private function validateData($form, $head, $data): self
    {
        if (!count($this->messages)) {
            $barcodes = [];
            foreach ($data as $line => $row) {
                $errors_line = [];
                $row = array_map(function($item) {
                    return mb_convert_encoding($item, 'UTF-8', 'UTF-8');
                }, $row);
                $assoc_row = $this->prepareRow($form, $head, $row);
                $name_line = 'Line#' . ($line + 1) . ' (' . ($row[1] ?? 'undefined') . ')';
                if (empty($assoc_row['name']))
                    $errors_line[$name_line][] = 'Name is required';
                if (empty($assoc_row['hs_code']))
                    $errors_line[$name_line][] = 'Hs_code is required';
                if (strlen($assoc_row['hs_code']) < 6 || strlen($assoc_row['hs_code']) > 12)
                    $errors_line[$name_line][] = 'Hs_code string minimum length is 6, maximum length is 12';
                if (empty($assoc_row['barcode']))
                    $errors_line[$name_line][] = 'Barcode is required';
                if (empty($assoc_row['customer_ref']))
                    $errors_line[$name_line][] = 'Customer reference is empty';
                $duplicates = SKUBarcodes::getNotUniqueOnlyBarcodes($assoc_row['barcode'], $form['supplier']);
                if (!empty($duplicates))
                    $errors_line[$name_line][] = 'Duplicate barcode found. Ignore lines with barcodes: '.implode(', ',
                            array_keys($duplicates));
                else
                    foreach ($assoc_row['barcode'] as $barcode) {
                        if (in_array($barcode, $barcodes, true))
                            $errors_line[$name_line][] = 'Duplicate barcode found in csv file. Duplicate barcode: '.$barcode;
                        if (!Barcode128Rule::validate($barcode))
                            $errors_line[$name_line][] = 'Invalid barcodes found: '.$barcode;
                        $barcodes[] = $barcode;
                    }
                isset($errors_line[$name_line])
                    ? $this->messages[$name_line] = $errors_line[$name_line]
                    : $this->rows[implode(',', $assoc_row['barcode'])] = $assoc_row;
            }
        }
        return $this;
    }

    private function prepareRow($form, $head, $row): array
    {
        $assoc_row = array_combine($head, $row);
        $assoc_row = array_filter($assoc_row, static function ($column) {
            return in_array($column, self::$fields_SKU, true);
        }, ARRAY_FILTER_USE_KEY);
        $assoc_row['supplier_id'] = $form['supplier'];
        $assoc_row['barcode'] = array_merge([$assoc_row['barcode']], empty($assoc_row['alternate_codes']) ? [] : array_map('trim', explode(',', $assoc_row['alternate_codes'] ?? '')));
        return $assoc_row;
    }

    private function getSuppliers(Warehouse $warehouse, User $user): array
    {
        if ($user->isRoleSupplierAdmin())
            $suppliers = Suppliers::getSuppliersByAdmin($warehouse->getId(), $user->getId());
        elseif ($user->isRoleSA())
            $suppliers = Suppliers::getSuppliers($warehouse->getLCId(), $warehouse->getId());
        elseif ($user->hasWorkerAccess())
            $suppliers = Suppliers::getByFilters($warehouse->getLCId(), ['warehouse_id' => $warehouse->getId()]);
        else
            $suppliers = Suppliers::getSuppliersByUser($warehouse->getId(), $user->getId());
        return $suppliers;
    }
}