<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ShippingService;

class ShippingServices
{
    public static function getListForWarehouse($warehouse_id, $carriers)
    {
        $params = ['warehouse_id' => (int)$warehouse_id];
        $whereCarriers = '';
        if (!empty($carriers) && is_array($carriers)) {
            $whereCarriers = 'WHERE ss.carrier_id IN (:carriers)';
            $params['carriers'] = array_map('intval', $carriers);
        }
        return (new SafeMysql())->fetchAll("
            SELECT ss.*, ssw.*, c.code ccode, c.logo carrier_logo, c.is_internal, c.max_weight carrier_max_weight
            FROM shipping_services ss
                LEFT JOIN shipping_service_warehouses ssw ON ss.id = ssw.shipping_service_id AND ssw.warehouse_id = :warehouse_id
                JOIN carriers c ON ss.carrier_id = c.id
            $whereCarriers
            ORDER BY ss.id", $params);
    }

    public static function getById($service_id): ?ShippingService
    {
        $shipping_service = (new SafeMysql())->fetchOne("SELECT * FROM shipping_services WHERE id = :id", ['id' => (int)$service_id]);
        return !empty($shipping_service) ? new ShippingService($shipping_service) : null;
    }

    public static function getByNameAndCode($name, $code)
    {
        return (new SafeMysql())->fetchOne("SELECT * FROM shipping_services WHERE name = :name AND code = :code", ['name' => $name, 'code' => $code]);
    }

    public static function getByCode($code)
    {
        return (new SafeMysql())->fetchOne("SELECT * FROM shipping_services WHERE code = :code LIMIT 1", ['code' => $code]);
    }

    public static function getByCarrier($carrier_id)
    {
        return (new SafeMysql())->fetchAll("SELECT * FROM shipping_services WHERE carrier_id = :carrier_id ORDER BY id", ['carrier_id' => (int)$carrier_id]);
    }

    public static function getSelectedServicesIds($selected_services)
    {
        if (is_array($selected_services))
            $selected_services = implode('_', $selected_services);
        return array_map('intval', explode('_', $selected_services));
    }
}