<?php

namespace app\api\models;

use app\backend\models\ASNHistories;
use app\backend\models\ASNs;
use app\backend\models\ASNSKUs;
use app\backend\models\Deliveries;
use app\backend\models\Locations;
use app\backend\models\OrderHistories;
use app\backend\models\Orders;
use app\backend\models\OrderSKUs;
use app\backend\models\ShippingServices;
use app\backend\models\ShopInventoryQueues;
use app\backend\models\SKUHistories;
use app\backend\models\SKULocations;
use app\backend\models\SKUs;
use app\backend\models\WebhookHistories;
use app\core\db\DBException;
use app\core\dbal\exceptions\DbQueryException;
use app\core\dbal\SafeMysql;
use app\core\entities\ASNSKU;
use app\core\entities\Carrier;
use app\core\entities\Order;
use app\core\entities\Shipping;
use app\core\entities\User;
use app\core\entities\Location;
use app\core\exceptions\BatchException;
use app\core\exceptions\VirtualException;
use app\core\helpers\Arr;
use app\core\helpers\notifiers\Notifier;
use app\core\models\Batches;
use app\core\models\Carriers;
use app\core\models\WarehouseCarriers;
use app\core\models\Warehouses;
use app\core\Queue;
use app\core\virtual\VirtualNotification;
use app\core\virtual\visible\VisibleProcessor;

class VisibleCallback
{
    private $user;
    private $warehouse_id;
    private $warehouses;
    private $locs;
    private $data;
    private $webhook_history_id;

    const SEND_MESSAGE = Notifier::GROUP_VIRTUAL;

    /** @throws VirtualException */
    public function __construct(User $user, $data)
    {
        $this->user = $user;
        $this->warehouse_id = $this->user->getWarehouseId();
        $this->warehouses = Warehouses::getLocations($this->warehouse_id);
        if (empty($this->warehouses))
            throw new VirtualException('Warehouses is not found');
        foreach ($this->warehouses as $name => $title) {
            $virt_loc = Location::SPECIAL_LOCATION__VIRTUAL;
            $loc = Locations::getByLocation($this->warehouse_id, $virt_loc[0], $virt_loc[1], $virt_loc[2], $name);
            if (empty($loc) || count($loc) != 1)
                throw new VirtualException('Warehouse is not configured');
            $this->locs[$name] = $loc[0];
        }
        $this->data = $data;
    }

    public function getWebhookHistoryId()
    {
        return $this->webhook_history_id;
    }

    public function setWebhookHistoryId($id)
    {
        $this->webhook_history_id = $id;
    }

