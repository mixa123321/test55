<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\dbal\SafeQueryBuilder;
use app\core\entities\LogRecord;
use app\core\entities\Warehouse;
use app\core\helpers\HTTP;
use app\core\helpers\Request;
use app\core\helpers\Str;
use app\core\statical\Timezones;

class Logger
{
    const LIMIT_PER_PAGE = 10;

    public static function addRecord($userId = 0, $response = '', $http_code = 200)
    {
        $record = new LogRecord();
        $record->setIp(Request::getIp());
        $record->setDateAdded(time());
        $record->setRequestExecTime(round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3) * 1000);
        $record->setUserId($userId);
        $record->setRequestUri(trim(strtok(Str::toUtf8(HTTP::getRequestUri()), '?'), "/"));
        $record->setMemoryUsage(memory_get_peak_usage(true));
        $record->setRequestMethod(Request::getRequestMethod());
        $record->setRequestGet(json_encode(Str::toUtf8($_GET)));
        $record->setRequestPost(json_encode(Str::toUtf8($_POST)));
        // delete json_decode(.., true) \/\/\/\/\/\/\/\/\/
        $record->setRequestRaw(empty(Request::getRawBody()) ? '' : Str::toUtf8(Request::getRawBody()));
        $record->setRequestHost(HTTP::getHost());
        $record->setRequestHeaders(json_encode(Str::toUtf8(Request::getRequestHeaders())));
        $record->setResponseBody($response);
        $record->setResponseHttpCode($http_code);

        $query = (new SafeQueryBuilder())
            ->insertInto('log')
            ->values($record->getDataToSave())
            ->build();

        (new SafeMysql())->insertQuery($query);
    }

    public static function getRecords(Warehouse $warehouse, array $filters)
    {
        $possible_filters = [
            'request_id',
            'ip',
            'response_http_code',
            'request_uri',
            'request_host',
            'request_method',
        ];

        $query = (new SafeQueryBuilder())
            ->selectAll()
            ->from('log');

        foreach ($possible_filters as $possible_filter) {
            if (!empty($filters[$possible_filter])) {
                $query->where($possible_filter, '=', trim((string) $filters[$possible_filter]));
            }
        }

        if (!empty($filters['user_id'])) {
            $query->whereIn('user_id', array_map('intval', $filters['user_id']));
        }

        $time_diff = (!empty($filters['date_from']) || !empty($filters['date_to']))
            ? Timezones::getDiff($warehouse->getTimezone())
            : 0;

        if (!empty($filters['date_from'])) {
            $date_from = empty($time_diff)
                ? $filters['date_from']
                : Timezones::getDiff($warehouse->getTimezone(), $filters['date_from'] . ' 00:00:00', true);

            $query->where('date_added', '>=', strtotime($date_from));
        }

        if (!empty($filters['date_to'])) {
            $date_to = empty($time_diff)
                ? $filters['date_to']
                : Timezones::getDiff($warehouse->getTimezone(), $filters['date_to'] . ' 23:59:59', true);

            $query->where('date_added', '<=', strtotime($date_to) + 86400 - 1);
        }

        if (!empty($filters['exec_time'])) {
            $query->where('request_exec_time', '>=', (int) $filters['exec_time']);
        }

        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);

        $query
            ->orderBy('id', 'DESC')
            ->limit(self::LIMIT_PER_PAGE + 1)
            ->offset($offset);

        $records_raw = (new SafeMysql())->fetchAllQuery($query->build());

        if (count($records_raw) === self::LIMIT_PER_PAGE + 1) {
            $next_page = true;
            array_pop($records_raw);
        } else $next_page = false;

        $record_objects = [];
        foreach ($records_raw as $item) {
            $record_objects[] = new LogRecord($item);
        }
        return ['records' => $record_objects, 'current_page' => $current_page, 'next_page' => $next_page];
    }

    public static function getRecordById($id)
    {
        $result = (new SafeMysql())->fetchOne(
            'SELECT * FROM log WHERE id = :id LIMIT 1',
            [
                'id' => (int) $id,
            ]
        );
        if (!empty($result)) {
            return new LogRecord($result);
        }
        return null;
    }
}
