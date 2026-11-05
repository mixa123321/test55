<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class ASNHistories
{
    public static function getByASN($id, $supplier_id)
    {
        $sql = "SELECT
                     ah.create_date,
                     IFNULL(u.login, 'System') login,
                     (SELECT CONCAT(\" (\", p.name, \"#\", p.user_id, \")\") FROM persons p WHERE p.user_id = u.id) `name`,
                     ah.comment,
                     ah.status,
                     su.supplier_id,
                     s.id
                FROM ASN_history ah
                    LEFT JOIN suppliers s ON s.user_id = ah.user_id AND s.id = :supplier_id
                    LEFT JOIN users u ON u.id = ah.user_id
                    LEFT JOIN supplier_users su ON su.user_id = ah.user_id AND su.supplier_id = :supplier_id
                WHERE ah.ASN_id = :id
                ORDER BY ah.create_date DESC";

       return (new SafeMysql())->fetchAll($sql, [
            'id' => (int) $id,
            'supplier_id' => (int) $supplier_id,
        ]);
    }

    public static function getLastComment(int $id)
    {
        $sql = "SELECT ah.create_date last_date,
                       u.login,
                       p.name,
                       ah.user_id,
                       su.supplier_id,
                       s.id
                FROM ASN_history ah
                    JOIN users u ON u.id = ah.user_id
                    LEFT JOIN persons p ON u.id = p.user_id
                    LEFT JOIN supplier_users su ON su.user_id = ah.user_id
                    LEFT JOIN suppliers s ON s.user_id = ah.user_id
                WHERE ah.ASN_id = :id
                ORDER BY last_date DESC
                LIMIT 1";

        return (new SafeMysql())->fetchOne($sql, [
            'id' => $id
        ]);
    }

    public static function getCreateDate($id)
    {
        $id = (int)$id;
        $create_date = (new SafeMysql())->fetchScalar("
            SELECT create_date
            FROM ASN_history
            WHERE ASN_id = :id AND comment LIKE 'ASN created%'", ['id' => $id]);
        if (empty($create_date))
            return 0;

        return strtotime($create_date);
    }
}
