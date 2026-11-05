<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class UserJob extends Entity
{

    private $id;
    private $user_id;
    private $status;
    private $create_date;
    private $update_date;
    private $type;
    private $addition;

    const TYPE_UPLOAD_ORDER_NUM = 1;
    const TYPE_GENERATE_ORDER_CSV_NUM = 2;
    const TYPE_GENERATE_SKU_CSV_NUM = 3;
    const TYPE_GENERATE_ASN_CSV_NUM = 4;
    const TYPE_GENERATE_EXPIRED_BATCHES_CSV_NUM = 7;
    const TYPE_GENERATE_BUNDLES_CSV_NUM = 8;
    const TYPE_ORDERS_BULK_CANCEL_NUM = 5;
    const TYPE_SKUS_BULK_UPDATE_NUM = 6;
    const TYPE_GENERATE_ASN_RETURN_CSV_NUM = 9;
    const TYPE_GENERATE_LOCATION_HISTORY_CSV_NUM = 10;

    const TYPE_UPLOAD_ORDER = 'UPLOAD ORDER';
    const TYPE_GENERATE_ORDER_CSV = 'GENERATE ORDER CSV';
    const TYPE_GENERATE_SKU_CSV = 'GENERATE SKU CSV';
    const TYPE_GENERATE_ASN_CSV = 'GENERATE ASN CSV';
    const TYPE_GENERATE_ASN_RETURN_CSV = 'GENERATE ASN RETURN CSV';
    const TYPE_GENERATE_EXPIRED_BATCHES_CSV = 'GENERATE EXPIRED BATCHES CSV';
    const TYPE_GENERATE_BUNDLES_CSV = 'GENERATE BUNDLES CSV';
    const TYPE_ORDERS_BULK_CANCEL = 'ORDERS BULK CANCEL';
    const TYPE_SKUS_BULK_UPDATE = 'SKUS BULK UPDATE';
    const TYPE_GENERATE_LOCATION_HISTORY_CSV = 'GENERATE LOCATION HISTORY CSV';

    const TYPES = [
        self::TYPE_UPLOAD_ORDER_NUM => self::TYPE_UPLOAD_ORDER,
        self::TYPE_GENERATE_ORDER_CSV_NUM => self::TYPE_GENERATE_ORDER_CSV,
        self::TYPE_GENERATE_SKU_CSV_NUM => self::TYPE_GENERATE_SKU_CSV,
        self::TYPE_GENERATE_ASN_CSV_NUM => self::TYPE_GENERATE_ASN_CSV,
        self::TYPE_ORDERS_BULK_CANCEL_NUM => self::TYPE_ORDERS_BULK_CANCEL,
        self::TYPE_SKUS_BULK_UPDATE_NUM => self::TYPE_SKUS_BULK_UPDATE,
        self::TYPE_GENERATE_EXPIRED_BATCHES_CSV_NUM => self::TYPE_GENERATE_EXPIRED_BATCHES_CSV,
        self::TYPE_GENERATE_BUNDLES_CSV_NUM => self::TYPE_GENERATE_BUNDLES_CSV,
        self::TYPE_GENERATE_ASN_RETURN_CSV_NUM => self::TYPE_GENERATE_ASN_RETURN_CSV,
        self::TYPE_GENERATE_LOCATION_HISTORY_CSV_NUM => self::TYPE_GENERATE_LOCATION_HISTORY_CSV,
    ];

    const STATUS_NEW_NUM = 1;
    const STATUS_PROGRESS_NUM = 2;
    const STATUS_ERROR_NUM = 3;
    const STATUS_ABORT_NUM = 4;
    const STATUS_DONE_NUM = 5;

    const STATUS_NEW = 'new';
    const STATUS_PROGRESS = 'in progress';
    const STATUS_ERROR = 'error';
    const STATUS_ABORT = 'aborted';
    const STATUS_DONE = 'done';

    const STATUSES = [
        self::STATUS_NEW_NUM => self::STATUS_NEW,
        self::STATUS_PROGRESS_NUM => self::STATUS_PROGRESS,
        self::STATUS_ERROR_NUM => self::STATUS_ERROR,
        self::STATUS_ABORT_NUM => self::STATUS_ABORT,
        self::STATUS_DONE_NUM => self::STATUS_DONE,
    ];

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * @param mixed $user_id
     */
    public function setUserId($user_id): void
    {
        $this->user_id = $user_id;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status): void
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getCreateDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->create_date);
    }

    /**
     * @param mixed $create_date
     */
    public function setCreateDate($create_date)
    {
        $this->create_date = WithDate::dateToInt($create_date);
    }

    /**
     * @return mixed
     */
    public function getUpdateDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->update_date);
    }

    /**
     * @param mixed $update_date
     */
    public function setUpdateDate($update_date)
    {
        $this->update_date = WithDate::dateToInt($update_date);
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type): void
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getAddition()
    {
        return $this->addition;
    }

    /**
     * @param mixed $addition
     */
    public function setAddition($addition): void
    {
        $this->addition = $addition;
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
            $this->setId(Mysql::saveRow("user_jobs", $data));
        } else {
            Mysql::saveRow("user_jobs", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'user_id' => $this->getUserId(),
            'status' => $this->getStatus(),
            'create_date' => $this->getCreateDate(),
            'update_date' => $this->getUpdateDate(),
            'type' => $this->getType(),
            'addition' => $this->getAddition(),
        ];
    }
}
