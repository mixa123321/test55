<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\db\QueryBuilder;
use app\core\entities\FileJob;
use app\core\entities\UserJob;
use app\core\helpers\Filter;
use app\core\helpers\formatters\FormatterCSV;
use app\core\helpers\Request;
use app\core\interfaces\ReportCSV as InterfaceReportCSV;
use app\core\traits\ReportCSV as TraitReportCSV;

class Bundles extends \app\core\models\Bundles implements InterfaceReportCSV
{
    use TraitReportCSV;

    const LIMIT_PER_PAGE = 10;

    const FILTER_PARAM = 'bundle_filters';

    private const REPORT_FOLDER_NAME = 'bundle_reports';
    private const REPORT_NAME = 'Fulfilment_bundle_list';

    private static function formatReportQuery($warehouse_id, $filters, $flag = true): QueryBuilder
    {
        $warehouse_id = (int)$warehouse_id;
        $query = new QueryBuilder();
        $query->setSelectFrom("SELECT SQL_CALC_FOUND_ROWS b.id, b.id as item_id, b.name, b.supplier_id, IFNULL(c.currency, '') as currency, b.cost_value, sup.name as supplier_name
                           " . ($flag ?
                                    ",IF(ob.order_id, IF(o.warehouse_id = $warehouse_id, o.warehouse_id, NULL), NULL) as exist_warehouse_orders,
                                     IF(ob.order_id, 1, NULL) as exist_orders" : '') . "
                    FROM bundles b ");
        $query->addJoin("JOIN bundles_SKUs bs ON b.id = bs.bundle_id");
        $query->addJoin("JOIN SKUs s ON bs.SKU_id = s.id");
        $query->addJoin("JOIN suppliers sup ON sup.id = b.supplier_id");
        $query->addJoin("LEFT JOIN currency c ON c.id = b.currency_id");
        if ($flag) {
            $query->addJoin("LEFT JOIN orders_bundles ob ON b.id = ob.bundle_id");
            $query->addJoin("LEFT JOIN orders o ON ob.order_id = o.id AND o.warehouse_id = $warehouse_id");
        }
        self::applySearchFilters($query, $filters);
        return $query;
    }

    private static function applySearchFilters(QueryBuilder $query, $filters): QueryBuilder
    {
        if (!empty($filters['supplier_id']))
            $query->addWhere("b.supplier_id = :supplier_id");
        elseif (!empty($filters['supplier_ids']))
            $query->addWhere("b.supplier_id IN (:supplier_ids)");

        if (!empty($filters['name']))
            $query->addWhere("b.name like :name");

        if (!empty($filters['barcode'])) {
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode($filters['barcode'], Filter::getFilterTypeFromRequest($filters, 'barcode'));
            $SKU_ids ? $query->addWhere("s.id IN (" . implode(', ', $SKU_ids) . ")") : $query->addWhere("FALSE");
        }

        if (!empty($filters['max_item_id']))
            $query->addWhere("b.id < :max_item_id");
        return $query;
    }

    private static function handleBundleSKUs($records_raw, $del = false)
    {
        array_walk($records_raw, function (&$item) use ($del) {
            $bundle_SKUs = self::getBundleSKUs($item['id']);
            $SKUs_arr = array_map(function($bundle_SKU) use ($del) {
                $str = $bundle_SKU['barcode']  . ' x ' . $bundle_SKU['quantity'];
                if ($del && $bundle_SKU['state'] == 0)
                    $str = '<del>' . $str . '</del>';
                return $str;
            }, $bundle_SKUs);
            $item['sku_list'] = implode(', ', $SKUs_arr);
        });
        return $records_raw;
    }

    public static function getAll($warehouse_id, array $filters = [], $del = false): array
    {
        $query = self::formatReportQuery($warehouse_id, $filters);
        $query->setGroupBy("b.id, b.supplier_id");
        $query->setOrderBy('b.id DESC');
        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);
        $query->setLimit("{$offset}, " . self::LIMIT_PER_PAGE);
        $filters['name'] = isset($filters['name']) ? '%' . $filters['name'] . '%' : null;
        $records_raw = (new SafeMysql())->fetchAll($query->build(), $filters);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');

        return ['records' => self::handleBundleSKUs($records_raw, $del), 'total' => $total, 'current_page' => $current_page];
    }

    static $headline = [];

    static function handleHeadlineCSV($user_id): array
    {
        $user = Users::getById($user_id);
        self::$headline = [
            'Bundle ID',
            'Bundle Name/SKU',
            'SKUs',
        ];
        if (!empty($user) && !$user->isRoleSupplierUser())
            self::$headline[] = 'Client';
        return self::$headline;
    }

    static function getTotalCountReportCSV(array $filters)
    {
        $warehouse_id = $filters['warehouse_id'] ?? null;
        unset($filters['warehouse_id']);
        return self::getAll($warehouse_id, $filters)['total'];
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
        $filters = $addition['filters'] ?? [];
        $warehouse_id = $filters['warehouse_id'] ?? null;
        unset($filters['warehouse_id']);
        $filters_extra = (!empty($max_item_id) ? ['max_item_id' => $max_item_id] : []) + ($addition['filters'] ?? []);
        $filters_extra['name'] = isset($filters_extra['name']) ? '%' . $filters_extra['name'] . '%' : null;
        $query = self::formatReportQuery($warehouse_id, $filters_extra, false);

        $query->setGroupBy("b.id, b.supplier_id");
        $query->setOrderBy('b.id DESC');
        $query->setLimit(self::LIMIT_GENERATE_REPORT + 1);

        $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters_extra);
        $next = false;
        if (!empty($raw_result)) {
            $next = self::processStep(self::handleBundleSKUs($raw_result), $csv, $userJob);
        }
        if (!$next || empty($raw_result)) {
            self::transferToCloud($fileJob);
            self::doneUserJob($userJob, $fileJob);
        }

        return ['success' => 'Handled part of Bundle report CSV for user job #' . $user_job_id];
    }

    static function handleDataToCSV(array $raw_result, FormatterCSV $csv, $addition = null)
    {
        foreach ($raw_result as $row) {
            $data = [
                'Bundle ID' => $row['id'],
                'Bundle Name/SKU' => $row['name'],
                'SKUs' => $row['sku_list']
            ];
            if (in_array('Client', self::$headline))
                $data['Client'] = $row['supplier_name'] ?? '';
            $csv->addRow($data);
        }
    }

    public static function getAllAsCSV($warehouse_id, array $filters, $user_id)
    {
        $query = self::formatReportQuery($warehouse_id, $filters, false);
        $query->setGroupBy("b.id, b.supplier_id");
        $query->setOrderBy('b.id DESC');
        $query->setLimit('10000');
        $csv = new FormatterCSV;
        $csv->addHeader(self::handleHeadlineCSV($user_id));
        $filters['name'] = isset($filters['name']) ? '%' . $filters['name'] . '%' : null;
        $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        while (!empty($raw_result)) {
            self::handleDataToCSV(self::handleBundleSKUs($raw_result), $csv, ['warehouse_id' => $warehouse_id, 'user_id' => $user_id]);
            $last_bundle_id = end($raw_result)['id'];
            $query->addWhere('b.id < ' . $last_bundle_id);
            $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        }
    }

    public static function getBySKU($SKU_id): array
    {
        $sql = 'SELECT b.* FROM bundles b JOIN bundles_SKUs bs ON b.id = bs.bundle_id AND bs.SKU_id = :SKU_id';
        return (new SafeMysql())->fetchAll($sql,
            [
               'SKU_id' => (int)$SKU_id
            ]
        );
    }
}
