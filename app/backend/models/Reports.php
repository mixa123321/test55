<?php

namespace app\backend\models;

use app\core\helpers\FileWrapper;
use app\core\models\Warehouses;
use app\core\dbal\SafeMysql;
use app\core\db\QueryBuilder;
use app\core\entities\Location;
use app\core\entities\Order;
use app\core\entities\Warehouse;
use app\core\statical\Timezones;
use app\core\statical\WithDate;
use app\core\Storage;
use app\core\helpers\TimeZonesConvertor;
use DateInterval;
use DatePeriod;
use DateTime;

class Reports extends \app\core\models\Reports
{
    private static $mode = null;
    private static $file = null;
    private static $cache = [
        'users' => [],
        'locations' => [],
        'orders' => null, // not populated
        'ASNs' => null, // not populated
    ];

    private const UNIT_PICKED = 1;
    private const UNIT_PACKED = 2;
    private const ORDERS_PACKED = 3;

    /** Value of the "All" option (no client filter) in the Locations report client dropdown */
    const LOCATIONS_REPORT_ALL = 'all';

    const REPORTS = [
        self::UNIT_PICKED => 'Units picked',
        self::UNIT_PACKED => 'Units packed',
        self::ORDERS_PACKED => 'Orders packed',
    ];

    const REPORT_DEFAULT = self::ORDERS_PACKED;

    const INTERVALS = [
//        30 => '30 minutes',
//        60 => '1 hour',
//        120 => '2 hours',
//        180 => '3 hours',
        360 => '6 hours',
        720 => '12 hours',
        1440 => '24 hours'
    ];

    private const LIMIT_DAYS = 31;
    private const CHUNK = 10000;

    public const PATH = 'reports';

    public const SKU_EVENTS = [
        SKUHistories::EVENT_SET_PARAMS,
        SKUHistories::EVENT_ASN,
        SKUHistories::EVENT_PUTAWAY,
        SKUHistories::EVENT_MOVEMENT,
        SKUHistories::EVENT_NON_COMPLIANT,
        SKUHistories::EVENT_NO_VERIFY,
        SKUHistories::EVENT_PACKING,
        SKUHistories::EVENT_DROPPING,
        SKUHistories::EVENT_DROPPING_T,
        SKUHistories::EVENT_PICKING,
        SKUHistories::EVENT_PICKING . 'L',  
        SKUHistories::EVENT_PICKING . 'S',
        SKUHistories::EVENT_PICKING . 'M',
        SKUHistories::EVENT_CLEAR_DH,
        SKUHistories::EVENT_EXPIRED_SETTINGS,
        SKUHistories::EVENT_EXPIRED_ORDER,
        SKUHistories::EVENT_DESTROY_EXPIRED,
        SKUHistories::EVENT_UPDATE_EXPIRED,
        SKUHistories::EVENT_SET_MAPPING,
        SKUHistories::EVENT_UPDATE_MEASURE,
        SKUHistories::EVENT_UPDATE_LOCATIONS,
        SKUHistories::EVENT_CANCEL_ORDER,
    ];

    public const SKU_EVENT_CATEGORIES = [
        'Stock Management' => [
            SKUHistories::EVENT_ASN,
            SKUHistories::EVENT_PUTAWAY,
            SKUHistories::EVENT_MOVEMENT,
            SKUHistories::EVENT_NON_COMPLIANT,
            SKUHistories::EVENT_NO_VERIFY,
            SKUHistories::EVENT_CLEAR_DH,
            SKUHistories::EVENT_DESTROY_EXPIRED,
        ],
        'Order Processing' => [
            SKUHistories::EVENT_PICKING . 'L',
            SKUHistories::EVENT_PICKING . 'S',
            SKUHistories::EVENT_PICKING . 'M',
            SKUHistories::EVENT_DROPPING,
            SKUHistories::EVENT_DROPPING_T,
            SKUHistories::EVENT_PACKING,
            SKUHistories::EVENT_CANCEL_ORDER,
            SKUHistories::EVENT_EXPIRED_ORDER,
        ],
        'Configuration' => [
            SKUHistories::EVENT_SET_PARAMS,
            SKUHistories::EVENT_SET_MAPPING,
            SKUHistories::EVENT_UPDATE_MEASURE,
            SKUHistories::EVENT_UPDATE_LOCATIONS,
            SKUHistories::EVENT_UPDATE_EXPIRED,
            SKUHistories::EVENT_EXPIRED_SETTINGS,
        ],
    ];

    /**
     * @param int $report_by
     * @return false|int
     */
    public static function setReportMode(int $report_by)
    {
        self::$mode = $report_by;
        return array_key_exists($report_by, self::REPORTS) ? $report_by : false;
    }

