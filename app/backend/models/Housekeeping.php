<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Batch;
use app\core\entities\Location;
use app\core\entities\Movement;
use app\core\entities\Order;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\Debug;
use app\core\helpers\FPDFExtended;
use app\core\helpers\View;
use app\core\models\Batches;
use app\core\models\Warehouses;

class Housekeeping
{
    public static function deleteBatch($SKU_id, $batch_id, $warehouse_id)
    {
        $batch = Batches::getById($batch_id);
        Batches::deleteBatch($batch_id);
        SKUHistories::deleteBatch($SKU_id, $batch, $warehouse_id);
    }

    public static function saveBatch($SKU_id, $batch_id, $number_new, $expired_new, Warehouse $warehouse, $user_id)
    {
        $SKU = SKUs::getById($SKU_id);
        if (empty($SKU))
            return ['error' => 'SKU is not found'];
        if ((new SafeMysql())->checkUniqueFields('batches', [
            'id' => $batch_id,
            'number' => $number_new,
            'expired' => $expired_new,
            'SKU_id' => $SKU_id,
        ]))
            return ['error' => 'Batch exists'];
        $exist_batch = Batches::getBatchBySKUAndNumber($SKU_id, $number_new);
        if (!empty($exist_batch) && $exist_batch['id'] != $batch_id)
            return ['error' => 'Batch number exists'];
        $batch = Batches::getById($batch_id);
        (new SafeMysql())->execute("UPDATE batches SET number = :number_new, expired = :expired_new WHERE id = :batch_id", [
            'number_new' => $number_new,
            'expired_new' => $expired_new,
            'batch_id' => $batch_id,
        ]);
        $SKU_history_id = SKUHistories::updateBatch($SKU_id, $batch_id, $batch->getNumber(), $batch->getExpired(), $number_new, $expired_new, $warehouse->getId());
        $sup_data = Suppliers::getWeeksBeforeExpiryBySupplierId($SKU->getSupplierId());
        if (empty($sup_data) || empty($sup_data['warehouses']))
            return ['success'];
        $warehouses = json_decode($sup_data['warehouses'], true);
        if (empty($warehouses))
            return ['success'];
        $date_from = date('Y-m-d', !empty($sup_data['weeks_before_expiry']) ? strtotime("{$sup_data['weeks_before_expiry']} weeks") : time());
        [$warehouses_all, $expired_locs] = Housekeeping::getWarehousesWithExpiredLocations($warehouse->getLCId());
        if ($expired_new <= $date_from) {
            Housekeeping::setExpiredSKUs($SKU->getSupplierId(), $warehouses, $sup_data['weeks_before_expiry'], $warehouses_all, $expired_locs);
            return ['success'];
        }
        $nums = SKULocations::clearExpired($warehouses_all, $SKU->getSupplierId(), $SKU_id, $batch_id);
        if ($nums)
            SKUHistories::updateExpiredSettings($SKU_id, ['id' => $SKU_history_id], 'Unexpired', $warehouse->getId(), $user_id);
        return ['success'];
    }

    public static function getLocations($warehouse_id, SKU $SKU, $type_id = Location::PF)
    {
        $preff_locs = SKUPreffLocs::getPreffLocs($warehouse_id, $SKU->getId(), $type_id);
        $locs = array_merge($preff_locs, SKULocations::getCandidateLocs($warehouse_id, $SKU->getId(), $type_id));
        $pre_locs = Locations::getBookLocationIds($warehouse_id, $SKU->getSupplierId(), $type_id);
        $const_loc_ids = Locations::getConstantLocationIds($warehouse_id);
        $zone = $zone_book = [];
        foreach ($locs as $key => &$loc) {
            if (in_array($loc['location_id'], $const_loc_ids)) {
                unset($locs[$key]);
                continue;
            }
            $loc['book'] = (int)in_array($loc['location_id'], $pre_locs);
            if (empty($zone[$loc['zone']]))
                $zone[$loc['zone']] = [];
            if (!in_array($loc['column'], $zone[$loc['zone']]))
                $zone[$loc['zone']][] = $loc['column'];
            if ($loc['book']) {
                if (empty($zone_book[$loc['zone']]))
                    $zone_book[$loc['zone']] = [];
                if (!in_array($loc['column'], $zone_book[$loc['zone']]))
                    $zone_book[$loc['zone']][] = $loc['column'];
            }
        } unset($loc);
        ksort($zone);
        ksort($zone_book);
        return [$preff_locs, array_values($locs), $zone, $zone_book];
    }

