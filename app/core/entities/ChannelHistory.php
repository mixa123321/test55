<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ChannelHistory extends Entity
{
    // common
    const TYPE_CONNECT = 'connect';
    const TYPE_DISABLE_CONNECT = 'disable connect';
    const TYPE_RESTORE_CONNECT = 'restore connect';
    const TYPE_UPDATE_CONNECT = 'update connect';
    // integration
    const TYPE_SET_RISK = 'set risk';
    const TYPE_SYNC_LOCATIONS = 'sync locations';
    const TYPE_PRIMARY_SHOP = 'primary shop';
    const TYPE_UPDATE_SHOP = 'update shop';
    const TYPE_SHIPPING_MAPPING = 'shipping mapping';

    private $id;
    private $channel_supplier_id;
    private $user_id;
    private $type;
    private $addition;
    private $create_date;
    private $warehouse_id;

    public function getId(): int
    {
        return (int)$this->id;
    }

    public function setId($id): void
    {
        $this->id = (int)$id;
    }

    public function getChannelSupplierId(): int
    {
        return (int)$this->channel_supplier_id;
    }

    public function setChannelSupplierId($channel_supplier_id): void
    {
        $this->channel_supplier_id = (int)$channel_supplier_id;
    }

    public function getUserId(): int
    {
        return (int)$this->user_id;
    }

    public function setUserId($user_id): void
    {
        $this->user_id = (int)$user_id;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type): void
    {
        $this->type = $type;
    }

    public function getAddition()
    {
        return $this->addition;
    }

    public function setAddition($addition): void
    {
        $this->addition = $addition;
    }

    public function getCreateDate()
    {
        return $this->create_date;
    }

    public function setCreateDate($create_date): void
    {
        $this->create_date = $create_date;
    }

    public function getWarehouseId(): int
    {
        return (int)$this->warehouse_id;
    }

    public function setWarehouseId($warehouse_id): void
    {
        $this->warehouse_id = (int)$warehouse_id;
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
            $this->setId(Mysql::saveRow("channel_history", $data));
        } else {
            Mysql::saveRow("channel_history", $data);
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'channel_supplier_id' => $this->getChannelSupplierId(),
            'user_id' => $this->getUserId(),
            'type' => $this->getType(),
            'addition' => $this->getAddition(),
            'create_date' => $this->getCreateDate(),
            'warehouse_id' => $this->getWarehouseId(),
        ];
    }
}
