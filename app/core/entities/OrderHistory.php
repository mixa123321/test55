<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class OrderHistory extends Entity
{
    private $id;
    private $order_id;
    private $create_date;
    private $user_id;
    private $comment;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getOrderId()
    {
        return $this->order_id;
    }

    public function setOrderId($order_id)
    {
        $this->order_id = (int)$order_id;
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
        Mysql::saveRow("order_history", $data);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'order_id' => $this->getOrderId(),
            'create_date' => $this->getCreateDate(),
            'user_id' => $this->getUserId(),
            'comment' => $this->getComment(),
        ];
    }
}