<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SectionBinding extends Entity
{
    private $id;
    private $num;
    private $object_id;
    private $type_id;

    const WORKERS = 'W';
    const TABLES = 'T';
    const LARGES = 'L';

    const TYPE_BIND_SINGLE_TABLE = 'C';
    const TYPE_BIND_LARGE_TABLE = 'U';

    const TYPES_BIND_TABLES = [
        self::TYPE_BIND_SINGLE_TABLE,
        self::TYPE_BIND_LARGE_TABLE,
    ];

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getNum()
    {
        return $this->num;
    }

    public function setNum($num)
    {
        $this->num = (string)$num;
    }

    public function getObjectId()
    {
        return $this->object_id;
    }

    public function setObjectId($id)
    {
        $this->object_id = (int)$id;
    }

    public function getTypeId()
    {
        return $this->type_id;
    }

    public function setTypeId($id)
    {
        $this->type_id = (string)$id;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save)) {
            return;
        }
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value) {
            if (!\in_array($data_field, $fields_to_save, true)) {
                unset($data[$data_field]);
            }
        }
        Mysql::saveRow("section_binding", $data);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'num' => $this->getNum(),
            'object_id' => $this->getObjectId(),
            'type_id' => $this->getTypeId(),
        ];
    }
}
