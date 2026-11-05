<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SupplierHistory extends Entity
{

    const TYPE_CREATE = 'create';
    const TYPE_UPDATE_INFO = 'update_info';
    const TYPE_CHANGE_ACCESS = 'change_access';
    const TYPE_SET_WAREHOUSE = 'set_warehouse';
    const TYPE_UPDATE_WAREHOUSE = 'update_warehouse';
    const TYPE_ADD_USER = 'add_user';
    const TYPE_LOCK = 'lock';
    const TYPE_UNLOCK = 'unlock';

    private $id;
    private $supplier_id;
    private $type;
    private $additional;
    private $user_id;


    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($supplier_id): void
    {
        $this->supplier_id = $supplier_id;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type): void
    {
        $this->type = $type;
    }

    public function getAdditional()
    {
        return $this->additional;
    }

    public function setAdditional($additional): void
    {
        $this->additional = $additional;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id): void
    {
        $this->user_id = $user_id;
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
            $this->setId(Mysql::saveRow("supplier_history", $data));
        } else {
            Mysql::saveRow("supplier_history", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'supplier_id' => $this->getSupplierId(),
            'type' => $this->getType(),
            'additional' => $this->getAdditional(),
            'user_id' => $this->getUserId(),
        ];
    }
}
