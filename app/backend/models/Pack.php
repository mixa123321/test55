<?php

namespace app\backend\models;

use app\core\db\DBException;
use app\core\db\Mysql;
use app\core\dbal\SafeMysql;
use app\core\entities\Carrier;
use app\core\entities\Order;
use app\core\entities\OrderPackageConsumable;
use app\core\entities\ShippingService;
use app\core\entities\Shipping as ShippingEntity;
use app\core\entities\User;
use app\core\entities\SKU;
use app\core\entities\LogisticCompany;
use app\core\helpers\Arr;
use app\core\helpers\Debug;
use app\core\helpers\View;
use app\core\models\OrderLocations;
use app\core\models\OrderLocationTemporaries;
use app\core\models\PackageConsumables;
use app\core\models\Returns;
use app\core\models\ReturnSAPI;
use app\core\models\SectionBindings;
use app\core\models\Shippings;
use app\core\models\OrderSKUSerialNumbers;
use app\core\Queue;
use app\core\services\shipping\Shipping;
use Exception;
use app\core\models\config\OrdersConfig;

class Pack
{
    public const LIMIT_PER_PAGE = 20;

    const BULK_PACK_LIMIT = 200;
    public const PACKING_SINGLE = 'single';
    public const PACKING_BULK = 'bulk';
    public const PACKING_MULTI = 'multi';
    public const PACKING_LARGE = 'large';

    public static $type_of_packing = [
        1 => self::PACKING_SINGLE,
        2 => self::PACKING_MULTI,
        3 => self::PACKING_LARGE,
    ];
    public static $type_of_packing_short = [
        's' => self::PACKING_SINGLE,
        'm' => self::PACKING_MULTI,
        'l' => self::PACKING_LARGE,
    ];
    /** in minutes */
    public const PACKING_LOCK_TIME = 60;
    public const SINGLE_PACKING_LOCK_TIME = 5;

    /** SINGLE */

    public const SINGLE_TABLE_ID = 'single_table_id';
    public const SINGLE_ORDERS = 'single_pack_orders';

    public const MULTI_BOX_CHANGE_SHIPPING_SERVICE_FROM = 'DHL01';
    public const MULTI_BOX_CHANGE_SHIPPING_SERVICE_TO = 'FSD-DHL';

    public static function getAllowedPackingStatuses()
    {
        return [
            Order::AWAITING_PACK_NUM,
            Order::PACKING_NUM,
            Order::PACKING_ISSUE_NUM,
            Order::PRINTING_DOCUMENTS_NUM,
            Order::PRINTING_DOCUMENTS_ISSUE_NUM,
            Order::PACKED_NUM,
        ];
    }

    public static function getSuccessPackingStatuses(): array
    {
        return [
            Order::AWAITING_PACK_NUM,
            Order::PACKING_NUM,
            Order::PRINTING_DOCUMENTS_NUM,
            Order::PACKED_NUM,
        ];
    }

