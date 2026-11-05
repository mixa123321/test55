<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\dbal\SafeQueryBuilder;
use app\core\entities\CurlLogRecord;
use app\core\entities\Warehouse;
use app\core\helpers\CURL;
use app\core\helpers\Request;
use app\core\services\crypto\Redactor;
use app\core\statical\Timezones;
use app\core\statical\WithDate;

class CurlLogger
{
    const LIMIT_PER_PAGE = 10;

    public static function log(CURL $curl, $anyway = false)
    {
        if (strpos($curl->getResultCode(), '2') !== 0 || $anyway) {
            $full_url = $curl->getUrl();
            $explode_url = explode('?', $full_url);
            $url = array_shift($explode_url);
            $data = [
                'url' => $url,
                'http_code' => $curl->getResultCode(),
                'response_time' => $curl->getResponseTime(),
                // Shop/Consero/Visible/SFTPGo keys, passwords and issued tokens are masked (BL-009)
                // curl_logs.request is NOT NULL and requests without a body (GET) leave it null
                'request' => is_string($curl->getBody()) ? Redactor::redactText($curl->getBody()) : ($curl->getBody() ?? ''),
                'response' => is_string($curl->getResponse()) ? Redactor::redactText($curl->getResponse()) : $curl->getResponse(),
                'response_headers' => $curl->getResultHeaders(),
                'request_id' => uniqid('', true) . '_' . time(),
                'source' => $curl->getSource(),
                'get_params' => '',
            ];
            if (!empty($explode_url))
                $data['get_params'] = Redactor::redactText(implode('?', $explode_url));

            $query = (new SafeQueryBuilder())
                ->insertInto('curl_logs')
                ->values($data)
                ->build();

            (new SafeMysql())->insertQuery($query);
        }
    }

    public static function getRecords(Warehouse $warehouse, array $filters): array
    {
        $possible_filters = [
            'request_id',
            'url',
            'http_code',
            'source',
        ];

        $query = (new SafeQueryBuilder())
            ->selectAll()
            ->from('curl_logs');

        foreach ($possible_filters as $possible_filter) {
            if (!empty($filters[$possible_filter])) {
                $query->where($possible_filter, '=', trim((string) $filters[$possible_filter]));
            }
        }

        if (!empty($filters['response_time'])) {
            $query->where('response_time', '>=', $filters['response_time']);
        }

        if (!empty($filters['result_error'])) {
            $query->where('http_code', '!=', 200);
        }

        $time_diff = (!empty($filters['date_from']) || !empty($filters['date_to']))
            ? Timezones::getDiff($warehouse->getTimezone())
            : 0;

        if (!empty($filters['date_from'])) {
            $date_from = empty($time_diff)
                ? date(WithDate::DATE_SQL_FORMAT, strtotime($filters['date_from']))
                : Timezones::getDiff($warehouse->getTimezone(), $filters['date_from'] . ' 00:00:00', true);

            $query->whereRaw(
                "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') >= :date_from",
                [
                    'date_from' => $date_from,
                ]
            );
        }

        if (!empty($filters['date_to'])) {
            $date_to = empty($time_diff)
                ? date(WithDate::DATE_SQL_FORMAT, strtotime($filters['date_to']))
                : Timezones::getDiff($warehouse->getTimezone(), $filters['date_to'] . ' 23:59:59', true);

            $date_to = date(WithDate::DATE_SQL_FORMAT, strtotime($date_to) + 86400 - 1);

            $query->whereRaw(
                "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') <= :date_to",
                [
                    'date_to' => $date_to,
                ]
            );
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
            $record_objects[] = new CurlLogRecord($item);
        }
        return ['records' => $record_objects, 'current_page' => $current_page, 'next_page' => $next_page];
    }

    public static function getDistinctHttpCodes()
    {
        $codes = [];
        foreach ((new SafeMysql())->fetchArrayOneField('SELECT DISTINCT http_code FROM curl_logs') as $code) {
            $codes[(int) $code] = (int) $code;
        }

        return $codes;
    }

    public static function getDistinctSources()
    {
        $sources = [];
        foreach ((new SafeMysql())->fetchArrayOneField('SELECT DISTINCT source FROM curl_logs') as $source) {
            $sources[$source] = $source;
        }

        return $sources;
    }

    public static function getRecordById($id)
    {
        $result = (new SafeMysql())->fetchOne(
            'SELECT * FROM curl_logs WHERE id = :id LIMIT 1',
            [
                'id' => (int) $id,
            ]
        );
        if (!empty($result)) {
            return new CurlLogRecord($result);
        }
        return null;
    }
}