    /**
     * @param $warehouse_id
     * @param $filters
     * @param $suppliers
     * @return array
     */
    public static function validate($warehouse_id, $filters, $suppliers): array
    {
        $errors = [];
        // validate DateInterval
        try {
            $earlier = new DateTime($filters['date_from']);
            $later = new DateTime($filters['date_to']);
            $diff = intval($later->diff($earlier)->format("%a"));
            if ($diff > self::LIMIT_DAYS)
                $errors[] = 'Date difference more than ' . self::LIMIT_DAYS . ' days';
        } catch (\Exception $e) {
            $errors[] = 'DateTime error';
        }
        if (empty($filters['interval']) || !array_key_exists((int)$filters['interval'], self::INTERVALS)) {
            $errors[] = 'Interval error';
        }

        $supplier_id = intval($filters['supplier_id'] ?? null);
        // validate SKU by id
        $SKU_by_id = null;
        if (!empty($filters['SKU_id'])) {
            $SKU_by_id = SKUs::getById((int)$filters['SKU_id']);
            if (empty($SKU_by_id) || !array_key_exists($SKU_by_id->getSupplierId(), $suppliers))
                $errors[] = 'SKU by id not found';
            if(!empty($SKU_by_id) && $supplier_id) {
                if ((int)$SKU_by_id->getSupplierId() !== $supplier_id)
                    $errors[] = 'SKU by id not found on supplier';
            }
        }

        // validate SKU by barcode
        $supplier_by_barcode = [];
        if (!empty($filters['SKU_barcode'])) {
            $supplier_by_barcode = SKUs::getSuppliersByBarcode($filters['SKU_barcode'], $warehouse_id);
            if (empty($supplier_by_barcode))
                $errors[] = 'SKU by barcode not found';
            else {
                $filtered_suppliers = array_filter($supplier_by_barcode, function($s) use ($supplier_id, $suppliers) {
                    if ($supplier_id)
                        return (int)$s['id'] === $supplier_id;
                    else
                        return array_key_exists($s['id'], $suppliers);
                });
                if (empty($filtered_suppliers))
                    $errors[] = 'SKU by barcode not found on supplier' . (!$supplier_id ? 's' : '');
            }
        }

        // validate SKU by id and barcode
        if (!empty($SKU_by_id) && !empty($supplier_by_barcode)) {
            $SKUs_by_barcode = SKUs::getByBarcodes([$filters['SKU_barcode']], $supplier_id);
            $SKU_by_barcode = $SKUs_by_barcode[$filters['SKU_barcode']] ?? [];
            if (intval($SKU_by_barcode['id'] ?? null) !== $SKU_by_id->getId())
                $errors[] = 'SKU error';
        }

        // check if SKU with same barcode in few suppliers
        if (!empty($filters['SKU_barcode']) && empty($filters['SKU_id']) && empty($filters['supplier_id'])) {
            if (count($supplier_by_barcode) > 1)
                $errors[] = 'Found several suppliers with this barcode. Please select supplier!';
        }
        return $errors;
    }

    /**
     * @param Warehouse $warehouse
     * @param array $filters
     * @param $user_id
     * @param $suppliers
     * @return array
     */
    private static function getReport(Warehouse $warehouse, array $filters, $user_id, $suppliers): array
    {
        switch (self::$mode) {
            case self::UNIT_PICKED:
                return self::getUnitPickedReport($warehouse, $filters, $user_id, $suppliers);
            case self::UNIT_PACKED:
                return self::getUnitPackedReport($warehouse, $filters, $user_id, $suppliers);
            case self::ORDERS_PACKED:
                return self::getOrdersPackedReport($warehouse, $filters, $user_id, $suppliers);
            default:
                self::$mode = null;
                return [];
        }
    }

    /**
     * @param $warehouse
     * @param array $filters
     * @param $user_id
     * @param $suppliers
     * @return array
     */
    public static function getReportChartData($warehouse, array $filters, $user_id, $suppliers): array
    {
        $role_annotations = new \stdClass();
        $role_annotations->role = 'annotation';
        $chart_data = [['name', 'total', $role_annotations]];
        $total = 0;
        foreach (self::getReport($warehouse, $filters, $user_id, $suppliers) as $interval => $count) {
            $chart_data[] = [str_replace('|', "\n", $interval), (int)$count, $count];
            $total += $count;
        }
        if (count($chart_data) > 1) {
            $filenameArr = explode(DIRECTORY_SEPARATOR, self::$file);
            $filename = array_pop($filenameArr);
        }
        return [$chart_data, $total, $filename ?? ''];
    }

