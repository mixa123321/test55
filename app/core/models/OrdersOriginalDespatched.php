<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Delivery;
use app\core\entities\Order;
use app\core\entities\SKULocation;
use app\core\services\archiving\ArchivingOrderService;
use Exception;

class OrdersOriginalDespatched
{

    public static function getByOrderId($order_id)
    {
        return (new SafeMysql())->fetchOne("SELECT * FROM orders_original_despatched WHERE order_id = :order_id", ['order_id' => (int)$order_id]);
    }

    public static function getItems($order_id)
    {
        $items = (new SafeMysql())->fetchScalar("SELECT items FROM orders_original_despatched WHERE order_id = :order_id", ['order_id' => (int)$order_id]);
        if (!empty($items))
            return json_decode($items, TRUE);
        return null;
    }

    public static function handleItems(array $SKU_items): array
    {
        if (!empty($SKU_items['OrderNumber'])) {
            if (empty($shipments = $SKU_items['ShipmentPackages']))
                return [];
            $SKU_items = [];
            foreach ($shipments as $shipment) {
                $shipment_items = $shipment['ShipmentItems'];
                if (empty($shipment_items))
                    continue;
                foreach ($shipment_items as $shipment_item) {
                    if (empty($SKU_id = $shipment_item['ItemCode']))
                        continue;
                    if (empty($SKU_items[$SKU_id]))
                        $SKU_items[$SKU_id] = (new SKULocation([
                            'SKU_id' => $SKU_id,
                            'location_id' => 0,
                            'quantity' => (int)$shipment_item['Quantity'],
                            'order_id' => $SKU_items['OrderNumber'] ?? null,
                            'type' => 31,
                            'reason' => -1,
                            'batch_id' => !empty($shipment_item['LotNumber']) ? (Batches::getBatchBySKUAndNumber($SKU_id, $shipment_item['LotNumber'])['id'] ?? null) : null
                        ]))->toArray();
                    else
                        $SKU_items[$SKU_id]['quantity'] += $shipment_item['Quantity'];
                }
            }
        }
        return array_values($SKU_items);
    }

    public static function getHandledItems(Order $order) : array
    {
        if ($order->isHashed() && ArchivingOrderService::orderIsArchived($order->getId()))
            try {
                $archived_data = ArchivingOrderService::getOriginalDespatched($order->getId());
                if (empty($archived_data['items']))
                    throw new Exception();
                $SKU_items = json_decode($archived_data['items'], true);
                if (empty($SKU_items))
                    throw new Exception();
            } catch (Exception $e) {
                $SKU_items = [];
            }
        else
            $SKU_items = self::getItems($order->getId()) ?: [];

        return self::handleItems($SKU_items);
    }

    public static function getItemsByOrderIds(array $order_ids): array
    {
        if (empty($order_ids))
            return [];
        $order_sql = implode(', ', $order_ids);
        $items = (new SafeMysql())->fetchAssocWithKey("SELECT order_id, items FROM orders_original_despatched 
                                                    WHERE order_id in (:order_ids)", ['order_ids' => array_map('intval', $order_ids)], 'order_id');
        return array_map(function ($rec) {
            return self::handleItems(json_decode($rec['items'], TRUE) ?? []);
        }, $items);
    }

    public static function getDelivery($order_id)
    {
        $delivery = (new SafeMysql())->fetchScalar("SELECT delivery FROM orders_original_despatched WHERE order_id = :order_id", ['order_id' => (int)$order_id]);
        if (!empty($delivery))
            return new Delivery(json_decode($delivery, TRUE));
        return null;
    }

    public static function getHandledDelivery(Order $order) : Delivery
    {
        if ($order->isHashed() && ArchivingOrderService::orderIsArchived($order->getId())) {
            try {
                $archived_data = ArchivingOrderService::getOriginalDespatched($order->getId());
                if (empty($archived_data['delivery']))
                    throw new Exception();
                $delivery_data = json_decode($archived_data['delivery'], true);
                if (empty($delivery_data))
                    throw new Exception();
            } catch (Exception $e) {
                $delivery_data = [];
            }
            $delivery = new Delivery($delivery_data);
        } else
            $delivery = self::getDelivery($order->getId());
        if (empty($delivery))
            $delivery = Deliveries::getById($order->getDeliveryId());
        if (empty($delivery))
            $delivery = new Delivery([]);
        return $delivery;
    }

}