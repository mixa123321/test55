<?php


namespace app\core\services\archiving;

use app\core\dbal\SafeMysql;
use app\core\statical\WithDate;

class ArchivingLogsCSVService extends ArchivingCSVService
{

    private $clean_threshold;
    public static $fields = [
                                'user_id', 'login', 'date_added', 'memory_usage', 'request_id',
                                'request_exec_time', 'request_host', 'request_method', 'request_get',
                                'request_post', 'request_raw', 'request_headers', 'response_body',
                                'response_headers', 'response_http_code', 'ip'
                                ];

    /**
     * @param null $clean_threshold
     * @throws \Exception
     */
    public function __construct($clean_threshold = null)
    {
        $this->clean_threshold =  date(self::DATE_FORMAT, !is_null($clean_threshold) ? $clean_threshold : time());
        $this->makeFile(self::DIR_LOGS, 'logs_before_' . $this->clean_threshold . '_created_' . date(self::DATE_FORMAT . "-His"));
    }

    public function getData($last_id): array
    {
        $data = (new SafeMysql())->fetchAll("SELECT l.*, u.login FROM log l
                                            LEFT JOIN users u ON u.id = l.user_id
                                                WHERE l.date_added < :date_threshold AND l.id > :last_id LIMIT 100", [
            'date_threshold' => strtotime($this->clean_threshold),
            'last_id' => $last_id,
        ]);

        array_walk($data, function(&$item) {
            $item['date_added'] = date(WithDate::DATE_SQL_FORMAT, $item['date_added']);
        });
        return $data;
    }

    public function destroyItems($items)
    {
        $this->destroyByIds('DELETE FROM log WHERE id in (:ids)', $items);
    }
}