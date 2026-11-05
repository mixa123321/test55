<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Order;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\Validator;
use app\core\models\TokensTranslations;
use app\core\statical\Countries;
use app\core\statical\Timezones;
use app\core\statical\WithDate;
use DatePeriod;
use DateTime;
use Exception;

class Dashboard
{
    public static function getGroupByList($tokens = [], $is_client = false)
    {
        $list = [
            'create_date_group' => TokensTranslations::getTranslate($tokens, 'Date created'),
            'despatch_date_group' => TokensTranslations::getTranslate($tokens, 'Date expected despatch'),
            'despatched_date_group' => TokensTranslations::getTranslate($tokens, 'Date despatched'),

            'country_id' => TokensTranslations::getTranslate($tokens, 'Destination country'),
            'status' => TokensTranslations::getTranslate($tokens, 'Status'),
        ];
        if(!$is_client)
            $list['client'] = TokensTranslations::getTranslate($tokens, 'Client');
        return $list;
    }

    /**
     * @throws Exception
     */
    public static function getAggregated(Warehouse $warehouse, $tokens, $params)
    {
        $group_by_list = self::getGroupByList();
        if (empty($params['group_by']) || empty($group_by_list[$params['group_by']]))
            //group by date_created by default
            $group_by = "create_date_group";
        else
            $group_by = $params['group_by'];
        $date_types = Orders::dateTypes();
        $params['date_type'] = (empty($params['date_type']) || empty($date_types[$params['date_type']]))
            ? 'create_date' : $params['date_type'];
        $group_by_select = $group_by;
        $time_diff = Timezones::getDiff($warehouse->getTimezone());
        // Only the values bound to placeholders below: $params is the whole request (date_type, group_by, ...)
        $sql_params = [];
        switch ($group_by) {
            case 'create_date_group':
                $group_by_select = "DATE(DATE_ADD(o.create_date, interval :time_diff minute)) as `create_date_group`";
                $sql_params['time_diff'] = $time_diff;
                break;
            case 'despatch_date_group':
                $group_by_select = "DATE(o.despatch_date) as `despatch_date_group`";
                break;
            case 'despatched_date_group':
                $group_by_select = "DATE(DATE_ADD(o.despatched_date, interval :time_diff minute)) as `despatched_date_group`";
                $sql_params['time_diff'] = $time_diff;
                break;
            case 'client':
                $group_by_select = "s.name as client";
                break;
            case 'country_id':
                $group_by_select = "d.country_id as country_id";
        }
        $sql = "SELECT " . $group_by_select . ", count(*) as count
                FROM orders o
                    JOIN supplier_users su ON o.user_id = su.user_id
                    JOIN suppliers s ON s.id = su.supplier_id 
                    JOIN deliveries d ON o.delivery_id = d.id
        ";
        $filters = array();
        $is_time_diff = in_array($params['date_type'], ['create_date', 'despatched_date']) && !empty($time_diff);
        $date_format = '%Y-%m-%d';
        if ($is_time_diff)
            $date_format .= ' %H:%i:%s';
        if (!empty($params['date_from'])) {
            $params['date_from'] = !$is_time_diff ? $params['date_from'] : Timezones::getDiff($warehouse->getTimezone(), $params['date_from'] . ' 00:00:00', TRUE);
            $filters[] = "DATE_FORMAT(o.{$params['date_type']}, '{$date_format}') >= :date_from";
            $sql_params['date_from'] = $params['date_from'];
        }
        if (!empty($params['date_to'])) {
            $params['date_to'] = !$is_time_diff ? $params['date_to'] : Timezones::getDiff($warehouse->getTimezone(), $params['date_to'] . ' 23:59:59', TRUE);
            $filters[] = "DATE_FORMAT(o.{$params['date_type']}, '{$date_format}') <= :date_to";
            $sql_params['date_to'] = $params['date_to'];
        }
        if (!empty($params['supplier_id'])) {
            if (is_array($params['supplier_id']))
                $filters[] = 's.id IN (:supplier_id)';
            else
                $filters[] = "s.id = :supplier_id";
            $sql_params['supplier_id'] = $params['supplier_id'];
        }
        if (!empty($params['warehouse_id'])) {
            $filters[] = "o.warehouse_id = :warehouse_id";
            $sql_params['warehouse_id'] = $params['warehouse_id'];
        }
        if (!empty($params['destination_country'])) {
            $filters[] = "d.country_id = :destination_country";
            $sql_params['destination_country'] = $params['destination_country'];
        }
        if (!empty($params['status'])) {
            if ($params['status'] !== 'ret') {
                $filters[] = "o.status = :status";
                $sql_params['status'] = $params['status'];
            } else {
                $sql .= " LEFT JOIN ASNs a ON a.order_id=o.id ";
                $filters[] = "o.status = :status_despatched AND a.order_id IS NOT NULL";
                $sql_params['status_despatched'] = Order::DESPATCHED_NUM;
            }
        }
        if ($filters) {
            $sql .= " WHERE " . implode(' AND ', $filters);
        }
        $sql .= ' GROUP BY ' . $group_by;
        $orders_data = (new SafeMysql())->fetchRow($sql, $sql_params);
        $result = [
            'chart_data' => [],
            'total' => 0,
        ];
        foreach ($orders_data as $item)
            $result['total'] += $item[1];
        $sort = true;
        switch ($group_by) {
            case 'create_date_group':
            case 'despatch_date_group':
            case 'despatched_date_group':
                $sort = false;
                $date_count_arr = [];
                foreach ($orders_data as $item)
                    $date_count_arr[$item[0]] = $item[1];
                $end = new DateTime($params['date_from']);
                $start = new DateTime($params['date_to']);
                $diff = $end->diff($start);
                $isStartEqualsEnd = $diff->days === 0;
                $strInterval = $isStartEqualsEnd ? '0 day' : '-1 day';
                $interval = \DateInterval::createFromDateString($strInterval);
                $period = new DatePeriod($start, $interval, $diff->days ?: 1, $isStartEqualsEnd ? DatePeriod::EXCLUDE_START_DATE : 0);

                foreach ($period as $dt) {
                    $date_formatted = $dt->format(WithDate::DATE_SIMPLE_FORMAT);
                    $result['chart_data'][] = [
                        $date_formatted,
                        empty($date_count_arr[$date_formatted]) ? 0 : (int) $date_count_arr[$date_formatted],
                        empty($date_count_arr[$date_formatted]) ? 0 : $date_count_arr[$date_formatted],
                    ];
                }
                break;
            case 'client':
                foreach ($orders_data as $item) {
                    $result['chart_data'][] = [$item[0], (int) $item[1], $item[1]];
                }
                break;
            case 'status':
                foreach ($orders_data as $item)
                    $result['chart_data'][] = [Order::getStateName($item[0], $tokens), (int) $item[1], $item[1]];
                break;
            case 'country_id':
                foreach ($orders_data as $item)
                    $result['chart_data'][] = [Countries::getName($item[0]), (int) $item[1], $item[1]];
                break;
            default:
                return [];
        }
        if ($sort) {
             uasort($result['chart_data'], function($a, $b) {
                 return $b[1] - $a[1];
             });
        }
        $role_annotations = new \stdClass();
        $role_annotations->role = 'annotation';
        $count = array_sum(Arr::column($result['chart_data'], 1));
        array_unshift($result['chart_data'],['name', 'count', $role_annotations]);
        return $result + ['fig_count' => $count];
    }

    public static function validate(array $params): array
    {
        $rules = [
            'date_from' => [
                'val' => $params['date_from'] ?? null,
                'rules' => [Validator::TYPE_DATE => []]
            ],
            'date_to' => [
                'val' => $params['date_to'] ?? null,
                'rules' => [Validator::TYPE_DATE => []]
            ]
        ];
        $validator = new Validator($rules);

        return $validator->validate();
    }

    /**
     * @param array $params
     * @param array<string, mixed> $fieldsToResolve
     * @return array
     */
    public static function resolveAggregateParams(array $params, array $fieldsToResolve): array
    {
        $errors = self::validate($params);
        if (empty($errors)) {
            foreach ($fieldsToResolve as $key => $defaultValue)
                if (empty($params[$key]))
                    $params[$key] = $defaultValue;
        } else {
            foreach ($errors as $error)
                if (isset($fieldsToResolve[$error['field']]))
                    $params[$error['field']] = $fieldsToResolve[$error['field']];
        }
        return $params;
    }
}
