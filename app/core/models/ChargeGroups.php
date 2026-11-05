<?php

namespace app\core\models;

use app\core\entities\ChargeGroup;
use app\core\dbal\SafeMysql;

class ChargeGroups
{
    public static function getById(int $id): ?ChargeGroup
    {
        $group = (new SafeMysql())->fetchOne("SELECT * FROM charge_groups WHERE id = :id", ['id' => $id]);
        if (!$group)
            return null;
        return new ChargeGroup($group);
    }

    public static function getAll(int $warehouse_id): array
    {
        $rows = (new SafeMysql())->fetchAll("SELECT * FROM charge_groups WHERE warehouse_id = :warehouse_id", ['warehouse_id' => $warehouse_id]);
        if (!$rows)
            return [];
        $groups = [];
        foreach ($rows as $row) {
            $groups[] = new ChargeGroup($row);
        }
        return $groups;
    }

    public static function getIdByCode(string $code, int $warehouse_id): int
    {
        $row = (new SafeMysql())->fetchOne(
            "SELECT id FROM charge_groups WHERE code = :code AND warehouse_id = :warehouse_id LIMIT 1", ['code' => $code, 'warehouse_id' => $warehouse_id]);
        return $row ? (int)$row['id'] : 0;
    }
}