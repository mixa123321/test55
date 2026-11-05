<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SFTPReport extends Entity
{
    private $sftp_export_id;
    private $report_id;
    private $is_active;
    private $frequency;
    private $date_time;
    private $additional;

    public function getSFTPExportId()
    {
        return $this->sftp_export_id;
    }

    public function setSFTPExportId($id)
    {
        $this->sftp_export_id = (int)$id;
    }

    public function getReportId()
    {
        return $this->report_id;
    }

    public function setReportId($id)
    {
        $this->report_id = (int)$id;
    }

    public function getIsActive()
    {
        return $this->is_active;
    }

    public function setIsActive($num)
    {
        $this->is_active = (int)$num;
    }

    public function getFrequency()
    {
        return $this->frequency;
    }

    public function setFrequency($num)
    {
        $this->frequency = (int)$num;
    }

    public function getDateTime()
    {
        return $this->date_time;
    }

    public function setDateTime($num)
    {
        $this->date_time = (int)$num;
    }

    public function getAdditional()
    {
        if (empty($this->additional))
            return [];
        if (is_array($this->additional))
            return $this->additional;
        return json_decode($this->additional, true);
    }

    public function setAdditional($add)
    {
        $this->additional = $add;
    }

    public function save()
    {
        $data = $this->toArray();
        $data['additional'] = json_encode($data['additional']);
        Mysql::saveRow('sftp_report', $data);
    }

    public function toArray()
    {
        return [
            'sftp_export_id' => $this->getSFTPExportId(),
            'report_id' => $this->getReportId(),
            'is_active' => $this->getIsActive(),
            'frequency' => $this->getFrequency(),
            'date_time' => $this->getDateTime(),
            'additional' => $this->getAdditional(),
        ];
    }
}