    /** @param $user_id */
    public static function clearFiles($user_id)
    {
        $path = CSV_STORAGE . $user_id . DIRECTORY_SEPARATOR . self::PATH;
        if (is_dir($path)) {
            $files = scandir($path);
            if (!empty($files))
                foreach ($files as $file)
                    if (strlen($file) > 2)
                        unlink($path . DIRECTORY_SEPARATOR . $file);
        }
    }

    /**
     * @param $user_id
     * @return resource
     */
    private static function handleReportCSVFile($user_id)
    {
        $path = $user_id . DIRECTORY_SEPARATOR . self::PATH;
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath(CSV_STORAGE)))->handleDir(CSV_STORAGE . $user_id);
        $file_wrapper->handleDir(CSV_STORAGE . $path);
        $report = str_replace(' ', '_', self::REPORTS[self::$mode]);
        $filename = time() . '_' . $report .  '.csv';
        self::$file = CSV_STORAGE . $path . DIRECTORY_SEPARATOR . $filename;
        return tmpfile();
    }

    /**
     * @param Warehouse $warehouse
     * @param $filters
     * @param $user_id
     * @param $suppliers
     * @return array
     */
    public static function getUnitPickedReport(Warehouse $warehouse, $filters, $user_id, $suppliers): array
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("SELECT        table_history.id as id, table_history.additional_info,
                                                   DATE_FORMAT(table_history.event_date, '%Y-%m-%d %H:%i:%s') as timestamp
                                            FROM SKU_history table_history");
        $query->addJoin("LEFT JOIN SKUs ON SKUs.id = table_history.SKU_id");
        $query->addJoin("LEFT JOIN suppliers ON suppliers.id = SKUs.supplier_id");
        list($query, $timestamps) = self::handleFilterParams($query, $warehouse, $filters, $suppliers);
        $query->setOrderBy('table_history.event_date');
        $query->setLimit(self::CHUNK);
        $raw_result = (new SafeMysql())->fetchAll($query->build());
        $worker_id = !empty($filters['worker_id']) ? (int)$filters['worker_id'] : null;
        $time_diff = Timezones::getDiff($warehouse->getTimezone());
        while (!empty($raw_result)) {
            foreach ($raw_result as $record) {
                $additional_info = json_decode($record['additional_info'], 1);
                if (!empty($worker_id) && $worker_id !== intval($additional_info['User'] ?? 0))
                    continue;
                $units = !empty($additional_info['Units']) ? intval($additional_info['Units']) : 1;
                $closestDate = WithDate::getClosestDate(array_keys($timestamps), empty($time_diff) ? $record['timestamp'] : Timezones::getDiff($warehouse->getTimezone(), $record['timestamp']));
                if (isset($timestamps[$closestDate])) {
                    $timestamps[$closestDate] += $units;
                }
            }
            $last_id = end($raw_result)['id'];
            $query->addWhere('table_history.id > ' . $last_id);
            $raw_result = (new SafeMysql())->fetchAll($query->build());
        }
        return self::handleTimestampIntervals($timestamps, $user_id, !empty($filters['empty']));
    }

    /**
     * @param Warehouse $warehouse
     * @param $filters
     * @param $user_id
     * @param $suppliers
     * @return array
     */
    private static function getUnitPackedReport(Warehouse $warehouse, $filters, $user_id, $suppliers): array
    {
        list($query, $timestamps, $SKU_id) = self::handlePackedReport($warehouse, $filters, $suppliers);
        $raw_result = (new SafeMysql())->fetchAll($query->build());
        $time_diff = Timezones::getDiff($warehouse->getTimezone());
        while (!empty($raw_result)) {
            $original_items = self::getOriginalItemList($raw_result);
            array_walk($raw_result, function ($record) use (&$timestamps, $warehouse, $SKU_id, $original_items, $time_diff) {
                $units = 0;
                $order_SKUs = $original_items[$record['order_id']] ?? [];
                if (empty($order_SKUs))
                    $order_SKUs = OrderSKUs::getSKUs($record['order_id']);
                if (!empty($order_SKUs)) {
                    array_walk($order_SKUs, function ($item) use ($SKU_id, &$units) {
                        $item['quantity'] = (int)$item['quantity'];
                        if (!is_null($SKU_id) && (int)$SKU_id !== (int)$item['SKU_id']) {
                            $item['quantity'] = 0;
                        }
                        $units += $item['quantity'] ?? 0;
                    });
                }
                $closestDate = WithDate::getClosestDate(array_keys($timestamps), empty($time_diff) ? $record['timestamp'] : Timezones::getDiff($warehouse->getTimezone(), $record['timestamp']));
                if (isset($timestamps[$closestDate])) {
                    $timestamps[$closestDate] += $units;
                }
            });
            $last_id = end($raw_result)['order_id'];
            $query->addWhere('table_history.id > ' . $last_id);
            $raw_result = (new SafeMysql())->fetchAll($query->build());
        }
        return self::handleTimestampIntervals($timestamps, $user_id, !empty($filters['empty']));
    }

    /**
     * @param Warehouse $warehouse
     * @param $filters
     * @param $user_id
     * @param $suppliers
     * @return array
     */
    private static function getOrdersPackedReport(Warehouse $warehouse, $filters, $user_id, $suppliers): array
    {
        list($query, $timestamps, $SKU_id) = self::handlePackedReport($warehouse, $filters, $suppliers);
        $all_SKUs = is_null($SKU_id);
        $raw_result = (new SafeMysql())->fetchAll($query->build());
        $time_diff = Timezones::getDiff($warehouse->getTimezone());
        while (!empty($raw_result)) {
            $original_items = [];
            if (!$all_SKUs)
                $original_items = self::getOriginalItemList($raw_result);
            array_walk($raw_result, function ($record) use ($warehouse, $SKU_id, &$timestamps, $all_SKUs, $original_items, $time_diff) {
                $order_SKUs = [];
                if (!$all_SKUs) {
                    $all_order_SKUs = $original_items[$record['order_id']] ?? [];
                    if (empty($all_order_SKUs))
                        $all_order_SKUs = OrderSKUs::getSKUs($record['order_id']);
                    $order_SKUs = array_filter($all_order_SKUs, function($item) use ($SKU_id) {
                        return (int)$item['SKU_id'] === (int)$SKU_id;
                    });
                }
                if ($all_SKUs || !empty($order_SKUs)) {
                    $closestDate = WithDate::getClosestDate(array_keys($timestamps), empty($time_diff) ? $record['timestamp'] : Timezones::getDiff($warehouse->getTimezone(), $record['timestamp']));
                    if (isset($timestamps[$closestDate]))
                        $timestamps[$closestDate] += 1;
                }
            });
            $last_id = end($raw_result)['order_id'];
            $query->addWhere('table_history.id > ' . $last_id);
            $raw_result = (new SafeMysql())->fetchAll($query->build());
        }
        return self::handleTimestampIntervals($timestamps, $user_id, !empty($filters['empty']));
    }

    /**
     * @param Warehouse $warehouse
     * @param $filters
     * @param $suppliers
     * @return array
     */
    private static function handlePackedReport(Warehouse $warehouse, $filters, $suppliers): array
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("SELECT        table_history.id as order_id,
                                                   DATE_FORMAT(table_history.despatched_date, '%Y-%m-%d %H:%i:%s') as timestamp
                                            FROM orders table_history");
        $worker_id = !empty($filters['worker_id']) ? (int)$filters['worker_id'] : null;
        if (!empty($worker_id)) {
            $comment = 'Status: ' . Order::DESPATCHED;
            $query->addJoin("JOIN order_history oh ON table_history.id = oh.order_id
                                        AND oh.user_id = {$worker_id} AND oh.comment = '{$comment}'");
        }
        $query->setGroupBy('table_history.id');
        $query->setOrderBy('table_history.id');
        $query->setLimit(self::CHUNK);
        list($query, $timestamps, $SKU_id) = self::handleFilterParams($query, $warehouse, $filters, $suppliers);
        return [$query, $timestamps, $SKU_id];
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param Warehouse $warehouse
     * @param array $filters
     * @param array $suppliers
     * @return array
     */
    private static function handleFilterParams(QueryBuilder $queryBuilder, Warehouse $warehouse, array $filters, array $suppliers): array
    {
        $interval = (int)$filters['interval'];
        $SKU_supplier_id = null;
        if (!empty($filters['SKU_id'])) {
            $SKU = SKUs::getById($filters['SKU_id']);
            if (!empty($SKU))
                $SKU_supplier_id = $SKU->getSupplierId();
        }
        $barcode_supplier_id = null;
        if (!empty($filters['SKU_barcode'])) {
            $barcode_supplier_id = null;
            $barcode_suppliers = SKUs::getSuppliersByBarcode($filters['SKU_barcode'], $warehouse->getId());
            if (!empty($barcode_suppliers)) {
                if (count($barcode_suppliers) > 1 && !empty($filters['supplier_id'])) {
                    $s_id = $filters['supplier_id'];
                    $filter_supplier = array_filter($barcode_suppliers, function($s) use ($s_id) {
                       return $s_id == $s['id'];
                    });
                    if (!empty($filter_supplier))
                        $barcode_supplier_id = intval(array_values($filter_supplier)[0]['id']);
                } else
                    $barcode_supplier_id = intval(array_values($barcode_suppliers)[0]['id']);
                $SKU = SKUs::getByBarcodeAndSupplier($filters['SKU_barcode'], $barcode_supplier_id);
            }
        }
        if (empty($filters['supplier_id']) && !$SKU_supplier_id && !$barcode_supplier_id)
            $supplier_ids = array_keys($suppliers);
        elseif ($SKU_supplier_id || $barcode_supplier_id)
            $supplier_ids = empty($filters['supplier_id']) ? ($SKU_supplier_id ?: $barcode_supplier_id) : (int)$filters['supplier_id'];
        else
            $supplier_ids = (int)($filters['supplier_id'] ?? 0);
        $date_from = date(WithDate::DATE_SQL_FORMAT,
            strtotime(!empty($filters['date_from']) ? ($filters['date_from'] . ' 00:00:00') : "-1 week"));
        $date_to = date(WithDate::DATE_SQL_FORMAT, !empty($filters['date_to']) ? strtotime($filters['date_to'] . ' 23:59:59') : time());
        $diff_time = Timezones::getDiff($warehouse->getTimezone());
        if (!empty($diff_time)) {
            $date_from = Timezones::getDiff($warehouse->getTimezone(), $date_from, TRUE);
            if (!empty($filters['date_to']))
                $date_to = Timezones::getDiff($warehouse->getTimezone(), $date_to, TRUE);
        }
        $params = [
            'empty' => !empty($filters['empty']),
            'interval' => $interval,
            'date_from' => $date_from,
            'date_to' => $date_to
        ];
        $queryBuilder = self::applyWhereQuery($queryBuilder, $warehouse->getId(), $filters, $supplier_ids, $params);
        try {
            $timestamps = self::handleTimestamps($params);
            if (!empty($diff_time)) {
                $timestamps_keys = array_map(function ($t) use ($warehouse)  {
                    return Timezones::getDiff($warehouse->getTimezone(), $t);
                }, array_keys($timestamps));
                $timestamps = array_combine($timestamps_keys, $timestamps);
            }
        } catch (\Exception $e) {
            $timestamps = [];
        }
        return [$queryBuilder, $timestamps, !empty($SKU) ? $SKU->getId() : null];
    }

    /**
     * @param $params
     * @return array
     * @throws \Exception
     */
    private static function handleTimestamps($params): array
    {
        $format = WithDate::DATE_SQL_FORMAT;
        $startDate = DateTime::createFromFormat($format, $params['date_from']);
        $endDate = DateTime::createFromFormat($format, $params['date_to']);
        $interval = new DateInterval("PT".$params['interval']."M");
        $dateRange = new DatePeriod($startDate, $interval, $endDate);
        $resTimestamp = [];
        $now_timestamp = date($format);
        foreach ($dateRange as $date) {
            $first_date = DateTime::createFromFormat($format,
                $date->format($format))->add(new DateInterval("PT0M"))->format($format);
            if (strtotime($first_date) > strtotime($now_timestamp))
                break;
            $resTimestamp[$first_date] = 0;
            $second_date = DateTime::createFromFormat($format,
                $date->format($format))->add($interval)->sub(new DateInterval("PT1M"))->format($format);
            if (strtotime($second_date) > strtotime($now_timestamp))
                $second_date = $now_timestamp;
            $resTimestamp[$second_date] = 0;
        };
        return $resTimestamp;
    }

    /**
     * @param  QueryBuilder  $queryBuilder
     * @param $warehouse_id
     * @param  array  $filters
     * @param  mixed  $supplier_ids
     * @param  array  $params
     * @return QueryBuilder
     */
    private static function applyWhereQuery(QueryBuilder $queryBuilder, $warehouse_id, array $filters, $supplier_ids, array $params): QueryBuilder
    {
        $queryBuilder->addWhere("table_history.warehouse_id = " . $warehouse_id);
        switch (self::$mode) {
            case self::UNIT_PICKED:
                $queryBuilder->addWhere("table_history.event = 'Dropping'");
                if (!empty($filters['SKU_id']))
                    $queryBuilder->addWhere("SKUs.id = " . (int)$filters['SKU_id']);
                if (!empty($filters['SKU_barcode'])) {
                    $SKU_ids = SKUBarcodes::getSKUIdsByBarcode(trim($filters['SKU_barcode']));
                    $queryBuilder->addWhere($SKU_ids ? "SKUs.id IN (" . implode(', ', $SKU_ids) . ")" : "FALSE");
                }
                if (!empty($supplier_ids))
                    $queryBuilder->addWhere(is_array($supplier_ids) ? ("suppliers.id in (" . implode(', ', $supplier_ids) . ")") : ("suppliers.id = {$supplier_ids}"));
                $queryBuilder->addWhere("DATE_FORMAT(table_history.event_date, '%Y-%m-%d %H:%i:%s') BETWEEN '" . $params['date_from'] . "' AND '" . $params['date_to'] . "'");
                break;
            case self::UNIT_PACKED:
            case self::ORDERS_PACKED:
                $queryBuilder->addWhere("table_history.status = " . Order::DESPATCHED_NUM);
                if (!empty($supplier_ids))
                    $queryBuilder->addWhere(is_array($supplier_ids) ? ("table_history.supplier_id in (" . implode(', ', $supplier_ids) . ")") : ("table_history.supplier_id = {$supplier_ids}"));
                $queryBuilder->addWhere("DATE_FORMAT(table_history.despatched_date, '%Y-%m-%d %H:%i:%s') BETWEEN '" . $params['date_from'] . "' AND '" . $params['date_to'] . "'");
                break;
        }
        return $queryBuilder;
    }

    /**
     * @param $timestamps
     * @param $user_id
     * @param bool $empty
     * @return array
     */
    private static function handleTimestampIntervals($timestamps, $user_id, bool $empty): array
    {
        $resIntervals = [];
        $check_empty = false;
        $output = self::handleReportCSVFile($user_id);
        fputcsv($output, ['Date', 'Interval', 'Count'], ',');
        flush();
        foreach ($timestamps as $start_timestamp => $start_units) {
            if (array_key_exists($start_timestamp, $timestamps)) {
                $end_units = next($timestamps);
                $end_timestamp = key($timestamps);
                $total = $start_units + $end_units;
                if ($total > 0)
                    $check_empty = true;
                if ($empty || $total > 0) {
                    $resIntervals[date('Y-m-d H:i', strtotime($start_timestamp)) . date('-H:i', strtotime($end_timestamp))] = $total;
                    fputcsv($output, [
                        'Date' => date('Y-m-d', strtotime($start_timestamp)),
                        'Interval' => date('H:i', strtotime($start_timestamp)) . date('-H:i', strtotime($end_timestamp)),
                        'Total' => $total
                    ], ',');
                }
                unset($timestamps[$end_timestamp]);
            }
        }
        if (!$check_empty)
            return [];
        (new FileWrapper(FileWrapper::getContainerByPath(self::$file)))->writeFileResource(self::$file, $output);
        return $resIntervals;
    }

    /**
     * @param $records
     * @return array
     */
    private static function getOriginalItemList($records): array
    {
        $order_ids = array_map(function ($record) {
            return (int)$record['order_id'];
        }, $records);
        return OrdersOriginalDespatched::getItemsByOrderIds($order_ids);
    }

    public static function LocationsHeldVirtual($warehouse_id, $supplier_id, $name, bool $empty = false)
    {
        if ($empty)
            [$sql, $params] = self::getEmptyLocationsQuery($warehouse_id, $supplier_id);
        else {
            $params = ['warehouse_id' => (int)$warehouse_id];
            $whereSupplier = '';
            if ($supplier_id !== self::LOCATIONS_REPORT_ALL) {
                $whereSupplier = 'WHERE s.supplier_id = :supplier_id';
                $params['supplier_id'] = (int)$supplier_id;
            }
            $sql = "SELECT s.id, l.row, IFNULL(SUM(quantity), 0) quantity
                    FROM SKUs s
                        LEFT JOIN SKU_locations sl ON s.id = sl.SKU_id
                        JOIN locations l ON sl.location_id = l.id AND l.warehouse = :warehouse_id
                    $whereSupplier
                    GROUP BY s.id, l.id";
        }
        $SKUs = (new SafeMysql())->fetchAll($sql, $params);
        SKUBarcodes::addBarcodesToArray($SKUs);
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath(REPORT_STORAGE)))->handleDir(REPORT_STORAGE);
        $filename = "report{$name}_{$supplier_id}_$warehouse_id" . ($empty ? '_empty' : '') . ".csv";
        $output = tmpfile();
        fputcsv($output, ['SKU Barcode', 'Subwarehouse it is held', 'Quantity in that location'], ',');
        foreach ($SKUs as $SKU)
            fputcsv($output, [$SKU['barcode'], $SKU['row'], $SKU['quantity']], ',');
        $file_wrapper->writeFileResource(REPORT_STORAGE . $filename, $output);
        return $filename;
    }

    public static function LocationsHeld($warehouse_id, $supplier_id, $name, bool $empty = false)
    {
        if (Warehouses::isVirtual($warehouse_id))
            return self::LocationsHeldVirtual($warehouse_id, $supplier_id, $name, $empty);
        if ($empty)
            [$sql, $params] = self::getEmptyLocationsQuery($warehouse_id, $supplier_id);
        else {
            $params = [
                'warehouse_id' => (int)$warehouse_id,
                'not_counted_types' => Location::NOT_COUNTED_TYPES,
            ];
            $andSupplier = '';
            if ($supplier_id !== self::LOCATIONS_REPORT_ALL) {
                $andSupplier = 'AND s.supplier_id = :supplier_id';
                $params['supplier_id'] = (int)$supplier_id;
            }
            // same exclusion as Locations::getExcludeLocationsPartialQuery()
            $andNotCancel = self::andNotCancelLocation($warehouse_id, $params);
            $sql = "SELECT s.id,
                        CONCAT(IF(w.label > '', w.label, w.id),'-',l.type_id,'-',l.zone,'-',l.`column`,'-',l.row) loc_name, IFNULL(SUM(sl.quantity), 0) as quantity
                    FROM SKU_preff_locs spl
                        JOIN SKUs s ON s.id = spl.SKU_id $andSupplier
                        JOIN locations l ON spl.location_id = l.id AND l.warehouse = :warehouse_id
                            AND l.type_id NOT IN (:not_counted_types) $andNotCancel
                        JOIN warehouses w ON l.warehouse = w.id
                        LEFT JOIN SKU_locations sl ON spl.SKU_id = sl.SKU_id AND spl.location_id = sl.location_id
                    GROUP BY s.id, l.id";
        }
        $SKUs = (new SafeMysql())->fetchAll($sql, $params);
        SKUBarcodes::addBarcodesToArray($SKUs);
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath(REPORT_STORAGE)))->handleDir(REPORT_STORAGE);
        $filename = "report{$name}_{$supplier_id}_$warehouse_id" . ($empty ? '_empty' : '') . ".csv";
        $output = tmpfile();
        fputcsv($output, ['SKU Barcode', 'Location it is held', 'Quantity in that location'], ',');
        foreach ($SKUs as $SKU)
            fputcsv($output, [$SKU['barcode'], $SKU['loc_name'], $SKU['quantity']], ',');
        $file_wrapper->writeFileResource(REPORT_STORAGE . $filename, $output);
        return $filename;
    }

    /**
     * Storage/Pick Face locations with no quantity of anything in them, in the LocationsHeld row format
     * (SKU is NULL). For a specific supplier, only locations assigned to that supplier's SKUs.
     *
     * @return array{0: string, 1: array<string, mixed>} SQL and its SafeMysql params
     */
    private static function getEmptyLocationsQuery($warehouse_id, $supplier_id): array
    {
        $params = [
            'warehouse_id' => (int)$warehouse_id,
            'storage_types' => Location::STORAGE_TYPES,
        ];
        // same exclusion as Locations::getExcludeLocationsPartialQuery()
        $andNotCancel = self::andNotCancelLocation($warehouse_id, $params);
        $andSupplier = '';
        if ($supplier_id !== self::LOCATIONS_REPORT_ALL) {
            $andSupplier = 'AND EXISTS (
                    SELECT 1 FROM SKU_preff_locs spl JOIN SKUs s ON s.id = spl.SKU_id
                    WHERE spl.location_id = l.id AND s.supplier_id = :supplier_id
                )';
            $params['supplier_id'] = (int)$supplier_id;
        }
        $sql = "SELECT NULL id, l.row,
                    CONCAT(IF(w.label > '', w.label, w.id),'-',l.type_id,'-',l.zone,'-',l.`column`,'-',l.row) loc_name, IFNULL(SUM(sl.quantity), 0) as quantity
                FROM locations l
                    JOIN warehouses w ON l.warehouse = w.id
                    LEFT JOIN SKU_locations sl ON sl.location_id = l.id
                WHERE l.warehouse = :warehouse_id AND l.type_id IN (:storage_types) $andNotCancel $andSupplier
                GROUP BY l.id
                HAVING quantity = 0";
        return [$sql, $params];
    }

    /**
     * Order Cancel location exclusion for alias `l`, as in Locations::getExcludeLocationsPartialQuery().
     * Adds :cancel_location_id to $params when the condition is used.
     */
    private static function andNotCancelLocation($warehouse_id, array &$params): string
    {
        $order_cancel = Locations::getByCancelLocation($warehouse_id);
        if (count($order_cancel) != 1)
            return '';
        $params['cancel_location_id'] = (int)reset($order_cancel)->getId();
        return 'AND l.id <> :cancel_location_id';
    }

    public static function CollectionTimeDaily(Warehouse $warehouse, $date_for)
    {
        [$head, $data] = ShippingServices::getReportDaily($warehouse, $date_for);
        if (empty($head))
            return false;
        $filename = "report_CT_{$warehouse->getId()}_$date_for.csv";
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath(REPORT_STORAGE)))->handleDir(REPORT_STORAGE);
        $output = tmpfile();
        fputcsv($output, $head, ',');
        foreach ($data as $service)
            fputcsv($output, $service, ',');
        $file_wrapper->writeFileResource(REPORT_STORAGE . $filename, $output);
        return $filename;
    }

    public static function CollectionTimeInterval(Warehouse $warehouse, $date_from, $date_to, $supplier_id)
    {
        [$head, $data] = ShippingServices::getReportInterval($warehouse, $date_from, $date_to, $supplier_id);
        if (empty($head))
            return false;
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath(REPORT_STORAGE)))->handleDir(REPORT_STORAGE);
        $filename = "report_CT_{$warehouse->getId()}_" . ($supplier_id ? "{$supplier_id}_" : '') . "{$date_from}_{$date_to}.csv";
        $output = tmpfile();
        fputcsv($output, $head, ',');
        foreach ($data as $service)
            fputcsv($output, $service, ',');
        $file_wrapper->writeFileResource(REPORT_STORAGE . $filename, $output);
        return $filename;
    }

    public static function saveCSV($storage, $filename, $path = '')
    {
        if ($filename === false)
            return false;
        $file_path = Storage::getFile($storage, $path . $filename);
        if (empty($file_path))
            return false;
        header('Content-Type: application/excel; charset=UTF-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        echo stream_get_contents((new FileWrapper(FileWrapper::getContainerByPath($file_path)))->getStream($file_path));
        return true;
    }

    public static function generateSKUHistoryReport(int $sku_id, Warehouse $warehouse, array $events = null)
    {
        $sku = SKUs::getById($sku_id);
        if (empty($sku))
            return ['error' => 'SKU not found'];

        $eventDisplayNames = SKUHistories::getEventDisplayNames();

        $events = array_intersect(self::SKU_EVENTS, $events);
        $eventDisplayNames = array_intersect_key($eventDisplayNames, array_flip($events));

        if (empty($eventDisplayNames))
            return ['error' => 'No valid events selected'];

        $offset = 0;
        $limit = 10000;
        $history = SKUHistories::getBySKUIdAndEvents($sku_id, array_keys($eventDisplayNames), $warehouse, $offset, $limit);
        if (empty($history))
            return ['error' => 'No history found'];
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath(REPORT_STORAGE)))->handleDir(REPORT_STORAGE);
        $filename = 'SKU_history_' . $sku_id . '_' . date('Y-m-d_H-i-s') . '.csv';
        $file = tmpfile();
        fputcsv($file, ['Date', 'Type', 'Order', 'No of Units', 'Origin Location', 'Destination Location', 'Details']);
        while (!empty($history)) {
            foreach ($history as $record) {
                $event_date = (new TimeZonesConvertor)
                    ->setDateByString($record['event_date'])
                    ->convert($warehouse->getTimezone())
                    ->getConvertedDateString();

                $event_display = isset($eventDisplayNames[$record['event']])
                    ? $eventDisplayNames[$record['event']]
                    : $record['event'];

                $details = SKUHistories::prepareAdditionalInfo(
                    $record,
                    self::$cache['locations'],
                    self::$cache['users'],
                    self::$cache['orders'],
                    self::$cache['ASNs'],
                    true,
                    $warehouse->getLabel()
                );
                $order = $details['Order'] ?? '';
                $units = $details['Units'] ?? '';
                $origin_location = $details['From'] ?? '';
                $destination = $details['To'] ?? '';

                unset($details['Order'], $details['Units'], $details['From'], $details['To']);

                fputcsv($file, [
                    $event_date,
                    $event_display,
                    $order,
                    $units,
                    $origin_location,
                    $destination,
                    empty($details) ? ($record['adjustment'] ?? '') : json_encode($details)
                ]);
            }
            $offset += $limit;
            $history = SKUHistories::getBySKUIdAndEvents($sku_id, array_keys($eventDisplayNames), $warehouse, $offset, $limit);
        }

        $file_wrapper->writeFileResource(REPORT_STORAGE . $filename, $file);
        return ['filename' => $filename, 'error' => null];
    }
}
