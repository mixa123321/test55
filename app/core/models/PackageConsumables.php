<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\PackageConsumable;
use app\core\helpers\Arr;

class PackageConsumables
{
    public static function isPackageConsumable($package_id, $consumable_id)
    {
        return (bool)(new SafeMysql())->fetchScalar("SELECT 1 FROM package_consumables WHERE package_id = :package_id AND consumable_id = :consumable_id",
            ['package_id' => (int)$package_id, 'consumable_id' => (int)$consumable_id]);
    }

    public static function getByPackageId($package_id)
    {
        $res = (new SafeMysql())->fetchAll("SELECT consumable_id, quantity FROM package_consumables WHERE package_id = :package_id",
            ['package_id' => (int)$package_id]);
        if (empty($res))
            return [];
        return Arr::map($res, 'consumable_id', 'quantity');
    }

    public static function getReuseByPackageId($package_id)
    {
        $res = (new SafeMysql())->fetchAll("
            SELECT pc.consumable_id, pc.quantity
            FROM package_consumables pc
                JOIN consumables con ON pc.consumable_id = con.id AND con.reuse = 1
            WHERE package_id = :package_id", ['package_id' => (int)$package_id]);
        if (empty($res))
            return [];
        return Arr::map($res, 'consumable_id', 'quantity');
    }

    public static function addPackageConsumables($package_id, $consumables)
    {
        if (empty($consumables))
            return;
        foreach ($consumables as $consumable_id => $quantity)
            if (!self::isPackageConsumable($package_id, $consumable_id)) {
                $package_consumable = new PackageConsumable([
                    'package_id' => $package_id,
                    'consumable_id' => $consumable_id,
                    'quantity' => $quantity,
                ]);
                $package_consumable->save(['package_id', 'consumable_id', 'quantity']);
            }
    }

    public static function updPackageConsumables($package_id, $consumables)
    {
        if (empty($consumables))
            return;
        $mysql = new SafeMysql();
        foreach ($consumables as $consumable_id => $quantity)
            if (self::isPackageConsumable($package_id, $consumable_id))
                $mysql->execute("UPDATE package_consumables SET quantity = :quantity WHERE package_id = :package_id AND consumable_id = :consumable_id", [
                    'quantity' => $quantity,
                    'package_id' => $package_id,
                    'consumable_id' => $consumable_id,
                ]);
    }

    public static function delPackageConsumables($package_id, $consumables)
    {
        if (empty($consumables))
            return;
        $mysql = new SafeMysql();
        foreach ($consumables as $consumable_id => $quantity)
            if (self::isPackageConsumable($package_id, $consumable_id))
                $mysql->execute("DELETE FROM package_consumables WHERE package_id = :package_id AND consumable_id = :consumable_id", [
                    'package_id' => $package_id,
                    'consumable_id' => $consumable_id,
                ]);
    }
}
