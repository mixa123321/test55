<?php

namespace app\core\models;

use app\backend\models\SKULocations as SKULocationsBackend;
use app\backend\models\SKUs as SKUsModel;
use app\core\dbal\SafeMysql;
use app\core\entities\Movement;
use app\core\entities\SKUPreffLoc;
use app\core\helpers\Arr;
use app\core\helpers\Debug;
use app\backend\models\SKUHistories;
use app\core\entities\Location;

class SKUPreffLocs
{
    const LONG_USED = 14;

    public static function getPreffLocs($warehouse_id, $SKU_id, $type_id = Location::PF, $exclude_ids = [], $zone = null, $column = null)
    {
        $params = ['warehouse_id' => (int)$warehouse_id, 'SKU_id' => (int)$SKU_id, 'type_id' => $type_id];
        $and_where = '';
        if (!empty($exclude_ids)) {
            $and_where .= ' AND spl.location_id NOT IN (:exclude_ids)';
            $params['exclude_ids'] = array_map('intval', $exclude_ids);
        }
        if (!is_null($zone)) {
            $and_where .= ' AND l.zone = :zone';
            $params['zone'] = (int)$zone;
        }
        if (!is_null($column)) {
            $and_where .= ' AND l.`column` = :col';
            $params['col'] = (int)$column;
        }
        $sql = "SELECT spl.location_id, l.zone, l.`column`, spl.priority, 
                       (SELECT COUNT(*) FROM SKU_locations sl WHERE spl.location_id = sl.location_id AND spl.SKU_id = sl.SKU_id) nums, 
                       CONCAT(IF(w.label IS NULL OR w.label = '', l.warehouse, w.label), '-', l.type_id, '-', l.zone, '-', l.`column`, '-', l.`row`) name,
                       IFNULL(l.alias, CONCAT(l.zone, l.`column`, l.`row`)) alias 
                FROM SKU_preff_locs spl
                    JOIN locations l ON spl.location_id = l.id AND l.type_id = :type_id AND l.warehouse = :warehouse_id $and_where
                    JOIN warehouses w ON l.warehouse = w.id
                WHERE spl.SKU_id = :SKU_id 
                ORDER BY l.warehouse, l.type_id, LPAD(l.zone, 5, ' '), LPAD(l.`column`, 5, '0'), LPAD(l.`row`, 5, ' ')";
        return (new SafeMysql())->fetchAll($sql, $params);
    }

    public static function getPreffLocZonesAndColumns($warehouse_id, $SKU_id, $type_id = Location::PF, $exclude_ids = [])
    {
        $exclude_sql = '';
        $params = ['warehouse_id' => (int)$warehouse_id, 'SKU_id' => (int)$SKU_id, 'type_id' => $type_id];
        if (!empty($exclude_ids)) {
            $exclude_sql = 'AND spl.location_id NOT IN (:exclude_ids)';
            $params['exclude_ids'] = array_map('intval', $exclude_ids);
        }
        $sql = "SELECT DISTINCT l.zone, l.`column`
                FROM SKU_preff_locs spl
                    JOIN locations l ON spl.location_id = l.id AND l.type_id = :type_id AND l.warehouse = :warehouse_id
                WHERE spl.SKU_id = :SKU_id $exclude_sql
                ORDER BY LPAD(l.zone, 5, ' '), LPAD(l.`column`, 5, '0')";
        $raw_result = (new SafeMysql())->fetchAll($sql, $params);
        $result = [];
        foreach ($raw_result as $row)
            $result[$row['zone']][] = $row['column'];
        return $result;
    }

    public static function getPlaces($warehouse_id, $SKUs = null/*, $type_id = PF*/)
    {
        $params = ['warehouse_id' => (int)$warehouse_id];
        $sql = "SELECT SKU_id, type_id, size_id, COUNT(*) nums
                FROM SKU_preff_locs
                    JOIN locations ON location_id = id AND warehouse = :warehouse_id ";
        if (!is_null($SKUs)) {
            $sql .= "WHERE SKU_id IN (:SKU_ids) ";
            $params['SKU_ids'] = array_map('intval', $SKUs);
        }
        $sql .= "GROUP BY SKU_id, type_id, size_id";
        return (new SafeMysql())->fetchAll($sql, $params);
    }

