<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\helpers\FileWrapper;

class SFTPUploads
{
    public static function getBySFTPExport($sftp_export_id)
    {
        return (new SafeMysql())->fetchAll("SELECT sftp_upload_types.title, sftp_upload_types.id as type_id, sftp_upload.*
                FROM sftp_upload_types  
                    LEFT JOIN sftp_upload ON sftp_upload_types.id = sftp_upload.sftp_upload_type_id AND sftp_upload.sftp_export_id = :sftp_export_id
                ORDER BY sftp_upload_types.id",
            ['sftp_export_id' => (int)$sftp_export_id]);
    }

    public static function createFolderForUpload($type_id, $active, $supplier_id)
    {
        $sftp_upload_type = (new SafeMysql())->fetchOne(
            "SELECT sftp_upload_types.title FROM sftp_upload_types WHERE id = :type_id",
            ['type_id' => (int)$type_id]
        );

        if (isset($sftp_upload_type['title']) && $active) {
            $sftp = SFTPExports::getBySupplierId($supplier_id);
            if (!$sftp)
                return [];
            $clear_login = FTPUsers::clearLogin($sftp->getLogin(), $supplier_id);
            $path_sftp = SFTP_STORAGE . DIRECTORY_SEPARATOR . $clear_login . DIRECTORY_SEPARATOR . FTPUsers::DIR_UPLOAD . DIRECTORY_SEPARATOR . $sftp_upload_type['title'];
            (new FileWrapper(FileWrapper::getContainerByPath($path_sftp)))->createDir($path_sftp);
        }
    }

    public static function getAllTitles(): array
    {
        $result = [];
        foreach ((new SafeMysql())->fetchAll("SELECT title FROM sftp_upload_types") as $item)
            $result[] = $item['title'];
        return $result;
    }

    public static function getActiveSFTPExports()
    {
        return (new SafeMysql())->fetchAll("SELECT sftp_export.supplier_id, sftp_export.login, sftp_upload_types.title, sftp_upload.user_id, users.warehouse_id
                FROM sftp_upload  
                    JOIN sftp_upload_types ON sftp_upload_types.id = sftp_upload.sftp_upload_type_id 
                    JOIN sftp_export ON sftp_export.id = sftp_upload.sftp_export_id
                    JOIN users ON users.id = sftp_upload.user_id
                WHERE sftp_upload.is_active = 1");
    }
}
