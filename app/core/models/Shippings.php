<?php

namespace app\core\models;

use app\backend\models\Orders as OrdersModel;
use app\backend\models\OrderSKUs as OrderSKUsModel;
use app\core\dbal\SafeMysql;
use app\core\entities\Order;
use app\core\entities\Shipping;
use app\core\helpers\Arr;

class Shippings
{
    const TYPE_TERM_DEFAULT = 0;
    const TYPE_TERM_DDU = 1;
    const TYPE_TERM_DDP = 2;

    const TYPE_TERM_DEFAULT_NAME = 'Default';
    const TYPE_TERM_DDU_NAME = 'DDU';
    const TYPE_TERM_DDP_NAME = 'DDP';

    const TERMS = [
        self::TYPE_TERM_DEFAULT => self::TYPE_TERM_DEFAULT_NAME,
        self::TYPE_TERM_DDU => self::TYPE_TERM_DDU_NAME,
        self::TYPE_TERM_DDP => self::TYPE_TERM_DDP_NAME,
    ];

    public static function getTermsValue($terms): string
    {
     return empty($terms) || $terms == self::TYPE_TERM_DEFAULT || !array_key_exists($terms, self::TERMS)
                                 ? ''
                                 : self::TERMS[$terms];
    }

    public static function getTermsKey($term_name): int
    {
        if ($term_name === Shippings::TYPE_TERM_DDU_NAME)
            return Shippings::TYPE_TERM_DDU;
        if ($term_name === Shippings::TYPE_TERM_DDP_NAME)
            return Shippings::TYPE_TERM_DDP;

        return Shippings::TYPE_TERM_DEFAULT;
    }

    public static function getAllChannelMappings($shop_id, $warehouse_id)
    {
        return (new SafeMysql())->fetchAll("
            SELECT css.*
            FROM channels_shipping_services css
                JOIN shop_warehouses sw ON css.shop_warehouse_id = sw.id AND sw.shop_id = :shop_id AND sw.warehouse_id = :warehouse_id",
            ['shop_id' => (int)$shop_id, 'warehouse_id' => (int)$warehouse_id]);
    }

    public static function getAllServices()
    {
        static $services = [];
        if (!$services) {
            $sql = "SELECT ss.*, c.code ccode
                    FROM shipping_services ss
                        JOIN carriers c ON ss.carrier_id = c.id";
            $services = (new SafeMysql())->fetchAssocWithKey($sql, [], 'id');
        }
        return $services;
    }

    public static function getAllServicesByWarehouseId($warehouse_id)
    {
        $sql = "SELECT ss.*, c.code ccode
                    FROM shipping_services ss
                        JOIN shipping_service_warehouses ssw ON ss.id = ssw.shipping_service_id 
                                                                AND ssw.warehouse_id = :warehouse_id
                        JOIN carriers c ON ss.carrier_id = c.id
                    ORDER BY ss.id";
        return (new SafeMysql())->fetchAssocWithKey($sql, ['warehouse_id' => (int)$warehouse_id], 'id');
    }

    public static function checkDiffMapping($mapping_id, $system_service_id, $DDP): bool
    {
        $map = (new SafeMysql())->fetchOne("SELECT * FROM channels_shipping_services WHERE id = :mapping_id",
            ['mapping_id' => (int)$mapping_id]);
        $DDP = in_array($DDP, array_keys(self::TERMS)) ? (int)$DDP : self::TYPE_TERM_DEFAULT;
        if (empty($map) || intval($map['shipping_services_id']) !== (int)$system_service_id || intval($map['DDP']) !== $DDP)
            return true;
        return false;
    }