    public static function getPercentST($warehouse_id, $SKU_id, $type_id = [Location::ST])
    {
        $sql = "SELECT DISTINCT size_id, location_id
                FROM SKU_preff_locs
                    JOIN locations ON location_id = id AND type_id IN (:type_ids) AND warehouse = :warehouse_id 
                WHERE SKU_id = :SKU_id";
        $res = (new SafeMysql())->fetchAssocWithKey($sql, [
            'type_ids' => $type_id,
            'warehouse_id' => (int)$warehouse_id,
            'SKU_id' => (int)$SKU_id,
        ], 'location_id');
        $another = count($res) ? SKULocationsBackend::getBusy($warehouse_id, $SKU_id, array_keys($res)) : [];
        $qties = $qties_tmp = [];
        foreach ($another as $loc) {
            $size = SKUsModel::getSize($loc['SKU_id'], $loc['size_id']);
            $qties_tmp[$loc['location_id']][] = $size ? ($loc['qty'] / $size) : 0;
        }
        foreach ($qties_tmp as $loc_id => $percents)
            $qties[$loc_id] = array_sum($percents);
        return [$res, $qties];
    }

    public static function getPlacesST($warehouse_id, $SKU_id)
    {
        $warehouse_id = (int)$warehouse_id;
        list($res, $qties) = self::getPercentST($warehouse_id, $SKU_id);
        $sizes = [];
        foreach ($res as $key => $loc) {
            $qty = 1 - (empty($qties[$key]) ? 0 : $qties[$key]);
            if (empty($sizes[$loc['size_id']]))
                $sizes[$loc['size_id']] = $qty;
            else
                $sizes[$loc['size_id']] += $qty;
        }
        return $sizes;
    }

