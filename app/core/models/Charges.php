<?php

namespace app\core\models;

use app\core\entities\Charge;
use app\core\dbal\SafeMysql;

class Charges
{
    public static function getAll(bool $is_standard = true): array
    {
        if ($is_standard) {
            $sql = "SELECT * FROM charges WHERE type ORDER BY id";
            $params = [];
        } else {
            $sql = "SELECT * FROM charges WHERE type = :type ORDER BY id";
            $params = ['type' => 0];
        }
        $charges = (new SafeMysql())->fetchAll($sql, $params);
        if (!$charges)
            return [];
        return $charges;
    }

    public static function getById(int $id): ?Charge
    {
        $row = (new SafeMysql())->fetchOne("SELECT * FROM charges WHERE id = :id", ['id' => $id]);
        if (!$row)
            return null;
        return new Charge($row);
    }

    public static function getIdByCode(string $code, bool $is_standard = true): int
    {
        $sql = "SELECT id FROM charges WHERE code = :code";
        $params = ['code' => $code,];
        $sql .= $is_standard ? " AND type" : " AND type = :type";
        if (!$is_standard) 
            $params['type'] = 0;
        $sql .= " ORDER BY type LIMIT 1";
        $row = (new SafeMysql())->fetchOne($sql, $params);
        return $row ? (int)$row['id'] : 0;
    }

    public static function getByCode(string $code, bool $is_standard = true)
    {
        $sql = "SELECT * FROM charges WHERE code = :code";
        $params = ['code' => $code,];
        $sql .= $is_standard ? " AND type" : " AND type = :type";
        if (!$is_standard)
            $params['type'] = 0;
        $sql .= " ORDER BY type LIMIT 1";
        return (new SafeMysql())->fetchOne($sql, $params);
    }
}
