<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\SKU;

class SKUWarehouses extends \app\core\models\SKUWarehouses
{
    public static function getMinStockLevel(SKU $SKU, $warehouse_id): int
    {
        return (int)(new SafeMysql())->fetchScalar("SELECT min_stock_level FROM SKU_warehouses 
                                                WHERE SKU_id = :SKU_id 
                                                  AND warehouse_id = :warehouse_id", [
            'SKU_id' => $SKU->getId(),
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function getMinStockLevels(int $SKU_id): array
    {
        return (new SafeMysql())->fetchAll("
            SELECT warehouse_id, min_stock_level as `value`
            FROM SKU_warehouses 
            WHERE SKU_id = :SKU_id", ['SKU_id' => $SKU_id]);
    }

    public static function setMinStockLevel($min_stock_level, SKU $SKU, $warehouse_id): void
    {
        $SKU_id = $SKU->getId();
        (new SafeMysql())->execute("INSERT INTO SKU_warehouses (SKU_id, warehouse_id, min_stock_level)
                                    VALUES (:SKU_id, :warehouse_id, :min_stock_level)
                                    ON DUPLICATE KEY UPDATE
                                        min_stock_level = :min_stock_level", [
            'SKU_id' => $SKU_id,
            'warehouse_id' => $warehouse_id,
            'min_stock_level' => $min_stock_level,
        ]);
    }

    public static function setCostCentre(int $SKU_id, int $warehouse_id, string $value): void
    {
        (new SafeMysql())->execute("INSERT INTO SKU_warehouses (SKU_id, warehouse_id, cost_centre)
                                    VALUES (:SKU_id, :warehouse_id, :value)
                                    ON DUPLICATE KEY UPDATE
                                        cost_centre = :value", [
            'SKU_id' => $SKU_id,
            'warehouse_id' => $warehouse_id,
            'value' => $value,
        ]);
    }

    public static function getCostCentre(int $SKU_id, int $warehouse_id): string
    {
        return (new SafeMysql())->fetchScalar("SELECT cost_centre FROM SKU_warehouses WHERE SKU_id = :SKU_id and warehouse_id = :warehouse_id", [
            'warehouse_id' => $warehouse_id,
            'SKU_id' => $SKU_id,
        ]) ?? '';
    }
}
