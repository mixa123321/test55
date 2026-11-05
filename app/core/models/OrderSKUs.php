<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Order;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\Debug;
use app\core\services\archiving\ArchivingOrderService;
use Exception;
use app\core\entities\Location;

class OrderSKUs
{
    public static function add(array $order_SKUs, $order_id)
    {
        if (empty($order_SKUs) || empty($order_id))
            return [false, []];
        $ids = SKUs::getByIds(array_keys($order_SKUs));
        $tmp = Arr::map($ids, 'id', 'state');
        $sql = "INSERT INTO order_SKUs (order_id, SKU_id, quantity, price) VALUES ";
        $values = [];
        $params = [];
        $prices = 0;
        foreach ($order_SKUs as $SKU_id => $SKU_data)
            if (!empty($tmp[$SKU_id])) {
                $values[] = "(:order_id_$SKU_id, :SKU_id_$SKU_id, :quantity_$SKU_id, :price_$SKU_id)";
                $params["order_id_$SKU_id"] = (int)$order_id;
                $params["SKU_id_$SKU_id"] = (int)$SKU_id;
                $params["quantity_$SKU_id"] = (int)$SKU_data['qty'];
                $params["price_$SKU_id"] = (float)$SKU_data['price'];
                $prices += ((int)$SKU_data['qty'] * (float)$SKU_data['price']);
                unset($tmp[$SKU_id]);
            }
        $sql .= implode(',', $values);
        if (count($ids) != count($tmp)) {
            (new SafeMysql())->execute($sql, $params);
            foreach ($ids as $key => $id)
                if (!isset($tmp[$id['id']]))
                    unset($ids[$key]);
            return [true, $ids, $prices];
        }
        return [false, $ids, $prices];
    }

