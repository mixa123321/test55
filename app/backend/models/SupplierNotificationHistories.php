<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\SupplierEmail;
use app\core\entities\SupplierNotificationHistory;

class SupplierNotificationHistories extends \app\core\models\SupplierNotificationHistories
{
    public static function addNotification($type_id, $supplier_id, $params)
    {
        $data = [
            'type_id' => $type_id,
            'supplier_id' => $supplier_id,
            'type' => SupplierNotificationHistory::TYPE_NOTIFICATION_CREATE,
            'additional' => json_encode($params),
            'create_date' => time(),
            'user_id' => -1
        ];
        $obj = new SupplierNotificationHistory($data);
        $obj->save(array_keys($data));
    }

    public static function sendNotification($type_id, $params)
    {
        $data = [
            'type_id' => $type_id,
            'supplier_id' => -1,
            'type' => SupplierNotificationHistory::TYPE_NOTIFICATION_SEND,
            'additional' => json_encode($params),
            'create_date' => time(),
            'user_id' => -1
        ];
        $obj = new SupplierNotificationHistory($data);
        $obj->save(array_keys($data));
    }

    public static function addSupplierEmail(SupplierEmail $supplier_email, $user_id)
    {
        $data = [
            'supplier_id' => $supplier_email->getSupplierId(),
            'type' => SupplierNotificationHistory::TYPE_CREATE_NEW_EMAIL,
            'additional' => json_encode([
                'id' => $supplier_email->getId(),
                'name' => $supplier_email->getName()
            ]),
            'create_date' => time(),
            'user_id' => $user_id
        ];
        $obj = new SupplierNotificationHistory($data);
        $obj->save(array_keys($data));
    }

    public static function removeSupplierEmail(SupplierEmail $supplier_email, $user_id)
    {
        $data = [
            'supplier_id' => $supplier_email->getSupplierId(),
            'type' => SupplierNotificationHistory::TYPE_REMOVE_NEW_EMAIL,
            'additional' => json_encode([
                'id' => $supplier_email->getId(),
                'name' => $supplier_email->getName()
            ]),
            'create_date' => time(),
            'user_id' => $user_id
        ];
        $obj = new SupplierNotificationHistory($data);
        $obj->save(array_keys($data));
    }

    public static function changeSupplierEmail($supplier_id, $user_id, $addition)
    {
        $data = [
            'supplier_id' => $supplier_id,
            'type' => SupplierNotificationHistory::TYPE_CHANGE_EMAIL,
            'additional' => json_encode($addition),
            'create_date' => time(),
            'user_id' => $user_id
        ];
        $obj = new SupplierNotificationHistory($data);
        $obj->save(array_keys($data));
    }

    public static function changeSupplierEmailForType($type_id, $supplier_email_id, $activate, $user_id)
    {
        $supplier_id = (new SafeMysql())->fetchScalar('SELECT supplier_id FROM supplier_emails WHERE id = :id', ['id' => $supplier_email_id]);
        $data = [
            'type_id' => $type_id,
            'supplier_id' => $supplier_id,
            'type' => SupplierNotificationHistory::TYPE_CHANGE_EMAIL_FOR_TYPE,
            'additional' => json_encode([
                'supplier_email_id' => $supplier_email_id,
                'activate' => $activate
            ]),
            'create_date' => time(),
            'user_id' => $user_id
        ];
        $obj = new SupplierNotificationHistory($data);
        $obj->save(array_keys($data));
    }

    public static function activateSupplierForType($supplier_id, $type_id, $user_id)
    {
        $data = [
            'type_id' => $type_id,
            'supplier_id' => $supplier_id,
            'type' => SupplierNotificationHistory::TYPE_ACTIVATE_SUPPLIER_FOR_TYPE,
            'create_date' => time(),
            'user_id' => $user_id
        ];
        $obj = new SupplierNotificationHistory($data);
        $obj->save(array_keys($data));
    }

    public static function deactivateSupplierForType($supplier_id, $type_id, $user_id)
    {
        $data = [
            'type_id' => $type_id,
            'supplier_id' => $supplier_id,
            'type' => SupplierNotificationHistory::TYPE_DEACTIVATE_SUPPLIER_FOR_TYPE,
            'create_date' => time(),
            'user_id' => $user_id,
        ];
        $obj = new SupplierNotificationHistory($data);
        $obj->save(array_keys($data));
    }
}
