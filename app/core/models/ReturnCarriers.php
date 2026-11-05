<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ReturnCarrier;
use app\core\entities\SupplierWarehouse;

class ReturnCarriers
{
    public static function getAll(int $lc_id): array
    {
        $res = (new SafeMysql())->fetchAll("
            SELECT rc.*
            FROM return_carriers rc
                JOIN logistic_company_return_carriers lcrc ON rc.id = lcrc.return_carrier_id AND lcrc.lc_id = :lc_id",
            ['lc_id' => $lc_id]);
        return array_map(function ($data) {
            return new ReturnCarrier($data);
        }, $res);
    }

    public static function getById($id)
    {
        $data = (new SafeMysql())->fetchOne('SELECT * FROM return_carriers WHERE id = :id', ['id' => (int)$id]);
        return empty($data) ? null : new ReturnCarrier($data);
    }

    public static function getBySupplierWarehouse(SupplierWarehouse $supplierWarehouse): ?ReturnCarrier
    {
        return self::getBySupplierWarehouseId($supplierWarehouse->getId());
    }

    public static function getBySupplierWarehouseId($supplier_warehouse_id): ?ReturnCarrier
    {
        $data = (new SafeMysql())->fetchAll("SELECT rc.*
                FROM return_carriers rc 
                    JOIN return_supplier_warehouses rsw ON rc.id = rsw.return_carrier_id AND rsw.supplier_warehouse_id = :sw_id",
            ['sw_id' => (int)$supplier_warehouse_id]);
        if (empty($data) || count($data) != 1)
            return null;
        return new ReturnCarrier($data[0]);
    }

    public static function getCount(int $lc_id): int
    {
        return (int) (new SafeMysql())->fetchScalar("
            SELECT count(*)
            FROM return_carriers rc
                JOIN logistic_company_return_carriers lcrc ON rc.id = lcrc.return_carrier_id AND lcrc.lc_id = :lc_id",
            ['lc_id' => $lc_id]);
    }

    public static function getWithLimit(int $lc_id, int $offset, int $count): array
    {
        $data = [];
        foreach ((new SafeMysql())->fetchAll("
            SELECT rc.id, rc.service_id, rc.name, rc.credentials_key, rc.is_test, rc.countries, rc.state
            FROM return_carriers rc 
                JOIN logistic_company_return_carriers lcrc ON rc.id = lcrc.return_carrier_id AND lcrc.lc_id = :lc_id
            LIMIT " . $offset . ", " . $count,
            ['lc_id' => $lc_id]) as $item)
            $data[] = new ReturnCarrier($item);
        return $data;
    }

    /**
     * @param ReturnCarrier[] $returnCarriers
     * @return int[]
     */
    public static function getIdsFromReturnCarriers(array $returnCarriers): array {
        return array_map(function ($item) {
            return (int)$item->getId();
        }, $returnCarriers);
    }

    public static function isNameDuplicated(string $name, int $id = 0): bool
    {
        $params = ['name' => $name];
        $where = '';
        if ($id) {
            $where = "AND id <> :id";
            $params['id'] = $id;
        }
        return (bool)(new SafeMysql())->fetchScalar("SELECT 1 FROM return_carriers WHERE name = :name $where", $params);
    }
}
