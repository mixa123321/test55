<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Address;
use app\core\entities\RetainMeCredentials;
use app\core\entities\ReturnSupplierWarehouse;
use app\core\entities\SupplierWarehouse;
use app\core\helpers\Arr;
use app\core\services\crypto\StoredSecret;

class SuppliersWarehouses
{
    public static function getWarehouses($supplier_id)
    {
        $rows = (new SafeMysql())->fetchAssocWithKey(
            "SELECT * FROM suppliers_warehouses WHERE supplier_id = :supplier_id",
            ['supplier_id' => (int)$supplier_id],
            'warehouse_id'
        );
        foreach ($rows as $warehouse_id => $row)
            $rows[$warehouse_id] = self::decryptSecrets($row);
        return $rows;
    }

    public static function getSupplierWarehouse($warehouse_id, $supplier_id)
    {
        list($warehouse_id, $supplier_id) = self::toIntType($warehouse_id, $supplier_id);
        $row = (new SafeMysql())->fetchOne("
            SELECT *
            FROM suppliers_warehouses
            WHERE supplier_id = :supplier_id AND warehouse_id = :warehouse_id",
            ['supplier_id' => $supplier_id, 'warehouse_id' => $warehouse_id]);
        return is_array($row) ? self::decryptSecrets($row) : $row;
    }

    /**
     * Decrypts the StoredSecret-encrypted columns (SupplierWarehouse::SECRET_FIELDS) of a row that selected any of them.
     */
    public static function decryptSecrets(array $row): array
    {
        return StoredSecret::decryptFields($row, SupplierWarehouse::SECRET_FIELDS);
    }

    public static function getVAT($warehouse_id, $supplier_id)
    {
        list($warehouse_id, $supplier_id) = self::toIntType($warehouse_id, $supplier_id);
        return (new SafeMysql())->fetchScalar(
            "SELECT VAT FROM suppliers_warehouses WHERE supplier_id = :supplier_id AND warehouse_id = :warehouse_id",
            ['supplier_id' => $supplier_id, 'warehouse_id' => $warehouse_id]
        );
    }

    public static function getShippingAPIKey($warehouse_id, $supplier_id)
    {
        list($warehouse_id, $supplier_id) = self::toIntType($warehouse_id, $supplier_id);
        $key = (new SafeMysql())->fetchScalar(
            "SELECT shipping_api_key FROM suppliers_warehouses WHERE supplier_id = :supplier_id AND warehouse_id = :warehouse_id",
            ['supplier_id' => $supplier_id, 'warehouse_id' => $warehouse_id]
        );
        return is_string($key) ? StoredSecret::decrypt($key) : $key;
    }

    public static function getPackingSlipType($warehouse_id, $supplier_id, $shop_id = 0)
    {
        list($warehouse_id, $supplier_id) = self::toIntType($warehouse_id, $supplier_id);
        $packing_slip_type = (new SafeMysql())->fetchScalar(
            "SELECT packing_slip_type FROM suppliers_warehouses WHERE supplier_id = :supplier_id AND warehouse_id = :warehouse_id",
            ['supplier_id' => $supplier_id, 'warehouse_id' => $warehouse_id]
        ) ?: PackingSlips::TYPE_NONE;
        if ($packing_slip_type == PackingSlips::TYPE_RETAIN_ME && $shop_id) {
            $shop_warehouse = ShopWarehouses::getByShopWarehouse($shop_id, $warehouse_id);
            if (!empty($shop_warehouse) && $shop_warehouse->getIsActive())
                if (!$shop_warehouse->getIsRetainMe())
                    $packing_slip_type = PackingSlips::TYPE_GENERIC;
        }
        return $packing_slip_type;
    }

    public static function getRetainMeCredentials($warehouse_id, $supplier_id): RetainMeCredentials
    {
        list($warehouse_id, $supplier_id) = self::toIntType($warehouse_id, $supplier_id);
        $credentials = (new SafeMysql())->fetchScalar(
            "SELECT retainme_credentials FROM suppliers_warehouses WHERE supplier_id = :supplier_id AND warehouse_id = :warehouse_id",
            ['supplier_id' => $supplier_id, 'warehouse_id' => $warehouse_id]
        );
        return new RetainMeCredentials(!empty($credentials) ? json_decode(StoredSecret::decrypt($credentials)) : []);
    }

    public static function getVirtualCredentials($warehouse_id, $supplier_id)
    {
        list($warehouse_id, $supplier_id) = self::toIntType($warehouse_id, $supplier_id);
        // fetchOne, not fetchScalar: "no suppliers_warehouses row" (false) must differ from a NULL column
        // (no credentials yet -> []); SafeMysql::fetchScalar returns null for both, legacy Mysql returned false/null
        $row = (new SafeMysql())->fetchOne(
            "SELECT virtual_credentials FROM suppliers_warehouses WHERE supplier_id = :supplier_id AND warehouse_id = :warehouse_id",
            ['supplier_id' => $supplier_id, 'warehouse_id' => $warehouse_id]
        );
        if ($row === null)
            return false;
        return json_decode(StoredSecret::decrypt($row['virtual_credentials'] ?: '[]'), true);
    }

