<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\FileJob;
use app\core\helpers\File;

class FileJobs
{

    public static function getById($id)
    {
       $file_job = (new SafeMysql())->fetchOne("SELECT * FROM file_jobs WHERE id = :id", ['id' => (int)$id]);
       return !empty($file_job) ? new FileJob($file_job) : null;
    }

    public static function addNew($filename, $user_job_id)
    {
        $file_job = new FileJob();
        $file_job->setFilename(File::clearPath($filename));
        $file_job->setUserJobId($user_job_id);
        $file_job->setCreateDate(time());
        $file_job->setUpdateDate(time());
        $file_job->save(['filename', 'user_job_id', 'create_date', 'update_date']);
        return $file_job->getId();
    }

    public static function removeById($file_job_id)
    {
        return (new SafeMysql())->execute("DELETE FROM file_jobs WHERE id = :id", ['id' => (int)$file_job_id]);
    }

    public static function removeByUserJobId($user_job_id)
    {
        // TODO remove all files
        return (new SafeMysql())->execute("DELETE FROM file_jobs WHERE user_job_id = :user_job_id", ['user_job_id' => (int)$user_job_id]);
    }

    public static function getByUserJobIdAndFile($user_job_id, $filename)
    {
        $file_job = (new SafeMysql())->fetchOne("SELECT * FROM file_jobs
                                                        WHERE user_job_id = :user_job_id
                                                                AND filename = :filename", ['user_job_id' => (int)$user_job_id, 'filename' => $filename]);
        return !empty($file_job) ? new FileJob($file_job) : null;
    }

    public static function getByUserJobId($user_job_id): ?array
    {
        $res =  (new SafeMysql())->fetchAll("SELECT * FROM file_jobs WHERE user_job_id = :user_job_id", ['user_job_id' => (int)$user_job_id]);
        return array_map(function($file_job) {
            return new FileJob($file_job);
        }, $res);
    }

    public static function getFirstByUserJobId($user_job_id): ?FileJob
    {
        $res =  (new SafeMysql())->fetchOne('SELECT * FROM file_jobs WHERE user_job_id = :user_job_id LIMIT 1', ['user_job_id' => (int)$user_job_id]);
        return !empty($res) ? new FileJob($res) : null;
    }
}
