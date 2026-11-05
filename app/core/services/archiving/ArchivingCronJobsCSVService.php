<?php


namespace app\core\services\archiving;

use app\core\dbal\SafeMysql;
use app\core\models\Jobs;
use app\core\statical\WithDate;
use DateTime;
use Exception;

class ArchivingCronJobsCSVService extends ArchivingCSVService
{

    protected $clean_threshold;
    public static $fields = [ 'id', 'job_data', 'status', 'message', 'created_at', 'updated_at' ];

    private $init;

    /**
     * @throws Exception
     */
    public function __construct($clean_threshold = null, $init = false)
    {
        $this->init = $init;
        $this->makeFile(self::DIR_CRON_JOBS, $this->getFileName($clean_threshold));
        $this->clean_threshold =  date(self::DATE_FORMAT, !is_null($clean_threshold) ? $clean_threshold : time());
    }

    /**
     * @throws Exception
     */
    protected function getFileName($clean_threshold = null): string
    {
        return 'cron_jobs_before_' . date(self::DATE_FORMAT, !is_null($clean_threshold) ? $clean_threshold : time()) . '_created_' . date(self::DATE_FORMAT . '-His', !$this->init ?  time() : (new DateTime(date(WithDate::DATE_SQL_FORMAT, $clean_threshold)))->modify('+1 month')->getTimestamp());
    }

    public function getData($last_id): array
    {
        return (new SafeMysql())->fetchAll("SELECT * FROM cron_jobs
                                                WHERE created_at < :clean_threshold AND id > :last_id
                                                        AND status NOT IN (:statuses)
                                                         LIMIT 100", [
            'clean_threshold' => $this->clean_threshold,
            'last_id' => $last_id,
            'statuses' => [Jobs::STATUS_NEW, Jobs::STATUS_IN_PROCESS],
        ]);
    }

    public function destroyItems($items)
    {
        $this->destroyByIds('DELETE FROM cron_jobs WHERE id in (:ids)', $items);
    }
}