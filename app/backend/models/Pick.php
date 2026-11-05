<?php

namespace app\backend\models;

use app\core\db\DBException;
use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\entities\Order;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\helpers\Debug;
use app\core\helpers\Filter;
use app\core\models\config\OrdersConfig;
use app\core\helpers\Defends;

class Pick
{
    public const LIMIT_PER_PAGE = 20;

    const PICKING_TIME_DELAY = 1;
    const PICKING_SINGLE = 'single';
    const PICKING_MULTI = 'multi';
    const PICKING_LARGE = 'large';

    public static $mode_val = [
        self::PICKING_SINGLE => [1, 2],
        self::PICKING_MULTI => [2, 4],
        self::PICKING_LARGE => [3, 1],
    ];

    public static function getMode($mode)
    {
        foreach (self::$mode_val as $key => $value)
            if ($value[0] == $mode)
                return $key;
        return 'none';
    }

    public static function leaveTrolleyNearTable($cart_id, $mode, $location_id)
    {
        $mode = addslashes($mode);
        if (!in_array($mode, [self::PICKING_SINGLE, self::PICKING_LARGE]))
            return false;
        $mode = $mode == self::PICKING_SINGLE ? 'C' : 'U';
        $cart_id = (int) $cart_id;
        $location_id = (int) $location_id;
        $sql = "
        INSERT IGNORE INTO section_binding (num, object_id, type_id, warehouse_id)
        SELECT l.`row`, $cart_id, '$mode', l.warehouse
        FROM locations l
            LEFT JOIN section_binding sb ON sb.object_id = $cart_id AND sb.warehouse_id = l.warehouse AND sb.type_id IN ('C', 'U')
        WHERE l.id = $location_id AND sb.id IS NULL";
        $result = (new SafeMysql())->execute($sql);
        if (!$result) {
            Debug::sendDebugInfo('Error leaveTrolleyNearTable', "cart_id $cart_id mode $mode location_id $location_id", Debug::TEST);
            throw new \Exception("Error leaveTrolleyNearTable cart_id $cart_id mode $mode location_id $location_id", 2);
        }
        return true;
    }

