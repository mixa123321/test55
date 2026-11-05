<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Webhook extends Entity
{
    private $id;
    private $name;
    private $callback;
    private $update_date;
    private $user_id;
    private $subscribed;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($txt)
    {
        $this->name = (string)$txt;
    }

    public function getCallback()
    {
        return $this->callback;
    }

    public function setCallback($url)
    {
        $this->callback = (string)$url;
    }

    public function getUpdateDate()
    {
        return is_null($this->update_date) ? null : $this->update_date;
    }

    public function setUpdateDate($update_date)
    {
        $this->update_date = $update_date;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($id)
    {
        $this->user_id = (int)$id;
    }

    public function getSubscribed()
    {
        return $this->subscribed;
    }

    public function setSubscribed($status)
    {
        $this->subscribed = (int)$status;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('webhooks', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        $return = [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'callback' => $this->getCallback(),
            'update_date' => $this->getUpdateDate(),
            'user_id' => $this->getUserId(),
            'subscribed' => $this->getSubscribed(),
        ];
        return $return;
    }
}