    public static function getFreePlace4SKU($SKU_id)
    {
        return (new SafeMysql())->fetchScalar("SELECT SUM(IFNULL((SELECT SUM(quantity)
                                   FROM SKU_locations sl
                                       JOIN locations l2 ON sl.location_id = l2.id AND l2.type_id NOT IN (:types)
                                   WHERE sl.location_id = spl.location_id AND sl.SKU_id = spl.SKU_id AND sl.order_id IS NULL AND sl.type = 1), 0))
                FROM SKU_preff_locs spl
                JOIN locations l ON spl.location_id = l.id AND l.state = 1
                WHERE SKU_id = :SKU_id",
            ['types' => Location::NOT_COUNTED_TYPES, 'SKU_id' => (int)$SKU_id]);
    }

    public static function getFreePlacePF4SKU($SKU_id)
    {
        return (new SafeMysql())->fetchScalar("SELECT SUM(IFNULL((SELECT SUM(quantity)
                                   FROM SKU_locations
                                   WHERE location_id = spl.location_id AND SKU_id = spl.SKU_id AND order_id IS NULL AND type = 1), 0))
                FROM SKU_preff_locs spl
                JOIN locations l ON spl.location_id = l.id AND l.type_id = :type AND l.state = 1
                WHERE SKU_id = :SKU_id",
            ['type' => Location::PF, 'SKU_id' => (int)$SKU_id]);
    }

    public static function getLocations4SKUs($SKUs)
    {
        if (!is_array($SKUs) || !count($SKUs))
            return [];
        $res = (new SafeMysql())->fetchAll("SELECT location_id, warehouse, type_id, zone, `column`, row, SKU_id id,
                    IFNULL((SELECT SUM(quantity) FROM SKU_locations WHERE location_id=spl.location_id AND SKU_id=spl.SKU_id AND type=1), 0) quantity
                FROM SKU_preff_locs spl, locations
                WHERE SKU_id IN (:SKU_ids) AND location_id=id AND state=1
                ORDER BY SKU_id, type_id, priority DESC",
            ['SKU_ids' => array_map('intval', array_keys($SKUs))]);
        $locs = [];
        foreach ($res as $key => $loc) {
            if (empty($loc['quantity']) || empty($SKUs[$loc['id']]))
                continue;
            $above = $SKUs[$loc['id']][0] <= $loc['quantity'];
            if  ($above)
                $loc['quantity'] = $SKUs[$loc['id']][0];
            $loc['name'] = $SKUs[$loc['id']]['name'];
            $loc['barcode'] = $SKUs[$loc['id']]['barcode'];
            $loc['photo'] = $SKUs[$loc['id']]['photo'];
            $locs[] = $loc;
            if (!$above) {
                $SKUs[$loc['id']][0] -= $loc['quantity'];
                unset($res[$key]);
            } else
                unset($SKUs[$loc['id']]);
        }
        return $locs;
    }

    public static function reorder($ids)
    {
        if (!is_array($ids) || !count($ids))
            return [];
        $ids_unique = [];
        foreach ($ids as $id) {
            list($loc_id, ) = explode('_', $id);
            $ids_unique[$loc_id] = $loc_id;
        }
        $sql = "SELECT o.location_id
                FROM (SELECT location_id, (@row_number:=@row_number+1) nums
                      FROM pf_path, (SELECT @row_number:=0) t) o
                WHERE o.location_id IN (:ids)
                ORDER BY o.nums";
        return (new SafeMysql())->fetchArrayOneField($sql, ['ids' => array_map('intval', array_values($ids_unique))]);
    }

    private static function getForReplenish($warehouse_id)
    {
        $sql = "SELECT spl.SKU_id, l.type_id, IFNULL(SUM(sl.quantity), 0) quantity, GROUP_CONCAT(CONCAT(spl.location_id, ':', IFNULL(sl.quantity, 0), ':', IFNULL(sl.batch_id, '-')) ORDER BY b.expired, spl.priority) ids
                FROM SKU_preff_locs spl
                    JOIN locations l ON l.id = spl.location_id AND l.type_id IN (:types) AND l.warehouse = :warehouse_id
                    LEFT JOIN SKU_locations sl ON sl.SKU_id = spl.SKU_id AND sl.location_id = spl.location_id AND sl.order_id IS NULL AND sl.type = 1 AND sl.reason = 2
                    LEFT JOIN batches b ON sl.batch_id = b.id
                GROUP BY spl.SKU_id, l.type_id
                ORDER BY spl.SKU_id";
        $res = (new SafeMysql())->fetchAll($sql, ['types' => Location::STORAGE_TYPES, 'warehouse_id' => (int)$warehouse_id]);
        $SKUs = [];
        foreach ($res as $SKU)
            $SKUs[$SKU['SKU_id']][$SKU['type_id']] = ['quantity' => $SKU['quantity'], 'ids' => $SKU['ids']];
        foreach ($SKUs as $SKU_id => $SKU)
            if (empty($SKU[Location::ST]['quantity']) || !isset($SKU[Location::PF]))
                unset($SKUs[$SKU_id]);
            elseif (!empty($SKU[Location::ST]['quantity']) && !empty($SKU[Location::PF]['quantity']))
                unset($SKUs[$SKU_id]);
        foreach ($SKUs as $SKU_id => $SKU) {
            $SKUs[$SKU_id][Location::PF] = array_unique(array_map(function ($el) { return explode(':', $el)[0]; }, explode(',', $SKU[Location::PF]['ids'])))[0];
            $tmp = array_map(function ($el) {
                $_el = explode(':', $el);
                return ["{$_el[0]}:{$_el[2]}", $_el[1]];
            }, explode(',', $SKU[Location::ST]['ids']));
            $res = [];
            foreach ($tmp as $t0)
                if (empty($res[$t0[0]]))
                    $res[$t0[0]] = $t0[1];
                else
                    $res[$t0[0]] += $t0[1];
            $res = array_filter($res, function ($el) { return !empty($el); });
            $res = array_map(function ($key, $val) {
                $tmp = explode(':', $key);
                return ['location_id' => (int)$tmp[0], 'quantity' => (int)$val, 'batch_id' => $tmp[1]];
            }, array_keys($res), $res);
            $tmp = [];
            $location_id = $res[0]['location_id'];
            $tmp[$location_id] = array_slice($res[0], 1);
            for ($i = 1; $i < count($res); $i++)
                if ($tmp[$location_id]['batch_id'] == $res[$i]['batch_id'])
                    $tmp[$res[$i]['location_id']] = array_slice($res[$i], 1);
            $SKUs[$SKU_id][Location::ST] = $tmp;
        }
        return $SKUs;
    }

    public static function getReserveSKU4Order($order_id, $SKU_id)
    {
        $params = ['SKU_id' => (int)$SKU_id, 'order_id' => (int)$order_id];
        $sql = "(SELECT 0 id, SUM(sl.quantity) quantity, -1 type_id
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id
                WHERE sl.SKU_id = :SKU_id AND (sl.order_id = :order_id OR (sl.type = 1 AND SUBSTRING_INDEX(sl.reason, '_', -1) = :order_id))
               ) UNION (
               SELECT l.id, sl.quantity, l.type_id
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id
                WHERE sl.SKU_id = :SKU_id AND (sl.order_id = :order_id OR (sl.type = 1 AND SUBSTRING_INDEX(sl.reason, '_', -1) = :order_id)))";
        $res = (new SafeMysql())->fetchAll($sql, $params);
        return [$res[0]['quantity'], array_slice($res, 1)];
    }

    public static function getLocationsPF($SKU_id)
    {
        $sql = "SELECT spl.location_id, IFNULL(SUM(sl.quantity), 0) quantity, b.id
                    FROM SKU_preff_locs spl
                        JOIN SKUs s ON spl.SKU_id = s.id
                        JOIN locations l ON spl.location_id = l.id AND l.type_id = :type
                        LEFT JOIN SKU_locations sl ON spl.location_id = sl.location_id
                        LEFT JOIN batches b ON sl.batch_id = b.id
                    WHERE spl.SKU_id = :SKU_id
                    GROUP BY spl.location_id, b.id
                    ORDER BY b.expired, spl.priority";
        return (new SafeMysql())->fetchAll($sql, ['SKU_id' => (int)$SKU_id, 'type' => Location::PF]);
    }

    public static function getMaxCapacityForSKU($warehouse_id, $SKU_id, $location_id = null, $types = Location::STORAGE_TYPES)
    {
        $params = ['warehouse_id' => (int)$warehouse_id, 'SKU_id' => (int)$SKU_id, 'types' => $types];
        $whereLocation = '';
        if (!is_null($location_id)) {
            if (is_array($location_id)) {
                $whereLocation = 'AND l.id IN (:location_ids)';
                $params['location_ids'] = array_map('intval', $location_id);
            } else {
                $whereLocation = 'AND l.id = :location_id';
                $params['location_id'] = (int)$location_id;
            }
        }
        $sql = "SELECT s.max_capacity, l.size_id
                FROM SKUs s
                    JOIN SKU_preff_locs spl ON spl.SKU_id = s.id 
                    JOIN locations l ON spl.location_id = l.id AND l.warehouse = :warehouse_id $whereLocation AND l.type_id in (:types)
                WHERE s.id = :SKU_id
                GROUP BY l.id";
        return array_reduce((new SafeMysql())->fetchAll($sql, $params), function($capacity, $loc){
            $max_capacity = json_decode($loc['max_capacity'], true);
            if (!empty($max_capacity[$loc['size_id']]))
                $capacity += $max_capacity[$loc['size_id']];
            return $capacity;
        }, 0);
    }

    public static function getFreePF4Replenish($warehouse_id, $SKUs)
    {
        $sql = "SELECT spl.SKU_id, spl.location_id, IFNULL(SUM(sl.quantity), 0) quantity, sl.batch_id
                FROM SKU_preff_locs spl
                    JOIN locations l ON spl.location_id = l.id AND l.type_id = :type AND l.warehouse = :warehouse_id
                    LEFT JOIN SKU_locations sl ON spl.SKU_id = sl.SKU_id AND spl.location_id = sl.location_id AND sl.type = 1
                    LEFT JOIN batches b ON sl.batch_id = b.id
                WHERE spl.SKU_id IN (:SKU_ids)
                GROUP BY spl.SKU_id, spl.location_id, sl.batch_id
                ORDER BY spl.SKU_id, b.expired, spl.priority";
        $tmp = (new SafeMysql())->fetchAll($sql, [
            'type' => Location::PF,
            'warehouse_id' => (int)$warehouse_id,
            'SKU_ids' => is_array($SKUs) ? array_map('intval', $SKUs) : [(int)$SKUs],
        ]);
        $SKUs = [];
        foreach ($tmp as $SKU)
            $SKUs[$SKU['SKU_id']][] = array_slice($SKU, 1);
        return $SKUs;
    }

    public static function replenishOrders($warehouse_id)
    {
        $largeSKUs = self::getSKUs4LargePick($warehouse_id);
        $SKUs4Replenish = SKULocations::getSKUs4Replenish($warehouse_id, $largeSKUs);
        if (empty($SKUs4Replenish))
            return [];
        $SKUs = self::getFreePF4Replenish($warehouse_id, array_keys($SKUs4Replenish));
        foreach ($SKUs4Replenish as $SKU_id => $SKUsFrom) {
            if (empty($SKUs[$SKU_id]))
                continue;
            $SKU_obj = SKUsModel::getById($SKU_id);
            foreach ($SKUsFrom as $SKUFrom) {
                foreach ($SKUs[$SKU_id] as $SKUTo) {
                    if (empty($qExist["{$SKUFrom['location_id']}_{$SKUTo['location_id']}"]))
                        $qExist["{$SKUFrom['location_id']}_{$SKUTo['location_id']}"] = 0;
                    if ($SKUFrom['batch_id'] != $SKUTo['batch_id'] && !empty($SKUTo['batch_id']))
                        continue;
                    if ($SKUFrom['batch_id'] != $SKUTo['batch_id'] && empty($SKUTo['batch_id']) && !empty($SKUTo['quantity']))
                        continue;
                    $qTo = explode('_', SKULocations::getCountFreeInLocation($warehouse_id, $SKU_obj, Locations::getById($SKUTo['location_id']), true));

                    if (!($qTo[0] - $qExist["{$SKUFrom['location_id']}_{$SKUTo['location_id']}"]))
                        continue;
                    if ($SKUFrom['batch_id'] != $SKUTo['batch_id'] && $qTo[1])
                        continue;
                    $qFrom = SKULocations::getQuantityExist($warehouse_id, $SKU_id, $SKUFrom['location_id'], 0, 1, 2, $SKUFrom['batch_id']);
                    $q = min($qFrom + $SKUFrom['quantity'], $qTo[0] - $qExist["{$SKUFrom['location_id']}_{$SKUTo['location_id']}"]);
                    $movements["{$SKU_id}_{$SKUFrom['location_id']}_{$SKUTo['location_id']}"][] = [
                        $q,
                        "Order #{$SKUFrom['order_id']}" . (empty($SKUFrom['number']) ? '' : (" (Batch#: {$SKUFrom['number']}; Exp#: " . date('jS M Y', strtotime($SKUFrom['expired'])) . ')'))
                    ];
                    $qExist["{$SKUFrom['location_id']}_{$SKUTo['location_id']}"] += $q;
                }
            }
        }
        if (empty($movements))
            return [];
        $res_movements = [];
        foreach ($movements as $key => $movement) {
            $quantity = array_sum(array_map(function($el) { return $el[0]; }, $movement));
            $comments = array_map(function($el) { return $el[1]; }, $movement);
            $ids = explode('_', $key);
            $res_movements[] = [
                'SKU_id' => $ids[0],
                'quantity' => $quantity,
                'location_from_id' => $ids[1],
                'location_to_id' => $ids[2],
                'comment' => $comments,
            ];
        }
        return $res_movements;
    }

    public static function replenishSKUs($warehouse_id)
    {
        $SKUs = self::getForReplenish($warehouse_id);
        if (empty($SKUs))
            return [];
        $movements = [];
        foreach ($SKUs as $SKU_id => $locs) {
            $SKU_obj = SKUs::getById($SKU_id);
            if (empty($SKU_obj)) {
                Debug::sendDebugInfo('replenishSKUs', "SKU #$SKU_id not found");
                return [];
            }
            $q = SKULocations::getCountFreeInLocation($warehouse_id, $SKU_obj, Locations::getById($locs[Location::PF]));
            if (!$q)
                continue;
            foreach ($locs[Location::ST] as $loc_from_id => $loc) {
                if (!$loc['quantity'])
                    continue;
                $batch = $loc['batch_id'] == '-' ? null : Batches::getById($loc['batch_id']);
                $qty = (int)min($q, $loc['quantity']);
                $comment = 'Replenish' . (empty($batch) ? '' : (' (Batch#: ' . $batch->getNumber() . '; Exp#: ' . date('jS M Y', strtotime($batch->getExpired())) . ')'));
                $movements[] = [
                    'SKU_id' => $SKU_id,
                    'quantity' => $qty,
                    'location_from_id' => $loc_from_id,
                    'location_to_id' => (int)$locs[Location::PF],
                    'comment' => $comment
                ];
                $q -= $qty;
                if (!$q)
                    break;
            }
        }
        return $movements;
    }

    private static function addMovements($movements, $priority)
    {
        foreach ($movements as $movement) {
            if (($movement_exist = Movements::isMovement($movement['SKU_id'], $movement['location_to_id'])) !== false)
                $quantity = $movement_exist['quantity'] >= $movement['quantity'] ? 0 : ($movement['quantity'] - $movement_exist['quantity']);
            else
                $quantity = $movement['quantity'];
            if (!$quantity && $movement_exist !== false) {
                foreach ($movement_exist['id_priority_map'] as $m_id => $m_priority) {
                    if ($m_priority < $priority)
                        Movements::changePriority($m_id, $priority);
                    if (in_array($priority, [Movement::ORDER_REPLEN_PRIORITY, Movement::CANCEL_ORDER_PRIORITY]))
                        Movements::changeCommentForMovementWithoutOrder($m_id, $movement['comment']);
                }
                continue;
            }
            if (!$quantity)
                continue;
            Movements::insertMovement(
                $movement['SKU_id'],
                $quantity,
                $movement['location_from_id'],
                $movement['location_to_id'],
                0,
                $movement['comment'],
                $priority
            );
        }
    }

    public static function replenish($warehouse_id)
    {
        $movements = self::replenishOrders($warehouse_id);
        if (!empty($movements))
            self::addMovements($movements, Movement::ORDER_REPLEN_PRIORITY);
        $movements = self::replenishSKUs($warehouse_id);
        if (!empty($movements))
            self::addMovements($movements, Movement::PF_REPLEN_PRIORITY);
    }

    public static function getSKU($warehouse_id, $SKU_id)
    {
        $locs = (new SafeMysql())->fetchAll("SELECT spl.SKU_id, l.type_id, COUNT(*) nums
                                  FROM SKU_preff_locs spl
                                      JOIN locations l ON spl.location_id = l.id AND l.type_id IN (:types) AND l.warehouse = :warehouse_id
                                        WHERE spl.SKU_id = :SKU_id
                                  GROUP BY spl.SKU_id, l.type_id",
            ['types' => Location::STORAGE_TYPES, 'warehouse_id' => (int)$warehouse_id, 'SKU_id' => (int)$SKU_id]);
        $res = [];
        foreach ($locs as $loc)
            $res[$loc['type_id']] = $loc['nums'];
        return $res;
    }

    private static function getSKUs($warehouse_id)
    {
        $locs = (new SafeMysql())->fetchAll("SELECT spl.SKU_id, l.type_id, COUNT(*) nums
                                  FROM SKU_preff_locs spl
                                      JOIN locations l ON spl.location_id = l.id AND l.type_id IN (:types) AND l.warehouse = :warehouse_id
                                  GROUP BY spl.SKU_id, l.type_id",
            ['types' => Location::STORAGE_TYPES, 'warehouse_id' => (int)$warehouse_id]);
        $res = [];
        foreach ($locs as $loc)
            $res[$loc['SKU_id']][$loc['type_id']] = $loc['nums'];
        return $res;
    }

    public static function getSKUs4LargePick($warehouse_id)
    {
        $SKUs = self::getSKUs($warehouse_id);
        foreach ($SKUs as $id => $SKU)
            if (!empty($SKU[Location::PF]) || empty($SKU[Location::ST]))
                unset($SKUs[$id]);
        return array_keys($SKUs);
    }

    public static function isUnassigned($warehouse_id, $SKUs)
    {
        if (is_array($SKUs)) {
            $SKU_ids = array_map('intval', array_keys($SKUs));
            $nums = count($SKUs);
        } else {
            $SKU_ids = [(int)$SKUs];
            $nums = 1;
        }
        $res = (new SafeMysql())->fetchAll("SELECT loc.SKU_id, COUNT(*) num
                FROM (SELECT spl.SKU_id, l.type_id
                      FROM SKU_preff_locs spl
                          JOIN locations l ON spl.location_id = l.id AND l.warehouse = :warehouse_id
                      WHERE spl.SKU_id IN (:SKU_ids)
                      GROUP BY spl.SKU_id, l.type_id) loc
                GROUP BY loc.SKU_id
                HAVING num >= 1",
            ['warehouse_id' => (int)$warehouse_id, 'SKU_ids' => $SKU_ids]);
        return $nums != count($res);
    }

    public static function isMeasure($warehouse_id, $SKUs)
    {
        if (is_array($SKUs)) {
            $SKU_ids = array_map('intval', array_keys($SKUs));
            $nums = count($SKUs);
        } else {
            $SKU_ids = [(int)$SKUs];
            $nums = 1;
        }
        $res = (new SafeMysql())->fetchAll("SELECT id, max_capacity FROM SKUs WHERE id IN (:SKU_ids)", ['SKU_ids' => $SKU_ids]);
        $sizes = Arr::column(Sizes::getAllSizes($warehouse_id), 'id');
        $capacity = [];
        foreach ($res as $SKU) {
            $tmp = json_decode($SKU['max_capacity'], true);
            if (!empty($tmp))
                foreach ($tmp as $key => $val)
                    if (in_array($key, $sizes)) {
                        $capacity[$SKU['id']] = 1;
                        break;
                    }
        }
        return $nums == count($capacity);
    }

    public static function getLocationsForSKU($warehouse_id, $SKU_id, $batch_id = null): array
    {
        $locs = (new SafeMysql())->fetchAll("SELECT DISTINCT spl.location_id, 
                                                        IF(sl.quantity IS NOT NULL, true, false) exist, sl.batch_id, l.type_id
                                                  FROM SKU_preff_locs spl
                                                      JOIN locations l ON l.id = spl.location_id AND l.warehouse = :warehouse_id
                                                      LEFT JOIN SKU_locations sl ON spl.SKU_id = sl.SKU_id AND spl.location_id = sl.location_id
                                                      LEFT JOIN batches b ON sl.batch_id = b.id
                                                  WHERE spl.SKU_id = :SKU_id AND l.type_id IN (:types)
                                                  ORDER BY l.type_id, IFNULL(b.expired, NOW()), spl.priority",
            ['warehouse_id' => (int)$warehouse_id, 'SKU_id' => (int)$SKU_id, 'types' => Location::STORAGE_TYPES]);
        if (empty($locs))
            return [];
        foreach ($locs as $i => $loc)
            if (!is_null($batch_id)) {
                if (!empty($loc['exist']) && (int)$loc['batch_id'] !== (int)$batch_id)
                    unset($locs[$i]);
            } elseif (!empty($loc['batch_id']))
                unset($locs[$i]);
        return array_map(function($loc) {
            return $loc['location_id'];
        }, $locs);
    }

    public static function existST($warehouse_id, $SKU_id)
    {
        return (new SafeMysql())->fetchScalar("
            SELECT 1
            FROM SKU_preff_locs spl
                JOIN locations l on spl.location_id = l.id AND l.warehouse = :warehouse_id AND l.type_id = :type
            WHERE spl.SKU_id = :SKU_id",
            ['warehouse_id' => (int)$warehouse_id, 'type' => Location::ST, 'SKU_id' => (int)$SKU_id]);
    }

    public static function cleanUnusedLocations($warehouse_id, $day_release, $long_used = false)
    {
        if (!$day_release)
            return;
        $max_date = date('Y-m-d', time() - 3600*24*$day_release);
        $SKU_locs = (new SafeMysql())->fetchAll("
            SELECT spl.SKU_id, spl.location_id, IFNULL(SUM(sl.quantity), 0) quantity
            FROM SKU_preff_locs spl
                JOIN locations l ON spl.location_id = l.id AND l.warehouse = :warehouse_id
                LEFT JOIN SKU_locations sl ON spl.SKU_id = sl.SKU_id AND spl.location_id = sl.location_id          
            GROUP BY spl.SKU_id, spl.location_id
            HAVING quantity < 1
            ORDER BY spl.SKU_id", ['warehouse_id' => (int)$warehouse_id]);
        foreach ($SKU_locs as $SKU_loc) {
            $loc_params = ['SKU_id' => (int)$SKU_loc['SKU_id'], 'location_id' => (int)$SKU_loc['location_id']];
            // PA zone
            $is_PA = (new SafeMysql())->fetchScalar("
                SELECT COUNT(*)
                FROM SKU_locations
                    JOIN locations ON location_id = id AND type_id = :type
                WHERE SKU_id = :SKU_id", ['type' => Location::PA, 'SKU_id' => $loc_params['SKU_id']]);
            if ($is_PA)
                continue;
            // movements
            $is_movs = (new SafeMysql())->fetchScalar("
                SELECT COUNT(*) 
                FROM movements
                WHERE SKU_id = :SKU_id AND (loc_from_id = :location_id OR loc_to_id = :location_id) AND type", $loc_params);
            if ($is_movs)
                continue;
            // assigned locations
            $is_locs = (new SafeMysql())->fetchScalar("
                SELECT IFNULL(SUM(sl.quantity), 0)
                FROM SKU_preff_locs spl
                    LEFT JOIN SKU_locations sl ON spl.SKU_id = sl.SKU_id AND spl.location_id = sl.location_id
                WHERE spl.SKU_id = :SKU_id AND spl.location_id = :location_id", $loc_params);
            if ($is_locs)
                continue;
            // long not used
            if ($long_used) {
                $loc_id_str = (string)$SKU_loc['location_id'];
                $history = (new SafeMysql())->fetchScalar("
                    SELECT MAX(DATE(event_date)) 
                    FROM SKU_history
                    WHERE SKU_id = :SKU_id AND (additional_info LIKE :pattern1 OR additional_info LIKE :pattern2)",
                    ['SKU_id' => $loc_params['SKU_id'], 'pattern1' => '%"' . $loc_id_str . '"%', 'pattern2' => '%:' . $loc_id_str . ',%']);
                if (empty($history) || $history >= $max_date)
                    continue;
            }
            $mysql = new SafeMysql();
            $mysql->beginTransaction();
            $mysql->execute("
                DELETE FROM SKU_preff_locs
                WHERE SKU_id = :SKU_id AND location_id = :location_id", [
                'SKU_id' => $SKU_loc['SKU_id'],
                'location_id' => $SKU_loc['location_id'],
            ]);
            SKUHistories::updateLocations($SKU_loc['SKU_id'], -1, ['deleted' => [$SKU_loc['location_id']]], $warehouse_id);
            $mysql->commit();
        }
    }

    public static function getSKUsByLocation($location_id)
    {
        $items = (new SafeMysql())->fetchAll("
            SELECT spl.SKU_id, spl.priority
            FROM SKU_preff_locs spl
                JOIN SKUs s ON spl.SKU_id = s.id
            WHERE spl.location_id = :location_id", ['location_id' => (int)$location_id]);
        SKUBarcodes::addBarcodesToArray($items, 'SKU_id');
        return $items;
    }

    public static function isAssignmentSKU($SKU_id, $location_id)
    {
        return (new SafeMysql())->fetchScalar("SELECT 1 FROM SKU_preff_locs WHERE SKU_id = :SKU_id AND location_id = :location_id",
            ['SKU_id' => (int)$SKU_id, 'location_id' => (int)$location_id]);
    }

    /** @throws */
    public static function getPreffLocsObjects(int $warehouse_id, int $SKU_id, string $type_id = Location::PF): array
    {
        if (!Location::isStorage($type_id))
            return [];
        $sql = "
        SELECT spl.location_id, spl.priority
        FROM SKU_preff_locs spl
            JOIN locations l ON spl.location_id = l.id AND l.type_id = :type_id AND l.warehouse = :warehouse_id
        WHERE spl.SKU_id = :SKU_id;";
        $data = [];
        foreach ((new SafeMysql())->fetchAll($sql, ['type_id' => $type_id, 'warehouse_id' => $warehouse_id, 'SKU_id' => $SKU_id]) as $item)
            $data[(int)$item['location_id']] = (new SKUPreffLoc())->setSKUId($SKU_id)->setLocationId((int)$item['location_id'])->setPriority((int)$item['priority']);
        return $data;
    }

    public static function getOtherSKUsByIds(int $id, array $ids): array
    {
        if (!$ids)
            return [];
        $ids = array_map('intval', $ids);
        $sql = "
        SELECT S.id, Spl.location_id
        FROM SKU_preff_locs Spl
        INNER JOIN SKUs S ON S.id = Spl.SKU_id
        WHERE Spl.SKU_id <> :SKU_id AND Spl.location_id IN (:location_ids)";
        if ($data = (new SafeMysql())->fetchAll($sql, ['SKU_id' => $id, 'location_ids' => $ids])) {
            SKUBarcodes::addBarcodesToArray($data);
            return $data;
        }
        return [];
    }

    public static function deleteByIds(int $SKU_id, array $ids): void
    {
        if (!$ids)
            return;
        $ids = array_map('intval', $ids);
        (new SafeMysql())->execute("DELETE FROM SKU_preff_locs WHERE SKU_id = :SKU_id AND location_id IN (:location_ids)", ['SKU_id' => $SKU_id, 'location_ids' => $ids]);
    }

    /** @param SKUPreffLoc[] $objects */
    public static function bulkInsertUpdate(array $objects): void
    {
        if (!$objects)
            return;
        $values = $bind = [];
        foreach ($objects as $key => $object) {
            $values[] = "(:SKU_id_$key, :location_id_$key, :priority_$key)";
            $bind["SKU_id_$key"] = $object->getSKUId();
            $bind["location_id_$key"] = $object->getLocationId();
            $bind["priority_$key"] = $object->getPriority();
        }
        $sql = "
        INSERT INTO SKU_preff_locs (SKU_id, location_id, priority)
        VALUES " . implode(', ', $values) . "
        ON DUPLICATE KEY UPDATE priority = VALUES(priority)";
        (new SafeMysql())->execute($sql, $bind);
    }

    public static function checkExistPreffLoc(int $warehouse_id, int $SKU_id, int $location_id, string $type_id): ?bool
    {
        if (!Location::isStorage($type_id))
            return null;
        $sql = "
        SELECT spl.location_id, spl.priority
        FROM SKU_preff_locs spl
            JOIN locations l ON spl.location_id = l.id AND l.warehouse = $warehouse_id
        WHERE spl.SKU_id = :SKU_id AND spl.location_id = :location_id;";
        return (bool)(new SafeMysql())->fetchScalar($sql, ['SKU_id' => $SKU_id, 'location_id' => $location_id]);
    }

    public static function assignments(int $SKU_id, array $locations)
    {
        $preff_loc_values = [];
        foreach ($locations as $location_id)
            $preff_loc_values[] = "($SKU_id, $location_id, 1)";
        (new SafeMysql())->execute("INSERT IGNORE INTO SKU_preff_locs (SKU_id, location_id, priority) VALUES " . implode(', ', $preff_loc_values));
    }
}