    public static function validatePackingConfirm($provided_data)
    {
        $required_fields = [
            'order_id' => "Order ID",
            'location_id' => "Location ID",
            'box' => "Boxes",
            'carrier_id' => "Carrier",
        ];
        foreach ($required_fields as $field_key => $field_desc) {
            if (empty($provided_data[$field_key])) {
                return "Incorrect {$field_desc} provided.
                 Please redo the packing process or contact the system administrator providing this error message";
            }
        }
        //check SKUs quantity
        $order_skus = OrderSKUs::getSKUs($provided_data['order_id']);
        $expected_barcodes = Arr::map($order_skus, 'id', 'barcode');
        if (empty($order_skus))
            return "The order you are trying to pack is not found in the system";

        $max_package_weight = ShippingServices::getMaxWeight($provided_data['shipping_service_id'] ?? null);
        $not_weighted = 0;
        foreach ($order_skus as $sku) {
            $sku_id = $sku['id'];
            $bc = $sku['barcode'];
            $expected_qty = $sku['quantity'];
            $provided_total_qty = 0;
            foreach ($provided_data['box'] as $box) {
                //Check for any unexpected SKUs in the provided data
                if (!empty($box['SKUs'])) {
                    foreach ($box['SKUs'] as $box_sku_id => $box_data) {
                        if (!isset($expected_barcodes[$box_sku_id])) {
                            return "Unexpected item {$box_sku_id} was found in the packages. Please redo the packing process";
                        }
                    }
                    if (!empty($box['SKUs'][$sku_id])) {
                        $provided_total_qty += $box['SKUs'][$sku_id];
                    }
                    if (empty($box['weight']) || (double)$box['weight'] <= 0)
                        $not_weighted++;
                    if (!empty($max_package_weight) && (double)$box['weight'] > $max_package_weight)
                        return "Package weight cannot be less than the maximum allowable for this shipping service ({$max_package_weight} kg)";
                    $packaging = Consumables::getByPackageId($box['package_type_id']);
                    if (empty($box['length']) || (double)$box['length'] <= 0)
                        $box['length'] = !empty($packaging) ? $packaging->getLength() : 0;
                    if (empty($box['width']) || (double)$box['width'] <= 0)
                        $box['width'] = !empty($packaging) ? $packaging->getWidth() : 0;
                    if (empty($box['height']) || (double)$box['height'] <= 0)
                        $box['height'] = !empty($packaging) ? $packaging->getHeight() : 0;
                }
            }
            if ($provided_total_qty != $expected_qty) {
                return "Expected quantity of the item {$bc} is {$expected_qty}.
                 Total packed quantity is {$provided_total_qty}. Please redo the packing process";
            }
        }
        if ($not_weighted > 0)
            return 'Not all parcels were weighed';
        if (isset($provided_data['return_carrier']) && is_null($provided_data['return_carrier']))
            return 'Return Carrier is not found';
        return true;
    }

    /**
     * @param $data
     * @param $user User
     * @param $warehouse_id
     * @param $SKUs array
     * @paarm $serial_numbers_objects OrderSKUSerialNumber[][]
     * @return bool
     */
    public static function packingConfirm($data, User $user, $warehouse_id, $SKUs, array $serial_numbers_objects): bool
    {
        $num = 0;
        $mysql = new SafeMysql();
        while (true) {
            $mysql->beginTransaction(Mysql::LEVEL_SERIALIZABLE);
            try {
                $order_id = $data['order_id'];
                OrderSKUSerialNumbers::delByOrderId($order_id);
                foreach ($data['box'] as $box_id => $box) {
                    if (!empty($box['SKUs'])) {
                        $sql = "INSERT INTO orders_packages
                                SET order_id = :order_id,
                                    package_type_id = :package_type_id,
                                    package_name = :package_name,
                                    length = :length,
                                    width = :width,
                                    height = :height,
                                    weight = :weight,
                                    order_inserts = :order_inserts";

                        // Add category directly to the INSERT if it exists
                        if (!empty($box['category'])) {
                            $sql .= ", category = :category";
                        }

                        if (!$order_packages_id = $mysql->insert($sql, [
                            'order_id' => $order_id,
                            // NOT NULL columns; legacy stored 0 / '' when the box fields were not posted
                            'package_type_id' => (int)($box['package_type_id'] ?? 0),
                            'package_name' => (string)($box['package_name'] ?? ''),
                            'length' => (double)$box['length'],
                            'width' => (double)$box['width'],
                            'height' => (double)$box['height'],
                            'weight' => (double)$box['weight'],
                            'order_inserts' => empty($box['order_inserts']) ? 0 : 1,
                            'category' => !empty($box['category']) ? $box['category'] : null
                        ])) {
                            $mysql->rollback();
                            return false;
                        }
                        $packaging = Consumables::getByPackageId($box['package_type_id']);
                        $consumables = PackageConsumables::getByPackageId($box['package_type_id']);
                        $order_package_consumables = new OrderPackageConsumable([
                            'order_package_id' => $order_packages_id,
                            'consumables' => json_encode([
                                'packaging' => $packaging ? $packaging->toArray() : [],
                                'consumables' => $consumables,
                            ])
                        ]);
                        $order_package_consumables->save();
                        if ($packaging)
                            ConsumablesCount::decreaseQuantity([$packaging->getId() => 1] + $consumables, $warehouse_id, $order_packages_id);
                        foreach ($box['SKUs'] as $SKU_id => $qty) {
                            $sql = "INSERT INTO orders_packages_SKUs
                                SET orders_packages_id = :orders_packages_id,
                                    SKU_id = :SKU_id,
                                    quantity = :quantity";
                            if (!$mysql->execute($sql, [
                                'orders_packages_id' => $order_packages_id,
                                'SKU_id' => $SKU_id,
                                'quantity' => $qty,
                            ])) {
                                $mysql->rollback();
                                return false;
                            }
                        }
                        if (!empty($serial_numbers_objects[$box_id])) {
                            $all_objects = [];
                            foreach ($serial_numbers_objects[$box_id] as $obj) {
                                $obj->setOrdersPackagesId($order_packages_id);
                                $all_objects[] = $obj;
                            }
                            OrderSKUSerialNumbers::bulkAdd((int)$order_id, (int)$warehouse_id, $all_objects);
                        }
                    }
                }
                
                $loc = OrderLocations::getLocation($order_id);
                foreach ($SKUs as $SKU) {
                    $move_id = Movements_::setPacking($loc, $order_id, $SKU['id'], $SKU['quantity'], $user->getId());
                    if (!$move_id) {
                        $mysql->rollback();
                        return false;
                    }
                    SKUHistories::addPacking($loc, $order_id, $SKU['id'], $SKU['quantity'], $move_id, $user->getId(), $warehouse_id);
                }
                Orders::changeStatus($order_id, Order::PRINTING_DOCUMENTS, $user->getId(), "Packing started (printing documents)");
            } catch (Exception $e) {
                $mysql->rollback();
                if ($e instanceof DBException && $e->isDeadlock($num++))
                    continue;
                return false;
            }
            $mysql->commit();
            break;
        }
        return true;
    }

