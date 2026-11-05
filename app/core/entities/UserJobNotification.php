<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class UserJobNotification extends Entity
{

    private $id;
    private $user_job_id;
    private $msg;
    private $type;
    private $create_date;

    const TYPE_SUCCESS = 'success';
    const TYPE_WARNING = 'warning';
    const TYPE_DANGER = 'danger';

    const GROUP_TYPES = [
        self::TYPE_SUCCESS,
        self::TYPE_WARNING,
        self::TYPE_DANGER
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
    public function getUserJobId()
    {
        return $this->user_job_id;
    }

    /**
     * @param mixed $user_job_id
     */
    public function setUserJobId($user_job_id): void
    {
        $this->user_job_id = $user_job_id;
    }

    /**
     * @return mixed
     */
    public function getMsg()
    {
        return $this->msg;
    }

    /**
     * @param $msg
     */
    public function setMsg($msg): void
    {
        $this->msg = $msg;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param $type
     */
    public function setType($type): void
    {
        $this->type = $type;
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
            $this->setId(Mysql::saveRow("user_job_notifications", $data));
        } else {
            Mysql::saveRow("user_job_notifications", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'user_job_id' => $this->getUserJobId(),
            'msg' => $this->getMsg(),
            'type' => $this->getType(),
            'create_date' => $this->getCreateDate(),
        ];
    }
}
