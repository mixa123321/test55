<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;

class SupplierNotificationTypeMap extends \app\core\models\SupplierNotificationTypeMap
{
    public static function getTypesBySupplier($supplier_id)
    {
        return (new SafeMysql())->fetchAll("SELECT t.id, t.name, IF(sntm.supplier_id, 1, 0) as is_active FROM supplier_notification_types t
                                            LEFT JOIN supplier_notification_type_map sntm on t.id = sntm.type_id AND sntm.supplier_id = :supplier_id", [
            'supplier_id' => $supplier_id
        ]);
    }

    public static function unsetActiveListForSupplier($supplier_id, $type_ids, $user_id)
    {
        $supplier_id = (int)$supplier_id;
        if (!empty($type_ids)) {
            array_walk($type_ids, function(&$t_id) use ($user_id, $supplier_id) {
                $t_id = (int)$t_id;
                SupplierNotificationHistories::deactivateSupplierForType($supplier_id, $t_id, $user_id);
            });
            $type_sql = implode(', ', $type_ids);
            (new SafeMysql())->execute("DELETE FROM supplier_notification_type_map WHERE supplier_id = {$supplier_id} AND type_id IN ({$type_sql})");
        }
    }

    public static function setActiveListForSupplier($supplier_id, $type_ids, $user_id)
    {
        $supplier_id = (int)$supplier_id;
        if (!empty($type_ids)) {
            $typeArr = [];
            foreach ($type_ids as $type_id) {
                $typeArr[] = '(' . (int)$type_id . ', ' . $supplier_id . ')';
                SupplierNotificationHistories::activateSupplierForType($supplier_id, (int)$type_id, $user_id);
            }
            $type_sql = implode(', ', $typeArr);
            (new SafeMysql())->execute("INSERT INTO supplier_notification_type_map (type_id, supplier_id) 
                                    VALUES " . $type_sql);
        }
    }
}
