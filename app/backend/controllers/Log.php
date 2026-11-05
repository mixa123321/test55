<?php

namespace app\backend\controllers;

use app\core\entities\CurlLogRecord;
use app\core\entities\LogRecord;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\HTTP;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\CurlLogger;
use app\core\models\Logger;
use app\backend\models\Users;
use app\core\statical\WithDate;

class Log extends AuthController
{
    protected $is_loggable = false;

    /** @throws */
    public function index()
    {
        $filters = $_GET;
        $current_filters = $filters;
        if (isset($current_filters))
            unset($current_filters['c']);
        $users_list = Arr::map(Users::getAll($this->warehouse), 'id', 'login');
        if (empty($filters['user_id']) || empty($users_list[$filters['user_id']]))
            $filters['user_id'] = array_keys($users_list);
        else
            $filters['user_id'] = [$filters['user_id']];
        $raw_data = Logger::getRecords($this->warehouse, $filters);
        if ($raw_data['current_page'] > 1 && empty($raw_data['records'])) {
            View::addFlash('logs', 'danger', 'Error pagination');
            throw new RedirectException('/log?'. http_build_query($current_filters));
        }
        $records = $raw_data['records'];
        if (!empty($records) && !empty($this->time_diff))
            array_walk($records, function($record) {
                /** @var $record LogRecord */
                $record->setDateAdded(strtotime($this->handleTimezone(date(WithDate::DATE_SQL_FORMAT, $record->getDateAdded()))));
            });
        $vars = [
            'log_records' => $records,
            'current_page' => $raw_data['current_page'],
            'next_page' => $raw_data['next_page'],
            'users' => [0 => 'all'] + $users_list,
            'http_codes' => ['' => 'all'] + HTTP::getCodes(),
            'request_methods' => ['' => 'all'] + Arr::toList(Request::getAllowedRequestMethods()),
            'current_filters' => http_build_query($current_filters),
        ];
        $this->render($vars, 'tools/log');
    }

    /** AJAX */
    public function details()
    {
        $id = Request::getPositiveId();
        if (!$id) {
            $this->response = json_encode(['error']);
            return;
        }
        $record = Logger::getRecordById($id);
        if($record) {
            $date_added = date(WithDate::DATE_SQL_FORMAT, $record->getDateAdded());
            if (!empty($this->time_diff))
                $date_added = $this->handleTimezone($date_added);
            $record->setDateAdded($date_added);
            $record->setMemoryUsage(round($record->getMemoryUsage() / 1024 / 1024, 3));
            $this->response = json_encode($record->toArray());
            return;
        }
        $this->response = json_encode(['error']);
    }

    /** @throws */
    public function curl()
    {
        $filters = $_GET;
        $raw_data = CurlLogger::getRecords($this->warehouse, $filters);
        $current_filters = $filters;
        if (isset($current_filters)) {
            unset($current_filters['c']);
        }
        $http_codes = CurlLogger::getDistinctHttpCodes();
        $sources = CurlLogger::getDistinctSources();
        if ($raw_data['current_page'] > 1 && empty($raw_data['records'])) {
            View::addFlash('curl_logs', 'danger', 'Error pagination');
            throw new RedirectException('/log/curl?'.http_build_query($current_filters));
        }
        $records = $raw_data['records'];
        if (!empty($records) && !empty($this->time_diff))
            array_walk($records, function($record) {
                /** @var $record CurlLogRecord */
                $record->setCreatedAt($this->handleTimezone($record->getCreatedAt()));
            });
        $vars = [
            'log_records' => $records,
            'current_page' => $raw_data['current_page'],
            'next_page' => $raw_data['next_page'],
            'http_codes' => ['' => 'all'] + $http_codes,
            'sources' => ['' => 'all'] + $sources,
            'current_filters' => http_build_query($current_filters),
        ];
        $this->render($vars, 'tools/curl_log');
    }

    /** AJAX */
    public function curlDetails()
    {
        $id = Request::getPositiveId();
        if (!$id) {
            $this->response = json_encode(['error']);
            return;
        }
        $record = CurlLogger::getRecordById($id);
        if($record) {
            $this->response = json_encode($record->toArray());
            return;
        }
        $this->response = json_encode(['error']);
    }
}