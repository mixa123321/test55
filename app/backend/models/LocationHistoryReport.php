<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\FileJob;
use app\core\entities\Location;
use app\core\entities\UserJob;
use app\core\entities\Warehouse;
use app\core\helpers\formatters\FormatterCSV;
use app\core\helpers\TimeZonesConvertor;
use app\core\interfaces\ReportCSV as InterfaceReportCSV;
use app\core\models\Batches;
use app\core\models\Warehouses;
use app\core\traits\ReportCSV as TraitReportCSV;
use app\core\Queue;

class LocationHistoryReport extends \app\core\models\Reports implements InterfaceReportCSV
{
    use TraitReportCSV;

    private const REPORT_FOLDER_NAME = 'location_history_reports';
    private const REPORT_NAME = 'Location_history';

    private const LOCATION_HISTORY_EVENTS = [
        SKUHistories::EVENT_UPDATE_LOCATIONS,
        SKUHistories::EVENT_HANDOVER,
        SKUHistories::EVENT_PUTAWAY,
        SKUHistories::EVENT_MOVEMENT,
        SKUHistories::EVENT_PICKING . 'S',
        SKUHistories::EVENT_PICKING . 'M',
        SKUHistories::EVENT_PICKING . 'L',
        SKUHistories::EVENT_DROPPING,
        SKUHistories::EVENT_DROPPING_T,
        SKUHistories::EVENT_PACKING,
        SKUHistories::EVENT_NON_COMPLIANT,
        SKUHistories::EVENT_NO_VERIFY,
        SKUHistories::EVENT_COUNTING,
        SKUHistories::EVENT_CLEAR_DH,
    ];

    private const UPDATE_LOCATIONS_LOCATION_TYPES = [
        Location::PF,
        Location::ST,
    ];

    private const LOCATION_SEARCH_EXCLUDED_TYPES = [
        Location::PA,
        Location::DH,
    ];

    private const NON_PICKING_LOCATION_TYPES = [
        Location::PF,
        Location::ST,
    ];

    private const LOCATION_HISTORY_HEADLINE = [
        'Date',
        'Event',
        'Action',
        'SKU ID',
        'SKU Name',
        'SKU Barcode',
        'Quantity',
        'From Location',
        'To Location',
        'User Login',
        'Reference',
        'Reason',
    ];

