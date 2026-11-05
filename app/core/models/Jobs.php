<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Job;
use app\core\entities\JobData;
use app\core\helpers\Defends;
use app\core\statical\WithDate;

class Jobs
{
    const STATUS_NEW = 'new';
    const STATUS_TOUCH = 'touch';
    const STATUS_IN_PROCESS = 'in_process';
    const STATUS_ERROR = 'error';
    const STATUS_COMPLETE = 'complete';

    const TIME_WAITING = 90;

    public static function getNewJobs($nums = 0, $next = 0)
    {
        $limit = $next_id = '';
        $params = [];
        if (!empty($nums))
            $limit = "LIMIT " . (int)$nums;
        if (!empty($next)) {
            $next_id = " AND id > :next";
            $params['next'] = (int)$next;
        }
        $sql = "SELECT * FROM cron_jobs WHERE status = :status $next_id ORDER BY id $limit";
        $params['status'] = self::STATUS_NEW;
        $rows = (new SafeMysql())->fetchAll($sql, $params);
        return array_map(function ($row) {
            return new Job($row);
        }, $rows);
    }

    public static function getStatusJob($job_id, $status)
    {
        return (new SafeMysql())->fetchScalar("SELECT 1 FROM cron_jobs WHERE status = :status AND id = :job_id", ['status' => $status, 'job_id' => (int)$job_id]);
    }

    public static function getByJobData($job_data)
    {
        return (new SafeMysql())->fetchArrayOneField("SELECT created_at FROM cron_jobs WHERE job_data = :job_data", ['job_data' => $job_data]);
    }

    public static function addNewJob(JobData $job)
    {
        if((new Defends())->setType(Defends::TYPE_JOBS)->setHash($job->toJson())->lockDate() === Defends::LOCK_NOT_UPDATE)
            return null;
        return (new SafeMysql())->execute("
            INSERT INTO cron_jobs
            SET job_data = :job_data, 
                created_at = :created_at, 
                status = :status", [
            'job_data' => $job->toJson(),
            'created_at' => (new \DateTime)->format(WithDate::DATE_SQL_FORMAT),
            'status' => self::STATUS_NEW,
        ]);
    }

    public static function setStatus(Job $CronJob, $status, $message = null)
    {
        if (in_array($status, [self::STATUS_ERROR, self::STATUS_COMPLETE]))
            (new Defends())->setType(Defends::TYPE_JOBS)->setHash($CronJob->getJobData()->toJson())->unLock();
        return (new SafeMysql())->execute("
            UPDATE cron_jobs
            SET updated_at = :updated_at, 
                status = :status,
                message = :message
            WHERE id = :id", [
            'updated_at' => (new \DateTime)->format(WithDate::DATE_SQL_FORMAT),
            'status' => $status,
            'message' => $message,
            'id' => $CronJob->getId(),
        ]);
    }

    public static function changeStatus(Job $CronJob, string $from_status, string $to_status, string $message = ''): bool
    {
        $id = (int)$CronJob->getId();
        $current_date = (new \DateTime)->format(WithDate::DATE_SQL_FORMAT);

        return (bool)(new SafeMysql())->execute("
        UPDATE cron_jobs cj
        SET cj.status = IF(cj.status = :from_status, :to_status, cj.status),
            cj.updated_at = IF(cj.status = :from_status, :current_date, cj.updated_at),
            cj.message = IF(cj.status = :from_status, :message, cj.message)
        WHERE cj.id = :id", [
            'from_status' => $from_status,
            'to_status' => $to_status,
            'current_date' => $current_date,
            'message' => $message,
            'id' => $id,
        ]);
    }
}