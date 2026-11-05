<?php

namespace app\core\models;

use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\entities\Warehouse;
use app\core\helpers\Request;
use app\core\statical\Timezones;

class ShopInventoryLogs
{
    const LIMIT_PER_PAGE = 20;

    public static function getRecords(Warehouse $warehouse, array $filters)
    {
        $warehouse_id = $warehouse->getId();
        $whereChannels = empty($filters['channel_id']) ? '' : "AND cs.channel_id = :channel_id";
        $whereShops = empty($filters['shop_id']) ? '' : "WHERE s.id = :shop_id";
        $sql = "SELECT s.id, c.name cname, sup.id sid, sup.name sname, sw.is_active
                FROM shops s 
                    JOIN shop_warehouses sw ON s.id = sw.shop_id AND sw.warehouse_id = :warehouse_id
                    JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id $whereChannels
                    JOIN suppliers sup ON cs.supplier_id = sup.id
                    JOIN channels c ON cs.channel_id = c.id
                $whereShops";
        $params = ['warehouse_id' => $warehouse_id];
        if (!empty($filters['channel_id']))
            $params['channel_id'] = (int)$filters['channel_id'];
        if (!empty($filters['shop_id']))
            $params['shop_id'] = (int)$filters['shop_id'];
        $shops = (new SafeMysql())->fetchAssocWithKey($sql, $params, 'id');
        if (empty($shops))
            return ['records' => [], 'current_page' => 1, 'next_page' => false];
        $shop_ids = implode(',', array_keys($shops));
        $possible_filters = [
            'type',
            'object_id',
            'SKU_id',
        ];
        $query_builder = new QueryBuilder();
        $query_builder->setSelectFrom('
            SELECT sil.*, u.login, s.name, s.id SKU_id
            FROM shop_inventory_logs sil');
        $query_builder->addJoin('LEFT JOIN users u ON sil.user_id = u.id');
        $query_builder->addJoin('JOIN SKUs s ON sil.SKU_id = s.id');
        $query_builder->addWhere('sil.warehouse_id = :wh_id');
        $query_builder->addWhere("sil.shop_id IN (:shop_ids)");
        $qParams = ['wh_id' => $warehouse_id, 'shop_ids' => array_map('intval', array_keys($shops))];
        foreach ($possible_filters as $possible_filter)
            if (!empty($filters[$possible_filter])) {
                $query_builder->addWhere("$possible_filter = :$possible_filter");
                $qParams[$possible_filter] = trim($filters[$possible_filter]);
            }

        $time_diff = Timezones::getDiff($warehouse->getTimezone());
        $date_format = '%Y-%m-%d %H:%i:%s';

        if (!empty($filters['create_date_from'])) {
            $date_from = $filters['create_date_from'] . ' 00:00:00';
            $date_from = empty($time_diff) ? $date_from : Timezones::getDiff($warehouse->getTimezone(), $date_from, TRUE);
            $query_builder->addWhere("DATE_FORMAT(sil.create_date, '{$date_format}') >= :date_from");
            $qParams['date_from'] = $date_from;
        }
        if (!empty($filters['create_date_to'])) {
            $date_to = $filters['create_date_to'] . ' 23:59:59';
            $date_to = empty($time_diff) ? $date_to : Timezones::getDiff($warehouse->getTimezone(), $date_to, TRUE);
            $query_builder->addWhere("DATE_FORMAT(sil.create_date, '{$date_format}') <= :date_to");
            $qParams['date_to'] = $date_to;
        }
        $query_builder->setOrderBy('sil.id DESC');
        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);
        $query_builder->setLimit("{$offset}, " . (self::LIMIT_PER_PAGE + 1));
        $records_raw = (new SafeMysql())->fetchAll($query_builder->build(), $qParams);
        SKUBarcodes::addBarcodesToArray($records_raw, 'SKU_id');
        if (count($records_raw) === self::LIMIT_PER_PAGE + 1) {
            $next_page = true;
            array_pop($records_raw);
        } else $next_page = false;
        foreach ($records_raw as &$record) {
            $record['cname'] = $shops[$record['shop_id']]['cname'];
            $record['sid'] = $shops[$record['shop_id']]['sid'];
            $record['sname'] = $shops[$record['shop_id']]['sname'];
            $record['is_active'] = $shops[$record['shop_id']]['is_active'];
        } unset($record);
        return ['records' => $records_raw, 'current_page' => $current_page, 'next_page' => $next_page];
    }
}
