<?php


namespace app\core\services\archiving;

use app\core\dbal\SafeMysql;
use app\core\models\Jobs;

class ArchivingFailedCronJobsCSVService extends ArchivingCronJobsCSVService
{

    protected function getFileName($clean_threshold = null): string
    {
        return 'failed_cron_jobs_' . date('Y-m-d-His', time());
    }

    public function getData($last_id): array
    {
        return (new SafeMysql())->fetchAll("SELECT * FROM cron_jobs
                                                WHERE created_at < :clean_threshold AND id > :last_id
                                                        AND status IN (:statuses)
                                                         LIMIT 100", [
            'clean_threshold' => $this->clean_threshold,
            'last_id' => $last_id,
            'statuses' => [Jobs::STATUS_NEW, Jobs::STATUS_IN_PROCESS],
        ]);
    }
}