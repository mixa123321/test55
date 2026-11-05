<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ReportConfig extends Entity
{
    private $report_id;
    private $field;
    private $type;

    public function getReportId()
    {
        return $this->report_id;
    }

    public function setReportId($id)
    {
        $this->report_id = (int)$id;
    }

    public function getField()
    {
        return $this->field;
    }

    public function getFieldName()
    {
        return str_replace(' ', '_', strtolower($this->field));
    }

    public function setField($name)
    {
        $this->field = (string)$name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($name)
    {
        $this->type = (string)$name;
    }

    public function save()
    {
        Mysql::saveRow('report_config', $this->toArray());
    }

    public function toArray()
    {
        return [
            'report_id' => $this->getReportId(),
            'field' => $this->getField(),
            'type' => $this->getType(),
        ];
    }
}