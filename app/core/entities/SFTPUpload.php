<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SFTPUpload extends Entity
{
    private $sftp_export_id;
    private $sftp_upload_type_id;
    private $is_active;
    private $user_id;

    public function getSFTPExportId()
    {
        return $this->sftp_export_id;
    }

    public function setSFTPExportId($id)
    {
        $this->sftp_export_id = (int)$id;
    }

    public function getSFTPUploadTypeId()
    {
        return $this->sftp_upload_type_id;
    }

    public function setSFTPUploadTypeId($sftp_upload_type_id)
    {
        $this->sftp_upload_type_id = $sftp_upload_type_id;
    }

    public function getIsActive()
    {
        return $this->is_active;
    }

    public function setIsActive($num)
    {
        $this->is_active = (int)$num;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = (int)$user_id;
    }

    public function save()
    {
        Mysql::saveRow('sftp_update', $this->toArray());
    }

    public function toArray()
    {
        return [
            'sftp_export_id' => $this->getSFTPExportId(),
            'sftp_upload_type_id' => $this->getSFTPUploadTypeId(),
            'is_active' => $this->getIsActive(),
            'user_id' => $this->getUserId(),
        ];
    }
}