    public static function addOnTable($SKU_id, $batch_id, $quantity, $mode, $order_id, $location_id, $cart_id, $user_id)
    {
        $SKU_id = (int) $SKU_id;
        $batch_id = (int) $batch_id;
        $quantity = (int) $quantity;
        $order_id = (int) $order_id;
        $location_id = (int) $location_id;
        $cart_id = (int) $cart_id;
        $user_id = (int) $user_id;
        $where = "SKU_id = $SKU_id AND order_id = $order_id AND batch_id " . (empty($batch_id) ? 'IS NULL' : "= $batch_id");
        $sql = "SELECT quantity
                FROM SKU_locations
                WHERE $where AND location_id = :location_id AND type = 31";
        $mysql = new SafeMysql();
        $q = $mysql->fetchScalar($sql, ['location_id' => $location_id]);
        if ($q) {
            $mysql->execute("UPDATE SKU_locations
                            SET quantity = quantity + $quantity, reason = '$user_id'
                            WHERE $where AND location_id = $location_id AND type = 31");
            $mysql->execute("DELETE FROM SKU_locations
                            WHERE $where AND location_id = $cart_id AND type = $mode");
        } else
            $mysql->execute("UPDATE SKU_locations
                            SET location_id = $location_id, type = 31, reason = '$user_id'
                            WHERE $where AND location_id = $cart_id AND type = $mode AND quantity = $quantity");
    }

    public static function  addNearTable($SKU_id, $batch_id, $quantity, $mode, $order_id, $cart_id, $user_id)
    {
        $SKU_id = (int) $SKU_id;
        $batch_id = (int) $batch_id;
        $quantity = (int) $quantity;
        $order_id = (int) $order_id;
        $cart_id = (int) $cart_id;
        $user_id = (int) $user_id;
        (new SafeMysql())->execute("UPDATE SKU_locations
                        SET type = 31, reason = '$user_id'
                        WHERE location_id = $cart_id AND
                              type = $mode AND
                              SKU_id = $SKU_id AND
                              order_id = $order_id AND
                              quantity = $quantity AND
                              batch_id " . (empty($batch_id) ? 'IS NULL' : "= $batch_id"));
    }

    public static function addDropping($SKU_id, $batch_id, $quantity, $all, $order_id, $location_id, $cart_id, $user_id)
    {
        $SKU_id = (int) $SKU_id;
        $batch_id = (int) $batch_id;
        $quantity = (int) $quantity;
        $order_id = (int) $order_id;
        $location_id = (int) $location_id;
        $cart_id = (int) $cart_id;
        $user_id = (int) $user_id;
        $whereBatch = (empty($batch_id) ? 'IS NULL' : "= $batch_id");
        $order_SKU = "order_id = $order_id AND SKU_id = $SKU_id AND batch_id $whereBatch";
        $sql = "SELECT quantity
                FROM SKU_locations
                WHERE type = 3 AND $order_SKU AND location_id = :location_id";
        $mysql = new SafeMysql();
        $q = $mysql->fetchScalar($sql, ['location_id' => $location_id]);
        $user_cart = $user_id . '_' . $cart_id;
        if ($all) {
            if ($q) {
                $mysql->execute("UPDATE SKU_locations
                                SET reason = '$user_cart', quantity = quantity + $quantity
                                WHERE type = 3 AND $order_SKU AND location_id = $location_id");
                $sql = "DELETE FROM SKU_locations
                        WHERE type = 22 AND $order_SKU AND location_id = $cart_id";
            } else
                $sql = "UPDATE SKU_locations
                        SET type = 3, reason = '$user_cart', location_id = $location_id
                        WHERE type = 22 AND $order_SKU AND location_id = $cart_id";
        } else {
            $mysql->execute("UPDATE SKU_locations
                            SET quantity = quantity - $quantity
                            WHERE type = 22 AND $order_SKU AND location_id = $cart_id");
            if ($q)
                $sql = "UPDATE SKU_locations
                        SET reason = '$user_cart', quantity = quantity + $quantity 
                        WHERE type = 3 AND $order_SKU AND location_id = $location_id";
            else
                $sql = "INSERT INTO SKU_locations (SKU_id, location_id, quantity, order_id, type, reason, batch_id)
                        VALUES ($SKU_id, $location_id, $quantity, $order_id, 3, '$user_cart', " . (empty($batch_id) ? 'NULL' : $batch_id) . ")";
        }
        $mysql->execute($sql);
        return true;
    }

    public static function getLocsZonePicking(Warehouse $warehouse, &$SKUs, $type_id, $mezzanine)
    {
        $workers = Users::getWorkersPicking($warehouse, time(), $type_id, $mezzanine);
        $loc_zones = Locations::getLocationZones($warehouse->getId(), $type_id, $mezzanine);
        foreach ($SKUs as $key => $SKU) {
            if ($SKU['mezzanine'] != $mezzanine || $SKU['type_id'] != $type_id) {
                unset($SKUs[$key]);
                continue;
            }
            $loc_zones[$SKU['zone']]['m']++;
            $loc_zones[$SKU['zone']]['u'] += $SKU['quantity'];
        }
        $movements = [];
        array_walk($loc_zones, function ($value, $key) use(&$movements) {
            $movements[$key] = $value['m'];
        });
        foreach ($workers as $zone => $lst_workers)
            $loc_zones[$zone]['w'] = $lst_workers;
        $items_main = [];
        foreach ($loc_zones as $zone => $aisles)
            $items_main[$zone] = 'Aisle ' . Locations::getAisles($zone, $mezzanine) . " - {$aisles['m']} movements/{$aisles['u']} units" .
                                 (empty($aisles['w']) ? '' : (' (' . implode('; ', $aisles['w']) . ')'));
        $history = $warehouse->getPickingZones($type_id, $mezzanine);
        $start_time = strtotime($warehouse->getStartWarehouse());
        $min_time = time();
        $least = null;
        $least_data = [];
        foreach ($loc_zones as $zone => $zone_data) {
            if (empty($zone_data['m']))
                continue;
            $curr_time = $history[$zone] ?? $start_time;
            if (
                $curr_time < $min_time ||
                (
                    $curr_time == $min_time &&
                    (
                        empty($least_data['m']) ||
                        $zone_data['m'] > $least_data['m'] ||
                        (
                            $zone_data['m'] == $least_data['m'] &&
                            (
                                empty($least_data['u']) ||
                                $zone_data['u'] > $least_data['u']
                            )
                        )
                    )
                )
            ) {
                $min_time = $curr_time;
                $least = $zone;
                $least_data = $zone_data;
            }
        }
        foreach ($items_main as $zone => $item)
            if (!Locations::isItemsAisles($zone, $type_id, $mezzanine, $warehouse->getId()))
                unset($items_main[$zone]);
        if (count($items_main) == 1)
            return [$items_main, $movements];
        $items[Filter::NOT_SELECTED] = 'Select zone';
        if ($least && !isset($items_main[$least])) {
            $least = null;
        }
        if (!empty($least))
            $items['LRP|' . $least] = "Least recently picked ({$items_main[$least]})";
        return [$items + $items_main, $movements];
    }

    public static function savePick(User $user, Warehouse $warehouse, $type_id, $zones, $mezzanine, $least = false)
    {
        $time = time();
        if ($user->isSystem()) {
            $user->setPickingData($type_id, $zones, $time, $mezzanine);
            $user->save(['id', 'settings']);
        }
        if (!$least)
            return;
        $warehouse->setPickingZone($time, $type_id, $zones, $mezzanine);
        $warehouse->save(['id', 'picking_history']);
    }

    /**
     * @param array<array{SKU_id: int|null, SKU: SKU|null, size: int, quantity: int, total_quantity: int, batch_id: int|null}>|null $SKUs
     */
    public static function pickSKUsFromLoc(User $user, Warehouse $warehouse, Location $from_loc, Location $to_loc, $mode, $mezzanine, $services, array $SKUs = null)
    {
        if ($SKUs === null) {
            // getting all SKUs for picking from location
            $SKUs_in_loc = SKULocations::getSKUsFromLocation($from_loc->getId(), true);
            $SKUs = [];
            foreach ($SKUs_in_loc as $SKU) {
                // we need SKUs ready for picking
                if (empty($SKU['order_id']) || $SKU['reason'] != '1' || $SKU['type'] != 1)
                    continue;
                if (!empty($SKUs[$SKU['SKU_id']])) {
                    $SKUs[$SKU['SKU_id']]['size']++;
                    $SKUs[$SKU['SKU_id']]['quantity'] += $SKU['summa'];
                    $SKUs[$SKU['SKU_id']]['total_quantity'] += $SKU['summa'];
                    continue;
                }
                $SKUs[$SKU['SKU_id']] = [
                    'SKU_id' => (int)$SKU['SKU_id'],
                    'size' => 1,
                    'quantity' => (int)$SKU['summa'],
                    'total_quantity' => (int)$SKU['summa'],
                    'batch_id' => $SKU['batch_id'],
                ];
            }
        }
        list(, $all_orders) = SKULocations::getLeftByOrder($warehouse->getId(), $mode, $user->getId(), $mezzanine, $services);
        $num = 0;
        $warehouse_label = $warehouse->getLabel() ?: $warehouse->getId();
        $mysql = new SafeMysql();
        while (true) {
            $mysql->beginTransaction();
            try {
                $successes = [];
                $errors = [];
                $sizes = [];
                $orders_to_backorder = $orders_to_re_create = $SKUs_free = $SKUs_to_query = [];
                foreach ($SKUs as $SKU_info) {
                    if ((empty($SKU_info['SKU_id']) && empty($SKU_info['SKU']))
                        || empty($SKU_info['size'])
                        || empty($SKU_info['total_quantity'])
                    ) {
                        $errors[] = 'SKU info is invalid';
                        continue;
                    }
                    $size = $SKU_info['size'];
                    $quantity = $SKU_info['quantity'];
                    $batch_id = $SKU_info['batch_id'];
                    $delta = $SKU_info['total_quantity'] - $quantity;
                    /** @var SKU */
                    $SKU = $SKU_info['SKU'] ?? SKUs::getById($SKU_info['SKU_id']);
                    $SKU_id = $SKU->getId();
                    if (empty($SKU)) {
                        $errors[] = "SKU (#{$SKU_info['SKU_id']}) is not found";
                        continue;
                    }
                    if ($delta < 0 || $quantity < 0) {
                        $errors[] = 'SKU #' . $SKU_id . ' quantity must be greater than 0!';
                        continue;
                    }
                    $index = $from_loc->getId() . '_' . $SKU_id . '_' . (empty($batch_id) ? '.' : $batch_id);
                    if (empty($all_orders[$index])) {
                        $errors[] = 'No orders found for picking of #' . $SKU->getId() . ' SKU';
                        continue;
                    }
                    $orders = array_filter($all_orders[$index]);
                    $query = $SKU_info['query'] ?? false;
                    if ($query && empty($SKU_info['quantity'])) {
                        $SKUs_to_query[$SKU_info['SKU_id']] = $SKU_info['SKU_id'];
                    } elseif (empty($SKU_info['quantity'])) {
                        $errors[] = 'SKU info is invalid';
                        continue;
                    }
                    $order_ids = array_combine(array_keys($orders), array_keys($orders));
                    foreach ($orders as $partial_order_id => $quan)
                        if ($quan <= $quantity) {
                            self::movedOrder($mode, $from_loc->getId(), $to_loc->getId(), $SKU_id, $batch_id, $quan, $partial_order_id, $user->getId(), $warehouse->getId());
                            $successes[] = "SKU (#$SKU_id" . (empty($batch_id) ? '' : ", Batch#: $batch_id") . "), quantity $quan, is reserved for order #$partial_order_id";
                            OrderHistories::pickedSKU($partial_order_id, SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()), empty($batch) ? '' : $batch->getInfo(), $quan, $from_loc->getLocation($warehouse_label), $user->getId());
                            $quantity -= $quan;
                            unset($order_ids[$partial_order_id]);
                        } elseif ($query)
                            $SKUs_to_query[$SKU_info['SKU_id']] = $SKU_info['SKU_id'];
                    if ($quantity) {
                        $partial_order_id = reset($order_ids) ?? null;
                        if (empty($partial_order_id) || !self::movedPartialOrder($mode, $from_loc->getId(), $to_loc->getId(), $SKU_id, $batch_id, $quantity, $partial_order_id, $user->getId(), $warehouse->getId()))
                            return ['result' => false, 'message' => 'Operation is not complete. Try again', 'errors' => $errors, 'successes' => $successes];
                        OrderHistories::pickedSKU($partial_order_id, SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()), empty($batch) ? '' : $batch->getInfo(), $quantity, $from_loc->getLocation($warehouse_label), $user->getId());
                        array_unshift($successes, "SKU (#$SKU_id), quantity $quantity, is reserved for order #$partial_order_id partially");
                        if ($query)
                            $SKUs_to_query[$SKU_info['SKU_id']] = $SKU_info['SKU_id'];
                    }
                    if (!$delta)
                        $size--;
                    $sizes[$SKU_id] = $size;
                }
            }
            catch (\Exception $e) {
                $mysql->rollback();
                if ($e instanceof DBException && $e->isDeadlock($num++))
                    continue;
                Debug::sendDebugInfo('Picking Confirmation', var_export([$e->getCode(), $e->getMessage()], 1));
                return ['result' => false, 'message' => 'Operation is not complete. Try again', 'errors' => $errors, 'successes' => $successes];
            }
            $mysql->commit();
            break;
        }
        if ($SKUs_to_query) {
            $loc_DH_M = Locations::getByDiscrepancyHoldMLocation($warehouse->getId());
            $orders_to_backorder = OrderSKUs::getOrdersDataForReconciliationWithQuery($SKUs_to_query, $from_loc->getId(), [
                Order::BACKORDER_NUM, Order::PENDING_APPROVAL_NUM, Order::WAITING_FOR_REPLEN_NUM, Order::APPROVED_NUM,
                Order::WAITING_FOR_LARGE_NUM, Order::WAITING_FOR_SINGLE_NUM, Order::WAITING_FOR_ORDER_BIN_NUM,
                Order::AWAITING_PICK_NUM, Order::AWAITING_LARGE_PICK_NUM, Order::AWAITING_SINGLE_PICK_NUM,
            ], $warehouse);
            $orders_to_re_create = OrderSKUs::getOrdersDataForReconciliationWithQuery($SKUs_to_query, $from_loc->getId(), [
                Order::LARGE_PICKING_NUM, Order::PICKING_NUM,
            ], $warehouse);
            $SKUs_free = SKULocations::getDataForReconciliationWithQuery($SKUs_to_query, $from_loc->getId(), $warehouse->getId());
        }
        foreach ($SKUs_free as $SKU_item) {
            $mysql = new SafeMysql();
            $mysql->beginTransaction();
            if (!SKULocations::removeFreeSKUsForCounting($SKU_item['location_id'], $SKU_item['SKU_id'], (int)$SKU_item['batch_id'], (int)$SKU_item['quantity'], (int)$SKU_item['quantity'])) {
                $mysql->rollback();
                $errors[] = 'The quantity has changed. Please try again.';
                continue;
            }
            SKULocations::addSKUsToDHForCounting($loc_DH_M[0]->getId(), $SKU_id, (int)$SKU_item['quantity']);
            $mysql->commit();
        }
        foreach ($orders_to_backorder as $order_item) {
            $order = Orders::getById($order_item['real_order_id']);
            $defends = (new Defends())->setType(Defends::TYPE_ORDERS)->setKey((string)$order->getId());
            if ($defends->lockDate() === Defends::LOCK_NOT_UPDATE) {
                $defends->unLock();
                return ['error' => "Order locked " . $order->getId() . ". Please try again."];
            }
            $mysql = new SafeMysql();
            $mysql->beginTransaction();
            OrdersConfig::saveConfigs([OrdersConfig::orderStatusBeforeQuery($order->getId(), Order::BACKORDER_NUM)]);
            Orders::changeStatus($order->getId(), Order::QUERY, $user->getId());
            if ($error = Orders::reconciliationSKUInOrder($order_item, $loc_DH_M[0]->getId(), $user->getId())) {
                $mysql->rollback();
                $errors[] = 'Reconciliation not finish. ' . $error;
                continue;
            }
            $qty = $order_item['old_quantity'] - $order_item['del_quantity'];
            $move_id = Movements_::movement($order_item['location_id'], $loc_DH_M[0]->getId(), $SKU_id, $batch_id, abs($qty), $user->getId());
            SKUHistories::movement($order_item['location_id'], $loc_DH_M[0]->getId(), $SKU_id, abs($qty), 'Reconciliation', $move_id, $user->getId(), $warehouse->getId(), 'Reconciliation');
            SKUHistories::changeStockTwoLocation($order_item['location_id'], $loc_DH_M[0]->getId(), $SKU_id, $warehouse->getId());
            $mysql->commit();
            $defends->unLock();
        }
        foreach ($orders_to_re_create as $order_item) {
            $order = Orders::getById($order_item['real_order_id']);
            $defends = (new Defends())->setType(Defends::TYPE_ORDERS)->setKey((string)$order->getId());
            if ($defends->lockDate() === Defends::LOCK_NOT_UPDATE) {
                $defends->unLock();
                return ['error' => "Order locked " . $order->getId() . ". Please try again."];
            }
            $qties = OrderSKUs::getSKUsQuantitiesByOrder($order->getId());
            $failDocs = OrderDocs::checkAwaitingDocs($order->getId());
            $mysql = new SafeMysql();
            $mysql->beginTransaction();
            if ($error = Orders::reconciliationSKUInOrder($order_item, $loc_DH_M[0]->getId(), $user->getId())) {
                $mysql->rollback();
                $errors[] = 'Reconciliation not finish. ' . $error;
                continue;
            }
            $movements = SKULocations::cancelOrder($order->getId());
            if ($movements === null) {
                $mysql->rollback();
                $errors[] = 'Conflict SKU. Please try again.';
                continue;
            }
            foreach ($movements as $movement) {
                $SKU_obj = SKUs::getById($movement['SKU_id']);
                if (empty($SKU_obj)) {
                    $mysql->rollback();
                    $errors[] = "SKU #{$movement['SKU_id']} not found";
                    continue 2;
                }
                $res = Movements::cancelSKUByOrder($SKU_obj, $movement['quantity'], $movement['location_id'], $movement['batch_id'], "Cancel order ({$order->getRef()})", $user->getId(), $warehouse->getId());
                if (!$res[0]) {
                    $mysql->rollback();
                    $errors[] = $res[1];
                    continue 2;
                }
                SKUHistories::cancelOrder($SKU_obj->getId(), $movement['quantity'], $movement['location_id'], $order->getId(), $user->getId(), $warehouse->getId());
                SKUHistories::updateStock($SKU_obj->getId(), $warehouse->getId());
            }
//            Orders::changeStatus((int)$order->getId(), Order::CANCELLED, $user->getId());
//            OrderHistories::cancelOrder($order->getId(), $user->getId());
            Orders::clearOrderTables((int)$order->getId(), $user->getId());
            // 3 Recreate order
//            OrderHistories::recreateOrder($order->getId(), $user->getId());
            if (!$failDocs) {
//                Orders::changeStatus($order->getId(), Order::NEW, $user->getId());
                Orders::creationOrder($order, $qties, $user->getId(), true);
                OrdersConfig::saveConfigs([OrdersConfig::orderStatusBeforeQuery($order->getId(), Order::BACKORDER_NUM)]);
            } else {
                Orders::changeStatus($order->getId(), Order::AWAITING_UPLOAD_DOCUMENTS, $user->getId());
                OrdersConfig::saveConfigs([OrdersConfig::orderStatusBeforeQuery($order->getId(), Order::AWAITING_UPLOAD_DOCUMENTS_NUM)]);
            }
            Orders::changeStatus($order->getId(), Order::QUERY, $user->getId());
            $mysql->commit();
            $defends->unLock();
        }
        foreach ($SKUs_to_query as $SKU_id)
            ShopInventoryQueues::addSKU($SKU_id, $warehouse->getId(), InventorySync::SYNC_SKU);
        if ($orders_to_backorder || $orders_to_re_create)
            $successes[] = "Orders to query #" . implode(', #', array_unique(array_column(array_merge($orders_to_backorder, $orders_to_re_create), 'real_order_id')));
        self::savePick($user, $warehouse, $from_loc->getTypeId(), $from_loc->getZone(), $warehouse->getMezzanine() ? $from_loc->getMezzanine() : null, true);
        return ['result' => $sizes, 'message' => 'Operation is complete', 'successes' => $successes, 'errors' => $errors];
    }

    private static function movedOrder($mode, $loc_id, $new_loc_id, $SKU_id, $batch_id, $qty, $order_id, $user_id, $warehouse_id)
    {
        $whereBatch = (empty($batch_id) ? 'IS NULL' : "= $batch_id");
        $sql = "SELECT 1
                FROM SKU_locations
                WHERE SKU_id = :SKU_id AND 
                      location_id = :new_loc_id AND 
                      order_id = :order_id AND 
                      batch_id $whereBatch";
        $mysql = new SafeMysql();
        $exist = $mysql->fetchScalar($sql, ['SKU_id' => $SKU_id, 'new_loc_id' => $new_loc_id, 'order_id' => $order_id]);
        $status = '2' . self::$mode_val[$mode][0];
        if ($exist) {
            // picking pallet
            if ($loc_id === $new_loc_id) {
                $sql = "UPDATE SKU_locations
                                SET type = $status, reason = '$user_id'
                                WHERE order_id = $order_id AND SKU_id = $SKU_id AND location_id = $new_loc_id AND batch_id $whereBatch";
            } else {
                // picking more
                $mysql->execute("UPDATE SKU_locations
                                SET type = $status, reason = '$user_id', quantity = quantity + $qty
                                WHERE order_id = $order_id AND SKU_id = $SKU_id AND location_id = $new_loc_id AND batch_id $whereBatch");
                $sql = "DELETE FROM SKU_locations
                        WHERE type = 1 AND order_id = $order_id AND SKU_id = $SKU_id AND location_id = $loc_id AND batch_id $whereBatch";
            }
            // just new pick
        } else
            $sql = "UPDATE SKU_locations
                    SET type = $status, reason = '$user_id', location_id = $new_loc_id
                    WHERE SKU_id = $SKU_id AND location_id = $loc_id AND quantity = $qty AND order_id = $order_id AND type = 1 AND reason = '1' AND batch_id $whereBatch";
        $mysql->execute($sql);
        $move_id = Movements_::setPicking($new_loc_id, $order_id, $SKU_id, $batch_id, $qty, $user_id, $mode);
        SKUHistories::addPicking($loc_id, $new_loc_id, $order_id,  $SKU_id, $qty, $move_id, $user_id, $mode, $warehouse_id);
        $order = Orders::getById($order_id);
        $status = ($mode == self::PICKING_MULTI ? '' : "$mode ") . Order::PICKING;
        if ($order->getStatusName() != $status)
            Orders::changeStatus($order->getId(), $status, $user_id);
        return true;
    }

    private static function movedPartialOrder($mode, $loc_id, $new_loc_id, $SKU_id, $batch_id, $qty, $order_id, $user_id, $warehouse_id)
    {
        $whereBatch = (empty($batch_id) ? 'IS NULL' : "= $batch_id");
        $sql = "SELECT quantity
                FROM SKU_locations
                WHERE SKU_id = :SKU_id AND 
                      location_id = :loc_id AND 
                      order_id = :order_id AND 
                      batch_id $whereBatch AND 
                      type = 1 AND 
                      reason = '1'";
        $mysql = new SafeMysql();
        $q = $mysql->fetchScalar($sql, ['SKU_id' => $SKU_id, 'loc_id' => $loc_id, 'order_id' => $order_id]);
        if ($q < $qty)
            return false;
        if ($q == $qty)
            return self::movedOrder($mode, $loc_id, $new_loc_id, $SKU_id, $batch_id, $qty, $order_id, $user_id, $warehouse_id);
        $mysql->execute("UPDATE SKU_locations
                        SET quantity = quantity - $qty
                        WHERE SKU_id = $SKU_id AND location_id = $loc_id AND order_id = $order_id AND type = 1 AND reason = '1' AND batch_id $whereBatch");
        $status = '2' . self::$mode_val[$mode][0];
        $exist = $mysql->fetchScalar("SELECT 1
                                    FROM SKU_locations
                                    WHERE SKU_id = :SKU_id AND 
                                          location_id = :new_loc_id AND 
                                          order_id = :order_id AND 
                                          batch_id $whereBatch",
                                    ['SKU_id' => $SKU_id, 'new_loc_id' => $new_loc_id, 'order_id' => $order_id]);
        if ($exist)
            $mysql->execute("UPDATE SKU_locations
                            SET quantity = quantity + $qty, reason = '$user_id', type = $status
                            WHERE SKU_id = $SKU_id AND 
                                  location_id = $new_loc_id AND 
                                  order_id = $order_id AND 
                                  batch_id $whereBatch");
        else
            $mysql->execute("INSERT INTO SKU_locations (SKU_id, location_id, quantity, order_id, type, reason, batch_id)
                            VALUES ($SKU_id, $new_loc_id, $qty, $order_id, $status, '$user_id', " . (empty($batch_id) ? 'NULL' : $batch_id) . ")");
        $move_id = Movements_::setPicking($new_loc_id, $order_id, $SKU_id, $batch_id, $qty, $user_id, $mode);
        SKUHistories::addPicking($loc_id, $new_loc_id, $order_id,  $SKU_id, $qty, $move_id, $user_id, $mode, $warehouse_id);
        $order = Orders::getById($order_id);
        $status = ($mode == self::PICKING_MULTI ? '' : "$mode ") . Order::PICKING;
        if ($order->getStatusName() != $status)
            Orders::changeStatus($order->getId(), $status, $user_id);
        return true;
    }

    public static function unpickSKUsInLoc(Warehouse $warehouse, User $user, Location $loc)
    {
        $warehouse_label = $warehouse->getLabel() ?: $warehouse->getId();
        $SKUs = SKULocations::getSKUsFromLocation($loc->getId(), true);
        $successes = [];
        $errors = [];
        $processed_orders = [];
        $mysql = new SafeMysql();
        foreach ($SKUs as $SKU) {
            $type = substr($SKU['type'], 0, 1);
            $mode = self::getMode(substr($SKU['type'], 1));
            if (empty($SKU['order_id']) || $type != 2 || $mode == 'none')
                continue;
            $SKU_id = $SKU['SKU_id'];
            $batch_id = $SKU['batch_id'];
            $quantity = $SKU['summa'];
            $order_id = $SKU['order_id'];
            $status = '1';
            $whereBatch = (empty($batch_id) ? 'IS NULL' : "= $batch_id");
            $sql = "SELECT quantity
                    FROM SKU_locations
                    WHERE SKU_id = :SKU_id AND 
                          location_id = :loc_id AND 
                          order_id = :order_id AND 
                          batch_id $whereBatch";
            $q = $mysql->fetchScalar($sql, ['SKU_id' => $SKU_id, 'loc_id' => $loc->getId(), 'order_id' => $order_id]);
            if ($q < $quantity) {
                $errors[] = 'SKU #' . $SKU_id . ' quantity must be greater than 0!';
                continue;
            }
            $mysql->execute("UPDATE SKU_locations
                            SET type = $status, reason = '1'
                            WHERE order_id = $order_id AND SKU_id = $SKU_id AND location_id = {$loc->getId()} AND batch_id $whereBatch");
            // $move_id = Movements_::setUnpicking($loc->getId(), $order_id, $SKU_id, $batch_id, $quantity, $user->getId());
            // SKUHistories::addUnpicking($loc->getId(), $order_id, $SKU_id, $batch_id, $quantity, $move_id, $user->getId(), $warehouse->getId());
            $successes[] = "SKU (#$SKU_id" . (empty($batch_id) ? '' : ", Batch#: $batch_id") . "), quantity $quantity, is unpicked for order #$order_id";
            OrderHistories::unpickedSKU($order_id, SKUBarcodes::getBarcodesBySKUIdString($SKU_id), empty($batch) ? '' : $batch->getInfo(), 
                                        $quantity, $loc->getLocation($warehouse_label), $user->getId());
            if (in_array($order_id, $processed_orders))
                continue;
            $order = Orders::getById($order_id);
            $status = 'awaiting ' . ($mode == self::PICKING_MULTI ? '' : ($mode . ' ')) . 'pick';
            if ($order->getStatusName() != $status)
                Orders::changeStatus($order->getId(), $status, $user->getId());
            $processed_orders[] = $order_id;
        }
        return ['successes' => $successes, 'errors' => $errors];
    }
}