    public static function unpack(Order $order, User $user, $cancel_shipment)
    {
        $supplier = Suppliers::getSupplierByUser($order->getWarehouseId(), $order->getUserId());
        if ($supplier === null)
            return [false, 'Cannot run the unpack operation on the selected order'];
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        //0. Удаляем серийные номера при распаковке
        OrderSKUSerialNumbers::delByOrderId($order->getId());
        //1. Cancel shipping shipment
        $shipments = Shippings::getByOrderId($order->getId());
        if (empty($shipments))
            $shipments[] = [
                'tracking_number' => '',
                'reference' => $order->getRef()
            ];
        $message = [];
        foreach ($shipments as $shipment) {
            $res = $cancel_shipment($supplier['shipping_api_key'], $shipment['tracking_number'], $shipment['reference']);
            if (!is_null($res))
                $message[] = $res;
        }
        //2. Cancel Return labels
        $returns = Returns::getByOrderId($order->getId());
        foreach ($returns as $return) {
            $res = ReturnSAPI::sendCancelReturnRequest($return['tracking_number'], (int)$return['is_test']);
            if (!empty($res['errors'])) {
                $errors = json_decode($res['errors'], true);
                $message[] = "Returns: {$errors['errors'][0]['message']}";
            }
            (new SafeMysql())->execute("UPDATE `returns` SET status = -1, update_date = CURRENT_TIMESTAMP WHERE id = :id", [
                'id' => $return['id'],
            ]);
        }
        //3. Increase consumables
        $packages_data = OrderPackages::getBoxesNotCancelled($order->getId());
        if (!empty($packages_data))
            foreach ($packages_data as $package_data) {
                $packaging = Consumables::getByPackageId($package_data['package_type_id'], true);
                $consumables = PackageConsumables::getReuseByPackageId($package_data['package_type_id']);
                ConsumablesCount::increaseQuantity((empty($packaging) ? [] : [$packaging->getId() => 1]) + $consumables, $order->getWarehouseId(), $package_data['id']);
            }
        //4. UPDATE order_packages to CANCELED
        (new SafeMysql())->execute("UPDATE orders_packages SET is_cancel = 1 WHERE order_id = :order_id", [
            'order_id' => $order->getId(),
        ]);
        //5.
        OrdersConfig::deleteDespatchedCarrierId($order->getId());
        OrdersConfig::deleteShippingServiceId($order->getId());
        //6. Return order status to 'awaiting pack'
        Orders::changeStatus($order->getId(), Order::AWAITING_PACK, $user->getId(), "Packing cancelled");
        $mysql->commit();
        if (!empty($message))
            return [true, implode('<br/>', array_map([View::class, 'toHtml'], $message))];
        return [true];
    }

