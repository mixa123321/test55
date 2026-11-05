<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\UserJobNotification;
use app\core\helpers\View;

class UserJobNotifications extends \app\core\models\UserJobNotifications
{
    public static function getByUserJobId($user_job_id, $type = null): array
    {
        $params = ['user_job_id' => $user_job_id];
        $sql = "SELECT * FROM user_job_notifications WHERE user_job_id = :user_job_id";
        if (in_array($type, UserJobNotification::GROUP_TYPES)) {
            $sql .= " AND `type` = :type";
            $params['type'] = $type;
        }
        $notes = (new SafeMysql())->fetchAll($sql, $params);
        if (empty($notes))
            return [];
        foreach ($notes as &$note) {
            if (!in_array($note['type'], UserJobNotification::GROUP_TYPES) )
                $note['type'] = UserJobNotification::TYPE_DANGER;
            $note['msg'] = View::toHtml($note['msg']);
        }
        return $notes;
    }
}