    public static function searchLocations($warehouse_id, SKU $SKU, $type_id, $zone, $column, $booked = false)
    {
        $preff_locs = SKUPreffLocs::getPreffLocs($warehouse_id, $SKU->getId(), $type_id, [], $zone, $column);
        $locs = array_merge($preff_locs, SKULocations::getCandidateLocs($warehouse_id, $SKU->getId(), $type_id, $zone, $column));
        $pre_locs = Locations::getBookLocationIds($warehouse_id, $SKU->getSupplierId(), $type_id);
        $const_loc_ids = Locations::getConstantLocationIds($warehouse_id);
        foreach ($locs as $key => &$loc) {
            if (in_array($loc['location_id'], $const_loc_ids)) {
                unset($locs[$key]);
                continue;
            }
            $loc['book'] = (int)in_array($loc['location_id'], $pre_locs);
            // if ($booked && !$loc['book'])
            //     unset($locs[$key]);
        } unset($loc);
        return array_values($locs);
    }

    /**
     * @return array [assigned locs, available zones, booked zones]
     */
    public static function getLocationZonesAndColumns($warehouse_id, SKU $SKU, $type_id = Location::PF)
    {
        $const_loc_ids = Locations::getConstantLocationIds($warehouse_id);
        return [
            SKUPreffLocs::getPreffLocs($warehouse_id, $SKU->getId(), $type_id, $const_loc_ids),
            array_merge(
                SKUPreffLocs::getPreffLocZonesAndColumns($warehouse_id, $SKU->getId(), $type_id, $const_loc_ids),
                SKULocations::getCandidateLocationZonesAndColumns($warehouse_id, $SKU->getId(), $type_id, $const_loc_ids)
            ),
            Locations::getBookedZonesAndColumns($warehouse_id, $SKU->getSupplierId(), $type_id, $const_loc_ids),
        ];
    }

    public static function changeLocations($locs, $preff)
    {
        foreach ($locs as $key => $val)
            if (!isset($preff[$val['location_id']]))
                $locs[$key]['status'] = 0;
            else {
                $locs[$key]['priority'] = $preff[$val['location_id']];
                $locs[$key]['status'] = 1;
            }
        return $locs;
    }

    public static function checkLocations($preff, $type_id = Location::PF)
    {
        $error = [];
        $tmp = array_unique($preff);
        if (count($tmp) != count($preff))
            $error[] = "Priorities cannot be repeated ($type_id)";
        sort($tmp);
        if (count($tmp) > 0 && !$tmp[0])
            $error[] = "Priority cannot be 0 ($type_id)";
        foreach ($tmp as $l => $p)
            if (0 > (int)$p) {
                $error[] = "Priority cannot be negative";
                break;
            }

        return empty($error) ? false : $error;
    }

    public static function separateLocations($warehouse_id, $id, $preff, $type_id = Location::PF)
    {
        $locs = SKUPreffLocs::getPreffLocs($warehouse_id, $id, $type_id);
        $del_loc = [];
        $upd_loc = [];
        foreach ($locs as $loc) {
            $loc_id = $loc['location_id'];
            if (!empty($preff[$loc_id])) {
                if ($preff[$loc_id] != $loc['priority'])
                    $upd_loc[$loc_id] = $preff[$loc_id];
                unset($preff[$loc_id]);
            } else
                $del_loc[] = $loc_id;
        }
        return [$preff, $upd_loc, $del_loc];
    }