    public static function printingDocsConfirm(Order $order, User $user, $mode)
    {
        $old_carrier = $old_shipping_service = $new_carrier = $new_shipping_service = null;
        if ($new_carrier_id = OrdersConfig::getDespatchedCarrierId((int)$order->getId())) {
            $old_carrier = Carriers::getById($order->getCarrierId())->getCode();
            $new_carrier = Carriers::getById($new_carrier_id)->getCode();
            $order->setCarrierId($new_carrier_id);
        }
        if ($new_shipping_service_id = OrdersConfig::getShippingServiceId((int)$order->getId())) {
            $old_shipping_service_id = $order->getShippingServicesId();
            $old_shipping_service = $old_shipping_service_id ? ShippingServices::getById($old_shipping_service_id)->getCode() : ShippingEntity::COLLECTION_BY_CUSTOMER;
            $new_shipping_service = ShippingServices::getById($new_shipping_service_id)->getCode();
            $order->setShippingServicesId($new_shipping_service_id);
        }
        $carts = SKULocations::getCALocation($order->getId());
        $num = 0;
        $mysql = new SafeMysql();
        while (true) {
            $mysql->beginTransaction();
            try {
                $mysql->execute('DELETE FROM order_location_temporary WHERE order_id = :order_id', [
                    'order_id' => (int)$order->getId(),
                ]);
                $mysql->execute('DELETE FROM order_location WHERE order_id = :order_id', [
                    'order_id' => (int)$order->getId(),
                ]);
                //Save despatched items before they are deleted. This will save batch info
                $despatched_items = (new SafeMysql())->fetchAll('SELECT * FROM SKU_locations WHERE order_id = :order_id', ['order_id' => $order->getId()]);
                $despatched_delivery = Deliveries::getById($order->getDeliveryId())->toArray();
                Orders::saveOriginalDespatched($order->getId(), $despatched_items, $despatched_delivery, $user->getId());
                $mysql->execute('DELETE FROM SKU_locations WHERE order_id = :order_id', [
                    'order_id' => (int)$order->getId(),
                ]);
                foreach ($carts as $cart_id)
                    if (!SKULocations::getCountLocation($cart_id))
                        SectionBindings::setFreeCart($user->getId(), $cart_id, $mode);
                foreach ($despatched_items as $item)
                    if (in_array($item['location_id'], $carts))
                        SKUHistories::updateStock($item['SKU_id'], $order->getWarehouseId());
                Orders::changeStatus($order->getId(), Order::PACKED, $user->getId());
                if ($old_carrier && $new_carrier)
                    OrderHistories::updateCarrierId((int)$order->getId(), $old_carrier, $new_carrier, $user->getId());
                if ($old_shipping_service || $new_shipping_service)
                    OrderHistories::updateShippingServiceId((int)$order->getId(), $old_shipping_service, $new_shipping_service, $user->getId());
                $order->setDespatchedDate(time());
                $order->save(['id', 'despatched_date', 'carrier_id', 'shipping_services_id']);
                Orders::changeStatus($order->getId(), Order::DESPATCHED, $user->getId());
                self::unAssignUser($order->getId());
                OrdersConfig::deleteDespatchedCarrierId($order->getId());
                OrdersConfig::deleteShippingServiceId($order->getId());
            } catch (\Exception $e) {
                $mysql->rollback();
                if ($e instanceof DBException && $e->isDeadlock($num++))
                    continue;
                Debug::sendDebugInfo('printingDocsConfirm', var_export([$e->getCode(), $e->getMessage()], 1));
                return;
            }
            $mysql->commit();
            break;
        }
        Queue::addJobToSetTrackInfo(['order_id' => $order->getId()]);
        Queue::addJobToUpdateOrderSearch(['order_id' => $order->getId()]);
        Queue::addJobToWebhook(['webhook' => 'orderDespatch', 'order_id' => $order->getId()]);
    }

