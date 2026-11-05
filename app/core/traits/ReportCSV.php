<?php

namespace app\core\traits;

use app\backend\models\FileJobs;
use app\backend\models\UserJobs;
use app\core\entities\FileJob;
use app\core\entities\UserJob;
use app\core\helpers\File;
use app\core\helpers\formatters\FormatterCSV;
use app\core\Queue;
use Exception;
use app\core\helpers\FileWrapper;

trait ReportCSV
{
    static function getReportFolderName(): string
    {
        return self::REPORT_FOLDER_NAME;
    }

    static function getReportName(): string
    {
        return self::REPORT_NAME . '_' .date("d_m_Y") . '.csv';
    }

    static function getParamsHeadline(UserJob $userJob)
    {
        return $userJob->getUserId();
    }

    public static function generateReportCSV($user_job_id): array
    {
        $userJob = UserJobs::getById($user_job_id);
        if (empty($userJob))
            throw new \Exception("User job not found");
        if ($userJob->getStatus() !== UserJob::STATUS_NEW)
            throw new \Exception("User job was already in work");;
        try {
            $path = $userJob->getUserId() . DIRECTORY_SEPARATOR . self::getReportFolderName();
//            $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath(CSV_STORAGE)))->handleDir(CSV_STORAGE . $userJob->getUserId());
//            $file_wrapper->handleDir(CSV_STORAGE . $path);
            File::handleDir(CSV_STORAGE . $path);
            $filename = CSV_STORAGE . $path . DIRECTORY_SEPARATOR . time() . '_' . self::getReportName();
            $csv = new FormatterCSV($filename);
            $csv->addHeader(self::handleHeadlineCSV(self::getParamsHeadline($userJob)));
            unset($csv);
            sleep(1);
            FileJobs::addNew($filename, $user_job_id);
            $userJob->setStatus(UserJob::STATUS_PROGRESS);
            $userJob->setUpdateDate(time());
            $userJob->save(['id', 'status', 'update_date']);
            self::handleReportCSV($user_job_id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        return ['success' => 'generated report CSV file for user job #' . $user_job_id];
    }

    /**
     * @param $user_job_id
     * @return array
     * @throws Exception
     */
    private static function initReportCSV($user_job_id): array
    {
        $userJob = UserJobs::getById($user_job_id);
        try {
            if (empty($userJob))
                throw new Exception('User job not found');
            self::initUserJob($userJob);
            $fileJob = FileJobs::getByUserJobId($user_job_id)[0] ?? null;
            $csv = self::initFormatterCSVReport($fileJob, $userJob);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return array($userJob, $fileJob, $csv);
    }

    public static function initUserJob(UserJob $userJob)
    {
        if ($userJob->getStatus() !== UserJob::STATUS_PROGRESS)
            throw new Exception('Job stopped unexpectedly');
        $userJob->setUpdateDate(time());
        $userJob->save(['id', 'update_date']);
    }

    public static function initFormatterCSVReport(?FileJob $fileJob, UserJob $userJob): ?FormatterCSV
    {
        $csv = null;
        if (!empty($fileJob) && is_file($fileJob->getPath()))
            $csv = new FormatterCSV($fileJob->getPath(), 'a+');

        if (empty($fileJob) || !empty($csv->getError())) {
            $userJob->setStatus(UserJob::STATUS_ERROR);
            $userJob->setUpdateDate(time());
            $userJob->save(['id', 'status', 'update_date']);
            FileJobs::removeByUserJobId($userJob->getId());
            throw new \Exception('File error' . (!empty($csv->getError()) ? (': ' . $csv->getError()) : ''));
        }
        return $csv;
    }

    public static function doneUserJob(UserJob $userJob, FileJob $fileJob): void
    {
        $userJob->setStatus(UserJob::STATUS_DONE);
        $userJob->setUpdateDate(time());
        $userJob->save(['id', 'status', 'update_date']);
        $fileJob->setIsDone(true);
        $fileJob->setUpdateDate(time());
        $fileJob->save(['id', 'is_done', 'update_date']);
    }

    static function processCSV(UserJob $userJob, $raw_result, FormatterCSV $csv, $addition)
    {
        self::handleHeadlineCSV(self::getParamsHeadline($userJob));
        self::handleDataToCSV($raw_result, $csv, $addition);
    }

    public static function processStep($raw_result, FormatterCSV $csv, UserJob $userJob)
    {
        $next = count($raw_result) > self::LIMIT_GENERATE_REPORT;
        if ($next) {
            array_pop($raw_result);
            $max_item_id = $raw_result[count($raw_result) - 1]['item_id'];
            Queue::addJobToHandleReportCSV([
                'user_job_id' => $userJob->getId(),
                'max_item_id' => $max_item_id,
            ], self::class);
        }
        $addition = json_decode($userJob->getAddition(), true);
        self::processCSV($userJob, $raw_result, $csv, $addition['addition'] ?? null);
        $userJob->setAddition(json_encode(['processed' => intval($addition['processed'] ?? 0) + count($raw_result)] + (!empty($addition) ? $addition : [])));
        $userJob->setUpdateDate(time());
        $userJob->save(['id', 'addition', 'update_date']);
        return $next;
    }

    public static function transferToCloud(FileJob $fileJob)
    {
        $file_wrapper = new FileWrapper(FileWrapper::getContainerByPath($fileJob->getPath()));
        if ($file_wrapper->getType() === FileWrapper::TYPE_AZURE) {
            //Переносим результат в облачное хранилище
            $file_wrapper->writeFilePath($fileJob->getPath(), $fileJob->getPath());
            unlink($fileJob->getPath());
        }
    }
}
