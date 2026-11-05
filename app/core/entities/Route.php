<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\dbal\SafeMysql;

class Route extends Entity
{
    private $id;
    private $name;
    private $editable;
    private $parent_id;
    private $alias;
    private $virtual_access;
    private $is_active;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function getEditable(): bool
    {
        return (bool)$this->editable;
    }

    public function setEditable($editable): void
    {
        $this->editable = (int)$editable;
    }

    public function getParentId()
    {
        return $this->parent_id;
    }

    public function setParentId($parent_id): void
    {
        $this->parent_id = $parent_id;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function setAlias($alias): void
    {
        $this->alias = $alias;
    }

    public function getVirtualAccess(): bool
    {
        return (bool)$this->virtual_access;
    }

    public function setVirtualAccess($virtual_access): void
    {
        $this->virtual_access = (int)$virtual_access;
    }

    public function getIsActive()
    {
        return $this->is_active;
    }

    public function setIsActive($is_active): void
    {
        $this->is_active = $is_active;
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
        if (!$this->getId()) {
            $this->setId(Mysql::saveRow("routes", $data));
        } else {
            Mysql::saveRow("routes", $data);
        }
    }

    public function delete()
    {
        if (!empty($this->getId()))
            (new SafeMysql())->execute('DELETE FROM routes WHERE id = :id', [
                'id' => $this->getId()
            ]);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'editable' => $this->getEditable(),
            'parent_id' => $this->getParentId(),
            'alias' => $this->getAlias(),
            'virtual_access' => $this->getVirtualAccess(),
            'is_active' => $this->getIsActive(),
        ];
    }

    public function getPath(): string
    {
        return '/' . $this->getName();
    }

    public function isVirtualAccess(): bool
    {
        return !empty($this->getVirtualAccess());
    }

    public function isEditable(): bool
    {
        return !empty($this->getEditable());
    }

    public function isActive(): bool
    {
        return !empty($this->getIsActive());
    }
}