    public static function getRetainMe($warehouse_id, $supplier_id)
    {
        list($warehouse_id, $supplier_id) = self::toIntType($warehouse_id, $supplier_id);
        return (int)(new SafeMysql())->fetchScalar("
            SELECT packing_slip_type = 'retain_me'
            FROM suppliers_warehouses
            WHERE supplier_id = :supplier_id AND warehouse_id = :warehouse_id",
            ['supplier_id' => $supplier_id, 'warehouse_id' => $warehouse_id]);
    }

    public static function getAddress($warehouse_id, $supplier_id): Address
    {
        list($warehouse_id, $supplier_id) = self::toIntType($warehouse_id, $supplier_id);
        $address = (new SafeMysql())->fetchScalar(
            "SELECT address FROM suppliers_warehouses WHERE supplier_id = :supplier_id AND warehouse_id = :warehouse_id",
            ['supplier_id' => $supplier_id, 'warehouse_id' => $warehouse_id]
        );
        return new Address(!empty($address) ? json_decode($address) : []);
    }

    public static function getBillingAddress($warehouse_id, $supplier_id): Address
    {
        list($warehouse_id, $supplier_id) = self::toIntType($warehouse_id, $supplier_id);
        $address = (new SafeMysql())->fetchScalar(
            "SELECT billing_address FROM suppliers_warehouses WHERE supplier_id = :supplier_id AND warehouse_id = :warehouse_id",
            ['supplier_id' => $supplier_id, 'warehouse_id' => $warehouse_id]
        );
        return new Address(!empty($address) ? json_decode($address) : []);
    }

    public static function getShortData(int $warehouse_id, array $supplier_ids): array
    {
        if (empty($supplier_ids))
            return [];
        $data = (new SafeMysql())->fetchAll("
            SELECT sw.supplier_id, sw.sage_id, sup.name
            FROM suppliers_warehouses sw
                JOIN suppliers sup ON sw.supplier_id = sup.id
            WHERE supplier_id IN (:supplier_ids) AND warehouse_id = :warehouse_id",
            ['supplier_ids' => $supplier_ids, 'warehouse_id' => $warehouse_id]);
        if (empty($data))
            return [];
        return Arr::maps($data, 'supplier_id', ['sage_id', 'name']);
    }

    public static function checkReturnCarrier($return_carrier_id): bool
    {
        if (empty($return_carrier_id))
            return false;
        return !empty(ReturnCarriers::getById($return_carrier_id));
    }

    public static function updateReturnCarrier(SupplierWarehouse $supplierWarehouse, $return_carrier_id)
    {
        if (!empty(ReturnCarriers::getBySupplierWarehouse($supplierWarehouse)) || empty($return_carrier_id))
            (new SafeMysql())->execute('
                DELETE FROM return_supplier_warehouses 
                WHERE supplier_warehouse_id = :sw_id', ['sw_id' => $supplierWarehouse->getId()]);
        $return_supplier_warehouse = new ReturnSupplierWarehouse();
        $return_supplier_warehouse->setSupplierWarehouseId($supplierWarehouse->getId());
        $return_supplier_warehouse->setReturnCarrierId($return_carrier_id);
        $return_supplier_warehouse->save();
    }

    public static function checkIsUsedReturnCarrier(int $id): bool
    {
        return (bool)(new SafeMysql())->fetchScalar("
            SELECT 1
            FROM return_supplier_warehouses
            WHERE return_carrier_id = :id", ['id' => $id]);
    }

    public static function getUsedReturnCarriers(array $ids): array
    {
        if (!$ids)
            return [];
        $data = (new SafeMysql())->fetchAll("
            SELECT return_carrier_id
            FROM return_supplier_warehouses
            WHERE return_carrier_id IN (:ids)", ['ids' => array_map('intval', $ids)]);
        return array_values(array_map('intval', array_column($data, 'return_carrier_id')));
    }

    private static function toIntType($warehouse_id, $supplier_id): array
    {
        return array((int)$warehouse_id, (int)$supplier_id);
    }

    public static function getPriorityOrders($warehouse_id)
    {
        $suppliers = (new SafeMysql())->fetchAll("
            SELECT sw.id, sup.id supplier_id, sup.name, IFNULL(sw.priority_order, 0) priority, sup.warehouses
            FROM suppliers sup
                JOIN suppliers_warehouses sw ON sup.id = sw.supplier_id AND sw.warehouse_id = :warehouse_id
            ORDER BY sup.name", ['warehouse_id' => (int)$warehouse_id]);
        if (empty($suppliers))
            return [];
        $res = [];
        foreach ($suppliers as $supplier) {
            $warehouses = json_decode($supplier['warehouses'], true);
            if (empty($warehouses))
                continue;
            if (in_array($warehouse_id, $warehouses))
                $res[$supplier['id']] = [
                    'name' => $supplier['name'],
                    'supplier_id' => $supplier['supplier_id'],
                    'priority' => $supplier['priority']
                ];
        }
        return $res;
    }

    public static function setPriorityOrders($data)
    {
        try {
            $mysql = new SafeMysql();
            foreach ($data as $sw_id => $priority)
                $mysql->execute("UPDATE suppliers_warehouses SET priority_order = :priority WHERE id = :id", [
                    'priority' => $priority,
                    'id' => $sw_id,
                ]);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    public static function getSageId(int $sw_id)
    {
        return (new SafeMysql())->fetchScalar("SELECT sage_id FROM suppliers_warehouses WHERE id = :id", ['id' => $sw_id]);
    }
}