<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ReportExport;

class ReportExports
{
    public static function getByMethod($method)
    {
        $names = explode('\\', $method);
        $method = array_pop($names);
        $data = (new SafeMysql())->fetchOne("SELECT * FROM report_export WHERE method = :method", ['method' => $method]);
        if (empty($data))
            return null;
        return new ReportExport($data);
    }
}