    /**
     * Returns locations matching the AJAX search term for the selected warehouse.
     */
    public static function getLocationsForSearch(int $warehouse_id, string $term, int $limit = 20): array
    {
        $term = $term === ' ' ? $term : trim($term);
        if ($warehouse_id <= 0 || $term === '' || $limit <= 0)
            return [];
        $name = Locations::getLocationNameSelect('l', 'w');
        $alias = Locations::getAliasSelect('l');
        $sql = "
            SELECT l.id, $name AS name, $alias AS alias
            FROM locations l
                JOIN warehouses w ON l.warehouse = w.id
            WHERE l.warehouse = :warehouse_id
                AND l.type_id NOT IN (:excluded_types)
                AND (
                    $name LIKE :term_like
                    OR $alias LIKE :term_like
                    OR l.id = :term_id
                )
            ORDER BY l.type_id, LPAD(l.zone, 10, ' '), LPAD(l.`column`, 10, '0'), LPAD(l.row, 10, ' ')
            LIMIT :limit
        ";
        $rows = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'excluded_types' => self::LOCATION_SEARCH_EXCLUDED_TYPES,
            'term_like' => '%' . $term . '%',
            'term_id' => ctype_digit($term) ? (int)$term : 0,
            'limit' => $limit,
        ]);
        $locations = [];
        foreach ($rows as $row) {
            $label = $row['name'];
            if ($row['alias'] !== '' && $row['alias'] !== $row['name'])
                $label .= ' (' . $row['alias'] . ')';
            $locations[] = [
                'id' => (int)$row['id'],
                'label' => $label,
                'value' => $row['name'],
            ];
        }
        return $locations;
    }

    /**
     * Returns the total amount of stock and location history records for a location report.
     */
    public static function getTotalCountReportCSV(array $filters): int
    {
        $db = new SafeMysql();
        $sku_count = (int)$db->fetchScalar(
            self::getLocationHistoryBaseSql('count(*)', false),
            self::getLocationHistoryParams($filters)
        );
        $lock_count = (int)$db->fetchScalar(
            self::getLocationLockHistoryBaseSql('count(*)'),
            self::getLocationLockHistoryParams($filters)
        );
        return $sku_count + $lock_count;
    }

    /**
     * Processes one queued CSV generation step and schedules the next step if needed.
     *
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public static function handleReportCSV($user_job_id, $max_item_id = 0): array
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
        $filters = (!empty($max_item_id) ? ['max_item_id' => (int)$max_item_id] : []) + ($addition['filters'] ?? []);
        $sku_result = self::getLocationHistoryRows($filters);
        $next = false;
        if (count($sku_result) > self::LIMIT_GENERATE_REPORT) {
            $next = true;
            array_pop($sku_result);
        }

        $raw_result = self::mergeLocationHistoryRows(
            $sku_result,
            self::getLocationLockHistoryRows($filters, $sku_result, $next)
        );

        if (!empty($raw_result)) {
            self::processCSV($userJob, $raw_result, $csv, $addition['addition'] ?? null);
            $addition = json_decode($userJob->getAddition(), true);
            $userJob->setAddition(json_encode(['processed' => intval($addition['processed'] ?? 0) + count($raw_result)] + (!empty($addition) ? $addition : [])));
            $userJob->setUpdateDate(time());
            $userJob->save(['id', 'addition', 'update_date']);
        }

        if ($next) {
            $max_item_id = $sku_result[count($sku_result) - 1]['item_id'];
            Queue::addJobToHandleReportCSV([
                'user_job_id' => $userJob->getId(),
                'max_item_id' => $max_item_id,
            ], self::class);
        }

        if (!$next) {
            self::transferToCloud($fileJob);
            self::doneUserJob($userJob, $fileJob);
        }
        return ['success' => 'Handled part of Location History report CSV for user job #' . $user_job_id];
    }

    /**
     * Returns the CSV header row for the Location History report.
     */
    public static function handleHeadlineCSV($user_id): array
    {
        return self::LOCATION_HISTORY_HEADLINE;
    }

    /**
     * Returns additional parameters for the CSV header builder.
     */
    public static function getParamsHeadline(UserJob $userJob)
    {
        return null;
    }

    /**
     * Writes a prepared batch of report rows to the CSV file.
     */
    public static function processCSV(UserJob $userJob, $raw_result, FormatterCSV $csv, $addition)
    {
        self::handleDataToCSV($raw_result, $csv, $addition);
    }

    /**
     * Formats raw stock and location history rows and appends them to the CSV.
     */
    public static function handleDataToCSV(array $raw_result, FormatterCSV $csv, $addition = null)
    {
        $warehouse = Warehouses::getById((int)($addition['warehouse_id'] ?? 0));
        $location_id = (int)($addition['location_id'] ?? 0);
        if (!$warehouse || !$location_id)
            return;

        $sku_ids = array_values(array_unique(array_filter(array_map('intval', array_column($raw_result, 'SKU_id')))));
        $sku_info = SKUs::getInfoByIds($sku_ids);
        $event_names = SKUHistories::getEventDisplayNames();
        $locations = [];
        $users = [];
        $batches = [];

        foreach ($raw_result as $record) {
            if (($record['source'] ?? '') === 'location_lock') {
                $csv->addRow(self::formatLocationLockHistoryRow($record, $warehouse, $users));
                continue;
            }
            foreach (self::formatLocationHistoryRows($record, $location_id, $warehouse, $sku_info, $event_names, $locations, $users, $batches) as $row)
                $csv->addRow($row);
        }
    }

    /**
     * Loads one chunk of SKU history records related to the selected location.
     */
    private static function getLocationHistoryRows(array $filters): array
    {
        $params = self::getLocationHistoryParams($filters);
        $params['limit'] = self::LIMIT_GENERATE_REPORT + 1;
        $params['max_item_id'] = (int)($filters['max_item_id'] ?? 0);
        return (new SafeMysql())->fetchAll(
            self::getLocationHistoryBaseSql("'sku_history' AS source, h.id AS item_id, h.id, h.SKU_id, h.event_date, h.event, h.adjustment, h.additional_info", true),
            $params
        );
    }

    /**
     * Builds the base SQL query for SKU history records used by count and chunk reads.
     */
    private static function getLocationHistoryBaseSql(string $select, bool $chunk): string
    {
        return "
            SELECT $select
            FROM SKU_history h
            WHERE h.warehouse_id = :warehouse_id
                AND h.event IN (:events)
                " . ($chunk ? "AND h.id > :max_item_id" : "") . "
                AND (
                    h.additional_info LIKE :from_int
                    OR h.additional_info LIKE :from_string
                    OR h.additional_info LIKE :to_int
                    OR h.additional_info LIKE :to_string
                    OR h.additional_info LIKE :where_int
                    OR h.additional_info LIKE :where_string
                    OR h.additional_info LIKE :inserted
                    OR h.additional_info LIKE :deleted
                    OR h.additional_info LIKE :updated
                )
            ORDER BY h.event_date, h.id
            " . ($chunk ? "LIMIT :limit" : "");
    }

    /**
     * Loads location history records that belong to the current SKU-history time window.
     */
    private static function getLocationLockHistoryRows(array $filters, array $sku_rows, bool $has_next_chunk): array
    {
        $params = self::getLocationLockHistoryParams($filters);
        $previous_date = self::getPreviousSkuEventDate((int)($filters['max_item_id'] ?? 0));
        if ($previous_date !== null)
            $params['previous_date'] = $previous_date;

        $last_date = null;
        foreach ($sku_rows as $row)
            if ($last_date === null || $row['event_date'] > $last_date)
                $last_date = $row['event_date'];
        if ($has_next_chunk && $last_date !== null)
            $params['last_date'] = $last_date;

        $sql = self::getLocationLockHistoryBaseSql(
            "'location_lock' AS source, lh.id AS item_id, lh.id, lh.location_id, lh.user_id, lh.event, lh.addition, lh.created_at AS event_date",
            $previous_date !== null,
            $has_next_chunk && $last_date !== null
        );
        return (new SafeMysql())->fetchAll($sql, $params);
    }

    /**
     * Builds the SQL query for location lock and unlock events.
     */
    private static function getLocationLockHistoryBaseSql(string $select, bool $with_previous_date = false, bool $with_last_date = false): string
    {
        return "
            SELECT $select
            FROM location_history lh
            WHERE lh.location_id = :location_id
                AND lh.event IN (:location_events)
                " . ($with_previous_date ? "AND lh.created_at > :previous_date" : "") . "
                " . ($with_last_date ? "AND lh.created_at <= :last_date" : "") . "
            ORDER BY lh.created_at, lh.id
        ";
    }

    /**
     * Builds SQL parameters for location history queries.
     */
    private static function getLocationLockHistoryParams(array $filters): array
    {
        $location_id = (int)($filters['location_id'] ?? 0);
        return [
            'location_id' => $location_id,
            'location_events' => self::getLocationHistoryLocationEvents($location_id),
        ];
    }

    /**
     * Returns location_history events allowed for the selected location type.
     */
    private static function getLocationHistoryLocationEvents(int $location_id): array
    {
        $events = ['lock'];
        $location = Locations::getById($location_id);
        if ($location && in_array($location->getTypeId(), self::NON_PICKING_LOCATION_TYPES, true))
            $events[] = 'is_non_picking';
        return $events;
    }

    /**
     * Returns the event date of the last processed SKU history record.
     */
    private static function getPreviousSkuEventDate(int $max_item_id): ?string
    {
        if ($max_item_id <= 0)
            return null;
        $date = (new SafeMysql())->fetchScalar(
            "SELECT event_date FROM SKU_history WHERE id = :id",
            ['id' => $max_item_id]
        );
        return $date !== null && $date !== '' ? (string)$date : null;
    }

    /**
     * Merges SKU history and location lock history rows in chronological order.
     */
    private static function mergeLocationHistoryRows(array $sku_rows, array $lock_rows): array
    {
        $rows = array_merge($sku_rows, $lock_rows);
        usort($rows, static function (array $a, array $b): int {
            $date_compare = strcmp((string)$a['event_date'], (string)$b['event_date']);
            if ($date_compare !== 0)
                return $date_compare;
            $source_compare = strcmp((string)$a['source'], (string)$b['source']);
            if ($source_compare !== 0)
                return $source_compare;
            return (int)$a['id'] <=> (int)$b['id'];
        });
        return $rows;
    }

    /**
     * Builds SQL parameters for SKU history queries.
     */
    private static function getLocationHistoryParams(array $filters): array
    {
        $location_id = (int)($filters['location_id'] ?? 0);
        return [
            'warehouse_id' => (int)($filters['warehouse_id'] ?? 0),
            'events' => self::getLocationHistoryEvents($location_id),
            'from_int' => '%"From":' . $location_id . '%',
            'from_string' => '%"From":"' . $location_id . '"%',
            'to_int' => '%"To":' . $location_id . '%',
            'to_string' => '%"To":"' . $location_id . '"%',
            'where_int' => '%"Where":' . $location_id . '%',
            'where_string' => '%"Where":"' . $location_id . '"%',
            'inserted' => '%"inserted":%' . $location_id . '%',
            'deleted' => '%"deleted":%' . $location_id . '%',
            'updated' => '%"updated":%' . $location_id . '%',
        ];
    }

    /**
     * Returns SKU history events allowed for the selected location type.
     */
    private static function getLocationHistoryEvents(int $location_id): array
    {
        $events = self::LOCATION_HISTORY_EVENTS;
        $location = Locations::getById($location_id);
        if (!$location || !in_array($location->getTypeId(), self::UPDATE_LOCATIONS_LOCATION_TYPES, true))
            $events = array_values(array_diff($events, [SKUHistories::EVENT_UPDATE_LOCATIONS]));
        return $events;
    }

    /**
     * Converts a raw SKU history record into one or more CSV rows.
     */
    private static function formatLocationHistoryRows(
        array $record,
        int $location_id,
        Warehouse $warehouse,
        array $sku_info,
        array $event_names,
        array &$locations,
        array &$users,
        array &$batches
    ): array {
        $additional_info = json_decode($record['additional_info'], true);
        if (!is_array($additional_info))
            return [];

        $event_date = (new TimeZonesConvertor)
            ->setDateByString($record['event_date'])
            ->convert($warehouse->getTimezone())
            ->getConvertedDateString();

        if ($record['event'] === SKUHistories::EVENT_UPDATE_LOCATIONS)
            return self::formatUpdateLocationsRows($record, $additional_info, $location_id, $event_date, $sku_info, $event_names, $locations, $users, $batches);
        if ($record['event'] === SKUHistories::EVENT_HANDOVER)
            return self::formatHandoverRows($record, $additional_info, $location_id, $event_date, $event_names, $locations, $users);

        $items = self::isList($additional_info) ? $additional_info : [$additional_info];
        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item))
                continue;
            $action = self::getStockLocationAction($record['event'], $item, $location_id);
            if (!$action)
                continue;
            $rows[] = self::buildLocationHistoryRow($record, $item, $action, $event_date, $sku_info, $event_names, $locations, $users, $batches);
        }
        return $rows;
    }

    /**
     * Converts a raw location_history record into a CSV row.
     */
    private static function formatLocationLockHistoryRow(array $record, Warehouse $warehouse, array &$users): array
    {
        $addition = json_decode($record['addition'] ?? '', true);
        $event_date = (new TimeZonesConvertor)
            ->setDateByString($record['event_date'])
            ->convert($warehouse->getTimezone())
            ->getConvertedDateString();

        return [
            'Date' => $event_date,
            'Event' => 'Location',
            'Action' => self::getLocationHistoryAction((string)($record['event'] ?? ''), is_array($addition) ? $addition : []),
            'SKU ID' => '',
            'SKU Name' => '',
            'SKU Barcode' => '',
            'Quantity' => '',
            'From Location' => '',
            'To Location' => '',
            'User Login' => self::getUserLogin((int)($record['user_id'] ?? 0), $users),
            'Reference' => '',
            'Reason' => '',
        ];
    }

    /**
     * Returns a readable action for a location_history event.
     */
    private static function getLocationHistoryAction(string $event, array $addition): string
    {
        if ($event === 'lock') {
            $state = (string)($addition['state'] ?? '');
            return $state === 'locked' ? 'Locked' : ($state === 'unlocked' ? 'Unlocked' : 'Lock changed');
        }
        if ($event === 'is_non_picking') {
            $state = (string)($addition['is_non_picking'] ?? '');
            return $state === 'non_picking' ? 'Non-picking' : ($state === 'picking' ? 'Picking' : 'Picking mode changed');
        }
        return 'Changed';
    }

    /**
     * Converts a Handover event into a cart history CSV row.
     */
    private static function formatHandoverRows(
        array $record,
        array $additional_info,
        int $location_id,
        string $event_date,
        array $event_names,
        array &$locations,
        array &$users
    ): array {
        $from = (int)($additional_info['From'] ?? 0);
        $to = (int)($additional_info['To'] ?? 0);
        if ($from !== $location_id && $to !== $location_id)
            return [];

        return [[
            'Date' => $event_date,
            'Event' => $event_names[$record['event']] ?? $record['event'],
            'Action' => $to === $location_id ? 'Handover In' : 'Handover Out',
            'SKU ID' => '',
            'SKU Name' => '',
            'SKU Barcode' => '',
            'Quantity' => '',
            'From Location' => self::getLocationName($from, $locations),
            'To Location' => self::getLocationName($to, $locations),
            'User Login' => self::getUserLogin((int)($additional_info['User'] ?? 0), $users),
            'Reference' => '',
            'Reason' => '',
        ]];
    }

    /**
     * Converts an UpdateLocations event into allocation-related CSV rows.
     */
    private static function formatUpdateLocationsRows(
        array $record,
        array $additional_info,
        int $location_id,
        string $event_date,
        array $sku_info,
        array $event_names,
        array &$locations,
        array &$users,
        array &$batches
    ): array {
        $changes = $additional_info['changes'] ?? [];
        if (!is_array($changes))
            return [];
        $actions = [
            'inserted' => 'Allocated',
            'deleted' => 'Unallocated',
            'updated' => 'Changed',
        ];
        $rows = [];
        foreach ($actions as $key => $action) {
            $values = $changes[$key] ?? [];
            if (!is_array($values))
                $values = [$values];
            foreach ($values as $value) {
                if (is_array($value))
                    $match = in_array($location_id, array_map('intval', array_keys($value)), true)
                        || in_array($location_id, array_map('intval', array_values($value)), true);
                else
                    $match = (int)$value === $location_id;
                if (!$match)
                    continue;
                $item = [
                    'Where' => $location_id,
                    'User' => $additional_info['user_id'] ?? null,
                ];
                $rows[] = self::buildLocationHistoryRow($record, $item, $action, $event_date, $sku_info, $event_names, $locations, $users, $batches);
            }
        }
        return $rows;
    }

    /**
     * Determines whether an event added, removed, or changed stock at the selected location.
     */
    private static function getStockLocationAction(string $event, array $info, int $location_id): ?string
    {
        if (isset($info['To']) && (int)$info['To'] === $location_id)
            return 'Added';
        if (isset($info['From']) && (int)$info['From'] === $location_id)
            return 'Removed';
        if (!isset($info['Where']) || (int)$info['Where'] !== $location_id)
            return null;
        if (in_array($event, [SKUHistories::EVENT_DROPPING_T, SKUHistories::EVENT_PACKING], true))
            return 'Removed';
        if ($event === SKUHistories::EVENT_COUNTING)
            return 'Changed';
        return 'Changed';
    }

    /**
     * Builds a CSV row for a stock-related location history event.
     */
    private static function buildLocationHistoryRow(
        array $record,
        array $info,
        string $action,
        string $event_date,
        array $sku_info,
        array $event_names,
        array &$locations,
        array &$users,
        array &$batches
    ): array {
        $sku_id = (int)$record['SKU_id'];
        $reference = self::buildLocationHistoryReference($info, $batches);

        return [
            'Date' => $event_date,
            'Event' => $event_names[$record['event']] ?? $record['event'],
            'Action' => $action,
            'SKU ID' => $sku_id > 0 ? $sku_id : '',
            'SKU Name' => $sku_info[$sku_id]['name'] ?? '',
            'SKU Barcode' => $sku_info[$sku_id]['barcode'] ?? '',
            'Quantity' => $info['Units'] ?? '',
            'From Location' => isset($info['From']) ? self::getLocationName((int)$info['From'], $locations) : '',
            'To Location' => isset($info['To']) ? self::getLocationName((int)$info['To'], $locations) : '',
            'User Login' => self::getUserLogin((int)($info['User'] ?? 0), $users),
            'Reference' => $reference,
            'Reason' => trim(($info['Reason'] ?? '') . (!empty($info['UserReason']) ? ' / ' . $info['UserReason'] : '')),
        ];
    }

    /**
     * Builds a readable reference string from event metadata.
     */
    private static function buildLocationHistoryReference(array $info, array &$batches): string
    {
        $references = [];
        foreach (['Order', 'Batch', 'ASN ID', 'ASN_id'] as $key)
            if (isset($info[$key]) && $info[$key] !== '' && $info[$key] !== '-') {
                if ($key === 'Batch')
                    $references[] = self::getBatchInfo((int)$info[$key], $batches);
                elseif ($key === 'Order')
                    $references[] = 'Order ID :' . $info[$key];
                else
                    $references[] = $key . ': ' . $info[$key];
            }
        return implode('; ', $references);
    }

    /**
     * Returns cached batch display information by batch id.
     */
    private static function getBatchInfo(int $batch_id, array &$batches): string
    {
        if ($batch_id <= 0)
            return '';
        if (!array_key_exists($batch_id, $batches)) {
            $batch = Batches::getById($batch_id);
            $batches[$batch_id] = $batch
                ? 'Batch#:' . $batch->getNumber() . ',Exp:' . date('jS M Y', strtotime($batch->getExpired()))
                : 'Batch#:' . $batch_id;
        }
        return $batches[$batch_id];
    }

    /**
     * Returns a cached location display name by location id.
     */
    private static function getLocationName(int $location_id, array &$locations): string
    {
        if ($location_id <= 0)
            return '';
        if (!array_key_exists($location_id, $locations)) {
            $data = Locations::getByIds([$location_id]);
            $locations[$location_id] = $data[$location_id]['name'] ?? ('Location #' . $location_id);
        }
        return $locations[$location_id];
    }

    /**
     * Returns a cached user login by user id.
     */
    private static function getUserLogin(int $user_id, array &$users): string
    {
        if ($user_id <= 0)
            return '';
        if (!array_key_exists($user_id, $users)) {
            $user = Users::getById($user_id);
            $users[$user_id] = $user ? $user->getLogin() : ('User #' . $user_id);
        }
        return $users[$user_id];
    }

    /**
     * Checks whether an array has consecutive numeric keys.
     */
    private static function isList(array $array): bool
    {
        return $array === [] || array_keys($array) === range(0, count($array) - 1);
    }
}
