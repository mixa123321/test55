<?php

namespace app\core\models;

use app\core\entities\Billing;
use app\core\dbal\SafeMysql;

class Billings
{
    /** @throws */
    public static function getCountFeeds($warehouse_id)
    {
        $sql = "
            SELECT COUNT(*) 
            FROM billing 
            WHERE DATE_FORMAT(created_at, '%Y-%m-%d') = :today 
            AND warehouse_id = :warehouse_id
        ";

        $res = (new SafeMysql())->fetchScalar($sql, ['today' => date('Y-m-d'), 'warehouse_id' => (int)$warehouse_id]);
        return (int)$res;
    }

    /** @throws */
    public static function getNewFeeds($warehouse_id)
    {
        $sql = "
            SELECT *
            FROM billing
            WHERE warehouse_id = :warehouse_id AND `status` = :status
        ";
        return (new SafeMysql())->fetchAll($sql, ['warehouse_id' => (int)$warehouse_id, 'status'       => Billing::NEW]);
    }

    /** @throws */
    public static function getById($id)
    {
        $sql = "SELECT * FROM billing WHERE id = :id LIMIT 1";
        $feed = (new SafeMysql())->fetchOne($sql, ['id' => (int)$id]);
        if (empty($feed))
            return null;
        return new Billing($feed);
    }
}
