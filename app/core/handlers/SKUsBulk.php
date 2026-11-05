<?php

namespace app\core\handlers;

use app\core\entities\File;
use app\core\entities\FileJob;
use app\core\entities\User;
use app\core\entities\UserJob;
use app\core\helpers\File as FileHelper;
use app\core\models\FileJobs;
use app\core\models\UserJobNotifications;
use app\core\models\UserJobs;
use app\core\models\Users;
use app\core\services\SKUsBulkService;

class SKUsBulk
{
    /**
     * QUEUE
     * @param $user_job_id
     * @return array|string[]
     */
    public static function handleUpdate($user_job_id): array
    {
        $userJob = UserJobs::getById($user_job_id);
        if (empty($userJob))
            return ['error' => 'Job #' . $user_job_id . ' not found'];

        try {
            self::changeUserJob($userJob, UserJob::STATUS_PROGRESS);

            $addition = json_decode($userJob->getAddition(), TRUE);

            /**
             * @var $fileJob FileJob|null
             */
            $fileJob = FileJobs::getFirstByUserJobId($userJob->getId());
            if (empty($fileJob)) {
                self::changeUserJob($userJob, UserJob::STATUS_ERROR);
                throw new \Exception('File job not found');
            }

            /**
             * @var $file File
             */
            list($file_open, $file) = self::handleFile($fileJob);
            if (!$file_open) {
                self::changeUserJob($userJob, UserJob::STATUS_ERROR);
                throw new \Exception('File not found');
            }

            $user = Users::getById($userJob->getUserId());
            if (empty($user)) {
                self::changeUserJob($userJob, UserJob::STATUS_ERROR);
                throw new \Exception('User not found');
            }

            list($open, $res) = self::executeService($file, $addition, $user);

            if (!$open) {
                self::changeUserJob($userJob, UserJob::STATUS_ERROR);
                throw new \Exception($res);
            }

            self::doneFileJob($fileJob);
            self::handleBulkResult($user_job_id, $res);
            self::changeUserJob($userJob, UserJob::STATUS_DONE);

            return ['success' => 'Successfully processed ' . count($res) . ' rows'];

        } catch (\Exception $e) {
            self::changeUserJob($userJob, UserJob::STATUS_ERROR);
            return ['error' => $e->getMessage()];
        }

    }

    private static function handleFile(FileJob $fileJob): array
    {
        if (!is_file($fileJob->getPath()))
            return [false, null];

        $file = File::create();
        $file->setPath($fileJob->getPath());
        $file->setTmpName($fileJob->getPath());
        $file->setType(FileHelper::getMimeType($file->getTmpName()));

        return [true, $file];
    }

    private static function executeService(File $file, $addition, User $user): array
    {
        $bulk = new SKUsBulkService($file);
        $error = $bulk->getError();
        if (!empty($error))
            return [false, $error];
        $bulk->prepareData($addition['fields'] ?? []);
        $bulk->setUser($user);
        $bulk->setWarehouseId($addition['warehouse_id'] ?? null);
        $bulk->setQueue();

        list($open, $data) = $bulk->execute();

        return [$open, $open ? $data : $bulk->getError()];
    }

    private static function changeUserJob(UserJob $userJob, string $status)
    {
        $userJob->setStatus($status);
        $userJob->setUpdateDate(time());
        $userJob->save(['id', 'status', 'update_date']);
    }

    private static function doneFileJob(FileJob $fileJob)
    {
        $fileJob->setIsDone(true);
        $fileJob->setUpdateDate(time());
        $fileJob->save(['id', 'is_done', 'update_date']);
    }

    private static function handleBulkResult($user_job_id, $data)
    {
        foreach ($data as $row) {
            if (!empty($row['errors'])) {
                foreach ($row['errors'] as $error)
                    UserJobNotifications::addNewDanger($user_job_id, $error);
            }
            if (!empty($row['result']))
                foreach ($row['result'] as $msg)
                    if(strpos($msg, 'not changes') !== false) {
                        UserJobNotifications::addNewWarning($user_job_id, $msg);
                    } else {
                        UserJobNotifications::addNewSuccess($user_job_id, $msg);
                    }
        }
    }
}