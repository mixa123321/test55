<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\SFTPReport;

class SFTPReports
{
    const DAILY     = 1;
    const WEEKLY    = 2;
    const ALL_TYPE  = [
        self::DAILY,
        self::WEEKLY,
    ];
    const FREQUENCY = [
        self::DAILY => [
            'Daily',
            'Twice a day',
            'Three times a day',
            'Four times a day',
        ],
        self::WEEKLY => [
            'Daily',
            'Weekly',
            'Monthly',
        ]
    ];

    const FREQUENCY_NUM = [
        self::DAILY => [1, 2, 3, 4],
        self::WEEKLY => [[], ['w', 0], ['j', 1]],
    ];

    public static function getBySFTPExport($sftp_export_id)
    {
        $data = (new SafeMysql())->fetchAll("SELECT re.*, IFNULL(sr.is_active, 0) is_active, IFNULL(sr.frequency, 0) frequency, IFNULL(sr.date_time, 0) date_time, sr.additional
                FROM report_export re 
                    LEFT JOIN sftp_report sr ON re.id = sr.report_id AND sr.sftp_export_id = :sftp_export_id
                ORDER BY re.id",
            ['sftp_export_id' => (int)$sftp_export_id]);
        if (empty($data))
            return [];
        $additional_field = ReportConfigs::getAllConfigs();
        foreach ($data as &$report_data) {
            $additional = json_decode($report_data['additional'] ?? '[]', true);
            $report_data['additional'] = [];
            if (empty($additional_field[$report_data['id']]))
                continue;
            foreach ($additional_field[$report_data['id']] as $field_data)
                $report_data['additional'][$field_data['name']] = [
                    'name' => $field_data['field'],
                    'type' => $field_data['type'],
                    'value' => $additional[$field_data['name']] ?? '',
                ];
        }
        return $data;
    }

    public static function getBySFTPExportReport($sftp_export_id, $report_id)
    {
        $report_data = (new SafeMysql())->fetchOne("SELECT *
                FROM sftp_report 
                WHERE sftp_export_id = :sftp_export_id AND report_id = :report_id
                LIMIT 1",
            ['sftp_export_id' => (int)$sftp_export_id, 'report_id' => (int)$report_id]);
        if (empty($report_data))
            return null;
        return new SFTPReport($report_data);
    }
}