    public static function undespatching(Order $order, User $user)
    {
        if (!$order->despatched())
            return [false, 'Order is not despathed'];
        if ($order->getShippingServicesId())
            return [false, "Service is not 'Collection by customer'"];
        $items = OrdersOriginalDespatched::getItems($order->getId());
        if (empty($items))
            return [false, 'SKUs are not despatched'];
        $location = Locations::getById($items[0]['location_id']);
        if (empty($location))
            return [false, 'Location is not found'];
        if ($location->OB())
            if (OrderLocations::inLocation($location->getId()))
                return [false, 'Location is busy'];
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        OrderHistories::undespatching($order->getId(), $user->getId());
        OrderLocations::reserveLocation($order->getId(), $location->getId());
        foreach ($items as $item) {
            OrderLocationTemporaries::addSKU($item['SKU_id'], $item['quantity'], $item['order_id'], $item['location_id']);
            SKULocations::addLocation($item['location_id'], $item['SKU_id'], [[
                'quantity' => $item['quantity'],
                'order_id' => $item['order_id'],
                'type' => $item['type'],
                'reason' => $item['reason'],
                'batch_id' => $item['batch_id']
            ]]);
            SKUHistories::updateStock($item['SKU_id'], $order->getWarehouseId());
        }
        $order->setDespatchedDate(null);
        $order->save(['id', 'despatched_date']);
        Orders::changeStatus($order->getId(), Order::PRINTING_DOCUMENTS, $user->getId());
        $mysql->commit();
        Queue::addJobToUpdateOrderSearch(['order_id' => $order->getId()]);
        return [true];
    }