    /** @throws VirtualException */
    public function InventoryReceived()
    {
        $this->setWebhookHistoryId(
            WebhookHistories::addExternalHistory('InventoryReceived',$this->data['PONumber'] ?? null, $this->data, $this->user->getId(),Notifier::GROUP_VIRTUAL)
        );
        if (empty($this->data['PONumber']))
            throw new VirtualException('ASN is not found');
        if (empty($this->data['InventoryDetails']) || count($this->data['InventoryDetails']) < 1)
            throw new VirtualException('SKUs in ASN is not found');
        if (empty($this->data['Warehouse']) || empty($this->warehouses[$this->data['Warehouse']]))
            throw new VirtualException('Warehouse is not found');
        // Flip Ref & ID
        $ASN_ref = $this->data['PONumber'];
        $supplier_ids = [];
        $errors = [];
        foreach ($this->data['InventoryDetails'] as $item_data) {
            $SKU = SKUs::getById($item_data['ItemCode']);
            if (empty($SKU)) {
                $errors[] = "#{$item_data['ItemCode']}";
                continue;
            }
            $supplier_ids[$SKU->getSupplierId()] = 1;
        }
        if (!empty($errors))
            throw new VirtualException('SKUs (' . implode(', ', $errors) . ') are not found');
        if (count($supplier_ids) != 1)
            throw new VirtualException('ASN has SKUs from different suppliers');
        $ASN_id = ASNs::getByRef($ASN_ref, array_keys($supplier_ids)[0], $this->warehouse_id);
        if ($ASN_id === false || $ASN_id === true)
            throw new VirtualException('ASN is not found or access denied');
        //-------------
        WebhookHistories::changeNumber($this->getWebhookHistoryId(), $ASN_id);
        $ASN = ASNs::getById($ASN_id);
        if (empty($ASN))
            throw new VirtualException('ASN is not found or access denied');
        if (!$ASN->new() && !$ASN->putaway())
            throw new VirtualException('ASN can not be completed');
        $ASN_SKUs = ASNSKUs::getSKUs($ASN_id);
        $addSKUs = $errors = $SKUs = [];
        foreach ($this->data['InventoryDetails'] as $item_data) {
            $SKU_id = (int)$item_data['ItemCode'];
            if (!empty($ASN_SKUs[$SKU_id])) {
                if (empty($SKUs[$SKU_id]['accepted_quantity']))
                    $SKUs[$SKU_id]['accepted_quantity'] = (int)$item_data['QuantityReceived'];
                else
                    $SKUs[$SKU_id]['accepted_quantity'] += (int)$item_data['QuantityReceived'];
            } else {
                $SKU = SKUs::getByIdAndSupplier($SKU_id, $ASN->getSupplierId());
                if (empty($SKU))
                    $errors[] = "#$SKU_id";
                else {
                    if (empty($addSKUs[$SKU_id]))
                        $addSKUs[$SKU_id] = (int)$item_data['QuantityReceived'];
                    else
                        $addSKUs[$SKU_id] += (int)$item_data['QuantityReceived'];
                }
            }
        }
        if (!empty($errors))
            throw new VirtualException('SKUs (' . implode(', ', $errors) . ') are not found');
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        if (!empty($addSKUs))
            foreach ($addSKUs as $SKU_id => $qty) {
                $data = [
                    'ASN_id' => $ASN_id,
                    'SKU_id' => $SKU_id,
                    'quantity' => 0,
                    'accepted_quantity' => $qty,
                ];
                $ASN_SKU = new ASNSKU($data);
                $ASN_SKU->save(array_keys($data));
                ASNHistories::putsASN($SKU_id, $ASN_id, $qty, 1, $this->user->getId());
            }
        foreach ($SKUs as $SKU_id => $SKU_data)
            if ($SKU_data['accepted_quantity'] > 0)
                ASNs::receive($ASN_id, $SKU_id, $SKU_data['accepted_quantity'], $this->user->getId());
        foreach ($this->data['InventoryDetails'] as $item_data) {
            $SKU_id = (int)$item_data['ItemCode'];
            $batch_id = null;
            try {
                if (!empty($item_data['Lot']))
                    $batch_id = Batches::addBatch($item_data['Lot'], $SKU_id, date('Y-m-d', strtotime($item_data['ExpirationDate'])));
            } catch (BatchException $e) {
                $mysql->rollback();
                throw new VirtualException($e->getMessage());
            }
            SKULocations::addSKU2Location($this->warehouse_id, $SKU_id, $this->locs[$this->data['Warehouse']]->getId(), $item_data['QuantityReceived'], null, 1, '2', $batch_id);
            SKUHistories::updateStock($SKU_id, $this->warehouse_id);
        }
        if (!$ASN->putaway()) {
            $errors = ASNs::putaway($ASN, $this->user->getId());
            if ($errors !== true) {
                $mysql->rollback();
                throw new VirtualException(json_encode($errors));
            }
        }
//        Queue::addJobToSetInventoryLevel(['asn_id' => $ASN_id]);
        ShopInventoryQueues::addASN($ASN_id);
        $mysql->commit();
        return $ASN_id;
    }

