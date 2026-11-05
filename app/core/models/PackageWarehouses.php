<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\backend\models\Packages;
use app\core\entities\PackageWarehouse;
use app\core\helpers\Arr;

class PackageWarehouses
{
    public static function isPackageWarehouse($package_id, $warehouse_id)
    {
        return (bool)(new SafeMysql())->fetchScalar("SELECT 1 FROM package_warehouses WHERE package_id = :package_id AND warehouse_id = :warehouse_id",
            ['package_id' => (int)$package_id, 'warehouse_id' => (int)$warehouse_id]);
    }

    public static function setDefaultPackage($package_id, $supplier_id, $warehouse_id)
    {
        $package_id = (int)$package_id;
        $supplier_id = (int)$supplier_id;
        $warehouse_id = (int)$warehouse_id;
        $packages = array_keys(Packages::getPackages($supplier_id, $warehouse_id));
        $mysql = new SafeMysql();
        $mysql->execute("UPDATE package_warehouses SET `default` = 0 WHERE package_id IN (:package_ids) AND warehouse_id = :warehouse_id", [
            'package_ids' => $packages,
            'warehouse_id' => $warehouse_id,
        ]);
        if (self::isPackageWarehouse($package_id, $warehouse_id))
            $mysql->execute("UPDATE package_warehouses SET `default` = 1 WHERE package_id = :package_id AND warehouse_id = :warehouse_id", [
                'package_id' => $package_id,
                'warehouse_id' => $warehouse_id,
            ]);
        else {
            $package_warehouse = new PackageWarehouse([
                'package_id' => $package_id,
                'warehouse_id' => $warehouse_id,
                'default' => 1,
            ]);
            $package_warehouse->save(['package_id', 'warehouse_id', 'default']);
        }
    }

    public static function addPackageWarehouse($package_id, $warehouse_id)
    {
        if (self::isPackageWarehouse($package_id, $warehouse_id))
            return;
        $package_warehouse = new PackageWarehouse([
            'package_id' => $package_id,
            'warehouse_id' => $warehouse_id,
        ]);
        $package_warehouse->save(['package_id', 'warehouse_id']);
    }

    public static function delPackageWarehouse($package_id, $warehouse_id)
    {
        if (!self::isPackageWarehouse($package_id, $warehouse_id))
            return;
        (new SafeMysql())->execute("DELETE FROM package_warehouses WHERE package_id = :package_id AND warehouse_id = :warehouse_id", [
            'package_id' => $package_id,
            'warehouse_id' => $warehouse_id,
        ]);
    }

    public static function getWarehousesByPackageId($package_id)
    {
        $res = (new SafeMysql())->fetchAll("SELECT warehouse_id, 1 exist FROM package_warehouses WHERE package_id = :package_id",
            ['package_id' => (int)$package_id]);
        if (empty($res))
            return [];
        return Arr::map($res, 'warehouse_id', 'exist');
    }
}