    public static function checkServiceLocations($warehouse_id, array $loc_ids, $label): array
    {
        $messages = [];
        $insert_locations = array_filter($loc_ids, function($p, $loc) use ($warehouse_id, $label, &$messages) {
            $res = !in_array($loc, Locations::getConstantLocationIds($warehouse_id));
            if (!$res)
                $messages[] = 'Trying to bind to service location ' . Locations::getById($loc)->getLocation($label);
            return $res;
        }, ARRAY_FILTER_USE_BOTH);

        return [$insert_locations, !empty($messages) ? $messages : false];
    }

    public static function writeSKU(FPDFExtended $pdf, $info, $W, $h, $x, $y)
    {
        $w_str = $pdf->GetStringWidth($info);
        $w_symb = $w_str / strlen($info);
        $n_symb = (int)($W / $w_symb);
        $pdf->SetY($y);
        while (strlen($info)) {
            $pdf->SetX($x);
            $pdf->Cell($W, $h, substr($info, 0, $n_symb));
            $pdf->Ln();
            $info = substr($info, $n_symb);
        }
        return $pdf->GetY() - $y;
    }

    public static function createCounting($loc_from, $loc_to, $SKU_id, $batch_id, $qty, $reason, string $user_reason, $user_id, $warehouse_id)
    {
        $batches = [['quantity' => abs($qty), 'order_id' => '', 'type' => 1, 'reason' => '2', 'batch_id' => '']];
        $A = $loc_from;
        $B = $loc_to;
        if ($qty > 0) {
            $A = $loc_to;
            $B = $loc_from;
        }
        SKULocations::addLocation($loc_to, $SKU_id, $batches);
        $batches[0]['batch_id'] = $batch_id;
        if ($qty > 0)
            SKULocations::addLocation($loc_from, $SKU_id, $batches);
        else
            if (!SKULocations::subLocation($SKU_id, $loc_from, -$qty, 1, '2', 0, $batch_id))
                Debug::sendDebugInfo("Reconciliation", var_export([$SKU_id, $loc_from, $qty, $batch_id], 1));
        $move_id = Movements_::movement($A, $B, $SKU_id, $batch_id, abs($qty), $user_id);
        SKUHistories::movement($A, $B, $SKU_id, abs($qty), $reason, $move_id, $user_id, $warehouse_id, $user_reason);
        SKUHistories::changeStockTwoLocation($A, $B, $SKU_id, $warehouse_id);
    }

