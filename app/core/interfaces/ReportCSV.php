<?php

namespace app\core\interfaces;

use app\core\entities\UserJob;
use app\core\helpers\formatters\FormatterCSV;

interface ReportCSV
{
    const LIMIT_FLASH_GENERATE_REPORT = 500;
    const LIMIT_GENERATE_REPORT = 8000;

    static function getReportFolderName(): string;
    static function handleHeadlineCSV($user_id): array;

    /** @return string */
    static function getReportName(): string;
    static function getParamsHeadline(UserJob $userJob);
    static function processCSV(UserJob $userJob, $raw_result, FormatterCSV $csv, $addition);
    /**
     * @param array $filters
     * @return int
     */
    static function getTotalCountReportCSV(array $filters);
    /**
     * @param $user_job_id
     * @return mixed
     */
    static function generateReportCSV($user_job_id);
    /**
     * @param $user_job_id
     * @param  int  $max_item_id
     * @return array
     */
    static function handleReportCSV($user_job_id, $max_item_id = 0): array;
    /**
     * @param  array  $raw_result
     * @param FormatterCSV $csv
     * @param  null $addition
     * @return void
     */
    static function handleDataToCSV(array $raw_result, FormatterCSV $csv, $addition = null);
}

