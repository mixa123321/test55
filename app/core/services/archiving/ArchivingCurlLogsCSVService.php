<?php

namespace app\core\services\archiving;

use app\core\dbal\SafeMysql;
use app\core\helpers\Arr;
use app\core\statical\WithDate;
use DateTime;

class ArchivingCurlLogsCSVService extends ArchivingCSVService
{
    protected $clean_threshold;
    protected $only_fail;
    private $limit;


    public $interval = [
        'start' => null,
        'end' => null,
    ];

    /**
     * @throws \Exception
     */
    public function __construct($clean_threshold, $only_fail = true)
    {
        $this->only_fail = (bool)$only_fail;
        $this->limit = $this->only_fail ? 100 : 10;
        $date = new DateTime($clean_threshold);
        if ($only_fail) {
            $date->modify('-1 week');
            $this->interval = [
                'start' => $date->modify('previous monday')->format('Y-m-d') . ' 00:00:00',
                'end' => $date->modify('next sunday')->format('Y-m-d') . ' 23:59:59'
            ];
        } else {
            $this->interval = [
                'start' => $date->modify('-8 hours')->format(WithDate::DATE_SQL_FORMAT),
                'end' => $date->modify('+4 hours')->format(WithDate::DATE_SQL_FORMAT)
            ];
        }
        $this->clean_threshold = $clean_threshold;
        if (empty($this->interval['start']) || empty($this->interval['end']))
            throw new \Exception('Interval error');

        $columns = (new SafeMysql())->fetchAll('SHOW COLUMNS FROM curl_logs');
        self::$fields = Arr::column($columns, 'Field');
        $this->makeFile(self::DIR_CURL_LOGS, $this->getFileName());
    }

    protected function getFileName(): string
    {
        $start = date(self::DATE_FORMAT . ($this->only_fail ? '' : '_Hi'), strtotime($this->interval['start']));
        $end = date(self::DATE_FORMAT . ($this->only_fail ? '' : '_Hi'), strtotime($this->interval['end']));
        return 'curl_logs_' . ($this->only_fail ? 'fail-week' : 'success-interval') . '-' . $start
            . '-to-' . $end
            . '_created-' . date(WithDate::DATE_SIMPLE_FORMAT . "-His");
    }

    public function getData($last_id): array
    {
        $mysql = new SafeMysql();
        $start = $this->interval['start'];
        $end = $this->interval['end'];
        $limit = $this->limit;
        $where_sql = "WHERE id > :last_id
                                    AND created_at BETWEEN :start AND :end
                                         AND LEFT(http_code, 1) " . ($this->only_fail ? '!' : '') . "= 2";
        if (!$this->only_fail) {
            $count = $mysql->fetchScalar('SELECT count(id) FROM curl_logs ' . $where_sql, [
                'last_id' => $last_id,
                'start' => $start,
                'end' => $end
            ]);
            if (empty($count))
                return [];
            if ((int)$count < $limit)
                $limit = (int)$count;
        }
        $sql = "SELECT * FROM curl_logs " . $where_sql . " ORDER BY id LIMIT " . $limit;
        $data = $mysql->fetchAll($sql, [
            'last_id' => $last_id,
            'start' => $start,
            'end' => $end
        ]);
        if (empty($data))
            return [];
        return $data;
    }

    public function destroyItems($items)
    {
        $this->destroyByIds('DELETE FROM curl_logs WHERE id in (:ids)', $items);
    }
}