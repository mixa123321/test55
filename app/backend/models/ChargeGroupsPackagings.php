<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;

class ChargeGroupsPackagings
{
    public static function getPackaging(int $warehouse_id, int $id): array
    {
        if (!$id)
            return [];
        $data = (new SafeMysql())->fetchOne("
            SELECT cgp.*
            FROM charge_groups_packagings cgp
                JOIN charge_groups cg ON cgp.charge_group_id = cg.id AND cg.warehouse_id = :warehouse_id
            WHERE cgp.packaging_id = :id
            LIMIT 1", [
            'warehouse_id' => $warehouse_id,
            'id' => $id,
        ]);
        return $data ?: [];
    }

    public static function add(int $charge_group_id, int $id)
    {
        if (!$charge_group_id || !$id)
            return;
        (new SafeMysql())->execute("
            INSERT IGNORE INTO charge_groups_packagings (charge_group_id, packaging_id) VALUES (:charge_group_id, :packaging_id)", [
            'charge_group_id' => $charge_group_id,
            'packaging_id' => $id,
        ]);
    }

    public static function delete(int $warehouse_id, int $id)
    {
        if (!$warehouse_id || !$id)
            return;
        (new SafeMysql())->execute("
            DELETE cgp FROM charge_groups_packagings cgp
                JOIN charge_groups cg ON cgp.charge_group_id = cg.id AND cg.warehouse_id = :warehouse_id
            WHERE cgp.packaging_id = :packaging_id", [
            'warehouse_id' => $warehouse_id,
            'packaging_id' => $id,
        ]);
    }

    public static function default(int $packaging_id, int $charge_group_id)
    {
        if (!$packaging_id || !$charge_group_id)
            return;
        $mysql = new SafeMysql();
        $mysql->execute("
            UPDATE charge_groups_packagings
            SET is_default = 0
            WHERE charge_group_id = :charge_group_id", [
            'charge_group_id' => $charge_group_id,
        ]);
        $mysql->execute("
            UPDATE charge_groups_packagings
            SET is_default = 1
            WHERE charge_group_id = :charge_group_id AND packaging_id = :packaging_id", [
            'charge_group_id' => $charge_group_id,
            'packaging_id' => $packaging_id,
        ]);
    }
}