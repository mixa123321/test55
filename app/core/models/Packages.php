<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Consumable;
use app\core\entities\Package;
use app\core\helpers\View;

class Packages
{
    public static function getByNameAndSupplierId($name, $supplier_id)
    {
        return (int)(new SafeMysql())->fetchScalar("SELECT id FROM packages WHERE name = :name AND supplier_id = :supplier_id LIMIT 1",
            ['name' => $name, 'supplier_id' => (int)$supplier_id]);
    }

    public static function getById($id): Package
    {
        static $packages = [];
        if (!array_key_exists($id, $packages)) {
            $data = (new SafeMysql())->fetchOne('SELECT * FROM packages WHERE id = :id', ['id' => (int)$id]);
            if ($data)
                $packages[$id] = new Package($data);
            else
                $packages[$id] = null;
        }
        return $packages[$id];
    }

    public static function getAllPackages($supplier_id, $warehouse_id, $state = -1)
    {
        $warehouse_id = (int)$warehouse_id;
        if (!$warehouse_id)
            return [];
        $whereArr = [];
        if ($state != -1)
            $whereArr[] = 'p.state = ' . (int)$state;
        if (is_array($supplier_id)) {
            if (!empty($supplier_id))
                $whereArr[] = 'p.supplier_id IN (' . implode(', ', array_map('intval', $supplier_id)) . ')';
        } elseif ($supplier_id > 0)
            $whereArr[] = 'p.supplier_id = ' . (int)$supplier_id;
        $whereSQL = empty($whereArr) ? '' : ('WHERE ' . implode(' AND ', $whereArr));
        return (new SafeMysql())->fetchAll("
            SELECT p.id, p.name, con.width y, con.length x, con.height z, con.max_weight weight, con.weight box_weight, p.instruction, p.supplier_id, p.state, p.barcode,
                   (SELECT s.name FROM suppliers s WHERE s.id = p.supplier_id) as supplier
            FROM packages p
                JOIN consumables con ON p.packaging_id = con.id AND con.status = 1
                JOIN package_warehouses pw ON p.id = pw.package_id AND pw.warehouse_id = :warehouse_id
            {$whereSQL}
            ORDER BY p.supplier_id DESC", ['warehouse_id' => $warehouse_id]);
    }

    public static function getDefaultPackageId($supplier_id, $warehouse_id)
    {
        $supplier_id = (int)$supplier_id;
        $warehouse_id = (int)$warehouse_id;
        return (new SafeMysql())->fetchScalar("
            SELECT p.id
            FROM packages p 
                JOIN package_warehouses pw ON p.id = pw.package_id AND pw.warehouse_id = :warehouse_id AND pw.`default` = 1
            WHERE p.supplier_id = :supplier_id",
            ['warehouse_id' => $warehouse_id, 'supplier_id' => $supplier_id]);
    }

    public static function getPackages($supplier_id, $warehouse_id)
    {
        $sql = "SELECT p.id, p.name, p.barcode, p.instruction, p.state,
                       cc.id c_id, cp.name pname, cp.width, cp.length, cp.height, cp.weight, cp.max_weight,
                       w.id w_id, w.name wname, w.is_virtual, cc.name cname, pc.quantity, pw.`default`, cc.type 
                FROM packages p
                    LEFT JOIN package_warehouses pw ON p.id = pw.package_id
                    LEFT JOIN warehouses w ON pw.warehouse_id = w.id
                    JOIN consumables cp ON p.packaging_id = cp.id AND cp.type = " . Consumable::TYPE_PACKAGING . "
                    LEFT JOIN package_consumables pc ON p.id = pc.package_id
                    LEFT JOIN consumables cc ON pc.consumable_id = cc.id 
                WHERE p.supplier_id = $supplier_id";
        $packages = (new SafeMysql())->fetchAll("SELECT p.id, p.name, p.barcode, p.instruction, p.state,
                       cc.id c_id, cp.name pname, cp.width, cp.length, cp.height, cp.weight, cp.max_weight,
                       w.id w_id, w.name wname, w.is_virtual, cc.name cname, pc.quantity, pw.`default`, cc.type 
                FROM packages p
                    LEFT JOIN package_warehouses pw ON p.id = pw.package_id
                    LEFT JOIN warehouses w ON pw.warehouse_id = w.id
                    JOIN consumables cp ON p.packaging_id = cp.id AND cp.type = :cp_type
                    LEFT JOIN package_consumables pc ON p.id = pc.package_id
                    LEFT JOIN consumables cc ON pc.consumable_id = cc.id 
                WHERE p.supplier_id = :supplier_id",
            ['cp_type' => Consumable::TYPE_PACKAGING, 'supplier_id' => (int)$supplier_id]);
        $res = [];
        foreach ($packages as $package) {
            $package_id = $package['id'];
            if (empty($res[$package_id]))
                $res[$package_id] = [
                    'name' => $package['name'],
                    'barcode' => $package['barcode'],
                    'instruction' => $package['instruction'],
                    'pname' => $package['pname'],
                    'sizes' => "{$package['length']} x {$package['width']} x {$package['height']}",
                    'weight' => $package['weight'],
                    'max_weight' => $package['max_weight'],
                    'state' => $package['state'],
                    'warehouses' => [],
                    'consumables' => [],
                    'in_package' => [],
                ];
            if (!empty($package['w_id']) && !$package['is_virtual']) {
                $res[$package_id]['warehouses'][$package['w_id']] = $package['w_id'] == $warehouse_id ? '<b>' . View::toHtml($package['wname']) . '</b>' : View::toHtml($package['wname']);
                if ($package['w_id'] == $warehouse_id)
                    $res[$package_id]['default'] = $package['default'];
            }
            if (!empty($package['cname'])) {
                $type = $package['type'] == Consumable::TYPE_CONSUMABLE ? 'consumables' : 'in_package';
                $res[$package_id][$type][$package['c_id']] = "{$package['cname']} x {$package['quantity']}";
            }
        }
        return $res;
    }

    public static function getPackagesByPackagingId($packaging_id)
    {
        $packaging_id = (int)$packaging_id;
        return (new SafeMysql())->fetchAll("
            SELECT p.id, CONCAT(p.name, ' (', s.name, ')') name
            FROM packages p
                JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.packaging_id = :packaging_id", ['packaging_id' => $packaging_id]);
    }

    public static function bulkLockPackages($package_ids)
    {
        if (!is_array($package_ids) || empty($package_ids))
            return false;
        // execute() returns the affected rows; a separate prepared SELECT ROW_COUNT() is not reliable
        return (new SafeMysql())->execute('UPDATE packages SET state = 0 WHERE id IN (:ids)', ['ids' => $package_ids]);
    }

    public static function getOrderPackageConsumables($order_id): array
    {
        return (new SafeMysql())->fetchAll('SELECT opc.* FROM orders_packages_consumables opc
                                            JOIN orders_packages op ON op.id = opc.order_package_id AND op.order_id = :order_id',
            ['order_id' => (int)$order_id]);
    }
}