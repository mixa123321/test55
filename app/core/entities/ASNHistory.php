<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class ASNHistory extends Entity
{
    private $ASN_id;
    private $create_date;
    private $user_id;
    private $comment;
    private $status;

    const ACTIVE    = 1;
    const USERS     = 2;

    public function getASNId()
    {
        return $this->ASN_id;
    }

    public function setASNId($ASN_id)
    {
        $this->ASN_id = (int)$ASN_id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = (int)$user_id;
    }

    public function getCreateDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->create_date);
    }

    public function getCreateDateInt()
    {
        return $this->create_date;
    }

    public function setCreateDate($create_date)
    {
        $this->create_date = WithDate::dateToInt($create_date);
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function setComment($comment)
    {
        $this->comment = (string)$comment;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = (int)$status;
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
        Mysql::saveRow("ASN_history", $data);
    }

    public function toArray()
    {
        return [
            'ASN_id' => $this->getASNId(),
            'create_date' => $this->getCreateDate(),
            'user_id' => $this->getUserId(),
            'comment' => $this->getComment(),
            'status' => $this->getStatus(),
        ];
    }
}