    public static function updateMapping($mapping_id, $system_service_id, $DDP)
    {
        $mapping_id = (int)$mapping_id;
        $system_service_id = (int)$system_service_id;
        $DDP = in_array($DDP, array_keys(self::TERMS)) ? (int)$DDP : self::TYPE_TERM_DEFAULT;
        return (new SafeMysql())->execute("UPDATE channels_shipping_services 
                                            SET shipping_services_id = :shipping_services_id, DDP = :DDP
                                      WHERE id = :id", [
            'shipping_services_id' => $system_service_id,
            'DDP' => $DDP,
            'id' => $mapping_id,
        ]);
    }

    /**
     * @param $id
     * @return Shipping | null
     */
    public static function getById($id)
    {
        static $shippings = [];
        if (!array_key_exists($id, $shippings)) {
            $data = (new SafeMysql())->fetchOne('SELECT * FROM shipping WHERE id = :id', ['id' => (int)$id]);
            if ($data) {
                $shippings[$id] = new Shipping($data);
            } else {
                $shippings[$id] = null;
            }
        }
        return $shippings[$id];
    }

    public static function getByOrderId($order_id, $errors = false)
    {
        return (new SafeMysql())->fetchAll("SELECT s.*, ops.order_package_id FROM shipping s
                JOIN order_packages_shipping ops ON ops.shipping_id = s.id
                JOIN orders_packages op ON ops.order_package_id = op.id AND op.is_cancel = 0
            WHERE op.order_id = :order_id" . ($errors ? '' : ' AND s.status = 4'),
            ['order_id' => (int)$order_id]);
    }

    public static function getVirtualWarehouses($order_ids)
    {
        if (!is_array($order_ids) || empty($order_ids))
            return [];
        $orders = (new SafeMysql())->fetchAll("
            SELECT DISTINCT op.order_id, s.carrier_tracking_number
            FROM shipping s
                JOIN order_packages_shipping ops ON ops.shipping_id = s.id
                JOIN orders_packages op ON ops.order_package_id = op.id AND op.is_cancel = 0 AND op.order_id IN (:order_ids)
            WHERE s.status = 4",
            ['order_ids' => $order_ids]);
        if (empty($orders))
            return [];
        return Arr::map($orders, 'order_id', 'carrier_tracking_number');
    }

    public static function checkServiceIdExists($service_id, $warehouse_id = null)
    {
        if (!$service_id) {
            //Service ID 0 means Collection by customer
            return true;
        }
        $params = ['service_id' => (int)$service_id];
        $sql = "SELECT COUNT(*) FROM shipping_services"
            . (!empty($warehouse_id) ? " JOIN shipping_service_warehouses ssw ON shipping_services.id = ssw.shipping_service_id 
                            AND ssw.warehouse_id = :warehouse_id" : '')
            . " WHERE id = :service_id";
        if (!empty($warehouse_id))
            $params['warehouse_id'] = (int)$warehouse_id;
        return (bool)(new SafeMysql())->fetchScalar($sql, $params);
    }

    public static function getServiceParamsById($service_id): array
    {
        $services = self::getAllServices();
        if (empty($services[$service_id]))
            return [
                'name' => Shipping::COLLECTION_BY_CUSTOMER,
                'special' => 0
            ];
        return [
            'name' => $services[$service_id]['ccode'] . ' - ' . $services[$service_id]['name'],
            'special' => $services[$service_id]['special'] ?: 0
        ];
    }

    public static function getServiceByCode($code, $warehouse_id)
    {
        $sql = "SELECT ss.*
                FROM shipping_services ss
                    JOIN shipping_service_warehouses ssw ON ss.id = ssw.shipping_service_id AND ssw.warehouse_id = :warehouse_id
                WHERE ss.`code` = :code
                LIMIT 1";
        return (new SafeMysql())->fetchOne($sql, ['warehouse_id' => (int)$warehouse_id, 'code' => $code]);
    }

    public static function getChannelServiceMappingByWarehouseId($shop_warehouse_id, $channel_shipping_code)
    {
        $sql = "SELECT css.id, css.channel_shipping_code, ssw.shipping_service_id, css.shop_warehouse_id, ss.carrier_id, css.DDP
                FROM channels_shipping_services css
                    JOIN shop_warehouses sw ON css.shop_warehouse_id = sw.id
                    LEFT JOIN shipping_service_warehouses ssw ON css.shipping_services_id = ssw.shipping_service_id AND sw.warehouse_id = ssw.warehouse_id
                    LEFT JOIN shipping_services ss ON ssw.shipping_service_id = ss.id
                WHERE css.shop_warehouse_id = :shop_warehouse_id AND
                      css.channel_shipping_code = :channel_shipping_code";
        return (new SafeMysql())->fetchOne($sql, [
            'shop_warehouse_id' => (int)$shop_warehouse_id,
            'channel_shipping_code' => $channel_shipping_code,
        ]);
    }

    public static function getChannelServiceMappingByData(array $data)
    {
        return (new SafeMysql())->fetchScalar("
            SELECT id
            FROM channels_shipping_services
            WHERE shop_warehouse_id = :shop_warehouse_id AND 
                  channel_shipping_code = :channel_shipping_code AND
                  shipping_services_id = :shipping_services_id",
            [
                'shop_warehouse_id' => (int)($data['shop_warehouse_id'] ?? 0),
                'channel_shipping_code' => $data['channel_shipping_code'] ?? '',
                'shipping_services_id' => (int)($data['shipping_services_id'] ?? 0),
            ]);
    }

    public static function addChannelServiceMapping(array $data)
    {
        $shop_warehouse_id = (int)($data['shop_warehouse_id'] ?? 0);
        $channel_shipping_code = $data['channel_shipping_code'] ?? '';
        $shipping_services_id = (int)($data['shipping_services_id'] ?? 0);
        return (new SafeMysql())->insert("
            INSERT INTO channels_shipping_services
            SET shop_warehouse_id = :shop_warehouse_id,
                channel_shipping_code = :channel_shipping_code,
                shipping_services_id = :shipping_services_id", [
            'shop_warehouse_id' => $shop_warehouse_id,
            'channel_shipping_code' => $channel_shipping_code,
            'shipping_services_id' => $shipping_services_id,
        ]);
    }

    public static function resolveUnmappedOrders($warehouse_id)
    {
        $sql = "SELECT o.id as order_id, css.shipping_services_id as service_id, ss.carrier_id, o.user_id, o.shop_id, o.warehouse_id, css.DDP
                FROM orders o
                    JOIN channels_shipping_services css ON o.channels_shipping_services_id = css.id AND css.shipping_services_id <> 0
                    JOIN shipping_services ss ON css.shipping_services_id = ss.id
                WHERE o.status = " . Order::AWAITING_CHANNEL_SERVICE_MAPPING_NUM . " AND o.warehouse_id = :warehouse_id";
        $mysql = new SafeMysql();
        $orders = $mysql->fetchAll($sql, ['warehouse_id' => (int)$warehouse_id]);
        if (!$orders)
            return;
        foreach ($orders as $order) {
            $mysql->execute("
                UPDATE orders 
                SET carrier_id = :carrier_id, shipping_services_id = :shipping_services_id, DDP = :DDP
                WHERE id = :order_id", [
                'carrier_id' => $order['carrier_id'],
                'shipping_services_id' => $order['service_id'],
                'DDP' => $order['DDP'],
                'order_id' => $order['order_id'],
            ]);
            $order_obj = Orders::getById($order['order_id']);
            /** @var Shops $shops */
            $shops = Channels::getUserChannel([
                'shop_id' => $order['shop_id'],
                'warehouse_id' => $order['warehouse_id'],
            ]);
            if ($shops)
                OrdersModel::changeStatus($order_obj->getId(), Order::NEW, -1);
            $qties = OrderSKUsModel::getSKUsQuantitiesByOrder($order_obj->getId());
            OrdersModel::creationOrder($order_obj, $qties, $order['user_id']);
        }
    }

    public static function getShippingDataByOrderId($order_id)
    {
        return (new SafeMysql())->fetchAll("SELECT s.id as shipment_id, s.create_date,
                                               IF(op.is_cancel, 
                                                   CONCAT('Canceled package (', op.package_name, ' ', DATE_FORMAT(op.packed_date, '%D %b %Y %H:%i '), ')'), op.package_name)
                                                        as package_name,
                                               IF(op.is_cancel, null, IFNULL(p.name, 'No box')) as box,
                                               s.tracking_number, s.url, s.status
                                        FROM shipping s
                                                 JOIN order_packages_shipping ops ON s.id = ops.shipping_id
                                                 JOIN orders_packages op ON op.id = ops.order_package_id AND op.order_id = :order_id
                                                 LEFT JOIN packages p on op.package_type_id = p.id
                                        ORDER BY op.is_cancel ASC, op.id, s.create_date DESC",
            ['order_id' => (int)$order_id]);
    }

    public static function handleShippingServicesByWarehouse($warehouse_id): array
    {
        $shipping_services = [];
        foreach (self::getAllServicesByWarehouseId($warehouse_id) as $shipping_service) {
            if (empty($shipping_services[$shipping_service['carrier_id']]) && Warehouses::isCollectionByCustomer($warehouse_id))
                $shipping_services[$shipping_service['carrier_id']][Shipping::COLLECTION_BY_CUSTOMER_ID] = Shipping::COLLECTION_BY_CUSTOMER;
            $shipping_services[$shipping_service['carrier_id']][$shipping_service['id']] = $shipping_service['name'];
        }
        return $shipping_services;
    }
}
