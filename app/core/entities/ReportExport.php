<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ReportExport extends Entity
{
    private $id;
    private $name;
    private $method;
    private $type_frequency;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($txt)
    {
        $this->name = (string)$txt;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function setMethod($txt)
    {
        $this->method = (string)$txt;
    }

    public function getTypeFrequency()
    {
        return $this->type_frequency;
    }

    public function setTypeFrequency($type)
    {
        $this->type_frequency = (int)$type;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('report_export', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'method' => $this->getMethod(),
            'type_frequency' => $this->getTypeFrequency(),
        ];
    }
}