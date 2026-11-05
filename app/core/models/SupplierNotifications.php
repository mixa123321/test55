<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class SupplierNotifications
{
    public static function getEmails($supplier_id, $type_id)
    {
        return (new SafeMysql())->fetchArrayOneField("
            SELECT name
            FROM supplier_notifications sn
                JOIN supplier_emails se ON sn.supplier_email_id = se.id AND se.supplier_id = :supplier_id AND se.is_active = 1
                JOIN supplier_notification_type_map sntm ON se.supplier_id = sntm.supplier_id AND sn.type_id = sntm.type_id
            WHERE sn.type_id = :type_id",
            ['supplier_id' => (int)$supplier_id, 'type_id' => (int)$type_id]);
    }
}
