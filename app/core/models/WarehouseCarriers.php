<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class WarehouseCarriers
{
    public static function getCarriers(int $warehouse_id): array
    {
        return (new SafeMysql())->fetchArrayOneField("
            SELECT DISTINCT lcc.carrier_id
            FROM warehouse_carriers wc
                JOIN logistic_company_carriers lcc ON wc.lc_carrier_id = lcc.id
            WHERE wc.warehouse_id = :warehouse_id", ['warehouse_id' => $warehouse_id]);
    }

    public static function getAvailableCarrierList(int $warehouse_id): array
    {
        return (new SafeMysql())->fetchAll("
            SELECT DISTINCT lcc.carrier_id as id, CONCAT(c.name, ' (', c.code, ')') title
            FROM warehouse_carriers wc
                JOIN logistic_company_carriers lcc ON wc.lc_carrier_id = lcc.id
                JOIN carriers c ON lcc.carrier_id = c.id
                JOIN shipping_services ss ON lcc.carrier_id = ss.carrier_id
                JOIN shipping_service_warehouses ssw ON wc.warehouse_id = ssw.warehouse_id AND
                                                        ss.id = ssw.shipping_service_id
            WHERE wc.warehouse_id = :warehouse_id", ['warehouse_id' => $warehouse_id]);
    }

    public static function getAvailableCarriers(int $lc_id): array
    {
        return (new SafeMysql())->fetchAll("
            SELECT DISTINCT c.id, CONCAT(c.name, ' (', c.code, ')') title
            FROM logistic_company_carriers lcc
                JOIN carriers c ON lcc.carrier_id = c.id
            WHERE lcc.lc_id = :lc_id", ['lc_id' => $lc_id]);
    }
}
