<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class OrderQueue
{
    public static function getByIds($ids)
    {
        if (empty($ids))
            return [];
        if (!is_array($ids))
            $ids = [$ids];
        return (new SafeMysql())->fetchAll("SELECT * FROM order_queue WHERE order_id IN (:order_id)", ['order_id' => $ids]);
    }
}
