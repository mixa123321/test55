<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\backend\models\SKUHistories;
use app\core\entities\SKU;

class SKUUsages
{
    private const IDENT = 'SKU_id';

    private const ASN_SKUS = 'ASN_SKUs';
    private const ASN_TEMPORARY = 'ASN_temporary';
    private const BATCHES = 'batches';
    private const BUNDLES_SKUS = 'bundles_SKUs';
    private const MOVEMENT = 'movement';
    private const MOVEMENTS = 'movements';
    private const ORDER_LOCATION_TEMPORARY = 'order_location_temporary';
    private const ORDER_SKUS = 'order_SKUs';
    private const ORDERS_PACKAGES_SKUS = 'orders_packages_SKUs';
    private const SHOP_INVENTORY_LOGS = 'shop_inventory_logs';
    private const SKU_CHANNEL_SUPPLIERS = 'SKU_channel_suppliers';
    private const SKU_HISTORY = 'SKU_history';
    private const SKU_PREFF_LOCS = 'SKU_preff_locs';
    private const SKU_LOCATIONS = 'SKU_locations';
    private const SKU_WAREHOUSES = 'SKU_warehouses';
    private const SKU_BARCODES = 'SKU_barcodes';

    private const USAGE = [
        self::ASN_SKUS => 'SKU was used in ASNs',
        self::ASN_TEMPORARY => 'SKU is using in active ASNs',
        self::BATCHES => 'SKU was used in batches',
        self::BUNDLES_SKUS => 'SKU was used in bundles',
        self::MOVEMENTS => 'SKU was used in movements',
        self::ORDER_LOCATION_TEMPORARY => 'SKU is using in active orders',
        self::ORDER_SKUS => 'SKU was used in orders',
        self::ORDERS_PACKAGES_SKUS => 'SKU was used in packages',
        self::SKU_CHANNEL_SUPPLIERS => 'SKU is assigned to channels',
        self::SKU_PREFF_LOCS => 'SKU is assigned to locations',
        self::SKU_LOCATIONS => 'SKU is in locations',
    ];

    private const EXCEPT = [
        self::MOVEMENT => 'SKU was cleared from movement history',
        self::SHOP_INVENTORY_LOGS => 'SKU was cleared from shop inventory logs',
        self::SKU_HISTORY => 'SKU was cleared from SKU history',
        self::SKU_WAREHOUSES => 'SKU was cleared from SKU settings',
        self::SKU_BARCODES => 'SKU was cleared from SKU barcodes',
    ];

    public static function get(int $SKU_id): array
    {
        $usage = [];
        foreach (self::USAGE as $table => $message)
            if (!self::checkTable($SKU_id, $table))
                $usage[] = $message;

        return [empty($usage) ,$usage];
    }

    public static function remove(int $SKU_id, $user_id, $warehouse_id): array
    {
        $SKU = SKUs::getById($SKU_id);
        if (empty($SKU))
            return [false, []];
        // Clear
        $cleared = [];
        $dump = self::getDump($SKU);
        foreach (self::EXCEPT as $table => $message)
            if (!self::checkTable($SKU_id, $table)) {
                self::clearTable($SKU_id, $table);
                $cleared[] = $message;
            }
        SKUHistories::hardRemove($SKU_id, $user_id, $warehouse_id, $dump);
        SKUHistories::clearStock($SKU_id, $warehouse_id);
        // REMOVE
        (new SafeMysql())->execute("DELETE FROM SKUs WHERE id = :id", ['id' => $SKU_id]);
        return [true, $cleared];
    }

    public static function getDump(SKU $SKU): array
    {
        return [
            'SKU' => $SKU->toArray(),
            'settings' => (new SafeMysql())->fetchAll('SELECT * FROM ' . self::SKU_WAREHOUSES . ' WHERE SKU_id = :id', ['id' => (int)$SKU->getId()]),
            'barcodes' => (new SafeMysql())->fetchAll('SELECT * FROM ' . self::SKU_BARCODES . ' WHERE SKU_id = :id', ['id' => (int)$SKU->getId()]),
        ];
    }

    private static function checkTable(int $SKU_id, $table): bool
    {
        return !(new SafeMysql())->fetchScalar("SELECT 1 FROM {$table} WHERE SKU_id = :SKU_id LIMIT 1", ['SKU_id' => $SKU_id]);
    }

    private static function clearTable(int $SKU_id, $table)
    {
        $ident = self::IDENT;
        (new SafeMysql())->execute("DELETE FROM {$table} WHERE {$ident} = :SKU_id", ['SKU_id' => $SKU_id]);
    }
}
