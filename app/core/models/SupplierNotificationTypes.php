<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\SupplierNotificationType;

class SupplierNotificationTypes
{
    public static function getById($id)
    {
        $SNT_data = (new SafeMysql())->fetchOne("SELECT * FROM supplier_notification_types WHERE id = :id", ['id' => (int)$id]);
        if (empty($SNT_data))
            return null;
        return new SupplierNotificationType($SNT_data);
    }

    public static function getAll()
    {
        return (new SafeMysql())->fetchAll("SELECT * FROM supplier_notification_types");
    }
}