    public static function isActualPacker($order_id, $user_id, $lock_time = self::PACKING_LOCK_TIME)
    {
        $order_id = (int) $order_id;
        $user_id = (int) $user_id;
        return (new SafeMysql())->fetchScalar("SELECT COUNT(*) 
                                  FROM orders_users_packing 
                                  WHERE order_id = :order_id AND
                                        user_id <> :user_id AND
                                        TIMESTAMPDIFF(MINUTE, FROM_UNIXTIME(`time`), NOW()) <= " . $lock_time, ['order_id' => $order_id, 'user_id' => $user_id]);
    }

    public static function errorUrl($mode)
    {
        $params = [];
        if ($mode === 'single') {
            $params[] = !empty($_REQUEST['printer_def']) ? 'printer_def=' . $_REQUEST['printer_def'] : '';
            $params[] = !empty($_REQUEST['table_id']) ? 'table_id=' . $_REQUEST['table_id'] : '';
        }
        if (!empty($_SESSION['pack_services'])) {
            if (!is_array($_SESSION['pack_services']))
                $_SESSION['pack_services'] = explode(',', $_SESSION['pack_services']);
            foreach ($_SESSION['pack_services'] as $service)
                $params[] = 'services[]=' . $service;
        }
        if (!empty($params)) {
            $params = implode('&', $params);
            $params = '?' . $params;
        } else $params = '';
        return "/pack/packing_{$mode[0]}" . $params;
    }

    public static function assignUser($order_id, $user_id, $lock_time = self::PACKING_LOCK_TIME)
    {
        if (self::isActualPacker($order_id, $user_id, $lock_time))
            return false;
        $order_id = (int) $order_id;
        $user_id = (int) $user_id;
        $now = time();
        return (new SafeMysql())->execute(
            "INSERT INTO orders_users_packing (id, user_id, order_id, time) VALUES (NULL, :user_id, :order_id, :now)
            ON DUPLICATE KEY UPDATE user_id = :user_id, `time` = :now",
            [
                'user_id' => $user_id,
                'order_id' => $order_id,
                'now' => $now,
            ]
        );
    }

    public static function unAssignUser($order_id)
    {
        return (new SafeMysql())->execute(
            "DELETE FROM orders_users_packing WHERE order_id = :order_id",
            ['order_id' => (int)$order_id]
        );
    }

    public static function getAssignedUser($order_id)
    {
        return (new SafeMysql())->fetchScalar("
                SELECT login FROM users 
                    where id = (SELECT user_id FROM orders_users_packing where order_id = :order_id)", ['order_id' => $order_id]);
    }

    public static function resolveOrderPackingLockTime(string $mode): int
    {
        switch ($mode) {
            case self::PACKING_SINGLE:
            case self::PACKING_BULK:
                return self::SINGLE_PACKING_LOCK_TIME;
            break;
            case self::PACKING_MULTI:
            case self::PACKING_LARGE:
                return self::PACKING_LOCK_TIME;
            break;
            default:
                return self::PACKING_LOCK_TIME;
        }
    }

    public static function addOrderReference(&$packages, $order_ref, string $shipping_class)
    {
        if (empty($packages) || empty($order_ref))
            return;
        $max_len = 200;
        if ($shipping_class === Shipping::CLASS_SHIPPING_AB)
            $max_len = 35;
        if (count($packages) == 1) {
            $packages[array_keys($packages)[0]]['order_reference'] = mb_substr($order_ref, 0, $max_len);
            return;
        }
        $box_number = 1;
        $len_order_ref = mb_strlen($order_ref);
        foreach ($packages as &$package) {
            $suffix = '-box' . $box_number;
            $len_suffix = mb_strlen($suffix);
            if ($len_order_ref + $len_suffix > $max_len) {
                $hash = hash('crc32', $order_ref . $suffix);
                $len_hash = mb_strlen($hash);
                $package['order_reference'] = mb_substr($order_ref, 0, $max_len - $len_suffix - $len_hash) . $hash . $suffix;
            } else
                $package['order_reference'] = $order_ref . $suffix;
            $box_number++;
        }
        unset($package);
    }

    public static function processBulkV2(int $order_id, int $warehouse_id, array $pack, int $user_id): string
    {
        $order_obj = Orders::getById($order_id);
        if (!$order_obj)
            return "Wrong Order #$order_id";
        if (!in_array($order_obj->getStatus(), Pack::getAllowedPackingStatuses()))
            return '';
        $carriers = Carriers::getProcessing($warehouse_id);
        if (empty($carriers))
            return "Carriers are not found Order #$order_id";
        if (!in_array($order_obj->getCarrierId(), $carriers) || $order_obj->getWarehouseId() != $warehouse_id)
            return "Order data not found Order #$order_id";
        $order = Orders::getPackingOrderById((int)$order_obj->getId(), $carriers);
        if (!$order)
            return "Order data not found Order #$order_id";
        $supplier = Suppliers::getSupplierByUser($warehouse_id, $order_obj->getUserId());
        if ($supplier === null)
            return "Company account may be blocked. Please contact system administrator Order #$order_id";
        $user = Users::getById($user_id);
        if ($order_obj->awaitingPack() || $order_obj->packing()) {
            if (!self::assignUser($order_obj->getId(), $user_id, self::resolveOrderPackingLockTime(self::PACKING_SINGLE)))
                return "User " . self::getAssignedUser($order_obj->getId()) . " is already packing this Order #$order_id . Try pack user #" . $user_id;
            if ($order_obj->awaitingPack())
                Orders::changeStatus($order_obj->getId(), Order::PACKING, $user_id);
            if (self::isActualPacker($order_obj->getId(), $user_id, self::resolveOrderPackingLockTime(self::PACKING_SINGLE)))
                return "You were not assigned to this packing Order #$order_id";
            //packingConf
            $SKUs = OrderLocationTemporaries::getSKUs($order_obj->getId());
            if (!$SKUs)
                return "Not found SKU for order Order #$order_id";
            if (count($SKUs) !== 1)
                return "More than one SKU for bulk Order #$order_id";
            $SKU_id = (int)current($SKUs)['SKU_id'];
            $SKU = SKUs::getById($SKU_id);
            if (!$SKU)
                return "Wrong SKU Order #$order_id";
            $provided_data = self::createPackDataForBulk($order_obj, $SKU, (int)$order['location_id'], $pack['package_type_id'], $pack['width'], $pack['height'], $pack['length'], $pack['weight'], false);
            $validation_result = self::validatePackingConfirm($provided_data);
            if ($validation_result !== true)
                return $validation_result .  " Order #$order_id";
            $SKUs = OrderSKUs::getSKUs($order_id);
            $confirm_result = self::packingConfirm($provided_data, $user, $warehouse_id, $SKUs, []);
            if (!$confirm_result)
                return "Pack error. Order #$order_id";
        }
        //printingDocuments
        $consignee = Deliveries::getById($order['delivery_id']);
        if ($consignee === null)
            return "Cannot create shipping label. Consignee address is empty Order #$order_id";
        $supplier_warehouses = SupplierWarehouses::getWarehouses($supplier['id']);
        if (empty($supplier_warehouses) || empty($supplier_warehouses[$warehouse_id]))
            return "Supplier is not found in warehouse Order #$order_id";
        $pack_bulk_data = self::packBulk($order_obj, $order, $supplier, $consignee, $user_id);
        if ($pack_bulk_data['error'])
            return $pack_bulk_data['error'] . " Order #$order_id";
        $order_obj = Orders::getById($order_id);
        //confirmPrinting
        if ($order_obj->despatched())
            return "Order is already despatched. Order #$order_id";
        if ($order_obj->printingDocuments())
            self::printingDocsConfirm($order_obj, $user, self::PACKING_BULK);
        return '';
    }

    public static function createPackDataForBulk(Order $order, SKU $SKU, int $location_id, int $package_id, float $width, float $height, float $length, float $weight, bool $order_inserts): array
    {
        $provided_data = ['order_id' => $order->getId(), 'printer_def' => 0, 'location_id' => $location_id, 'carrier_id' => $order->getCarrierId(), 'shipping_service_id' => $order->getShippingServicesId()];
        $provided_data['box'] = [
            [
                'package_name' => 'Bulk Pack',
                'order_inserts' => $order_inserts,
                'package_type_id' => $package_id,
                'length' => $length,
                'width' => $width,
                'height' => $height,
                'weight' => $weight,
                'SKUs' => [
                    $SKU->getId() => 1
                ],
            ]
        ];
        return $provided_data;
    }

    public static function packBulk(Order $order_obj, array $order, array $supplier, $consignee, int $user_id): array
    {
        $packages = Orders::getPackages($order_obj->getId());
        if (empty($packages))
            return ['error' => 'Packages not found', 'packages' => []];
        $warehouse = Warehouses::getById($order_obj->getWarehouseId());
        $logistic_company = LogisticCompanies::getById($warehouse->getLCId(), LogisticCompany::ACTIVE);
        [$shipping, $carrier, $shipping_class] = Shipping::getShipping($logistic_company, $order['carrier_id']);
        Orders::createShipping($user_id, $shipping, $supplier, $consignee, $warehouse, $order, $packages, $shipping_class);
        return ['error' => "", 'packages' => $packages];
    }

    public static function getMaxWeight(Carrier $carrier, $shipping_func, ?ShippingService $shipping_service = null): array
    {
        $max_weight = null;
        $weight_message = null;
        if ($carrier->getMaxWeight() > 0) {
            $max_weight = $carrier->getMaxWeight();
            $weight_message = $carrier->getCode();
        }
        if ($shipping_func() > 0)
            if (is_null($max_weight) || $max_weight > $shipping_func()) {
                $max_weight = $shipping_func();
                $weight_message = $carrier->getCode();
            }
        if (!empty($shipping_service) && $shipping_service->getMaxWeight() > 0)
            if (is_null($max_weight) || $max_weight > $shipping_service->getMaxWeight()) {
                $max_weight = $shipping_service->getMaxWeight();
                $weight_message = $shipping_service->getName() . ' service';
            }
        if (!is_null($max_weight))
            $weight_message = 'Maximum weight for <b>' . View::toHtml($weight_message) . "</b> can't be above <b>" . round($max_weight, 1) . '</b> kg';
        return [$max_weight, $weight_message];
    }
}
