<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Shipping;
use app\core\helpers\Debug;
use app\core\services\archiving\ArchivingOrderService;
use Exception;
use app\core\Storage;

class Shippings extends \app\core\models\Shippings
{
    public static function getShippingLabelFullPath($relative_path)
    {
        return str_replace("\\", "/", Storage::getFile(SHIPPING_LABELS_STORAGE, ltrim($relative_path, "/")));
    }

    public static function getShippingErrors($order_id)
    {
        $sql = "SELECT url
                FROM shipping 
                WHERE id IN (
                             SELECT MAX(shipping_id) shipping_id
                             FROM orders_packages op
                                 JOIN order_packages_shipping ops ON op.id = ops.order_package_id
                                 JOIN shipping sh ON ops.shipping_id = sh.id
                             WHERE is_cancel = 0 AND order_id = :order_id
                             GROUP BY reference
                             ORDER BY reference) AND
                      status <> 4";
        return (new SafeMysql())->fetchArrayOneField($sql, ['order_id' => (int)$order_id]);
    }

    public static function getTrackingNumbersByOrders($order_ids)
    {
        if (empty($order_ids) || !is_array($order_ids))
            return [[], []];
        $order_ids = array_map('intval', $order_ids);
        $sql = "SELECT o.id, o.is_hash
                FROM orders o
                WHERE o.id IN (:order_ids)";
        $orders_normal = $orders_archived = [];
        foreach ((new SafeMysql())->fetchAll($sql, ['order_ids' => $order_ids]) as $item) {
            if ($item['is_hash'] && ArchivingOrderService::orderIsArchived($item['id']))
                $orders_archived[] = (int)$item['id'];
            else
                $orders_normal[] = (int)$item['id'];
        }
        $res_tn = $res_ctn = [];
        if ($orders_normal) {
            $sql = "
            SELECT op.order_id, op.id, sh.tracking_number, sh.carrier_tracking_number
            FROM orders_packages op
                INNER JOIN order_packages_shipping ops ON op.id = ops.order_package_id
                INNER JOIN shipping sh ON ops.shipping_id = sh.id AND sh.status = 4 AND sh.tracking_number IS NOT NULL
            WHERE op.is_cancel = 0 AND op.order_id IN (:order_ids)
            GROUP BY sh.tracking_number";
            foreach ((new SafeMysql())->fetchAll($sql, ['order_ids' => $orders_normal]) as $item) {
                $res_tn[$item['order_id']][$item['id']] = $item['tracking_number'];
                //TODO бага carrier_tracking_number разный для разных упаковок как и tracking_number
                //TODO пока что не знаю как лучше поправить, учитывая что у нас уже такая структура в архиве заказов
                $res_ctn[$item['order_id']] = $item['carrier_tracking_number'];
            }
        }
        foreach ($orders_archived as $order_id) {
            $archived_tracking_numbers = [];
            try {
                $archived_packages = ArchivingOrderService::getPackages($order_id);
                $archived_tracking_numbers = $archived_packages['tracking_numbers'] ?? [];
            } catch (Exception $exception) {
                Debug::sendDebugInfo('ArchivingOrder', 'Original Despatched: ' . $exception->getMessage());
            }
            if (!empty($archived_tracking_numbers[0]))
                $res_tn[$order_id] = $archived_tracking_numbers[0];
            if (!empty($archived_tracking_numbers[1]))
                $res_ctn[$order_id] = $archived_tracking_numbers[1];
        }
        return [$res_tn, $res_ctn];
    }

    public static function getDataForHashing($order_id)
    {
        $order_id = (int)$order_id;
        if (empty($order_id))
            return [];
        $sql = "
            SELECT s.id, s.tracking_number, s.status, s.url
            FROM shipping s
                JOIN order_packages_shipping ops ON s.id = ops.shipping_id
                JOIN orders_packages op ON ops.order_package_id = op.id AND op.order_id = :order_id";
        return (new SafeMysql())->fetchAll($sql, ['order_id' => $order_id]);
    }

    public static function getShippingForBilling($tracking_number)
    {
        $sql = "
            SELECT  sh.tracking_number, op.weight, sh.reference, o.despatched_date, o.notes, o.id order_id, supw.sage_id, supw.address, o.delivery_id
            FROM shipping sh
                JOIN order_packages_shipping ops ON sh.id = ops.shipping_id
                JOIN orders_packages op ON ops.order_package_id = op.id AND op.is_cancel = 0
                JOIN orders o ON op.order_id = o.id
                JOIN suppliers_warehouses supw ON o.supplier_id = supw.supplier_id AND o.warehouse_id = supw.warehouse_id
            WHERE sh.tracking_number = :tracking_number AND sh.status = :status
            LIMIT 1";
        return (new SafeMysql())->fetchOne($sql, [
            'tracking_number' => $tracking_number,
            'status' => Shipping::STATUS_SUCCESS
        ]);
    }
}