    public static function getSKUs4Similar(Order $order)
    {
        $SKUs = [];
        if ($order->despatched()) {
            $SKUs_despatched = OrdersOriginalDespatched::getHandledItems($order);
            if (!empty($SKUs_despatched)) {
                $ids = Arr::column($SKUs_despatched, 'SKU_id');
                $barcodes = SKUBarcodes::getBarcodesBySKUids($ids);
                foreach ($SKUs_despatched as $SKU)
                    $SKUs[implode(', ', $barcodes[$SKU['SKU_id']])] = ['quantity' => $SKU['quantity']];
            }
        } else {
            $items = (new SafeMysql())->fetchAll("
                SELECT os.SKU_id, os.quantity
                FROM order_SKUs os
                WHERE os.order_id = :order_id
                ORDER BY 1", ['order_id' => (int)$order->getId()]);
            SKUBarcodes::addBarcodesToArray($items, 'SKU_id');
            $SKUs = [];
            foreach ($items as $item)
                $SKUs[$item['barcode']] = ['quantity' => $item['quantity']];;
        }
        return $SKUs;
    }

    public static function getSKUs($id)
    {
        $id = (int) $id;
        $sql = "SELECT os.SKU_id id, 
                    s.name, s.hs_code, 
                    os.quantity, os.quantity*os.price `value`, os.quantity*s.market_value `value2`, 
                    s.weight, s.market_currency,
                    os.price, s.customer_ref, s.serial_number, s.DDS, s.exemption_TARIC
                FROM order_SKUs os
                    LEFT JOIN SKUs s ON os.SKU_id = s.id
                WHERE os.order_id = $id";
        $result = (new SafeMysql())->fetchAll("SELECT os.SKU_id id, 
                    s.name, s.hs_code, 
                    os.quantity, os.quantity*os.price `value`, os.quantity*s.market_value `value2`, 
                    s.weight, s.market_currency,
                    os.price, s.customer_ref, s.serial_number, s.DDS, s.exemption_TARIC
                FROM order_SKUs os
                    LEFT JOIN SKUs s ON os.SKU_id = s.id
                WHERE os.order_id = :order_id", ['order_id' => $id]);
        SKUBarcodes::addBarcodesToArray($result);
        return $result;
    }

    public static function getSKUsByOrder($order_id)
    {
        return (new SafeMysql())->fetchAssocWithKey("SELECT SKU_id, quantity, IFNULL(price, (SELECT market_value FROM SKUs WHERE id = SKU_id)) price FROM order_SKUs WHERE order_id = :order_id", ['order_id' => (int)$order_id], 'SKU_id');
    }

    public static function getSKUsByOrders(array $order_ids)
    {
        if(empty($order_ids))
            return [];
        $order_ids = array_map('intval', $order_ids);
        $sql = 'SELECT os.order_id, s.id, s.name, s.hs_code, os.quantity, os.quantity*os.price `value`, s.weight
                FROM order_SKUs os
                    JOIN SKUs s ON os.SKU_id = s.id
                WHERE os.order_id IN (' . implode(",", $order_ids) . ')';
        $items = (new SafeMysql())->fetchAssocWithKey('SELECT os.order_id, s.id, s.name, s.hs_code, os.quantity, os.quantity*os.price `value`, s.weight
                FROM order_SKUs os
                    JOIN SKUs s ON os.SKU_id = s.id
                WHERE os.order_id IN (:order_ids)', ['order_ids' => $order_ids], 'order_id');
        SKUBarcodes::addBarcodesToArray($items);
        return $items;
    }

    public static function getOrdersDataForReconciliationWithQuery(array $SKU_ids, int $loc_id, array $statuses, Warehouse $warehouse): array
    {
        if(empty($SKU_ids))
            return [];
        $SKU_ids = array_map('intval', $SKU_ids);
        $sql = 'SELECT os.order_id real_order_id, os.SKU_id, os.quantity AS order_qty, sl.order_id, sl.quantity, sl.location_id, sl.batch_id, sl.type, sl.reason, b.number, b.expired
                FROM order_SKUs os
                    JOIN orders o ON os.order_id = o.id AND o.status IN (:status)
                    JOIN SKU_locations sl ON (sl.order_id = o.id OR sl.reason = CONCAT("2_", os.order_id)) AND sl.SKU_id = os.SKU_id AND sl.location_id = :location_id
                    JOIN locations l ON l.id = sl.location_id AND l.type_id IN (:types)
                    LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
                WHERE os.SKU_id IN (:SKU_ids)';
        $items = (new SafeMysql())->fetchAll($sql, ['SKU_ids' => $SKU_ids, 'location_id' => $loc_id, 'status' => $statuses, 'types' => [Location::PF, Location::ST]]);
        $result = $locs_name = [];
        foreach ($items as $item) {
            if (empty($locs[$item['location_id']]))
                $locs_name[$item['location_id']] = Locations::getById($item['location_id'])->getLocation($warehouse->getLabel() ?: $warehouse->getId());;
            $result[] = [
                'real_order_id' => $item['real_order_id'], 'order_qty' => $item['order_qty'],
                'SKU_id' => $item['SKU_id'], 'location_id' => $item['location_id'], 'type' => $item['type'], 'reason' => $item['reason'], 'order_id' => $item['order_id'], 'batch_id' => $item['batch_id'],
                'loc_name' => $locs_name[$item['location_id']],
                'del_quantity' => $item['quantity'], 'old_quantity' => $item['quantity'],
                'number' => $item['number'], 'expired' => $item['expired'],
                'batch' => $item['batch_id'] ? (new Batch())->setId($item['batch_id'])->setNumber($item['number'])->setSKUId((int)$item['SKU_id'])->setExpired($item['expired']) : null,
            ];
        }
        return $result;
    }

    public static function getQuantity($order_id, $SKU_id)
    {
        $order_id = (int) $order_id;
        $SKU_id = (int) $SKU_id;
        return (new SafeMysql())->fetchScalar("SELECT SUM(quantity) FROM order_SKUs WHERE order_id = :order_id AND SKU_id = :SKU_id",
            ['order_id' => $order_id, 'SKU_id' => $SKU_id]);
    }

    public static function replaceSKUs($order_id, $new_skus)
    {
        (new SafeMysql())->execute("DELETE FROM order_SKUs WHERE order_id = :order_id", ['order_id' => (int)$order_id]);
        return self::add($new_skus, $order_id);
    }

    public static function getSKUInfoByOrdersPackages($ids, $archived_order_ids = []): array
    {
        if (empty($ids) || !is_array($ids))
            return [];
        $whereDels = implode(',', array_map('intval', $ids));
        $sql = "SELECT ops.orders_packages_id, ops.SKU_id, s.customer_ref, ops.quantity
                FROM orders_packages_SKUs ops 
                    LEFT JOIN SKUs s ON ops.SKU_id = s.id
                WHERE ops.orders_packages_id IN ($whereDels)";
        $SKUs = (new SafeMysql())->fetchAll("SELECT ops.orders_packages_id, ops.SKU_id, s.customer_ref, ops.quantity
                FROM orders_packages_SKUs ops 
                    LEFT JOIN SKUs s ON ops.SKU_id = s.id
                WHERE ops.orders_packages_id IN (:ids)", ['ids' => array_map('intval', $ids)]);
        SKUBarcodes::addBarcodesToArray($SKUs, 'SKU_id');
        $res = [];
        foreach ($SKUs as $SKU)
            $res[$SKU['orders_packages_id']][$SKU['SKU_id']] = [
                'barcode' => $SKU['barcode'],
                'customer_ref' => $SKU['customer_ref'],
                'quantity' => $SKU['quantity']];

        if (!empty($archived_order_ids)) {
            foreach ($archived_order_ids as $archived_order_id) {
                try {
                    $archived_packages = ArchivingOrderService::getPackages($archived_order_id);
                    $archived_SKU_info = $archived_packages['SKU_info'] ?? [];
                    array_walk($archived_SKU_info, function($a_SKU, $a_orders_packages_id) use (&$res) {
                        $res[$a_orders_packages_id] = $a_SKU;
                    });
                } catch (Exception $exception) {
                    Debug::sendDebugInfo('ArchivingOrder', "SKUInfoByOrdersPackages Order #$archived_order_id: " . $exception->getMessage());
                }
            }
        }
        return $res;
    }

    public static function getSKUInfoByOrders($ids): array
    {
        if (empty($ids) || !is_array($ids))
            return [];
        $whereDels = implode(',', array_map('intval', $ids));
        $sql = "SELECT o.id as order_id, s.id as SKU_id, s.customer_ref, os.quantity, o.is_hash
                FROM orders o 
                    LEFT JOIN order_SKUs os ON o.id = os.order_id
                    LEFT JOIN SKUs s ON os.SKU_id = s.id
                WHERE o.id IN ($whereDels)";
        $SKUs = (new SafeMysql())->fetchAll("SELECT o.id as order_id, s.id as SKU_id, s.customer_ref, os.quantity, o.is_hash
                FROM orders o 
                    LEFT JOIN order_SKUs os ON o.id = os.order_id
                    LEFT JOIN SKUs s ON os.SKU_id = s.id
                WHERE o.id IN (:ids)", ['ids' => array_map('intval', $ids)]);
        SKUBarcodes::addBarcodesToArray($SKUs, 'SKU_id');
        if (empty($SKUs))
            return [];
        $res = [];
        foreach ($SKUs as $SKU) {
            if (!empty($SKU['is_hash']) && ArchivingOrderService::orderIsArchived($SKU['order_id']) && !array_key_exists($SKU['order_id'], $res)) {
                try {
                    $res[$SKU['order_id']] = ArchivingOrderService::getSKUs($SKU['order_id']);
                } catch (\Exception $exception) {
                    Debug::sendDebugInfo('ArchivingOrder', 'getSKUInfoByOrders: ' . $exception->getMessage());
                }
            } else
                $res[$SKU['order_id']][$SKU['SKU_id']] = [
                    'barcode' => $SKU['barcode'],
                    'customer_ref' => $SKU['customer_ref'],
                    'quantity' => $SKU['quantity'],
                ];
        }

        return $res;
    }
}