    /** @throws VirtualException */
    public function OrderStatusChange()
    {
        $this->setWebhookHistoryId(
            WebhookHistories::addExternalHistory('OrderStatusChange', $this->data['OrderNumber'] ?? null, $this->data, $this->user->getId(),Notifier::GROUP_VIRTUAL)
        );
        if (empty($this->data['OrderNumber']))
            throw new VirtualException('Order is not found');
        $order_id = $this->data['OrderNumber'];
        $order = Orders::getById($order_id);
        if (!$order || $order->getWarehouseId() != $this->warehouse_id)
            throw new VirtualException('Order is not found or access denied');
        if (empty(VisibleProcessor::$status_order_mapping[$this->data['OrderStatus']])) {
            $msg = "Status '{$this->data['OrderStatus']}' is not mapped (order #{$this->data['OrderNumber']})";
            VirtualNotification::sendErrorToTelegram('OrderStatusChange', $msg, self::SEND_MESSAGE);
            VirtualNotification::sendNotificationsToEmails('OrderStatusChange', $msg);
            throw new VirtualException($msg);
        }
        if ($order->despatched())
            throw new VirtualException("Order #{$this->data['OrderNumber']} is already despatched");
        if ($order->cancelled())
            throw new VirtualException("Order #{$this->data['OrderNumber']} is already cancelled");
        $status = VisibleProcessor::$status_order_mapping[$this->data['OrderStatus']];
        switch ($status) {
            case Order::ALLOCATED:
                throw new VirtualException('Order is already exists');
            case Order::BACKORDER:
            case Order::WAITING_FOR_REPLEN:
            case Order::AWAITING_PICK:
            case Order::PICKING:
            case Order::PROCESSING:
            case Order::AWAITING_PACK:
            case Order::PACKING:
            case Order::PACKED:
                if ($order->getStatusName() != $status)
                    Orders::changeStatus($order->getId(), $status, $this->user->getId(), null, strtotime($this->data['LastUpdated']));
                break;
            case Order::CANCELLED:
                if (!Order::canCancelOrderVirtual($order->getStatus()))
                    throw new VirtualException('Order can not cancelled');
                $res = Orders::cancelOrder($order, $this->user->getId(), false);
                if (!$res[0])
                    throw new VirtualException($res[1]);
                break;
            case Order::DESPATCHED:
                break;
        }
        return $order_id;
    }

    private function validatePackages($provided_data)
    {
        $order_SKUs = OrderSKUs::getSKUs($provided_data['order_id']);
        $expected_barcodes = Arr::map($order_SKUs, 'id', 'barcode');
        if (empty($order_SKUs))
            return 'The order you are trying to pack is not found in the system';
        $max_package_weight = ShippingServices::getMaxWeight($provided_data['shipping_service_id'] ?? null);
        $not_weighted = 0;
        foreach ($order_SKUs as $SKU) {
            $SKU_id = $SKU['id'];
            $bc = $SKU['barcode'];
            $expected_qty = $SKU['quantity'];
            $provided_total_qty = 0;
            foreach ($provided_data['box'] as $box)
                if (!empty($box['SKUs'])) {
                    foreach ($box['SKUs'] as $box_SKU_id => $box_data)
                        if (!isset($expected_barcodes[$box_SKU_id]))
                            return "Unexpected item {$box_SKU_id} was found in the packages. Please redo the packing process";
                    if (!empty($box['SKUs'][$SKU_id]))
                        $provided_total_qty += $box['SKUs'][$SKU_id]['quantity'];
                    if (empty($box['weight']) || (double)$box['weight'] <= 0)
                        $not_weighted++;
                    if (!empty($max_package_weight) && (double)$box['weight'] > $max_package_weight)
                        return "Package weight cannot be less than the maximum allowable for this shipping service ({$max_package_weight} kg)";
                }
            if ($provided_total_qty != $expected_qty)
                return "Expected quantity of the item {$bc} is {$expected_qty}. Total packed quantity is {$provided_total_qty}. Please redo the packing process";
        }
        if ($not_weighted > 0)
            return 'Not all parcels were weighed';
        return true;
    }

    private function packagesConfirm(&$data, $warehouse, $order)
    {
        $order_id = $data['order_id'];
        $SKUs = OrderSKUs::getSKUs($order_id);
        $mysql = new SafeMysql();
        foreach ($data['box'] as $key => $box) {
            if (empty($box['SKUs']))
                continue;
            $sql = "INSERT INTO orders_packages 
                    SET order_id = :order_id,
                        package_type_id = :package_type_id,
                        package_name = :package_name,
                        length = :length,
                        width = :width,
                        height = :height,
                        weight = :weight";
            $params = [
                'order_id' => (int)$order_id,
                'package_type_id' => (int)$box['package_type_id'],
                'package_name' => $box['package_name'],
                'length' => (double)$box['length'],
                'width' => (double)$box['width'],
                'height' => (double)$box['height'],
                'weight' => (double)$box['weight'],
            ];
            $order_packages_id = $mysql->insert($sql, $params);
            if (!$order_packages_id)
                return false;
            $data['box'][$key]['order_packages_id'] = $order_packages_id;
            foreach ($box['SKUs'] as $SKU_id => $SKU_data) {
                $sql = "INSERT INTO orders_packages_SKUs
                        SET orders_packages_id = :orders_packages_id,
                            SKU_id = :SKU_id,
                            quantity = :quantity";
                $params = [
                    'orders_packages_id' => $order_packages_id,
                    'SKU_id' => (int)$SKU_id,
                    'quantity' => (int)$SKU_data['quantity'],
                ];
                if (!$mysql->insert($sql, $params))
                    return false;
            }
        }
        foreach ($SKUs as $SKU)
            SKUHistories::addPackingVirtual($order_id, $SKU['id'], $SKU['quantity'], $this->user->getId(), $warehouse, $this->warehouse_id);
        Orders::changeStatus($order_id, Order::PACKED, $this->user->getId(), "ShipConfirm");
        return true;
    }

