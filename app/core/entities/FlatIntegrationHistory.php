<?php

namespace app\core\entities;

use app\core\db\Mysql;

class FlatIntegrationHistory extends Entity
{
    const ERROR                 = 'error';
    const FILE_UPLOAD           = 'upload';
    const FILE_DONE             = 'done';
    const FILE_MOVE             = 'move';
    const FILE_DELETE           = 'delete';
    const FILE_EXPORT_ORDERS    = 'export_orders';
    const FILE_EXPORT_SKUS      = 'export_SKUs';
    const FILE_MANUAL           = 'file_manual';
    const FILE_CHECK            = 'check';
    const LAST_ORDER            = 'last_order';

    const ALL_MODES = [
        self::ERROR,
        self::FILE_UPLOAD,
        self::FILE_DONE,
        self::FILE_MOVE,
        self::FILE_DELETE,
        self::FILE_EXPORT_ORDERS,
        self::FILE_EXPORT_SKUS,
        self::FILE_MANUAL,
        self::FILE_CHECK,
    ];

    const MODE_COLOR = [
        self::ERROR => 'danger',
        self::FILE_UPLOAD => 'success',
        self::FILE_DONE => 'success',
        self::FILE_MOVE => 'warning',
        self::FILE_DELETE => 'danger',
        self::FILE_EXPORT_ORDERS => 'success',
        self::FILE_EXPORT_SKUS => 'success',
        self::FILE_MANUAL => 'warning',
        self::FILE_CHECK => 'success',
    ];

    private $id;
    private $flat_integration_id;
    private $create_date;
    private $filename;
    private $additional_info;
    private $mode;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getFlatIntegrationId()
    {
        return $this->flat_integration_id;
    }

    public function setFlatIntegrationId($id)
    {
        $this->flat_integration_id = (int)$id;
    }

    public function getCreateDate()
    {
        return $this->create_date;
    }

    public function getCreateDateInt()
    {
        return strtotime($this->create_date);
    }

    public function setCreateDate($create_date)
    {
        $this->create_date = $create_date;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function setFilename($txt)
    {
        $this->filename = (string)$txt;
    }

    public function getAdditionalInfo()
    {
        return $this->additional_info;
    }

    public function setAdditionalInfo($txt)
    {
        $this->additional_info = (string)$txt;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function setMode($txt)
    {
        $this->mode = (string)$txt;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('flat_integration_history', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        $return = [
            'id' => $this->getId(),
            'flat_integration_id' => $this->getFlatIntegrationId(),
            'create_date' => $this->getCreateDate(),
            'filename' => $this->getFilename(),
            'additional_info' => $this->getAdditionalInfo(),
            'mode' => $this->getMode(),
        ];
        return $return;
    }
}