    public static function getSKUs($location_id)
    {
        $SKUs = SKULocations::getSKUsFromLocation($location_id, true);
        $tmp = [];
        foreach ($SKUs as $key => $SKU) {
            $index = (empty($SKU['batch_id']) ? '.' : $SKU['batch_id']) . '|' . (empty($SKU['order_id']) ? '.' : $SKU['order_id']) . "|{$SKU['type']}|{$SKU['reason']}";
            if (empty($tmp[$SKU['SKU_id']]['detail'][$index]))
                $tmp[$SKU['SKU_id']]['detail'][$index]['quantity'] = $SKU['summa'];
            else
                $tmp[$SKU['SKU_id']]['detail'][$index]['quantity'] += $SKU['summa'];
            if (empty($tmp[$SKU['SKU_id']]['quantity']))
                $tmp[$SKU['SKU_id']]['quantity'] = $SKU['summa'];
            else
                $tmp[$SKU['SKU_id']]['quantity'] += $SKU['summa'];
            if (empty($tmp[$SKU['SKU_id']]['detail'][$index]['number']))
                $tmp[$SKU['SKU_id']]['detail'][$index]['number'] = $SKU['number'];
            if (empty($tmp[$SKU['SKU_id']]['detail'][$index]['expired']))
                $tmp[$SKU['SKU_id']]['detail'][$index]['expired'] = $SKU['expired'];
            unset(
                $SKUs[$key]['summa'],
                $SKUs[$key]['batch_id'],
                $SKUs[$key]['number'],
                $SKUs[$key]['expired'],
                $SKUs[$key]['order_id'],
                $SKUs[$key]['type'],
                $SKUs[$key]['reason']
            );
        }
        foreach ($tmp as $SKU_id => &$SKU_info) {
            $detail = [];
            foreach ($SKU_info['detail'] as $ids => $batch) {
                list($batch_id, $order_id, $type, $reason) = explode('|', $ids);
                if ($order_id == '.')
                    $order_id = '';
                $detail[] = '<tr>
                                 <td class="m-0 p-0">' . ($batch_id == '.' ? '-' : Batch::toBatchInfo($batch['number'], $batch['expired'], true, true)) . '</td>
                                 <td class="m-0 p-0">' . SKULocations::getInfoType($type, $reason, $order_id) . "</td>
                                 <td class='m-0 p-0'>{$batch['quantity']}</td>
                             </tr>";
            }
            $SKU_info['detail'] = '<table class="table table-hover mt-3 mb-0">
                                        <colgroup>
                                            <col width="45%">
                                            <col width="45%">
                                            <col width="10%">
                                        </colgroup>
                                        <thead> 
                                           <tr><th class="m-0 p-0">Batch</th><th class="m-0 p-0">Comment</th><th class="m-0 p-0">Quantity</th></tr>
                                       </thead>
                                       <tbody>' .
                                            implode('', $detail) . '
                                       </tbody>
                                   </table>';
            unset(
                $SKU_info['number'],
                $SKU_info['expired'],
            );
        } unset($SKU_info);
        $res = [];
        foreach ($SKUs as $SKU)
            if (empty($res[$SKU['SKU_id']])) {
                $res[$SKU['SKU_id']] = $SKU;
                $res[$SKU['SKU_id']]['summa'] = $tmp[$SKU['SKU_id']]['quantity'];
                $res[$SKU['SKU_id']]['detail'] = $tmp[$SKU['SKU_id']]['detail'];
            }
        return array_values($res);
    }

    public static function getListMaximumOrders($warehouse_id)
    {
        $warehouse_id = (int) $warehouse_id;
        $sql = "SELECT SKU_id, MAX(quantity) max, SUM(quantity) sum, qty, nums, GROUP_CONCAT(id ORDER BY quantity DESC) ids
                FROM (
                      SELECT m.id, m.SKU_id, m.quantity, m.loc_from_id, m.loc_to_id,
                             SUM(sl.quantity) qty,
                             COUNT(sl.reason) nums,
                             GROUP_CONCAT(SUBSTRING_INDEX(sl.reason, '_', -1)) orders
                      FROM movements m
                          JOIN locations l ON m.loc_to_id = l.id AND l.type_id IN (:storage_types) AND l.warehouse = :warehouse_id
                          JOIN SKU_locations sl ON m.SKU_id = sl.SKU_id AND sl.type = 1 AND sl.reason = 2 AND POSITION('2_' IN sl.reason) = 1
                      WHERE m.type
                      GROUP BY m.id
                     ) movs
                GROUP BY SKU_id
                ORDER BY nums DESC";
        $SKUs = (new SafeMysql())->fetchAll($sql, [
            'storage_types' => Location::STORAGE_TYPES,
            'warehouse_id' => $warehouse_id,
        ]);
        $res = [];
        foreach ($SKUs as $SKU) {
            $ids = explode(',', $SKU['ids']);
            if ($SKU['qty'] <= $SKU['max'])
                $res[] = $ids[0];
            else
                $res = array_merge($res, $ids);
        }
        return $res;
    }

