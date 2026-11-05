<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\UserJob;

class UserJobs
{

    public static function getById($id)
    {
        $user_job = (new SafeMysql())->fetchOne("SELECT * FROM user_jobs WHERE id = :id", ['id' => (int)$id]);
        return !empty($user_job) ? new UserJob($user_job) : null;
    }

    public static function getUserId($id)
    {
        return (new SafeMysql())->fetchScalar("SELECT user_id FROM user_jobs WHERE id = :id", ['id' => (int)$id]);
    }

    public static function addNew($user_id, $type, $params = [])
    {
        $userJob = new UserJob();
        $userJob->setUserId($user_id);
        $userJob->setCreateDate(time());
        $userJob->setUpdateDate(time());
        $userJob->setStatus(UserJob::STATUS_NEW);
        $userJob->setType($type);
        $userJob->setAddition(json_encode($params));
        $userJob->save(['user_id', 'create_date', 'update_date','status', 'type', 'addition']);
        return $userJob->getId();
    }

    /**
     * Cascade delete (tables: user_job_notifications, file_jobs)
     */
    public static function clearDoneTasks()
    {
        return (new SafeMysql())->execute("DELETE FROM user_jobs WHERE status = :status", ['status' => UserJob::STATUS_DONE]);
    }
}
