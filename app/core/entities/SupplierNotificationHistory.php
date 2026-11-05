<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class SupplierNotificationHistory extends Entity
{
    private $id;
    private $type_id;
    private $supplier_id;
    private $type;
    private $additional;
    private $create_date;
    private $user_id;

    const TYPE_CREATE_NEW_EMAIL = 'create email';
    const TYPE_REMOVE_NEW_EMAIL = 'remove email';
    const TYPE_CHANGE_EMAIL = 'change email';
    const TYPE_CHANGE_EMAIL_FOR_TYPE = 'change email for type';
    const TYPE_ACTIVATE_SUPPLIER_FOR_TYPE = 'activate supplier for type';
    const TYPE_DEACTIVATE_SUPPLIER_FOR_TYPE = 'deactivate supplier for type';
    const TYPE_NOTIFICATION_CREATE = 'notification was created';
    const TYPE_NOTIFICATION_SEND = 'notification was sent';

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getTypeId()
    {
        return $this->type_id;
    }

    public function setTypeId($type_id): void
    {
        $this->type_id = $type_id;
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

    public function getCreateDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->create_date);
    }

    public function setCreateDate($create_date)
    {
        $this->create_date = WithDate::dateToInt($create_date);
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
            $this->setId(Mysql::saveRow("supplier_notification_history", $data));
        } else {
            Mysql::saveRow("supplier_notification_history", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'type_id' => $this->getTypeId(),
            'supplier_id' => $this->getSupplierId(),
            'type' => $this->getType(),
            'additional' => $this->getAdditional(),
            'create_date' => $this->getCreateDate(),
            'user_id' => $this->getUserId(),
        ];
    }
}