    /** @throws VirtualException */
    public function ShipConfirm()
    {
        $this->setWebhookHistoryId(
            WebhookHistories::addExternalHistory('ShipConfirm', $this->data['OrderNumber'] ?? null, $this->data, $this->user->getId(),Notifier::GROUP_VIRTUAL)
        );
        if (empty($this->data['OrderNumber']))
            throw new VirtualException('Order is not found');
        $order_id = $this->data['OrderNumber'];
        $order = Orders::getById($order_id);
        if (!$order || $order->getWarehouseId() != $this->warehouse_id)
            throw new VirtualException('Order is not found or access denied');
        if (empty($this->data['ShipFromWarehouse']) || empty($this->warehouses[$this->data['ShipFromWarehouse']]))
            throw new VirtualException('Warehouse is not found');
//        if (!$order->packing() && !$order->packed() && !$order->despatched())
//            throw new VirtualException('Order is can\'t shipping');
        if (empty($this->data['ShipmentPackages']))
            throw new VirtualException('Shipment packages is empty');
        $packages = Orders::getPackages($order_id);
        if (!empty($packages))
            throw new VirtualException('Order is already packaged');
        $provided_data = ['order_id' => $order_id];
        $fields_to_save = ['id'];
        if (!empty($this->data['ShipmentServiceType'])) {
            $shipping_service = ShippingServices::getByCode($this->data['ShipmentServiceType']);
            if (empty($shipping_service))
                throw new VirtualException('Shipment service type is not found');
            /** @var Carrier $carrier */
            $carrier = Carriers::getById($shipping_service['carrier_id']);
            if (empty($carrier) || $carrier->getCode() !== $this->data['ShipmentCarrier'])
                throw new VirtualException('Shipment carrier is not found');
            $provided_data['shipping_service_id'] = $shipping_service['id'];
        } else {
            if (!Warehouses::isCollectionByCustomer($this->warehouse_id))
                throw new VirtualException('Shipment service must exist');
            $carriers = WarehouseCarriers::getAvailableCarrierList($this->warehouse_id);
            if (empty($carriers))
                throw new VirtualException('Carriers are not found');
            $carrier = Carriers::getById($carriers[0]['id']);
            $provided_data['shipping_service_id'] = Shipping::COLLECTION_BY_CUSTOMER_ID;
        }
        $provided_data['carrier_id'] = $carrier->getId();
        if ($order->getCarrierId() != $provided_data['carrier_id']) {
            OrderHistories::addCommentOrder($order_id, $this->user->getId(),"Carrier changed");
            $order->setCarrierId($provided_data['carrier_id']);
            $fields_to_save[] = 'carrier_id';
        }
        if ($order->getShippingServicesId() != $provided_data['shipping_service_id']) {
            OrderHistories::addCommentOrder($order_id, $this->user->getId(),"Shipping service changed");
            $order->setShippingServicesId($provided_data['shipping_service_id']);
            $fields_to_save[] = 'shipping_services_id';
        }
        if (count($fields_to_save) > 1)
            $order->save($fields_to_save);
        $provided_data['box'] = [];
        foreach ($this->data['ShipmentPackages'] as $package) {
            if (empty($package['ShipmentItems']))
                throw new VirtualException("Shipment items (package {$package['PackageNumber']}) is empty");
            $SKUs = [];
            foreach ($package['ShipmentItems'] as $item) {
                $SKU = SKUs::getById($item['ItemCode']);
                if (empty($SKU) || $order->getSupplierId() != $SKU->getSupplierId())
                    throw new VirtualException("Item '{$item['ItemCode']}' (package {$package['PackageNumber']}) is not found");
                if (!empty($item['LotNumber'])) {
                    $batch = Batches::getBatchBySKUAndNumber($item['ItemCode'], $item['LotNumber']);
                    if (empty($batch))
                        throw new VirtualException("Item '{$item['ItemCode']}' (package {$package['PackageNumber']}) batch '{$item['LotNumber']}' is not found");
                }
                $SKUs[$item['ItemCode']] = [
                    'quantity' => $item['Quantity'],
                    'batch_id' => $batch['id'] ?? 0,
                ];
            }
            $provided_data['box'][] = [
                'package_name' => "{$this->data['VisibleShipmentNumber']}-{$package['PackageNumber']}",
                'package_type_id' => -1,
                'length' => $package['Length'],
                'width' => $package['Width'],
                'height' => $package['Height'],
                'weight' => $package['WeightUOM'] == 'lbs' ?  ($package['Weight'] / 2.205) : $package['Weight'],
                'created' => $package['ShipDate'],
                'tracking_number' => $package['TrackingNumber'],
                'SKUs' => $SKUs
            ];
        }
        $validation_result = $this->validatePackages($provided_data);
        if ($validation_result !== true)
            throw new VirtualException($validation_result);
        $num = 0;
        $mysql = new SafeMysql();
        while (true) {
            try {
                $mysql->beginTransaction();
                $confirm_result = $this->packagesConfirm($provided_data, $this->data['ShipFromWarehouse'], $order);
                if (!$confirm_result) {
                    $mysql->rollback();
                    throw new VirtualException('Error occurred while Ship confirming...');
                }
                foreach ($provided_data['box'] as $package) {
                    $shipping = new Shipping();
                    $shipping->setStatus(Shipping::STATUS_SUCCESS); // Confirmed
                    $shipping->setReference($package['package_name']);
                    $shipping->setCreateDate($package['created']);
                    $shipping->setUserId($this->user->getId());
                    $shipping->setCarrierId($provided_data['carrier_id']);
                    $shipping->setTrackingNumber($package['tracking_number']);
                    $shipping->setUrl($carrier->getUrl() . $package['tracking_number']);
                    $shipping->setCarrierTrackingNumber($this->data['ShipFromWarehouse']);
                    $shipping->save(['id', 'status', 'reference', 'create_date', 'user_id', 'carrier_id', 'tracking_number', 'url', 'carrier_tracking_number']);
                    Orders::setOrderPackageShipping($package['order_packages_id'], $shipping->getId());
                }
                $despatched_delivery = Deliveries::getById($order->getDeliveryId())->toArray();
                Orders::saveOriginalDespatched($order->getId(), $this->data, $despatched_delivery, $this->user->getId());
                foreach ($provided_data['box'] as $package)
                    foreach ($package['SKUs'] as $SKU_id => $SKU_data) {
                        $batch_id = $SKU_data['batch_id'] ?? null;
                        $mysql->execute("
                            UPDATE SKU_locations
                            SET quantity = quantity - :quantity
                            WHERE SKU_id = :SKU_id AND location_id = :location_id AND batch_id " . ($batch_id ? "= :batch_id" : "IS NULL"), array_merge([
                            'quantity' => (int)$SKU_data['quantity'],
                            'SKU_id' => (int)$SKU_id,
                            'location_id' => (int)$this->locs[$this->data['ShipFromWarehouse']]->getId(),
                        ], $batch_id ? ['batch_id' => (int)$batch_id] : []));
                    }
                $order_SKUs = OrderSKUs::getSKUs($order_id);
                foreach ($order_SKUs as $SKU_data)
                    SKUHistories::updateStock($SKU_data['id'], $this->warehouse_id);
                if (!$order->despatched()) {
                    $order->setDespatchedDate(time());
                    $order->save(['id', 'despatched_date']);
                    Orders::changeStatus($order->getId(), Order::DESPATCHED, $this->user->getId());
                }
            } catch (\Exception $e) {
                $mysql->rollback();
                if (($e instanceof DBException || $e instanceof DbQueryException) && $e->isDeadlock($num++))
                    continue;
                VirtualNotification::sendErrorToTelegram('ShipConfirm', 'Deadlock', Notifier::GROUP_VIRTUAL);
                throw new VirtualException('Deadlock');
            }
            $mysql->commit();
            break;
        }
        Queue::addJobToSetTrackInfo(['order_id' => $order_id]);
        Queue::addJobToUpdateOrderSearch(['order_id' => $order_id]);
        Queue::addJobToWebhook(['webhook' => 'orderDespatch', 'order_id' => $order->getId(), 'additional' => 'Visible']);
        return $order_id;
    }
}
