<?php

namespace app\core\models;

use app\core\entities\UserJobNotification;

class UserJobNotifications
{

    public static function addNew($user_job_id, $msg, $type)
    {
        $userJobNotification = new UserJobNotification();
        $userJobNotification->setUserJobId($user_job_id);
        $userJobNotification->setMsg($msg);
        $userJobNotification->setType($type);
        $userJobNotification->setCreateDate(time());
        $userJobNotification->save(['user_job_id', 'msg', 'type', 'create_date']);
    }

    public static function addNewSuccess($user_job_id, $msg)
    {
        self::addNew($user_job_id, $msg, UserJobNotification::TYPE_SUCCESS);
    }

    public static function addNewWarning($user_job_id, $msg)
    {
        self::addNew($user_job_id, $msg, UserJobNotification::TYPE_WARNING);
    }

    public static function addNewDanger($user_job_id, $msg)
    {
        self::addNew($user_job_id, $msg, UserJobNotification::TYPE_DANGER);
    }

}
