<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ReportConfig;

class ReportConfigs
{
    public static function getAllConfigs()
    {
        $configs_data = (new SafeMysql())->fetchAll("SELECT * FROM report_config");
        if (empty($configs_data))
            return [];
        $configs = [];
        foreach ($configs_data as $config_data) {
            $config = new ReportConfig($config_data);
            if (empty($configs[$config->getReportId()]))
                $configs[$config->getReportId()] = [];
            $configs[$config->getReportId()][] = [
                'field' => $config->getField(),
                'name' => $config->getFieldName(),
                'type' => $config->getType(),
            ];
        }
        return $configs;
    }
}
