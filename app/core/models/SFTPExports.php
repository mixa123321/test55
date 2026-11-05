<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\SFTPExport;

class SFTPExports
{
    public static function getBySupplierId($supplier_id)
    {
        $sftp = (new SafeMysql())->fetchOne("
            SELECT *
            FROM sftp_export 
            WHERE supplier_id = :supplier_id
            LIMIT 1", ['supplier_id' => (int)$supplier_id]);
        if (empty($sftp))
            return null;
        return new SFTPExport($sftp);
    }

    public static function getAllActive()
    {
        return (new SafeMysql())->fetchAll("
            SELECT id, supplier_id
            FROM sftp_export 
            WHERE is_active = 1");
    }
}
