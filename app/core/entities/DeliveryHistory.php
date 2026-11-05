<?php

namespace app\core\entities;

use app\core\db\Mysql;

class DeliveryHistory extends Entity
{

    private $id;
    private $create_date;
    private $delivery_id;
    private $comment;
    private $fields;
    private $user_id;

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
    public function getCreateDate()
    {
        return $this->create_date;
    }

    /**
     * @param mixed $create_date
     */
    public function setCreateDate($create_date): void
    {
        $this->create_date = $create_date;
    }

    /**
     * @return mixed
     */
    public function getDeliveryId()
    {
        return $this->delivery_id;
    }

    /**
     * @param mixed $delivery_id
     */
    public function setDeliveryId($delivery_id): void
    {
        $this->delivery_id = $delivery_id;
    }

    /**
     * @return mixed
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param mixed $comment
     */
    public function setComment($comment): void
    {
        $this->comment = $comment;
    }

    /**
     * @return mixed
     */
    public function getFields()
    {
        return json_decode($this->fields);
    }

    /**
     * @param mixed $fields
     */
    public function setFields($fields): void
    {
        $this->fields = json_encode($fields);
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
        Mysql::saveRow("delivery_history", $data);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'create_date' => $this->getCreateDate(),
            'delivery_id' => $this->getDeliveryId(),
            'comment' => $this->getComment(),
            'fields' => $this->getFields(),
            'user_id' => $this->getUserId(),
        ];
    }
}