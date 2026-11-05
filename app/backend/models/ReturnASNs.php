<?php

namespace app\backend\models;

use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\entities\ASN;
use app\core\entities\ASNSKU;
use app\core\entities\FileJob;
use app\core\entities\UserJob;
use app\core\helpers\Filter;
use app\core\helpers\formatters\FormatterCSV;
use app\core\helpers\Request;
use app\core\interfaces\ReportCSV as InterfaceReportCSV;
use app\core\models\Warehouses;
use app\core\statical\Timezones;
use app\core\traits\ReportCSV as TraitReportCSV;

class ReturnASNs implements InterfaceReportCSV
{
    use TraitReportCSV;

    private const REPORT_FOLDER_NAME = 'Return_ASN_reports';
    private const REPORT_NAME = 'Return_ASN_list';

    const LIMIT_PER_PAGE = 10;

    public static function getAll($filters)
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT SQL_CALC_FOUND_ROWS 
                   a.*, s.name as supplier_name, s.id supplier_id, o.ref order_ref, o.ref2 order_ref2, u.login, ood.delivery contact, o.is_hash email
            FROM ASNs a");
        $query->addJoin("JOIN suppliers s ON a.supplier_id = s.id");
        $query->addJoin("JOIN orders o ON a.order_id = o.id");
        $query->addJoin("JOIN users u ON a.user_id = u.id");
        $query->addJoin("LEFT JOIN orders_original_despatched ood ON o.id = ood.order_id");
        if (!empty($filters['barcode'])) {
            $query->addJoin("JOIN ASN_SKUs ask ON a.id = ask.ASN_id");
            $query->addJoin("JOIN SKUs ON ask.SKU_id = SKUs.id");
        }
        self::applySearchFilters($query, $filters);
        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);
        $query->setGroupBy("a.id");
        $query->setOrderBy("a.id DESC");
        $query->setLimit("{$offset}, " . self::LIMIT_PER_PAGE);
        $records_raw = (new SafeMysql())->fetchAll($query->build(), $filters);

        foreach ($records_raw as &$rasn) {
            $delivery_data = json_decode($rasn['contact'] ?: '{"email":"-","contact":"-"}', true);
            $is_hash = $rasn['email'];
            $rasn['email'] = $is_hash ? '<span class="badge badge-danger">Hashed</span>' : $delivery_data['email'];
            $rasn['contact'] = $is_hash ? '<span class="badge badge-danger">Hashed</span>' : $delivery_data['contact'];
        } unset($rasn);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        return ['records' => $records_raw, 'total' => $total, 'current_page' => $current_page];
    }

    public static function getAllAsCSV(array $filters)
    {
        $query = self::formatReportQuery($filters);
        $query->setGroupBy("a.id");
        $query->setOrderBy("a.id DESC");
        $query->setLimit('10000');
        $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        $csv = new FormatterCSV;
        $csv->addHeader(self::handleHeadlineCSV(null));
        while (!empty($raw_result)) {
            self::handleDataToCSV($raw_result, $csv, $filters['warehouse_id'] ?? null);

            $last_asn_id = intval(end($raw_result)['id']);
            $query->addWhere('a.id < ' . $last_asn_id);
            $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        }
    }

    private static function formatReportQuery(array &$filters): QueryBuilder
    {
        $SKUs_barcode = !empty($filters['barcode']) ? "(SELECT GROUP_CONCAT(barcode SEPARATOR ', ') FROM SKU_barcodes WHERE SKU_id = SKUs.id) AS SKU_barcode," : '';
        $sql = "
        SELECT
            a.id as item_id,           -- Нужно для работы скрипта, с большими CSV
            a.id,                      -- ASN ID
            s.name AS supplier_name,   -- Client
            a.order_id,                -- Order ID
            o.ref,                     -- Reference
            o.ref2,                    -- Reference 2
            ood.delivery,              -- Contact name, -- Email (json)
            o.despatched_date,         -- Despatched date                                                        
            (SELECT
                create_date
             FROM
                ASN_history AS ah
             WHERE
                ah.ASN_id = a.id
                AND ah.comment LIKE 'ASN created%'
             LIMIT 1) AS create_date,  -- Return create date
            a.status,                  -- Status
            a.actioned,                -- CS Actioned (Populate with 'Y' if selected)

            $SKUs_barcode              -- Нужно для удаления лишних полей в методе handleDataToCSV
            o.is_hash                  -- Если true, то вместо `Contact name`, `Email` нужно вывести `Hashed`
        FROM
            ASNs AS a";
        $query = new QueryBuilder();
        $query->setSelectFrom($sql);
        $query->addJoin("JOIN suppliers s ON a.supplier_id = s.id");
        $query->addJoin("JOIN orders o ON a.order_id = o.id");
        $query->addJoin("JOIN users u ON a.user_id = u.id");
        $query->addJoin("LEFT JOIN orders_original_despatched ood ON o.id = ood.order_id");
        self::applySearchFilters($query, $filters);
        return $query;
    }

    static function handleHeadlineCSV($user_id): array
    {
        return [
            'ASN ID',
            'Client',
            'Order ID',
            'Reference',
            'Reference 2',
            'Contact name',
            'Email',
            'Despatched date',
            'Return create date',
            'Status',
            'Product name',
            'Barcode',
            'Quantity',
            'Condition',
            'Reason',
            'Refund or Exchange',
            'Action required',
            'CS Actioned'
        ];
    }

    static function getTotalCountReportCSV(array $filters)
    {
        return (int)self::getAll($filters)['total'];
    }

    static function handleReportCSV($user_job_id, $max_item_id = 0): array
    {
        /**
         * @var FileJob $fileJob
         * @var UserJob $userJob
         * @var FormatterCSV $csv
         */
        try {
            list($userJob, $fileJob, $csv) = self::initReportCSV($user_job_id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        $addition = json_decode($userJob->getAddition(), true);
        $filters = (!empty($max_item_id) ? ['max_item_id' => $max_item_id] : []) + ($addition['filters'] ?? []);
        $query = self::formatReportQuery($filters);
        $query->setGroupBy("a.id");
        $query->setOrderBy("a.id DESC");
        $query->setLimit(self::LIMIT_GENERATE_REPORT + 1);
        $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        $next = false;
        if (!empty($raw_result)) {
            $next = self::processStep($raw_result, $csv, $userJob);
        }
        if (!$next || empty($raw_result)) {
            self::transferToCloud($fileJob);
            self::doneUserJob($userJob, $fileJob);
        }
        return ['success' => 'Handled part of ASN report CSV for user job #' . $user_job_id];
    }

    static function handleDataToCSV(array $raw_result, FormatterCSV $csv, $addition = null)
    {
        $timezone = '';
        if ($warehouse = Warehouses::getById($addition)) {
            $timezone = $warehouse->getTimezone();
        }
        $SKUs = self::getSKUsByASNs(array_column($raw_result, 'id'));
        foreach ($raw_result  as $row) {
            $delivery = json_decode($row['delivery'], true) ?? [];
            $data = [
                'ASN ID' => $row['id'],
                'Client' => $row['supplier_name'],
                'Order ID' => $row['order_id'],
                'Reference' => $row['ref'],
                'Reference 2' => $row['ref2'],
                'Contact name' => $row['is_hash'] ? 'Hashed' : ($delivery['contact'] ?? ''),
                'Email' =>  $row['is_hash'] ? 'Hashed' : ($delivery['email'] ?? ''),
                'Despatched date' => $timezone ? Timezones::getDiff($warehouse->getTimezone(), $row['despatched_date']) : $row['despatched_date'],
                'Return create date' => $timezone ? Timezones::getDiff($warehouse->getTimezone(), $row['create_date']) : $row['create_date'],
                'Status' => ASN::getStateNameReturn($row['status']),
                'Product name' => '',
                'Barcode' => '',
                'Quantity' => '',
                'Condition' => '',
                'Reason' => '',
                'Refund or Exchange' => '',
                'Action required' => '',
                'CS Actioned' => $row['actioned'] ? 'Y' : 'N',
            ];
            if (empty($SKUs[$row['id']])) {
                $csv->addRow($data);
            } else {
                foreach ($SKUs[$row['id']] as $SKU) {
                    if (!empty($row['SKU_barcode']) && $row['SKU_barcode'] !== $SKU['barcode']) {
                        continue;
                    }
                    $currentData = $data;
                    $batch_number = json_decode($SKU['batch_number'], true) ?? [];

                    $currentData['Product name'] = $SKU['product_name'];
                    $currentData['Barcode'] = $SKU['barcode'];
                    $currentData['Quantity'] = $SKU['quantity'];
                    $currentData['Condition'] = ASN::CONDITIONS[$batch_number['condition'] ?? 0];
                    $currentData['Reason'] = ASN::REASONS[$batch_number['reason'] ?? 0];
                    $currentData['Refund or Exchange'] = ASN::REFUNDS[$batch_number['refund'] ?? 0];
                    $currentData['Action required'] = ASN::ACTIONS[$batch_number['action'] ?? 0];
                    $csv->addRow($currentData);
                }
            }
        }
    }

    private static function getSKUsByASNs(array $ids): array {
        $ids = array_map('intval', $ids);
        $sql = "
        SELECT
            ask.ASN_id AS id,
            skus.name AS product_name,         -- Product name
            ask.quantity AS quantity,          -- Quantity
            ask.batch_number AS batch_number,   -- Condition, -- Reason, -- Refund or Exchange, Action required (json)
            skus.id AS SKU_id
        FROM
            ASN_SKUs AS ask

            INNER JOIN SKUs AS skus
            ON ask.SKU_id = skus.id
        WHERE
            ask.ASN_id IN (:ids)";
        $items = (new SafeMysql())->fetchAll($sql, ['ids' => $ids]);
        SKUBarcodes::addBarcodesToArray($items, 'SKU_id');
        $SKUs = [];
        foreach ($items  as $item) {
            $SKUs[$item['id']][] = $item;
        }
        return $SKUs;
    }

    private static function applySearchFilters(QueryBuilder $query, array &$filters): void
    {
        $query->addWhere("a.order_id > 0");
        if (!empty($filters['warehouse_id'])) {
            $filters['warehouse_id'] = (int)$filters['warehouse_id'];
            $query->addWhere("a.warehouse_id = :warehouse_id");
        }
        if (!empty($filters['supplier_id'])) {
            if (is_array($filters['supplier_id']))
                $query->addWhere('s.id IN (:supplier_id)');
            else {
                $filters['supplier_id'] = (int)$filters['supplier_id'];
                $query->addWhere("s.id = :supplier_id");
            }
        }
        if (!empty($filters['date_created_from']))
            $query->addWhere("a.arrival_date >= :date_created_from");
        if (!empty($filters['date_created_to']))
            $query->addWhere("a.arrival_date <= :date_created_to");
        if (!empty($filters['id'])) {
            $filters['id'] = (int)$filters['id'];
            $query->addWhere("a.id = :id");
        }
        if (!empty($filters['order_id'])) {
            $filters['order_id'] = (int)$filters['order_id'];
            $query->addWhere("o.id = :order_id");
        }
        if (!empty($filters['ref']))
            $query->addWhere("o.ref = :ref");
        if (!empty($filters['ref2']))
            $query->addWhere("o.ref2 = :ref2");
        if (isset($filters['status']))
            if ($filters['status'] !== 'all') {
                $filters['status'] = (int)$filters['status'];
                $query->addWhere("a.status = :status");
            }
        if (!empty($filters['barcode'])) {
            $query->addJoin("JOIN ASN_SKUs ask ON a.id = ask.ASN_id");
            $query->addJoin("JOIN SKUs ON ask.SKU_id = SKUs.id");
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode($filters['barcode'], Filter::getFilterTypeFromRequest($filters, 'barcode'));
            $SKU_ids ? $query->addWhere("SKUs.id IN (" . implode(', ', $SKU_ids) . ")") : $query->addWhere("FALSE");
        }
        if (!empty($filters['contact']))
            $query->addWhere("JSON_UNQUOTE(JSON_EXTRACT(ood.delivery, '$.contact')) = :contact");
        if (!empty($filters['email']))
            $query->addWhere("JSON_UNQUOTE(JSON_EXTRACT(ood.delivery, '$.email')) = :email");
        if (isset($filters['actioned']) && $filters['actioned'] !== 'all') {
            $filters['actioned'] = (int)$filters['actioned'];
            $query->addWhere("a.actioned = :actioned");
        }
        if (!empty($filters['max_item_id'])) {
            $filters['max_item_id'] = (int)$filters['max_item_id'];
            $query->addWhere("a.id < :max_item_id");
        }
    }

    public static function updateActions($ASNSKUs, $actions, $user_id)
    {
        foreach ($ASNSKUs as $ASNSKU_data) {
            $fields = false;
            if (!empty($actions['condition'])) {
                $condition = $actions['condition'][$ASNSKU_data['SKU']->getSKUId()] ?? 0;
                if ($ASNSKU_data['SKU']->getCondition() != $condition) {
                    $ASNSKU_data['SKU']->setCondition($condition);
                    $fields = true;
                }
            }
            if (!empty($actions['reason'])) {
                $reason = $actions['reason'][$ASNSKU_data['SKU']->getSKUId()] ?? 0;
                if ($ASNSKU_data['SKU']->getReason() != $reason) {
                    $ASNSKU_data['SKU']->setReason($reason);
                    $fields = true;
                }
            }
            if (!empty($actions['refund'])) {
                $refund = $actions['refund'][$ASNSKU_data['SKU']->getSKUId()] ?? 0;
                if ($ASNSKU_data['SKU']->getRefund() != $refund) {
                    $ASNSKU_data['SKU']->setRefund($refund);
                    $fields = true;
                }
            }
            if ($fields) {
                $ASNSKU_data['SKU']->update(['batch_number']);
                ASNHistories::commentASN($ASNSKU_data['SKU']->getASNId(), $user_id,
                    "SKU #{$ASNSKU_data['SKU']->getSKUId()}: condition - '" . ASN::CONDITIONS[$ASNSKU_data['SKU']->getCondition()] .
                    "', reason - '" . ASN::REASONS[$ASNSKU_data['SKU']->getReason()] .
                    "', refund or exchange - '" . ASN::REFUNDS[$ASNSKU_data['SKU']->getRefund()] . "'");
            }
        }
    }

    public static function updateWarehouses($ASNSKUs, $warehouses, $user_id)
    {
        foreach ($ASNSKUs as $ASNSKU_data) {
            /** @var ASNSKU $ASNSKU */
            $ASNSKU = $ASNSKU_data['SKU'];
            if (!empty($warehouses['action'])) {
                $action = $warehouses['action'][$ASNSKU->getSKUId()] ?? 0;
                if ($ASNSKU->getAction() != $action) {
                    $ASNSKU->setAction($action);
                    $ASNSKU->update(['batch_number']);
                    ASNHistories::commentASN($ASNSKU_data['SKU']->getASNId(), $user_id,
                        "SKU #{$ASNSKU_data['SKU']->getSKUId()}: action - '" . ASN::ACTIONS[$ASNSKU_data['SKU']->getAction()] . "'");
                }
            }
        }
    }
}
