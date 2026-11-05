<?php

namespace app\backend\models;

use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\entities\Movement;
use app\core\entities\Warehouse;
use app\core\helpers\Filter;
use app\core\helpers\Request;
use app\core\statical\Timezones;

class Processings
{
    const FILTER_PARAM = 'processings_filters';

    public static function getByFilters(Warehouse $warehouse, $filters = []): array
    {
        $filters['warehouse_id'] = $warehouse->getId();
        $filters['m_status_progress'] = Movement::IN_PROGRESS;
        $equalFilters = [
            'SKU_id' => 'm.SKU_id',
            'type_from' => 'lf.type_id',
            'loc_from_id' => 'm.loc_from_id',
            'type_to' => 'lt.type_id',
            'loc_to_id' => 'm.loc_to_id',
        ];
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT SQL_CALC_FOUND_ROWS m.id, m.SKU_id, s.name, s.photo, s.supplier_id, m.quantity,
                   m.loc_from_id, CONCAT(IF(w.label = '' OR w.label IS NULL, lf.warehouse, w.label), '-', lf.type_id, '-', lf.zone, '-', lf.column, '-', lf.row) lf_name,
                   m.loc_to_id, CONCAT(IF(w.label = '' OR w.label IS NULL, lt.warehouse, w.label), '-', lt.type_id, '-', lt.zone, '-', lt.column, '-', lt.row) lt_name,
                   m.create_date, m.create_user_id, uc.login clogin, m.user_id, u.login, m.status, m.reason, m.type
            FROM movements m");
        $query->addJoin("JOIN SKUs s ON m.SKU_id = s.id");
        $query->addJoin("JOIN locations lf ON m.loc_from_id = lf.id AND lf.warehouse = :warehouse_id");
        $query->addJoin("JOIN locations lt ON m.loc_to_id = lt.id AND lt.warehouse = :warehouse_id");
        $query->addJoin("JOIN warehouses w ON lf.warehouse = w.id");
        $query->addJoin("LEFT JOIN users uc ON m.create_user_id = uc.id");
        $query->addJoin("LEFT JOIN users u ON m.user_id = u.id");
        $query->addWhere('m.type = ' . Movement::TYPE_UNRESTRICTED);
        foreach ($filters as $key => $filter)
            if (in_array($key, array_keys($equalFilters)) && !empty($filter))
                $query->addWhere("{$equalFilters[$key]} = '" . addslashes($filter) . "'");
        $time_diff = Timezones::getDiff($warehouse->getTimezone());
        $date_format = '%Y-%m-%d %H:%i:%s';
        if (!empty($filters['date_created_from']) && strtotime($filters['date_created_from']) > 0) {
            $date_from = $filters['date_created_from'] . ' 00:00:00';
            $filters['date_from'] = empty($time_diff) ? $date_from : Timezones::getDiff($warehouse->getTimezone(), $date_from, TRUE);
            $query->addWhere("DATE_FORMAT(m.create_date, '{$date_format}') >= :date_from");
        }
        if (!empty($filters['date_created_to']) && strtotime($filters['date_created_to']) > 0) {
            $date_to = $filters['date_created_to'] . ' 23:59:59';
            $filters['date_to'] = empty($time_diff) ? $date_to : Timezones::getDiff($warehouse->getTimezone(), $date_to, TRUE);
            $query->addWhere("DATE_FORMAT(m.create_date, '{$date_format}') <= :date_to");
        }
        if (!empty($filters['barcode'])) {
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode($filters['barcode'], Filter::getFilterTypeFromRequest($filters, 'barcode'));
            $filters['SKU_ids'] = $SKU_ids;
            $SKU_ids ? $query->addWhere("s.id IN (:SKU_ids)") : $query->addWhere("FALSE");
        }
        if (!empty($filters['statuses']))
            $query->addWhere("m.status IN (:statuses)");
        if (!empty($filters['completed']))
            $query->addWhere("((m.status = :m_status_progress AND m.user_id = :completed) OR (m.status <> :m_status_progress))");
        $current_page = Request::getPage($filters);
        $offset = Pagination::LIMIT_PER_PAGE_BIG * ($current_page - 1);
        $query->setLimit("{$offset}, " . Pagination::LIMIT_PER_PAGE_BIG);
        $records_raw = (new SafeMysql())->fetchAll($query->build(), $filters);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        SKUBarcodes::addBarcodesToArray($records_raw, 'SKU_id');
        return ['records' => $records_raw, 'total' => $total, 'current_page' => $current_page];
    }

    public static function colorMovement($movement)
    {
        if (Movement::isNonCompliant($movement['status']))
            return 'danger';
        return 'secondary';
    }
}
