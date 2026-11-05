<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Printer extends Entity
{
    private $id;
    private $name;
    private $type;
    private $source_id;
    private $warehouse_id;
    private $state;

    const RETAIN_ME = 'retain_me';
    const PRINTER_BLOCKED = 0;
    const PRINTER_ACTIVE = 1;
    const PRINTER_DEFAULT = 2;

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

    public function setName($name)
    {
        $this->name = (string)$name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = (string)$type;
    }

    public function getSourceId()
    {
        return $this->source_id;
    }

    public function setSourceId($id)
    {
        $this->source_id = (int)$id;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state)
    {
        $this->state = (int)$state;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save)) {
            return;
        }
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('printers', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'type' => $this->getType(),
            'source_id' => $this->getSourceId(),
            'warehouse_id' => $this->getWarehouseId(),
            'state' => $this->getState(),
        ];
    }
}