    public static function getPlacementHistory(int $SKU_id, $warehouse_id): array
    {
        $data = (new SafeMysql())->fetchAssocWithKey("SELECT id, additional_info, event_date FROM SKU_history 
                                        WHERE SKU_id = :SKU_id 
                                        AND event = :event
                                        AND warehouse_id = :warehouse_id
                                        ORDER BY event_date DESC", [
            'SKU_id' => $SKU_id,
            'event' => SKUHistories::EVENT_UPDATE_LOCATIONS,
            'warehouse_id' => $warehouse_id,
        ], 'id');
        $records = [];
        if (empty($data))
            return $records;
        $users = [];
        $locations = [];
        foreach ($data as $id => $item) {
            $info = json_decode($item['additional_info'], 1);
            unset($data[$id]['additional_info']);
            $user_id = $info['user_id'] ?? null;
            if ($user_id && !in_array($user_id, $users))
                $users[] = $user_id;
            $data[$id]['user_id'] = $user_id;
            $changes = $info['changes'] ?? [];
            if (!empty($changes)) {
                if (!empty($changes['inserted'])) {
                    $data[$id]['inserted'] = $changes['inserted'];
                    foreach ($changes['inserted'] as $inserted)
                        if (!in_array($inserted, $locations))
                            $locations[] = $inserted;
                }
                if (!empty($changes['updated'])) {
                    foreach ($changes['updated'] as $changed => $p) {
                        $data[$id]['updated'][$changed] = $p;
                        if (!in_array($changed, $locations))
                            $locations[] = $changed;
                    }
                }
                if (!empty($changes['deleted'])) {
                    $data[$id]['deleted'] = $changes['deleted'];
                    foreach ($changes['deleted'] as $deleted)
                        if (!in_array($deleted, $locations))
                            $locations[] = $deleted;
                }
            }
        }
        // records are returned as HTML fragments (the view inserts them as-is), so DB values are escaped here
        $users = array_map([View::class, 'toHtml'], Users::getByIds($users));
        if (array_key_exists(-1, $users))
            $users[-1] = '<b><b>Weekly</b></b>';
        $locations = array_map(function($l) {
            return View::toHtml($l['name']);
        }, Locations::getByIds($locations));
        foreach ($data as $data_res) {
            $date = $data_res['event_date'];
            $user = $users[$data_res['user_id']] ?? '';
            $setRecord = function($date, $user, $operation, $info) use (&$records) {
                $records[] = [
                    'date' => $date,
                    'user' => $user,
                    'operation' => $operation,
                    'locations' => $info
                ];
            };
            if (!empty($data_res['inserted'])) {
                $inserted_locs = [];
                foreach ($data_res['inserted'] as $data_item_i)
                    $inserted_locs[] = $locations[$data_item_i] ?? '';
                $setRecord($date, $user, 'Insert', implode('<br>', $inserted_locs));
            }
            if (!empty($data_res['updated'])) {
                $updated_locs = [];
                foreach ($data_res['updated'] as $data_item_u => $p)
                    $updated_locs[] = ($locations[$data_item_u] ?? '') . ' (' . View::toHtml($p[0] ?? 0) . ' to ' . View::toHtml($p[1] ?? 0) . ')';
                $setRecord($date, $user, 'Update', implode('<br>', $updated_locs));
            }
            if (!empty($data_res['deleted'])) {
                $deleted_locs = [];
                foreach ($data_res['deleted'] as $data_item_d)
                    $deleted_locs[] = $locations[$data_item_d] ?? '';
                $setRecord($date, $user, 'Delete', implode('<br>', $deleted_locs));
            }
        }
        return $records;
    }

    public static function prepareInventoryVisible($inventory)
    {
        $SKU_id = null;
        $result = [];
        foreach ($inventory as $warehouse) {
            if (is_null($SKU_id))
                $SKU_id = $warehouse['ItemCode'];
            if ($SKU_id != $warehouse['ItemCode'])
                return "$SKU_id: data is not correct (few different SKUs)";
            $result[$warehouse['Warehouse']]['units'] = $warehouse['OnHandQuantity'];
            $sum = $warehouse['OnHandQuantity'];
            if ($sum < 0)
                $sum = 0;
            foreach ($warehouse['InventoryDetails'] as $detail) {
                if ($SKU_id != $detail['ItemCode'])
                    return "$SKU_id: data is not correct (few different SKUs)";
                $index = (!empty($detail['LotNumber']) && !empty($detail['LotExpirationDate']))
                    ? "{$detail['LotNumber']}_{$detail['LotExpirationDate']}" : "_";
                if (empty($result[$warehouse['Warehouse']][$index]))
                    $result[$warehouse['Warehouse']][$index] = $detail['Quantity'];
                else
                    $result[$warehouse['Warehouse']][$index] += $detail['Quantity'];
                $sum -= $detail['Quantity'];
            }
            if ($sum)
                return "$SKU_id: data is not correct (quantity)";
        }
        if (empty($result))
            return "$SKU_id: data is not correct (empty)";
        $result['SKU_id'] = $SKU_id;
        return $result;
    }

    public static function setExpiredSKUs($supplier_id, $supplier_warehouses, $weeks_before_expiry, array $warehouses, array $expired_locs, $limit = 10)
    {
        $date_from = date('Y-m-d', !empty($weeks_before_expiry) ? strtotime("$weeks_before_expiry weeks") : time());
        foreach ($supplier_warehouses as $warehouse_id) {
            if ($warehouses[$warehouse_id])
                continue;
            if (empty($expired_locs[$warehouse_id])) {
                Debug::sendDebugInfo('Expired', "Expired location for warehouse #$warehouse_id is absent", Debug::TEST);
                continue;
            }
            while ($expired_SKUs = SKULocations::getExpired($warehouse_id, $supplier_id, $date_from, $limit)) {
                foreach ($expired_SKUs as $SKU_data) {
                    $mysql = new SafeMysql();
                    $mysql->beginTransaction();
                    $order_id = '';
                    if ($SKU_data['type'] != 1 || $SKU_data['reason'] != '2') {
                        $order_id = empty($SKU_data['order_id']) ? explode('_', $SKU_data['reason'])[1] : $SKU_data['order_id'];
                        $order = Orders::getById($order_id);
                        if (empty($order)) {
                            Debug::sendDebugInfo('setExpiredSKUs', "Order #{$order_id} is not found", Debug::TEST);
                            continue;
                        }
                        if (!$order->expired())
                            Orders::changeStatus($order->getId(), Order::EXPIRED, -1, 'Order has expired batch');
                    }
                    SKULocations::toExpired($SKU_data['SKU_id'], $SKU_data['location_id'], $SKU_data['quantity'], $SKU_data['order_id'], $SKU_data['type'], $SKU_data['reason'], $SKU_data['batch_id']);
                    SKUHistories::updateExpired($SKU_data['SKU_id'], $SKU_data,'Expired', $warehouse_id, -1);
                    $comments = ["Expired (Batch#: {$SKU_data['number']}; Exp#: " . date('jS M Y', strtotime($SKU_data['expired'])) . ')'];
                    if (!empty($order_id))
                        $comments[] = "Order #$order_id";
                    Movements::insertMovement(
                        $SKU_data['SKU_id'],
                        $SKU_data['quantity'],
                        $SKU_data['location_id'],
                        $expired_locs[$warehouse_id]->getId(),
                        0,
                        $comments,
                        Movement::EXPIRED_PRIORITY,
                        Movement::EXPIRED_BATCH);
                    $mysql->commit();
                }
            }
        }
    }

    public static function getWarehousesWithExpiredLocations($lc_id)
    {
        $warehouses = Arr::map(Warehouses::getAll($lc_id), 'id', 'is_virtual');
        $expired_locs = [];
        foreach ($warehouses as $warehouse_id => $is_virtual)
            if (!$is_virtual) {
                $locs = Locations::getByExpiredLocation($warehouse_id);
                $expired_locs[$warehouse_id] = array_pop($locs);
            }
        return [$warehouses, $expired_locs];
    }

    public static function prepareDestination($data, $warehouse_id, $warehouse_label)
    {
        if (empty($data) || empty($data['loc_to']) || empty($data['loc_from_id']) || empty($data['SKUs']))
            return ['error' => 'Invalid information'];
        $loc_from_id = (int)$data['loc_from_id'];
        $loc = Locations::getByScan($warehouse_id, trim($data['loc_to']));
        if (is_null($loc) || !$loc->ST())
            return ['error' => "Pallet Merge is impossible for location {$data['loc_to']}"];
        if (empty($loc->getId()) || $loc->getId() == $loc_from_id)
            return ['error' => "Invalid destination location ({$data['loc_to']})"];
        if (Locations::isExpiredLocation($loc) || Locations::isCancelLocation($loc) || !$loc->isActive())
            return ['error' => "Location '{$data['loc_to']}' is denied to use or locked"];
        $loc_from = Locations::getById($loc_from_id);
        if (empty($loc_from) || !$loc_from->isActive())
            return ['error' => "Location '{$data['loc_from']}' is denied to use or locked"];
        $SKUs_from = Arr::maps(SKULocations::getSKUsFromLocation($loc_from_id), 'SKU_id', ['summa', 'batch_id']);
        $res = [];
        foreach ($data['SKUs'] as $SKU_data) {
            $SKU_id = (int)($SKU_data['SKU_id'] ?? 0);
            $quantity = (int)($SKU_data['quantity'] ?? 0);
            if (empty($SKUs_from[$SKU_id]))
                return ['error' => "SKU #$SKU_id is not found in source location"];
            if ($quantity < 1 || $quantity > $SKUs_from[$SKU_id]['summa'])
                return ['error' => "SKU #$SKU_id: quantity cannot be less than 0 or more than {$SKUs_from[$SKU_id]['summa']}"];
            $res[$SKU_id][$SKUs_from[$SKU_id]['batch_id']] = $quantity;
        }
        $SKUs_to = Arr::maps(SKULocations::getSKUsFromLocation($loc->getId()), 'SKU_id', ['summa', 'batch_id']);
        $SKUs_info = SKUs::getInfoByIds(array_keys($res));
        try {
            $free_volume = Sizes::getVolume($loc->getSizeId())*(100 - Locations::getFullnessPercent($loc, false))/100;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
        $volumes = array_sum(
            array_map(function($item1, $item2) {
                return $item1['width']*$item1['height']*$item1['depth']*array_sum($item2);
            }, $SKUs_info, $res)
        );
        if ($volumes > $free_volume)
            return ['error' => "Not enough space in destination location ({$data['loc_to']})"];
        $max_qty = array_map(function($item) use($loc) {
            $capacity = json_decode($item['max_capacity'], true);
            return (int)$capacity[$loc->getSizeId()] ?? 0;
        }, $SKUs_info);
        foreach ($res as $SKU_id => $SKU_data) {
            $qty = 0;
            if (!empty($SKUs_to[$SKU_id]))
                if (empty($SKU_data[$SKUs_to[$SKU_id]['batch_id']]))
                    return ['error' => "In destination location ({$data['loc_to']}) cannot be mixed batches"];
                else
                    $qty = $SKUs_to[$SKU_id]['summa'];
            if (array_values($SKU_data)[0] + $qty > $max_qty[$SKU_id])
                return ['error' => "Max capacity for SKU #$SKU_id is exceeded ({$data['loc_to']})"];
        }
        return ['success' => [$loc, $res, SKULocations::getSKUsFromLocation($loc_from_id, true)]];
    }

    public static function completeDestination($loc_from_id, Location $loc_to, $SKUs, $SKU_locs, User $user, $warehouse_id)
    {
        $loc_from_id = (int)$loc_from_id;
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        try {
            foreach ($SKU_locs as $SKU_data) {
                $SKU_id = $SKU_data['SKU_id'];
                if (empty($SKUs[$SKU_id])) {
                    continue;
                }
                $batch_id = $SKU_data['batch_id'] ?: '';
                $quantity = (int)$SKUs[$SKU_id][$batch_id];
                $where_order_id = empty($SKU_data['order_id']) ? 'IS NULL' : "= :order_id";
                $where_batch_id = empty($batch_id) ? 'IS NULL' : "= :batch_id";
                $where_rec = "SKU_id = :SKU_id AND order_id $where_order_id AND batch_id $where_batch_id AND type = :type AND reason = :reason";
                $params_rec = [
                    'SKU_id' => $SKU_id,
                    'order_id' => $SKU_data['order_id'] ?? null,
                    'batch_id' => $batch_id,
                    'type' => $SKU_data['type'],
                    'reason' => $SKU_data['reason']
                ];
                $params_from = $params_rec + ['loc_from_id' => $loc_from_id];
                $params_to = $params_rec + ['loc_to_id' => $loc_to->getId()];
                // 1
                if (!$mysql->fetchScalar("SELECT 1 FROM SKU_locations WHERE location_id = :loc_to_id AND $where_rec", $params_to)) {
                    if ($quantity < $SKU_data['summa']) {
                        $mysql->execute("
                            INSERT INTO SKU_locations (SKU_id, location_id, quantity, order_id, type, reason, batch_id)
                            VALUES (:SKU_id, :location_id, :quantity, :order_id, :type, :reason, :batch_id)", [
                            'SKU_id' => $SKU_id,
                            'location_id' => $loc_to->getId(),
                            'quantity' => $quantity,
                            'order_id' => empty($SKU_data['order_id']) ? null : $SKU_data['order_id'],
                            'type' => $SKU_data['type'],
                            'reason' => $SKU_data['reason'],
                            'batch_id' => empty($batch_id) ? null : $batch_id,
                        ]);
                        $mysql->execute("
                            UPDATE SKU_locations
                            SET quantity = quantity - :quantity
                            WHERE location_id = :loc_from_id AND $where_rec", $params_from + ['quantity' => $quantity]);
                    } else {
                        $mysql->execute("
                            UPDATE SKU_locations
                            SET location_id = :loc_to_id
                            WHERE location_id = :loc_from_id AND $where_rec", $params_from + ['loc_to_id' => $loc_to->getId()]);
                    }
                } else {
                    $mysql->execute("
                        UPDATE SKU_locations
                        SET quantity = quantity + :quantity
                        WHERE location_id = :loc_to_id AND $where_rec", $params_to + ['quantity' => $quantity]);
                    if ($quantity < $SKU_data['summa']) {
                        $mysql->execute("
                            UPDATE SKU_locations
                            SET quantity = quantity - :quantity
                            WHERE location_id = :loc_from_id AND $where_rec", $params_from + ['quantity' => $quantity]);
                    } else {
                        $mysql->execute("
                            DELETE FROM SKU_locations
                            WHERE location_id = :loc_from_id AND $where_rec", $params_from);
                    }
                }
                if ($quantity > $SKU_data['summa']) {
                    $SKUs[$SKU_id][$batch_id] -= $SKU_data['summa'];
                } else {
                    unset($SKUs[$SKU_id][$batch_id]);
                    if (empty($SKUs[$SKU_id])) {
                        unset($SKUs[$SKU_id]);
                    }
                }
                $move_id = Movements_::movement($loc_from_id, $loc_to->getId(), $SKU_id, $batch_id, $quantity, $user->getId());
                SKUHistories::movement($loc_from_id, $loc_to->getId(), $SKU_id, $quantity, 'Pallet merge', $move_id, $user->getId(), $warehouse_id);
                // 2
                if (!SKUPreffLocs::isAssignmentSKU($SKU_id, $loc_to->getId())) {
                    SKUPreffLocs::add($SKU_id, $loc_to);
                    SKUHistories::updateLocations($SKU_id, $user->getId(), ['inserted' => [$loc_to->getId()]], $warehouse_id);
                }
                // 3
                MovementHistories::merge(new Movement([
                    'id' => -1,
                    'SKU_id' => $SKU_id,
                    'quantity' => $quantity,
                    'loc_from_id' => $loc_from_id,
                    'loc_to_id' => $loc_to->getId(),
                    'create_date' => time(),
                    'create_user_id' => -1,
                    'user_id' => $user->getId(),
                    'status' => Movement::NEW_MOVEMENT,
                    'comment' => 'Pallet merge ' . date('jS M Y H:i') . ' by ' . $user->getLogin(),
                    'priority' => Movement::NON_PRIORITY,
                ]), $user, 'Pallet merge');
            }
        } catch (\Exception $e) {
            $mysql->rollback();
            return ['error' => $e->getMessage()];
        }
        $mysql->commit();
        return ['success' => true];
    }
}
