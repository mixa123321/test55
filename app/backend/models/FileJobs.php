<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;

class FileJobs extends \app\core\models\FileJobs
{
    public static function getListByUserJobId($user_job_id)
    {
        return (new SafeMysql())->fetchAll("SELECT * FROM file_jobs WHERE user_job_id = :user_job_id", ['user_job_id' => $user_job_id]);
    }

}