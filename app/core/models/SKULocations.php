<?php

namespace app\core\models;

use app\backend\models\OrderHistories;
use app\backend\models\Pack;
use app\backend\models\Pick;
use app\backend\models\ShopInventoryQueues;
use app\backend\models\InventorySync;
use app\core\db\DBException;
use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\entities\Order;
use app\core\entities\SKU;
use app\core\helpers\Arr;
use app\core\helpers\Debug;
use app\core\helpers\View;
use app\backend\models\Locations as LocationsBackend;
use Exception;
use InvalidArgumentException;
use app\core\Environment;

class SKULocations
{
    /** b.number HTML-escaped in SQL, for batch_number markup built by CONCAT() */
    const SQL_HTML_BATCH_NUMBER = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(b.number, '&', '&amp;'), '<', '&lt;'), '>', '&gt;'), '\"', '&quot;'), '''', '&#039;')";

    public static function removeByIndex(int $SKU_id, int $location_id, int $order_id, int $batch_id): void
    {
        (new SafeMysql())->execute("DELETE FROM SKU_locations 
                                    WHERE SKU_id = :SKU_id 
                                        AND location_id = :location_id
                                        AND order_id = :order_id
                                        AND batch_id = :batch_id", [
            'SKU_id' => $SKU_id,
            'location_id' => $location_id,
            'order_id' => $order_id,
            'batch_id' => $batch_id,
        ]);
    }

    public static function getConsistsSKUInLocation($SKU_id, $location_id)
    {
        $SKU_id = (int)$SKU_id;
        $location_id = (int)$location_id;
        $res = (new SafeMysql())->fetchAll("
            SELECT reason, SUM(quantity) qty
                FROM SKU_locations
                    JOIN locations ON location_id = id
                WHERE location_id = :location_id AND SKU_id = :SKU_id
                GROUP BY POSITION('_' IN reason)
        ", ['location_id' => $location_id, 'SKU_id' => $SKU_id]);
        if (empty($res))
            return [];
        $consists = ['reserved' => 0, 'replenish' => 0];
        foreach ($res as $consist)
            if (strpos($consist['reason'], '_'))
                $consists['reserved'] = $consist['qty'];
            else
                $consists['replenish'] = $consist['qty'];
        return $consists;
    }

    /**
     * @return array<int>
     */
    public static function getSKUs($location_id)
    {
        $location_id = (int)$location_id;
        $sql = "SELECT SKU_id
                FROM SKU_locations
                WHERE location_id = :location_id
                GROUP BY SKU_id";
        return (new SafeMysql())->fetchArrayOneField($sql, ['location_id' => $location_id]);
    }

    public static function getCandidateLocs($warehouse_id, $SKU_id, $type_id = Location::PF, $zone = null, $column = null)
    {
        $params = [
            'SKU_id' => $SKU_id,
            'type_id' => $type_id,
            'warehouse_id' => $warehouse_id
        ];
        if (Location::hasUniqueSKU($type_id)) {
            $PFlimits1 = '(SELECT COUNT(*) FROM SKU_locations WHERE location_id = l.id AND SKU_id <> :SKU_id)';
            $PFlimits2 = '';
        } else {
            $PFlimits1 = '0';
            $PFlimits2 = ' WHERE SKU_id = :SKU_id';
        }
        $and_where = '';
        if (!empty($zone)) {
            $and_where .= ' AND l.zone = :zone';
            $params['zone'] = $zone;
        }
        if (!empty($column)) {
            $and_where .= ' AND l.`column` = :column';
            $params['column'] = $column;
        }
        $sql = "SELECT l.id location_id, l.zone, l.`column`, 0 priority,
                       (SELECT COUNT(*) FROM SKU_locations WHERE location_id = l.id AND SKU_id = :SKU_id) nums, $PFlimits1 nums_not,
                       CONCAT(IF(w.label IS NULL OR w.label = '', l.warehouse, w.label), '-', l.type_id, '-', l.zone, '-', l.`column`, '-', l.`row`) name,
                       IFNULL(l.alias, CONCAT(l.zone, l.`column`, l.`row`)) alias
                FROM locations l
                    JOIN warehouses w ON l.warehouse = w.id
                WHERE l.type_id = :type_id AND l.warehouse = :warehouse_id AND l.state = 1
                    AND l.id NOT IN (SELECT DISTINCT location_id FROM SKU_preff_locs $PFlimits2)
                    $and_where
                ORDER BY l.warehouse, l.type_id, LPAD(l.zone, 5, ' '), LPAD(l.`column`, 5, '0'), LPAD(l.`row`, 5, ' ')";
        $res = (new SafeMysql())->fetchAll($sql, $params);
        foreach ($res as $key => $val)
            if ($val['nums_not'])
                unset($res[$key]);
            else
                unset($res[$key]['nums_not']);
        return $res;
    }

    public static function getCandidateLocationZonesAndColumns($warehouse_id, $SKU_id, $type_id = Location::PF, $exclude_ids = [])
    {
        $SKU = SKUs::getById($SKU_id);
        if (!$SKU)
            return [];
        $exclude_sql = '';
        $params = [
            'SKU_id' => $SKU_id,
            'supplier_id' => $SKU->getSupplierId(),
            'type_id' => $type_id,
            'warehouse_id' => $warehouse_id,
            'state' => Location::ACTIVE,
        ];
        if (!empty($exclude_ids)) {
            $exclude_sql = ' AND l.id NOT IN (:exclude_ids)';
            $params['exclude_ids'] = $exclude_ids;
        }
        if (Location::hasUniqueSKU($type_id)) {
            $PFlimits1 = '(SELECT COUNT(*) FROM SKU_locations WHERE location_id = l.id AND SKU_id <> :SKU_id)';
            $PFlimits2 = '';
        } else {
            $PFlimits1 = '0';
            $PFlimits2 = ' WHERE SKU_id = :SKU_id';
        }
        // @WARNING: This is a very slow query, it should be optimized
        $exclude_booked = 'AND NOT EXISTS (
            SELECT 1 FROM book_locations bl
                JOIN suppliers_warehouses sw ON bl.supplier_warehouse_id = sw.id AND (sw.supplier_id != :supplier_id OR sw.warehouse_id != :warehouse_id)
            WHERE bl.location_id = l.id
            LIMIT 1
        ) AND NOT EXISTS (
            SELECT 1 FROM book_range_locations brl
                JOIN suppliers_warehouses sw ON brl.supplier_warehouse_id = sw.id AND (sw.supplier_id != :supplier_id OR sw.warehouse_id != :warehouse_id)
            WHERE brl.zone = l.zone
                AND (brl.`column` = l.`column` OR brl.`column` IS NULL)
                AND brl.type_id = l.type_id
            LIMIT 1
        )';

        $sql = "SELECT DISTINCT l.zone, l.`column`, $PFlimits1 nums_not
                FROM locations l
                WHERE l.type_id = :type_id AND l.warehouse = :warehouse_id AND l.state = :state
                    AND l.id NOT IN (SELECT DISTINCT location_id FROM SKU_preff_locs $PFlimits2)
                    $exclude_sql
                    $exclude_booked
                ORDER BY LPAD(l.zone, 5, ' '), LPAD(l.`column`, 5, '0')";
        $raw_result = (new SafeMysql())->fetchAll($sql, $params);
        $result = [];
        foreach ($raw_result as $row)
            if (!$row['nums_not'])
                $result[$row['zone']][] = $row['column'];
        return $result;
    }

    public static function getBookedLocationsBySomeOneElse(array $locations_ids, $warehouse_id, $supplier_id)
    {
        if (empty($locations_ids))
            return false;
        $sql = "SELECT l.id
                FROM locations l
                WHERE l.id IN (:locations_ids)
                    AND (EXISTS (
                            SELECT 1 FROM book_locations bl
                                JOIN suppliers_warehouses sw ON bl.supplier_warehouse_id = sw.id
                            WHERE bl.location_id = l.id
                                AND (sw.supplier_id != :supplier_id OR sw.warehouse_id != :warehouse_id)
                            LIMIT 1
                        ) OR EXISTS (
                            SELECT 1 FROM book_range_locations brl
                                JOIN suppliers_warehouses sw ON brl.supplier_warehouse_id = sw.id
                            WHERE brl.zone = l.zone
                                AND (brl.`column` = l.`column` OR brl.`column` IS NULL)
                                AND brl.type_id = l.type_id
                                AND (sw.supplier_id != :supplier_id OR sw.warehouse_id != :warehouse_id)
                            LIMIT 1
                        )
                    )";
        return (new SafeMysql())->fetchArrayOneField($sql, [
            'supplier_id' => $supplier_id,
            'warehouse_id' => $warehouse_id,
            'locations_ids' => $locations_ids
        ]);
    }

    public static function getBooker($loc_id)
    {
        $loc_id = (int)$loc_id;
        $supplier_id = (new SafeMysql())->fetchScalar("SELECT sw.supplier_id
                                  FROM book_locations bl
                                      JOIN suppliers_warehouses sw ON bl.supplier_warehouse_id = sw.id
                                  WHERE location_id = :loc_id", ['loc_id' => $loc_id]);
        if (!empty($supplier_id))
            return $supplier_id;
        return (new SafeMysql())->fetchScalar("SELECT sw.supplier_id
                                  FROM book_range_locations brl
                                      JOIN suppliers_warehouses sw ON brl.supplier_warehouse_id = sw.id
                                  WHERE zone = (SELECT zone FROM locations WHERE id = :loc_id)
                                      AND (`column` = (SELECT `column` FROM locations WHERE id = :loc_id) OR `column` IS NULL)
                                      AND type_id = (SELECT type_id FROM locations WHERE id = :loc_id)", ['loc_id' => $loc_id]);
    }

    public static function getLocations($SKU_id)
    {
        $SKU_id = (int)$SKU_id;
        $sql = "SELECT location_id id, warehouse, type_id, `zone`, `column`, row, size_id, SUM(quantity) summa
                FROM SKU_locations sl, locations l
                WHERE sl.location_id=l.id AND l.type_id IN (:storage_types) AND sl.SKU_id = :SKU_id
                GROUP BY sl.location_id";
        return (new SafeMysql())->fetchAll($sql, ['storage_types' => Location::STORAGE_TYPES, 'SKU_id' => $SKU_id]);
    }

    public static function getAllLocations($warehouse_id, $SKU_id)
    {
        $warehouse_id = (int)$warehouse_id;
        $SKU_id = (int)$SKU_id;
        $sql = "SELECT location_id id,
                       type_id,
                       IF (type_id = :us_type,
                           CONCAT('Virtual (', u.login, ')'),
                           CONCAT(IF(w.label = '' OR w.label IS NULL, warehouse, w.label), '-', type_id, '-', `zone`, '-', `column`, '-', `row`)) name,
                       size_id,
                       SUM(quantity) summa,
                       SUM(IF(sl.type = 4, 1, 0)) expiry
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id AND l.warehouse = :warehouse_id
                    JOIN warehouses w ON l.warehouse = w.id
                    LEFT JOIN users u ON l.row = u.id
                WHERE sl.SKU_id = :SKU_id
                GROUP BY sl.location_id
                ORDER BY warehouse, type_id, LPAD(zone, 5, '0'), LPAD(`column`, 5, '0'), LPAD(`row`, 5, '0')";
        return (new SafeMysql())->fetchAll($sql, ['us_type' => Location::US, 'warehouse_id' => $warehouse_id, 'SKU_id' => $SKU_id]);
    }

    public static function getSKUsFromLocation($location_id, $types = false)
    {
        $location_id = (int) $location_id;
        $groupTypes = $types ? ', sl.order_id, sl.type, sl.reason' : '';
        $sql = "SELECT sl.SKU_id, s.name, s.photo, su.id, su.name sname, SUM(sl.quantity) summa, b.id batch_id, b.number, b.expired, sl.order_id, sl.type, sl.reason
                FROM SKU_locations sl
                    JOIN SKUs s ON s.id = sl.SKU_id
                    JOIN suppliers su ON s.supplier_id = su.id
                    LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
                WHERE sl.location_id = :location_id
                GROUP BY sl.SKU_id, sl.batch_id $groupTypes
                ORDER BY sl.SKU_id, sl.order_id, IFNULL(b.expired, NOW()), b.number";
        $items = (new SafeMysql())->fetchAll($sql, ['location_id' => $location_id]);
        SKUBarcodes::addBarcodesToArray($items, 'SKU_id');
        return $items;
    }

    public static function getCALocation($order_id)
    {
        $order_id = (int) $order_id;
        $sql = "SELECT DISTINCT location_id
                FROM SKU_locations
                    JOIN locations ON id = location_id AND type_id = :ca_type
                WHERE order_id = :order_id";
        return (new SafeMysql())->fetchArrayOneField($sql, ['ca_type' => Location::CA, 'order_id' => $order_id]);
    }

    public static function getCountLocation($location_id)
    {
        $location_id = (int) $location_id;
        return (new SafeMysql())->fetchScalar("SELECT COUNT(*) num
                                  FROM SKU_locations
                                  WHERE location_id = :location_id", ['location_id' => $location_id]);
    }

    private static function isInsert($batch, $consist)
    {
        foreach ($consist as $record)
            if ($batch['order_id'] == $record['order_id'] &&
                $batch['type'] == $record['type'] &&
                $batch['reason'] == $record['reason'] &&
                ($batch['batch_id'] == $record['batch_id']))
                return false;
        return true;
    }

    public static function addLocation($loc_id, $SKU_id, $batches)
    {
        if (empty($batches))
            return false;
        $loc_id = (int)$loc_id;
        $SKU_id = (int)$SKU_id;
        $sql = "SELECT quantity, order_id, type, reason, batch_id
                FROM SKU_locations
                WHERE location_id = :loc_id AND SKU_id = :SKU_id";
        $mysql = new SafeMysql();
        $consist = $mysql->fetchAll($sql, ['loc_id' => $loc_id, 'SKU_id' => $SKU_id]);
        foreach ($batches as $batch) {
            $order_id = empty($batch['order_id']) ? null : $batch['order_id'];
            $batch_id = empty($batch['batch_id']) ? null : $batch['batch_id'];
            if (self::isInsert($batch, $consist))
                $mysql->execute("INSERT INTO SKU_locations (SKU_id, location_id, quantity, order_id, type, reason, batch_id)
                        VALUES (:SKU_id, :loc_id, :quantity, :order_id, :type, :reason, :batch_id)", [
                    'SKU_id' => $SKU_id,
                    'loc_id' => $loc_id,
                    'quantity' => $batch['quantity'],
                    'order_id' => $order_id,
                    'type' => $batch['type'],
                    // reason may be missing in old despatched JSON; legacy wrote '' (NOT NULL column)
                    'reason' => (string)($batch['reason'] ?? ''),
                    'batch_id' => $batch_id,
                ]);
            else {
                $whereUpdate = "WHERE SKU_id = :SKU_id AND
                                location_id = :loc_id AND
                                order_id " . ($order_id === null ? 'IS NULL' : '= :order_id') . " AND
                                type = :type AND
                                reason = :reason
                                AND batch_id " . ($batch_id === null ? 'IS NULL' : '= :batch_id');
                $params = [
                    'SKU_id' => $SKU_id,
                    'loc_id' => $loc_id,
                    'type' => $batch['type'],
                    'reason' => (string)($batch['reason'] ?? ''),
                ];
                if ($order_id !== null)
                    $params['order_id'] = $order_id;
                if ($batch_id !== null)
                    $params['batch_id'] = $batch_id;

                $emptyInLoc = !$mysql->fetchScalar("SELECT 1 FROM SKU_locations " . $whereUpdate, $params);
                if ($emptyInLoc)
                    return false;
                $params['quantity'] = $batch['quantity'];
                $mysql->execute("UPDATE SKU_locations
                        SET quantity = quantity + :quantity " . $whereUpdate, $params);
            }
        }
        return true;
    }

    public static function subLocation($SKU_id, $loc_id, $qty, $type, $reason, $order_id = 0, $batch_id = -1, $reserve = false, $from_DH = false)
    {
        $where_order = 'AND order_id ' . (empty($order_id) ? 'IS NULL' : "= $order_id");
        $where_batch = $batch_id == -1 ? '' : ('AND batch_id ' . (!empty($batch_id) ? "= $batch_id" : 'IS NULL'));
        $where = "location_id = $loc_id AND SKU_locations.SKU_id = $SKU_id AND type = $type";
        $where_reason = ' AND reason = ' . ($reserve ? ($reason ?: "''") : "'$reason'");
        $sql = "SELECT quantity, order_id, type, reason, batch_id
                FROM SKU_locations
                    LEFT JOIN batches ON batch_id = id AND SKU_locations.SKU_id = batches.SKU_id
                WHERE $where $where_order $where_reason $where_batch
                ORDER BY IFNULL(expired, NOW()), quantity DESC, IF(reason = '2', '999999999999999', LPAD(SUBSTRING_INDEX(reason, '_', -1),15,' '))";
        $mysql = new SafeMysql();
        $res = $mysql->fetchAll($sql);
        $quantity = array_sum(Arr::column($res, 'quantity'));
        if ($qty > $quantity && !$from_DH)
            return false;
        $sub = [];
        $q = $qty;
        foreach ($res as $key => $val)
            if ($q >= $val['quantity']) {
                $params = ['loc_id' => $loc_id, 'SKU_id' => $SKU_id, 'type' => $type, 'reason' => $val['reason'], 'quantity' => $val['quantity']];
                $order_where = empty($val['order_id']) ? 'IS NULL' : '= :order_id';
                if (!empty($val['order_id']))
                    $params['order_id'] = $val['order_id'];
                $batch_where = empty($val['batch_id']) ? 'IS NULL' : '= :batch_id';
                if (!empty($val['batch_id']))
                    $params['batch_id'] = $val['batch_id'];
                $mysql->execute("DELETE FROM SKU_locations
                                WHERE location_id = :loc_id AND SKU_locations.SKU_id = :SKU_id AND type = :type AND
                                      reason = :reason AND
                                      order_id $order_where AND
                                      batch_id $batch_where AND
                                      quantity = :quantity", $params);
                $q -= $val['quantity'];
                $sub[] = $val;
                unset($res[$key]);
                if (!$q)
                    return $sub;
            }
        if ($q) {
            if (empty($res) && $from_DH) {
                $sub[] = ['quantity' => $q, 'order_id' => null, 'type' => 1, 'reason' => '2', 'batch_id' => null];
            } else {
                $pos = array_keys($res)[0];
                $params = ['loc_id' => $loc_id, 'SKU_id' => $SKU_id, 'type' => $type, 'reason' => $res[$pos]['reason'], 'q' => $q];
                $order_where = empty($res[$pos]['order_id']) ? 'IS NULL' : '= :order_id';
                if (!empty($res[$pos]['order_id']))
                    $params['order_id'] = $res[$pos]['order_id'];
                $batch_where = empty($res[$pos]['batch_id']) ? 'IS NULL' : '= :batch_id';
                if (!empty($res[$pos]['batch_id']))
                    $params['batch_id'] = $res[$pos]['batch_id'];
                $mysql->execute("UPDATE SKU_locations
                                SET quantity = quantity - :q
                                WHERE location_id = :loc_id AND SKU_locations.SKU_id = :SKU_id AND type = :type AND
                                      reason = :reason AND
                                      order_id $order_where AND
                                      batch_id $batch_where", $params);
                $res[$pos]['quantity'] = $q;
                $sub[] = $res[$pos];
            }
        }
        return $sub;
    }

    public static function getQuantityByOrder($SKU_id, $order_id)
    {
        $SKU_id = (int)$SKU_id;
        $order_id = (int)$order_id;
        if (empty($order_id) || empty($SKU_id))
            return false;
        return (new SafeMysql())->fetchScalar("SELECT IFNULL(SUM(quantity), 0)
                                  FROM SKU_locations
                                  WHERE SKU_id = :SKU_id AND order_id = :order_id", ['SKU_id' => $SKU_id, 'order_id' => $order_id]);
    }

    public static function getReservedQuantityInLocation($SKU_id, $order_id, $location_id)
    {
        $SKU_id = (int)$SKU_id;
        $order_id = (int)$order_id;
        $location_id = (int)$location_id;
        if (empty($order_id) || empty($SKU_id) || empty($location_id))
            return false;
        return (new SafeMysql())->fetchScalar("SELECT IFNULL(SUM(quantity), 0)
                                  FROM SKU_locations
                                  WHERE SKU_id = :SKU_id AND location_id = :location_id AND order_id = :order_id AND type = 1 AND reason = '1'",
            ['SKU_id' => $SKU_id, 'location_id' => $location_id, 'order_id' => $order_id]);
    }

    public static function getReservedQuantity($order_id, $SKU_id)
    {
        $SKU_id = (int)$SKU_id;
        $order_id = (int)$order_id;
        if (empty($order_id) || empty($SKU_id))
            return false;
        return (new SafeMysql())->fetchScalar("SELECT IFNULL(SUM(quantity), 0)
                                  FROM SKU_locations
                                  WHERE SKU_id = :SKU_id AND order_id = :order_id AND type = 1 AND reason = '1'",
            ['SKU_id' => $SKU_id, 'order_id' => $order_id]);
    }

    public static function getQuantity($SKU_id, $location_id, $batch_id = -1, $reserved = false)
    {
        $SKU_id = (int) $SKU_id;
        $location_id = (int) $location_id;
        $batch_id = (int) $batch_id;
        if ($batch_id == -1)
            $whereBatch = '';
        elseif (empty($batch_id))
            $whereBatch = ' AND batch_id IS NULL';
        else
            $whereBatch = " AND batch_id = $batch_id";
        $whereReason = $reserved ? "reason = 2" : "reason = '2'";
        $sql = "SELECT SUM(quantity)
                FROM SKU_locations
                WHERE SKU_id = $SKU_id AND
                      location_id = $location_id AND
                      order_id IS NULL AND
                      type = 1 AND
                      $whereReason $whereBatch";
        return (int)(new SafeMysql())->fetchScalar($sql);
    }

    public static function getQuantityInLocation($location_id)
    {
        return (new SafeMysql())->fetchScalar("SELECT SUM(quantity) FROM SKU_locations WHERE location_id = :location_id", ['location_id' => (int)$location_id]);
    }

    public static function getQuantities($locs)
    {
        if (!count($locs))
            return [];
        $sql = "SELECT location_id, SUM(quantity) qty FROM SKU_locations WHERE location_id IN (:locs) GROUP BY location_id";
        $qty = (new SafeMysql())->fetchAll($sql, ['locs' => $locs]);
        $res = [];
        foreach ($qty as $loc)
            $res[$loc['location_id']] = $loc['qty'];
        return $res;
    }

    public static function getNotFreeLocations($locations)
    {
        if (empty($locations)) {
            return [];
        }

        $locationsIds = [];
        foreach ($locations as $location) {
            $locationsIds[] = $location->getId();
        }
        $sql = "SELECT location_id FROM SKU_locations where
                location_id in (:locationsIds) and (order_id is not null or reason like '2_%')
                group by location_id
                ";
        return array_keys((new SafeMysql())->fetchAssocWithKey($sql, ['locationsIds' => $locationsIds], 'location_id'));
    }

    public static function getDroppingByWorker($mode, $cart_id, $mezzanine = null, $services = -1)
    {
        $orders = [];
        if ($mode == 'single')
            $SKUs = SKULocations::getDroppingToTable($cart_id, 1, $services);
        elseif ($mode == 'multi')
            $SKUs = SKULocations::getDroppingToMulti($cart_id, true, $mezzanine, $services);
        elseif ($mode == 'large')
            $SKUs = SKULocations::getDroppingToTable($cart_id, 3, $services);
        $res = [];
        foreach ($SKUs as $SKU) {
            $id = sprintf(
                '%s_%s_%s',
                !empty($SKU['location_id']) ? $SKU['location_id'] : '.',
                $SKU['id'],
                !empty($SKU['batch_id']) ? $SKU['batch_id'] : '.',
            );
            if (!empty($SKU['picking']))
                $SKU['picking'] = "Order #{$SKU['order_id']} {$SKU['quantity']} unit(s): {$SKU['picking']}";
            if (empty($res[$id]))
                $res[$id] = $SKU;
            else
                $res[$id]['quantity'] += $SKU['quantity'];
            if (empty($orders[$id . '_'][$SKU['order_id']]))
                $orders[$id][$SKU['order_id']] = $SKU['quantity'];
            else
                $orders[$id][$SKU['order_id']] += $SKU['quantity'];
        }
        $SKUs = [];
        foreach ($res as $SKU) {
            unset($SKU['order_id']);
            if (isset($SKU['priority']))
                $SKU['priority'] = $SKU['priority'] > 0;
            $SKUs[] = $SKU;
        }
        return [$SKUs, $orders];
    }

    public static function getDroppingToMulti($loc_id, $priority = false, $mezzanine = null, $services = 0)
    {
        $prioritySQL = [
            'row' => $priority ? ', o.priority ' : '',
            'sort' => $priority ? 'o.priority = 0, o.priority ASC,' : ''
        ];
        $mezzanineSQL = '';
        if (!is_null($mezzanine))
            $mezzanineSQL = ' AND l.mezzanine = ' . (int)(bool)$mezzanine;
        $shipping_services = 'JOIN shipping_services ss ON o.shipping_services_id = ss.id';
        $is_customer = false;
        $where_services = $where_shipping_services = '';
        if ($services != -1) {
            $service_ids = ShippingServices::getSelectedServicesIds($services);
            $where_shipping_services = 'AND o.shipping_services_id IN (' . implode(',', $service_ids) . ')';
            $pos = array_search(0, $service_ids);
            if ($pos !== false) {
                unset($service_ids[$pos]);
                $is_customer = true;
            }
            $where_services = empty($service_ids)
                ? $shipping_services
                : ("$shipping_services AND ss.id IN (" . implode(',', $service_ids) . ')');
        }
        if (empty($where_services))
            $where_services = "LEFT $shipping_services";
        if ($is_customer)
            $where_services = "LEFT $where_services";
        $sql = "SELECT s.id, s.name, s.photo, s.supplier_id, 
                       IF(s.state = 0, true, false) as locked,
                       SUM(sl.quantity) quantity, sl.order_id, o.despatch_date, o.picking, ol.location_id, 
                       IF(w.label = '' OR w.label IS NULL, l.warehouse, w.label) warehouse, 
                       l.type_id, l.zone, l.`column`, l.row, sl.batch_id,
                       CONCAT('<b>Batch#:</b> ', " . self::SQL_HTML_BATCH_NUMBER . ", '<br/><b>Exp#:</b> ', DATE_FORMAT(b.expired, '%D %b %Y')) batch_number, 
                       IFNULL(ss.next_day, false) as next_day
                       {$prioritySQL['row']}
                FROM SKU_locations sl
                    JOIN SKUs s ON s.id = sl.SKU_id
                    JOIN orders o ON sl.order_id = o.id $where_shipping_services
                    JOIN order_location ol ON sl.order_id = ol.order_id
                    JOIN locations l ON ol.location_id = l.id {$mezzanineSQL}
                    JOIN warehouses w ON l.warehouse = w.id
                    LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
                    $where_services
                WHERE sl.type = '22' AND sl.location_id = $loc_id 
                GROUP BY s.id, sl.batch_id, sl.order_id
                ORDER BY {$prioritySQL['sort']} l.`column`, LPAD(l.row, 5, ' '), s.id, IFNULL(b.expired, NOW())";
        $result = (new SafeMysql())->fetchAll($sql);
        SKUBarcodes::addBarcodesToArray($result);
        return $result;
    }

    public static function getDroppingToTable($loc_id, $mode, $services = -1)
    {
        $shipping_services = 'JOIN shipping_services ss ON o.shipping_services_id = ss.id';
        $is_customer = false;
        $where_services = $where_shipping_services = '';
        if ($services != -1) {
            $service_ids = ShippingServices::getSelectedServicesIds($services);
            $where_shipping_services = 'AND o.shipping_services_id IN (' . implode(',', $service_ids) . ')';
            $pos = array_search(0, $service_ids);
            if ($pos !== false) {
                unset($service_ids[$pos]);
                $is_customer = true;
            }
            $where_services = empty($service_ids)
                ? $shipping_services
                : ("$shipping_services AND ss.id IN (" . implode(',', $service_ids) . ')');
        }
        if (empty($where_services))
            $where_services = "LEFT $shipping_services";
        if ($is_customer)
            $where_services = "LEFT $where_services";
        $sql = "SELECT s.id, s.name, s.photo, 
                       IF(s.state = 0, true, false) as locked,
                       SUM(sl.quantity) quantity, sl.order_id, o.despatch_date, sl.batch_id,
                       CONCAT('<b>Batch#:</b> ', " . self::SQL_HTML_BATCH_NUMBER . ", '<br/><b>Exp#:</b> ', DATE_FORMAT(b.expired, '%D %b %Y')) batch_number,
                       IFNULL(ss.next_day, false) as next_day
                FROM SKU_locations sl
                    JOIN SKUs s ON s.id = sl.SKU_id
                    JOIN orders o ON sl.order_id = o.id $where_shipping_services
                    LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
                    $where_services
                WHERE sl.type = '2$mode' AND sl.location_id = $loc_id
                GROUP BY s.id, sl.batch_id, sl.order_id
                ORDER BY s.id, IFNULL(b.expired, NOW())";
        $result = (new SafeMysql())->fetchAll($sql);
        SKUBarcodes::addBarcodesToArray($result);
        return $result;
    }

    public static function getDropping($user_id, $warehouse_id, $mode, $priority = false, $mezzanine = null, $services = 0)
    {
        $warehouse_id = (int)$warehouse_id;
        $whereUser = '';
        if (is_array($user_id))
            $whereUser = ' AND u.id ' . (count($user_id) > 0 ? ('IN (' . implode(',', $user_id) . ')') : '= 0');
        $prioritySQL = [
            'row' => $priority ? ', SUM(o.priority != 0) priority ' : '',
            'sort' => $priority ? 'priority = 0, priority DESC,' : ''
        ];
        $mezzanineSQL = '';
        if (!is_null($mezzanine))
            $mezzanineSQL = ' AND l.mezzanine = ' . (int)(bool)$mezzanine;
        $shipping_services = 'JOIN shipping_services ss ON o.shipping_services_id = ss.id';
        $is_customer = false;
        $where_services = $where_shipping_services = '';
        if ($services != -1) {
            $service_ids = ShippingServices::getSelectedServicesIds($services);
            $where_shipping_services = 'AND o.shipping_services_id IN (' . implode(',', $service_ids) . ')';
            $pos = array_search(0, $service_ids);
            if ($pos !== false) {
                unset($service_ids[$pos]);
                $is_customer = true;
            }
            $where_services = empty($service_ids)
                ? $shipping_services
                : ("$shipping_services AND ss.id IN (" . implode(',', $service_ids) . ')');
        }
        if (empty($where_services))
            $where_services = "LEFT $shipping_services";
        if ($is_customer)
            $where_services = "LEFT $where_services";
        $sql = "SELECT l.mezzanine, sl.location_id, IF(w.label = '' OR w.label IS NULL, l.warehouse, w.label) warehouse, l.type_id, l.zone, l.`column`, l.row,
                       s.id, s.name, s.photo,
                       o.despatch_date,
                       COUNT(*) nums, SUM(sl.quantity) quantity, sl.`type`,
                       CONCAT(u.login, ' (', u.id, ')') login, u2.id cart_id,
                       CONCAT('<b>Batch#:</b> ', " . self::SQL_HTML_BATCH_NUMBER . ", ' (', SUM(sl.quantity), ' units)<br/><b>Exp#:</b> ', DATE_FORMAT(b.expired, '%D %b %Y')) batch_number, IFNULL(ss.next_day, false) as next_day
                       {$prioritySQL['row']}
                FROM SKU_locations sl
                    JOIN locations l ON l.id = sl.location_id AND l.warehouse = $warehouse_id $mezzanineSQL
                    JOIN warehouses w ON l.warehouse = w.id
                    JOIN orders o ON sl.order_id = o.id $where_shipping_services
                    JOIN SKUs s ON s.id = sl.SKU_id
                    LEFT JOIN users u ON u.cart_id = sl.location_id $whereUser
                    LEFT JOIN users u2 ON  u2.cart_id = sl.location_id
                    LEFT JOIN batches b ON sl.batch_id = b.id
                    $where_services
                WHERE sl.type = '2" . Pick::$mode_val[$mode][0] . "'
                GROUP BY sl.location_id, s.id, sl.type
                ORDER BY {$prioritySQL['sort']} LPAD(l.row, 5, 0), s.id";
        $res = (new SafeMysql())->fetchAll($sql);
        SKUBarcodes::addBarcodesToArray($res);
        foreach ($res as $key => $tmp) {
            if (empty($tmp['login'])) {
                if (!empty($tmp['cart_id'])) {
                    unset($res[$key]);
                    continue;
                } elseif (Location::isUS($tmp['type_id'])) {
                    $usr = Users::getById($tmp['row']);
                    $res[$key]['login'] = "{$usr->getLogin()} ({$usr->getId()})";
                    $res[$key]['cart_id'] = 'VIRTUAL';
                }
            }
//            if (isset($tmp['next_day']))
//                $res[$key]['next_day'] = in_array($tmp['next_day'], ShippingService::NEXT_DAY_SERVICES);
        }
        return $res;
    }

    public static function getSKUsToUnload($location_id, $mode)
    {
        if (!in_array($mode, [Pack::PACKING_SINGLE, Pack::PACKING_LARGE])) {
            throw new InvalidArgumentException('$mode should equal single or large');
        }
        $location_id = (int)$location_id;

        $sql = "SELECT s.id, s.name, s.photo, SUM(sl.quantity) quantity, sl.order_id, sl.batch_id,
                       CONCAT('<b>Batch#:</b> ', " . self::SQL_HTML_BATCH_NUMBER . ", '<br/><b>Exp#:</b> ', DATE_FORMAT(b.expired, '%D %b %Y')) batch_number
                FROM SKU_locations sl
                    JOIN SKUs s ON s.id = sl.SKU_id
                    LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
                WHERE sl.type = '31' AND sl.location_id = $location_id
                    GROUP BY s.id, sl.batch_id, sl.order_id
                    ORDER BY s.id, b.expired";

        $SKUs = (new SafeMysql())->fetchAll($sql);
        SKUBarcodes::addBarcodesToArray($SKUs);
        $orders = [];
        foreach ($SKUs as $SKU) {
            $id = (!empty($SKU['location_id']) ? $SKU['location_id'] : '.') . '_' . $SKU['id'] . '_' . (!empty($SKU['batch_id']) ? $SKU['batch_id'] : '.');
            if (empty($orders[$id . '_'][$SKU['order_id']]))
                $orders[$id][$SKU['order_id']] = $SKU['quantity'];
            else
                $orders[$id][$SKU['order_id']] += $SKU['quantity'];
        }
        $filtered_orders = [];
        foreach ($SKUs as $SKU) {
            $id = (!empty($SKU['location_id']) ? $SKU['location_id'] : '.') . '_' . $SKU['id'] . '_' . (!empty($SKU['batch_id']) ? $SKU['batch_id'] : '.');
            if ($mode == Pack::PACKING_LARGE && self::isOrderLarge($SKU['order_id'])) {
                $filtered_orders[$id][$SKU['order_id']] = $orders[$id][$SKU['order_id']];
            } elseif ($mode == Pack::PACKING_SINGLE && $orders[$id][$SKU['order_id']] == 1) {
                $filtered_orders[$id][$SKU['order_id']] = 1;
            }
        }
        return $filtered_orders;
    }

    public static function updateDH($warehouse)
    {
        $locST = Locations::getByDiscrepancyHoldSTLocation($warehouse);
        $locP = Locations::getByDiscrepancyHoldPLocation($warehouse);
        $locM = Locations::getByDiscrepancyHoldMLocation($warehouse);
        if (count($locST) != 1 || count($locM) != 1 || count($locP) != 1) {
            echo "Invalid " . Location::DH . " locations\n";
            return;
        }
        $whereLoc = "JOIN locations ON location_id = id AND warehouse = $warehouse";
        $sql = "SELECT SKU_id, SUM(summa) qty, SUM(num) nums, type
                FROM (SELECT SKU_id, SUM(quantity) summa, COUNT(*) num, 'P' type
                      FROM SKU_locations
                      $whereLoc  
                      WHERE location_id IN ({$locST[0]->getId()}, {$locP[0]->getId()}) 
                      GROUP BY SKU_id
                      UNION
                      SELECT SKU_id, -SUM(quantity) summa, COUNT(*) num, 'M' type
                      FROM SKU_locations
                      $whereLoc  
                      WHERE location_id = {$locM[0]->getId()}
                      GROUP BY SKU_id) dh
                GROUP BY SKU_id, type";
        $SKUs = (new SafeMysql())->fetchAll($sql);
        if (empty($SKUs))
            return;
        $res = [];
        foreach ($SKUs as $SKU)
            $res[$SKU['SKU_id']][$SKU['type']] = ['nums' => $SKU['nums'], 'qty' => $SKU['qty']];
        $del_SKUs = [];
        foreach ($res as $SKU_id => $DH_info)
            if (count($DH_info) == 1) {
                $sign = !empty($DH_info['M']) ? 'M' : 'P';
                if ($DH_info[$sign]['nums'] == 1)
                    if ($sign == 'M' && $DH_info[$sign]['qty'] < 0 || $sign == 'P' && $DH_info[$sign]['qty'] > 0)
                        continue;
                $del_SKUs[$SKU_id] = $DH_info[$sign]['qty'];
            } else
                $del_SKUs[$SKU_id] = $DH_info['M']['qty'] + $DH_info['P']['qty'];
        if (empty($del_SKUs))
            return;
        $SKU_ids_list = array_map('intval', array_keys($del_SKUs));
        $locs_list = [$locST[0]->getId(), $locP[0]->getId(), $locM[0]->getId()];
        $values = [];
        $params = [];
        $i = 0;
        foreach ($del_SKUs as $SKU_id => $qty)
            if (!empty($qty)) {
                $values[] = "(:SKU_id_$i, :location_id_$i, :quantity_$i, NULL, 1, '2', NULL)";
                $params["SKU_id_$i"] = (int)$SKU_id;
                $params["location_id_$i"] = $qty < 0 ? $locM[0]->getId() : $locP[0]->getId();
                $params["quantity_$i"] = abs($qty);
                $i++;
            }
        $num = 0;
        $mysql = new SafeMysql();
        while (true) {
            $mysql->beginTransaction();
            try {
                $mysql->execute("DELETE FROM SKU_locations WHERE SKU_id IN (:SKU_ids) AND location_id IN (:locs)", [
                    'SKU_ids' => $SKU_ids_list,
                    'locs' => $locs_list,
                ]);
                if (!empty($values))
                    $mysql->execute("INSERT INTO SKU_locations (SKU_id, location_id, quantity, order_id, type, reason, batch_id) VALUES " . implode(',', $values), $params);
            } catch (Exception $e) {
                $mysql->rollback();
                if ($e instanceof DBException && $e->isDeadlock($num++))
                    continue;
                Debug::sendDebugInfo('Update ' . Location::DH, var_export([$e->getCode(), $e->getMessage()], 1));
                return;
            }
            $mysql->commit();
            break;
        }
    }

//    private static function getIndexLocation($loc_id, $locs)
//    {
//        foreach ($locs as $key => $loc) {
//            list($id, , ) = explode('_', $key);
//            if ($id == $loc_id)
//                return $key;
//        }
//        return -1;
//    }

    public static function getLeftByOrder($warehouse_id, $mode, $user_id = null, $mezzanine = null, $services = 0, $client = -1)
    {
        list($tmp_SKUs, $all_SKUs) =
            $mode === 'single' ? self::getLeftBySingleOrder($warehouse_id, $mezzanine, $services, $client)
                               : ($mode === 'multi' ? self::getLeftByMultiOrder($warehouse_id, $user_id, $mezzanine, $services, $client)
                                                    : ($mode === 'large' ? self::getLeftByLargeOrder($warehouse_id, $mezzanine, $services, $client)
                                                                        : [[], []]));
        $SKUs = [];
        if (count($tmp_SKUs)) {
            $locs = Arr::columnAssoc($tmp_SKUs,'type_id');
            $locsPF = [];
            foreach ($locs as $key => $loc)
                if (Location::isPF($loc))
                    $locsPF[$key] = $loc;
            foreach ($tmp_SKUs as $loc_id => $SKU) {
                if (!empty($SKU['priority']) && $SKU['priority'] > 0) {
                    $SKUs[] = $SKU;
                    unset($tmp_SKUs[$loc_id]);
                }
            }
            // Todo Path!!!
//            $ids = array_map(function($loc_id) use ($locs) {
//                return self::getIndexLocation($loc_id, $locs);
//            }, SKUPreffLocs::reorder(array_keys($locsPF)));
//            foreach ($ids as $loc_id) {
//                if (array_key_exists($loc_id, $tmp_SKUs)) {
//                    $SKUs[] = $tmp_SKUs[$loc_id];
//                }
//                unset($tmp_SKUs[$loc_id]);
//            }
            foreach ($tmp_SKUs as $SKU)
                $SKUs[] = $SKU;
            array_walk($SKUs, function (&$SKU) {
                $SKU['priority'] = !empty($SKU['priority']) && $SKU['priority'] > 0;
//                $SKU['next_day'] = in_array($SKU['next_day'], ShippingService::NEXT_DAY_SERVICES);
            });
        }
        return [$SKUs, $all_SKUs];
    }

    private static function prepareOrderSKUs($locs)
    {
        $res1 = [];
        $res2 = [];
        foreach ($locs as $key => $loc) {
            $tmp = $loc['location_id'] . '_' . $loc['id'] . '_' . (empty($loc['batch_id']) ? '.' : $loc['batch_id']);
            if (empty($res2[$tmp][$loc['order_id']]))
                $res2[$tmp][$loc['order_id']] = $loc['quantity'];
            else
                $res2[$tmp][$loc['order_id']] += $loc['quantity'];
            if (!empty($loc['picking']))
                $loc['picking'] = [$key => "Order #{$loc['order_id']} {$loc['quantity']} unit(s): {$loc['picking']}"];
            unset($locs[$key]['order_id']);
            if (empty($res1[$tmp]))
                $res1[$tmp] = $loc;
            else {
                if (!empty($loc['picking']))
                    $res1[$tmp]['picking'] = array_merge($res1[$tmp]['picking'] ?? [], $loc['picking']);
                $res1[$tmp]['quantity'] += $loc['quantity'];
                if (!empty($loc['priority']))
                    $res1[$tmp]['priority'] = $res1[$tmp]['priority'] > $loc['priority']
                        ? $loc['priority']
                        : $res1[$tmp]['priority'];
            }
            if (!empty($loc['batch_id'])) {
                if (empty($res1[$tmp]['batches'][$loc['batch_id']]))
                    $res1[$tmp]['batches'][$loc['batch_id']] = [$loc['number'], $loc['quantity'], $loc['expired']];
                else
                    $res1[$tmp]['batches'][$loc['batch_id']][1] += $loc['quantity'];
            }
        }
        foreach ($res1 as $key => &$loc) {
            unset($loc['number'], $loc['expired']);
            $loc['batches'] = array_map(function ($el) {
                return '<b>Batch#:</b> ' . View::toHtml($el[0]) . " ({$el[1]} units)<br/><b>Exp#:</b> {$el[2]}";
            }, empty($loc['batches']) ? [] : $loc['batches']);
        } unset($loc);
        return [$res1, $res2];
    }

    private static function getOrderSKUs($warehouse_id, $types, $priority = false, $mezzanine = null, $services = 0, $client = -1)
    {
        $warehouse_id = (int) $warehouse_id;
        $types = implode(',', $types);
        $prioritySQL = [
          'row' => $priority ? ', o.priority': '',
          'sort' => $priority ? 'o.priority = 0, o.priority ASC,' : ''
        ];
        $mezzanineSQL = '';
        if (!is_null($mezzanine))
            $mezzanineSQL = ' AND l.mezzanine = ' . (int)(bool)$mezzanine;
        $shipping_services = 'JOIN shipping_services ss ON o.shipping_services_id = ss.id';
        $is_customer = false;
        $where_services = $where_shipping_services = $where_client = $join_client = '';
        $params = [];
        if ($services != -1) {
            $service_ids = ShippingServices::getSelectedServicesIds($services);
            $where_shipping_services = 'AND o.shipping_services_id IN (' . implode(',', $service_ids) . ')';
            $pos = array_search(0, $service_ids);
            if ($pos !== false) {
                unset($service_ids[$pos]);
                $is_customer = true;
            }
            $where_services = empty($service_ids)
                ? $shipping_services
                : ("$shipping_services AND ss.id IN (" . implode(',', $service_ids) . ')');
        }
        if ($client != -1) {
            $join_client = "JOIN suppliers sup ON sup.id = s.supplier_id";
            if (is_array($client)) {
                $where_client = " AND sup.id IN (:client)";
                $params['client'] = array_map('intval', $client);
            } else {
                $where_client = " AND sup.id = :client";
                $params['client'] = (int)$client;
            }
        }
        if (empty($where_services))
            $where_services = "LEFT $shipping_services";
        if ($is_customer)
            $where_services = "LEFT $where_services";
        $sql = "SELECT sl.location_id,
                       IF(w.label = '' OR w.label IS NULL, l.warehouse, w.label) warehouse,
                       l.type_id, l.zone, l.`column`, l.row, l.mezzanine,
                       s.id, s.name, s.photo, s.supplier_id,
                       IF(s.state = 0, true, false) as locked,
                       sl.order_id, sl.quantity,
                       o.despatch_date, o.picking, o.shipping_services_id,
                       b.id batch_id, b.number, DATE_FORMAT(b.expired, '%D %b %Y') expired, IFNULL(ss.next_day, false) as next_day
                       {$prioritySQL['row']}
                FROM orders o
                    JOIN SKU_locations sl ON sl.order_id = o.id AND sl.type = 1 AND sl.reason = '1'
                    JOIN locations l ON l.id = sl.location_id AND l.warehouse = $warehouse_id $mezzanineSQL
                    JOIN warehouses w ON l.warehouse = w.id
                    JOIN SKUs s ON s.id = sl.SKU_id
                    $join_client
                    LEFT JOIN batches b ON sl.batch_id = b.id
                    $where_services
                WHERE o.status IN ($types) AND o.warehouse_id = $warehouse_id $where_shipping_services$where_client
                ORDER BY {$prioritySQL['sort']} l.type_id, LPAD(l.zone, 5, ' '), LPAD(l.`column`, 5, '0'), LPAD(l.row, 5, ' '), s.id, sl.quantity DESC";
        $items = (new SafeMysql())->fetchAll($sql, $params);
        SKUBarcodes::addBarcodesToArray($items);
        return $items;
    }

    public static function getLeftBySingleOrder($warehouse_id, $mezzanine = null, $services = 0, $client = -1)
    {
        return self::prepareOrderSKUs(self::getOrderSKUs($warehouse_id, [Order::AWAITING_SINGLE_PICK_NUM, Order::SINGLE_PICKING_NUM], false, $mezzanine, $services, $client));
    }

    public static function getLeftByMultiOrder($warehouse_id, $user_id, $mezzanine = null, $services = 0, $client = -1)
    {
        return self::prepareOrderSKUs(self::getOrderSKUs($warehouse_id, [Order::AWAITING_PICK_NUM, Order::PICKING_NUM], true, $mezzanine, $services, $client));
    }

    public static function getLeftByLargeOrder($warehouse_id, $mezzanine = null, $services = 0, $client = -1)
    {
        return self::prepareOrderSKUs(self::getOrderSKUs($warehouse_id, [Order::AWAITING_LARGE_PICK_NUM, Order::LARGE_PICKING_NUM], false, $mezzanine, $services, $client));
    }

    public static function getByParams($SKU_ids, $order_id)
    {
        $whereSKUs = implode(',', array_map('intval', $SKU_ids));
        $order_id = (int) $order_id;
        $sql = "SELECT sl.SKU_id,
                       sl.location_id,
                       sl.quantity,
                       sl.order_id,
                       sl.type,
                       sl.reason,
                       sl.batch_id,
                       CONCAT(IF(w.label = '' OR w.label IS NULL, l.warehouse, w.label), '-', l.type_id, '-', l.`zone`, '-', l.`column`, '-', l.row, IF(sl.order_id IS NULL, ' (reserved)', '')) location_name,
                       l.type_id,
                       CONCAT('<b>Batch#:</b> ', " . self::SQL_HTML_BATCH_NUMBER . ", ' (', sl.quantity, ' units)<br/><b>Exp#:</b> ', DATE_FORMAT(b.expired, '%D %b %Y')) batch_number
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id
                    JOIN warehouses w ON l.warehouse = w.id
                    LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
                WHERE sl.SKU_id IN ($whereSKUs) AND (sl.order_id = $order_id OR (sl.type = 1 AND SUBSTRING_INDEX(sl.reason, '_', -1) = $order_id))";
        $tmp = (new SafeMysql())->fetchAll($sql);
        $result = [];
        foreach ($tmp as $value) {
            if(empty($result[$value['SKU_id']])) {
                $result[$value['SKU_id']] = [];
            }
            $result[$value['SKU_id']][] = $value;
        }
        return $result;
    }

    public static function getNotMapped($SKU_ids)
    {
        if (empty($SKU_ids) || !is_array($SKU_ids))
            return null;
        $res = (new SafeMysql())->fetchAll("
            SELECT SKU_id, COUNT(*) nums
            FROM SKU_locations
            WHERE SKU_id IN (:SKU_ids)
            GROUP BY SKU_id", ['SKU_ids' => $SKU_ids]);
        return Arr::map($res, 'SKU_id', 'nums');
    }

    public static function getQuantityInStockLocations($SKU_ids)
    {
        if (empty($SKU_ids) || !is_array($SKU_ids))
            return null;
        $types = array_merge(Location::NOT_COUNTED_TYPES, Location::CART_TYPES);
        $res = (new SafeMysql())->fetchAll("
            SELECT sl.SKU_id, COUNT(*) nums
            FROM SKU_locations sl
                JOIN locations l ON sl.location_id = l.id
            WHERE SKU_id IN (:SKU_ids)
                AND l.type_id NOT IN (:types)
            GROUP BY SKU_id", [
                'SKU_ids' => $SKU_ids,
                'types' => $types,
            ]);
        return Arr::map($res, 'SKU_id', 'nums');
    }

    public static function getCountSKUinDH($warehouse_id, $SKU_ids)
    {
        if (empty($SKU_ids) || !is_array($SKU_ids))
            return null;
        $warehouse_id = (int)$warehouse_id;
        $res = (new SafeMysql())->fetchAll("
            SELECT SKU_id, COUNT(*) nums
            FROM SKU_locations
                JOIN locations ON location_id = id AND type_id = :dh_type AND warehouse = :warehouse_id
            WHERE SKU_id IN (:SKU_ids)
            GROUP BY SKU_id", ['dh_type' => Location::DH, 'warehouse_id' => $warehouse_id, 'SKU_ids' => $SKU_ids]);
        return Arr::map($res, 'SKU_id', 'nums');
    }

    public static function clearSKUinDH($warehouse_id, $SKU_id)
    {
        $warehouse_id = (int)$warehouse_id;
        $SKU_id = (int)$SKU_id;
        $sql = "SELECT location_id `From`, SUM(IF(`row` = 'M', -quantity, quantity)) Units
                FROM SKU_locations
                    JOIN locations ON location_id = id AND type_id = :dh_type AND warehouse = :warehouse_id
                WHERE SKU_id = :SKU_id
                GROUP BY location_id";
        $mysql = new SafeMysql();
        $locs = $mysql->fetchAll($sql, ['dh_type' => Location::DH, 'warehouse_id' => $warehouse_id, 'SKU_id' => $SKU_id]);
        if (empty($locs))
            return null;
        $mysql->execute("
            DELETE FROM SKU_locations
            WHERE SKU_id = :SKU_id AND
                  location_id IN (:locs_ids)
        ", [
            'SKU_id' => $SKU_id,
            'locs_ids' => array_map('intval', Arr::column($locs, 'From')),
        ]);
        return $locs;
    }

    public static function getBatches($warehouse_id, $SKU_ids, $group_location = false, array $exclude = Location::NOT_COUNTED_TYPES): array
    {
        $warehouse_id = (int)$warehouse_id;
        if (is_array($SKU_ids))
            $whereSKUs = 'IN (' . implode(',', array_map('intval', $SKU_ids)) . ')';
        else
            $whereSKUs = '= ' . (int) $SKU_ids;
        $whereLocations = LocationsBackend::getExcludeLocationsPartialQuery($warehouse_id, 'sl.', $exclude);
        $group_locations = $group_location ? ', sl.location_id' : '';
        $sql = "SELECT sl.SKU_id, b.number batch_number, b.expired, SUM(sl.quantity) quantity $group_locations
                FROM SKU_locations sl
                    JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
                    $whereLocations
                WHERE sl.SKU_id $whereSKUs
                GROUP BY sl.SKU_id, sl.batch_id $group_locations";
        $batches = (new SafeMysql())->fetchAll($sql);
        $data = [];
        foreach ($batches as $batch) {
            if(empty($data[$batch['SKU_id']]))
                $data[$batch['SKU_id']] = [];
            $data[$batch['SKU_id']][] = array_slice($batch, 1);
        }
        return $data;
    }

    public static function getBatchesInLocation($SKU_id, $location_id)
    {
        $SKU_id = (int) $SKU_id;
        $location_id = (int) $location_id;
        $sql = "SELECT DISTINCT sl.batch_id, b.number, b.expired
                FROM SKU_locations sl
                    LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
                WHERE sl.SKU_id = :SKU_id AND sl.location_id = :location_id";
        return (new SafeMysql())->fetchAll($sql, ['SKU_id' => $SKU_id, 'location_id' => $location_id]);
    }

    public static function hasSkuWithEmptyMaxCapacity($warehouse_id, Location $location_obj)
    {
        $sql = "SELECT s.max_capacity, s.id
                FROM SKU_locations sl
                    JOIN SKUs s ON sl.SKU_id = s.id
                    JOIN locations l ON l.id = sl.location_id AND l.warehouse = :warehouse_id
                WHERE sl.location_id = :location_id and s.max_capacity is null
                GROUP BY sl.SKU_id";
        $SKUs = (new SafeMysql())->fetchAll($sql, ['warehouse_id' => (int)$warehouse_id, 'location_id' => (int)$location_obj->getId()]);
        return (bool) count($SKUs);
    }

    public static function getCountFreeInLocation($warehouse_id, SKU $SKU_obj, Location $location_obj, $exist = false)
    {
        if (empty($SKU_obj) || empty($location_obj) || empty($SKU_obj->getMaxCapacity()))
            return false;
        $sql = "SELECT sl.SKU_id, SUM(sl.quantity) qty, s.max_capacity, l.size_id
                FROM SKU_locations sl
                    JOIN SKUs s ON sl.SKU_id = s.id
                    JOIN suppliers sup ON s.supplier_id = sup.id
                    JOIN locations l ON l.id = sl.location_id AND l.warehouse = :warehouse_id
                WHERE sl.location_id = :location_id
                GROUP BY sl.SKU_id";
        $SKUs = (new SafeMysql())->fetchAll($sql, ['warehouse_id' => (int)$warehouse_id, 'location_id' => (int)$location_obj->getId()]);
        $sum = 0;
        foreach ($SKUs as $SKU) {
            $max_capacity = json_decode($SKU['max_capacity'], 1);
            if (empty($max_capacity[$SKU['size_id']])) {
                $sum = 1.1;
                break;
            }
            $sum += $SKU['qty'] / $max_capacity[$SKU['size_id']];
        }
        if ($sum > 1)
            return false;
        if (empty($SKU_obj->getMaxCapacity()[$location_obj->getSizeId()]))
            return false;
        $max = $SKU_obj->getMaxCapacity()[$location_obj->getSizeId()];
        return round($max*(1 - $sum), 0) . ($exist ? "_$sum" : '');
    }

    private static function prepareParams(&$SKU_id, &$location_id, &$quantity, &$order_id, &$type, &$reason, &$batch_id)
    {
        if (empty($SKU_id) || empty($location_id) || empty($quantity) || empty($type) || empty($reason))
            return false;
        $SKU_id = (int) $SKU_id;
        $location_id = (int) $location_id;
        $quantity = (int) $quantity;
        $order_id = (int) $order_id;
        $type = (int) $type;
        $reason = addslashes($reason);
        $batch_id = (int) $batch_id;
        return true;
    }

    public static function getQuantityExist($warehouse_id, $SKU_id, $location_id, $order_id, $type, $reason, $batch_id, $reserved = false)
    {
        $quantity = -1;
        if (!self::prepareParams($SKU_id, $location_id, $quantity, $order_id, $type, $reason, $batch_id))
            return false;
        $whereLocation = $location_id != -1 ? "location_id = $location_id AND" : '';
        if ($order_id == -1)
            $whereOrder = '';
        else {
            $whereOrder = 'order_id ' . (!empty($order_id) ? "= $order_id" : 'IS NULL') . ' AND ';
            $whereOrder .= 'reason = ' . ($reserved ? $reason : "'$reason'") . ' AND';
        }
        $whereBatch = $batch_id == -1 ? '' : (' AND batch_id ' . (!empty($batch_id) ? "= $batch_id" : 'IS NULL'));
        $exclude_locations = '';
        if ($location_id == -1 && $type == 1 && $reason == '2' && !$reserved) {
            $exclude_locations = [];
            $loc_can = Locations::getByCancelLocation($warehouse_id);
            if (!empty($loc_can))
                $exclude_locations[] = 'id <> ' . $loc_can[0]->getId();
            $loc_exp = Locations::getByExpiredLocation($warehouse_id);
            if (!empty($loc_exp))
                $exclude_locations[] = 'id <> ' . $loc_exp[0]->getId();
            $exclude_locations[] = "type_id NOT IN (:not_counted_types)";
            $exclude_locations = ' AND ' . implode(' AND ', $exclude_locations);
        }
        $params = ['warehouse_id' => (int)$warehouse_id, 'SKU_id' => (int)$SKU_id, 'type' => (int)$type];
        if (strpos($exclude_locations, ':not_counted_types') !== false)
            $params['not_counted_types'] = Location::NOT_COUNTED_TYPES;
        return (int)(new SafeMysql())->fetchScalar("
            SELECT SUM(quantity)
            FROM SKU_locations
                JOIN locations ON location_id = id AND warehouse = :warehouse_id $exclude_locations
            WHERE SKU_id = :SKU_id AND
                  $whereLocation
                  $whereOrder
                  type = :type
                  $whereBatch", $params);
    }

    public static function addSKU2Location($warehouse_id, $SKU_id, $location_id, $quantity, $order_id, $type, $reason, $batch_id)
    {
        if (!self::prepareParams($SKU_id, $location_id, $quantity, $order_id, $type, $reason, $batch_id))
            return false;
        $qty = self::getQuantityExist($warehouse_id, $SKU_id, $location_id, -1, $type, $reason, $batch_id);
        if ($qty < 0)
            return false;
        $loc_obj = Locations::getById($location_id);
        $size_obj = Sizes::getById($loc_obj->getSizeId());
        if ($size_obj->getName() != 'UNLIMITEDSIZE' && !Location::isCart($loc_obj->getTypeId())) {
            $max = self::getCountFreeInLocation($warehouse_id, SKUs::getById($SKU_id), $loc_obj);
            if (!$max || $quantity > $max)
                return false;
            $q = min($quantity, $max);
        } else
            $q = $quantity;
        $whereOrder = !empty($order_id) ? '= :order_id' : 'IS NULL';
        $whereBatch = !empty($batch_id) ? '= :batch_id' : 'IS NULL';
        $params = ['SKU_id' => $SKU_id, 'location_id' => $location_id, 'type' => $type];
        if (!empty($order_id))
            $params['order_id'] = $order_id;
        if (!empty($batch_id))
            $params['batch_id'] = $batch_id;
        $mysql = new SafeMysql();
        $qty = $mysql->fetchScalar("
            SELECT quantity
            FROM SKU_locations
            WHERE SKU_id = :SKU_id AND
                  location_id = :location_id AND
                  order_id $whereOrder AND
                  type = :type AND
                  reason = '$reason' AND
                  batch_id $whereBatch", $params);
        if ($qty) {
            $params['q'] = $q;
            $mysql->execute("UPDATE SKU_locations
                                    SET quantity = quantity + :q
                                    WHERE SKU_id = :SKU_id AND
                                    location_id = :location_id AND
                                    order_id $whereOrder AND
                                    type = :type AND
                                    reason = '$reason' AND
                                    batch_id $whereBatch", $params);
        } else {
            $mysql->execute("INSERT INTO SKU_locations (SKU_id, location_id, quantity, order_id, type, reason, batch_id)
                            VALUES (:SKU_id, :location_id, :q, :order_id, :type, '$reason', :batch_id)", [
                'SKU_id' => $SKU_id,
                'location_id' => $location_id,
                'q' => $q,
                'order_id' => $order_id ?: null,
                'type' => $type,
                'batch_id' => $batch_id ?: null,
            ]);
        }
        return $q;
    }

    public static function getLocations4ReservedMass(int $warehouse_id, array $SKU_ids): array
    {
        if (empty($SKU_ids))
            return [];
        $sql = "SELECT spl.SKU_id, sl.location_id, l.type_id, sl.quantity, sl.batch_id, b.expired, spl.priority
                FROM SKU_preff_locs spl
                    JOIN locations l ON spl.location_id = l.id AND l.warehouse = :warehouse_id and l.is_non_picking = 0
                    JOIN SKU_locations sl ON spl.location_id = sl.location_id AND
                                             spl.SKU_id = sl.SKU_id AND
                                             sl.order_id IS NULL AND
                                             sl.type = 1 AND
                                             sl.reason = '2'
                    LEFT JOIN batches b ON sl.batch_id = b.id
                WHERE spl.SKU_id IN (:SKU_ids);";
        return (new SafeMysql())->fetchAll($sql, ['warehouse_id' => $warehouse_id, 'SKU_ids' => $SKU_ids]);
    }

    public static function getLocationsBySKUAndBatch($warehouse_id, $SKU_id, $batch_id, $excludeBatch = false)
    {
        $warehouse_id = (int)$warehouse_id;
        $SKU_id = (int)$SKU_id;
        $batch_id = (int)$batch_id;
        $sql = "SELECT DISTINCT location_id
                FROM SKU_locations
                    JOIN locations ON location_id = id AND warehouse = :warehouse_id
                WHERE SKU_id = :SKU_id AND batch_id " . ($excludeBatch ? '!' : '') . "= :batch_id AND quantity > 0";
        return (new SafeMysql())->fetchArrayOneField($sql, ['warehouse_id' => $warehouse_id, 'SKU_id' => $SKU_id, 'batch_id' => $batch_id]);
    }

    public static function getBatchByLocations($SKU_id, $loc_ids)
    {
        $SKU_id = (int) $SKU_id;
        if (is_array($loc_ids))
            $whereLoc = 'IN (' . implode(',', array_map('intval', $loc_ids)) . ')';
        else
            $whereLoc = '= (' . (int)$loc_ids;
        $sql = "SELECT sl.batch_id
                FROM SKU_locations sl
                    JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
                WHERE sl.SKU_id = $SKU_id AND sl.location_id $whereLoc
                ORDER BY IFNULL(b.expired, NOW())
                LIMIT 1";
        return (new SafeMysql())->fetchScalar($sql);
    }

    public static function getSKUs4Replenish($warehouse_id, $SKUs)
    {
        if (empty($SKUs))
            $whereSKUs = '';
        elseif (is_array($SKUs))
            $whereSKUs =  'AND sl.SKU_id NOT IN (' . implode(',', $SKUs) . ')';
        else
            $whereSKUs = 'AND sl.SKU_id = ' . (int) $SKUs;
        $sql = "SELECT sl.SKU_id, sl.location_id, IFNULL(SUM(sl.quantity), 0) quantity, SUBSTRING_INDEX(sl.reason, '_', -1) order_id, sl.batch_id, b.number, b.expired
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id AND l.type_id = '" . Location::ST . "' AND l.warehouse = $warehouse_id
                    JOIN orders o ON o.id = SUBSTRING_INDEX(sl.reason, '_', -1) AND o.status NOT IN ('" . Order::BACKORDER_NUM . "')
                    LEFT JOIN batches b ON sl.batch_id = b.id
                WHERE sl.order_id IS NULL AND
                      sl.type = 1 AND
                      POSITION('2_' IN sl.reason) = 1 $whereSKUs
                GROUP BY sl.SKU_id, sl.location_id, sl.order_id, sl.batch_id";
        $tmp = (new SafeMysql())->fetchAll($sql);
        $SKUs = [];
        foreach ($tmp as $SKU)
            $SKUs[$SKU['SKU_id']][] = array_slice($SKU, 1);
        return $SKUs;
    }

    public static function moveToReserveSKU($warehouse_id, $order_id, $SKU, $types = [Location::PF])
    {
        $order_id = (int) $order_id;
        $SKU_id = $SKU->getId();
        $types_arr = is_array($types) ? $types : [$types];
        $mysql = new SafeMysql();
        $params = ['types' => $types_arr, 'warehouse_id' => (int)$warehouse_id, 'SKU_id' => (int)$SKU_id, 'order_id' => $order_id];
        $sql = "SELECT CONCAT(location_id, '_', IFNULL(batch_id, '.')) id, quantity
                FROM SKU_locations
                    JOIN locations ON location_id = id AND type_id IN (:types) AND warehouse = :warehouse_id
                WHERE SKU_id = :SKU_id AND
                      order_id = :order_id AND
                      type = 1 AND
                      reason = '1'";
        $locs_to = $mysql->fetchAssocWithKey($sql, $params, 'id');
        $sql = "SELECT CONCAT(location_id, '_', IFNULL(batch_id, '.')) id, quantity
                FROM SKU_locations
                    JOIN locations ON location_id = id AND type_id IN (:types) AND warehouse = :warehouse_id
                WHERE SKU_id = :SKU_id AND
                      order_id IS NULL AND
                      type = 1 AND
                      reason = :reason";
        $locs_from = $mysql->fetchAssocWithKey($sql, array_merge($params, ['reason' => '2_' . $order_id]), 'id');
        $sum = $sum_backup = array_sum(Arr::column($locs_to, 'quantity'));
        if (empty($locs_from))
            return $sum;
        $label = Warehouses::getLabel($warehouse_id);
        $num = 0;
        while (true) {
            $sum = $sum_backup;
            $mysql->beginTransaction();
            try {
                foreach ($locs_to as $loc_batch => $tmp)
                    if (!empty($locs_from[$loc_batch])) {
                        list($location_id, $batch_id) = explode('_', (string)$loc_batch);
                        $batch = $batch_id == '.' ? null : Batches::getById($batch_id);
                        $location = Locations::getById($location_id);
                        $batch_where = $batch_id == '.' ? 'IS NULL' : '= :batch_id';
                        $batch_params = $batch_id == '.' ? [] : ['batch_id' => $batch_id];
                        $sum += $locs_from[$loc_batch]['quantity'];
                        $mysql->execute("UPDATE SKU_locations
                                SET quantity = quantity + :qty
                                WHERE SKU_id = :SKU_id AND
                                      location_id = :location_id AND
                                      order_id = :order_id AND
                                      type = 1 AND
                                      reason = '1' AND
                                      batch_id $batch_where", array_merge([
                            'qty' => $locs_from[$loc_batch]['quantity'],
                            'SKU_id' => $SKU_id,
                            'location_id' => $location_id,
                            'order_id' => $order_id,
                        ], $batch_params));
                        $mysql->execute("DELETE FROM SKU_locations
                                WHERE SKU_id = :SKU_id AND
                                      location_id = :location_id AND
                                      order_id IS NULL AND
                                      type = 1 AND
                                      reason = :reason AND
                                      batch_id $batch_where", array_merge([
                            'SKU_id' => $SKU_id,
                            'location_id' => $location_id,
                            'reason' => '2_' . $order_id,
                        ], $batch_params));
                        OrderHistories::fixedSKU($order_id, $SKU, $batch, $locs_from[$loc_batch]['quantity'], $location->getLocation($label));
                        unset($locs_from[$loc_batch]);
                    }
                foreach ($locs_from as $key => $tmp) {
                    if (empty($tmp['quantity'])) {
                        Debug::sendDebugInfo('MoveToReserveSKU', "Quantity is null :(\n" . json_encode($locs_from));
                        continue;
                    }
                    list($location_id, $batch_id) = explode('_', (string)$key);
                    $batch = $batch_id == '.' ? null : Batches::getById($batch_id);
                    $location = Locations::getById($location_id);
                    $batch_where = $batch_id == '.' ? 'IS NULL' : '= :batch_id';
                    $batch_params = $batch_id == '.' ? [] : ['batch_id' => $batch_id];
                    $q = $mysql->fetchScalar("SELECT 1
                                    FROM SKU_locations
                                    WHERE SKU_id = :SKU_id AND
                                          location_id = :location_id AND
                                          order_id = :order_id AND
                                          type = 1 AND
                                          reason = '1' AND
                                          batch_id $batch_where", array_merge([
                        'SKU_id' => $SKU_id,
                        'location_id' => $location_id,
                        'order_id' => $order_id,
                    ], $batch_params));
                    if ($q) {
                        $mysql->execute("UPDATE SKU_locations
                                SET quantity = quantity + :qty
                                WHERE SKU_id = :SKU_id AND
                                      location_id = :location_id AND
                                      order_id = :order_id AND
                                      type = 1 AND
                                      reason = '1' AND
                                      batch_id $batch_where", array_merge([
                            'qty' => $tmp['quantity'],
                            'SKU_id' => $SKU_id,
                            'location_id' => $location_id,
                            'order_id' => $order_id,
                        ], $batch_params));
                        $mysql->execute("DELETE FROM SKU_locations
                                WHERE SKU_id = :SKU_id AND
                                      location_id = :location_id AND
                                      order_id IS NULL AND
                                      type = 1 AND
                                      reason = :reason AND
                                      batch_id $batch_where", array_merge([
                            'SKU_id' => $SKU_id,
                            'location_id' => $location_id,
                            'reason' => '2_' . $order_id,
                        ], $batch_params));
                    } else
                        $mysql->execute("UPDATE SKU_locations
                                SET order_id = :order_id, reason = '1'
                                WHERE SKU_id = :SKU_id AND
                                      location_id = :location_id AND
                                      order_id IS NULL AND
                                      type = 1 AND
                                      reason = :reason AND
                                      batch_id $batch_where", array_merge([
                            'order_id' => $order_id,
                            'SKU_id' => $SKU_id,
                            'location_id' => $location_id,
                            'reason' => '2_' . $order_id,
                        ], $batch_params));
                    OrderHistories::fixedSKU($order_id, $SKU, $batch, $tmp['quantity'], $location->getLocation($label));
                    $sum += $tmp['quantity'];
                }
            } catch (Exception $e) {
                $mysql->rollback();
                if ($e instanceof DBException && $e->isDeadlock($num++)) {
                    Debug::sendDebugInfo('MoveToReserveSKU!', "Error is fixed! {$e->getMessage()}");
                    continue;
                }
                Debug::sendDebugInfo('MoveToReserveSKU', var_export([$e->getCode(), $e->getMessage()], 1));
                return false;
            }
            $mysql->commit();
            break;
        }
        return $sum;
    }

    public static function fixedSTinPF($warehouse_id, SKU $SKU, $loc_from_id, $loc_to_id, $quantity, $order_id)
    {
        $loc_from_id = (int) $loc_from_id;
        $location = Locations::getById($loc_to_id);
        $SKU_id = (int) $SKU->getId();
        $order_id = (int) $order_id;
        $quantity = (int) $quantity;
        $sql = "SELECT sl.quantity, sl.batch_id 
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id AND l.type_id = '" . Location::ST . "' AND l.warehouse = $warehouse_id
                    LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
                WHERE sl.SKU_id = $SKU_id AND 
                      sl.location_id = $loc_from_id AND 
                      sl.order_id IS NULL AND 
                      sl.type = 1 AND 
                      sl.reason = '2_$order_id'
                ORDER BY IFNULL(b.expired, NOW()), sl.quantity";
        $STs = (new SafeMysql())->fetchAll($sql);
        if (empty($STs))
            return [false, "Source location is empty (SKU #$SKU_id)"];
        $sum = array_sum(Arr::column($STs, 'quantity'));
        if ($sum < $quantity)
            return [false, "Not enough goods in source location (SKU #$SKU_id)"];
        $res = self::reserveInPF($warehouse_id, $SKU, $location, $quantity, $order_id, -1);
        if (!$res[0])
            return $res;
        $qty = $quantity;
        foreach ($STs as $ST) {
            $q = min($qty, $ST['quantity']);
            $res = self::cancelPrereserveInST($warehouse_id, $SKU_id, $loc_from_id, $q, $order_id, $ST['batch_id']);
            if (!$res[0])
                return $res;
            $qty -= $res[1];
            if (!$qty)
                break;
        }
        return [true, $quantity - $qty];
    }

    private static function cancelPrereserveInST($warehouse_id, $SKU_id, $location_id, $quantity, $order_id, $batch_id)
    {
        $SKU_id = (int) $SKU_id;
        $location_id = (int) $location_id;
        $quantity = (int) $quantity;
        $order_id = (int) $order_id;
        $whereBatch = empty($batch_id) ? 'IS NULL' : "= $batch_id";
        $where = "SKU_id = $SKU_id AND location_id = $location_id AND type = 1 AND order_id IS NULL";
        $whereNoOrder = "$where AND reason = '2'";
        $whereOrder = "$where AND reason = '2_$order_id'";
        $sql = "SELECT quantity, batch_id 
                FROM SKU_locations
                    JOIN locations l ON location_id = id AND type_id = '" . Location::ST . "' AND warehouse = $warehouse_id
                WHERE $whereOrder AND batch_id $whereBatch";
        $ST0 = (new SafeMysql())->fetchOne($sql);
        if (empty($ST0))
            return [false, 'Source location without reserve'];
        $sql = "SELECT quantity, batch_id
                FROM SKU_locations
                    JOIN locations l ON location_id = id AND type_id = '" . Location::ST . "' AND warehouse = $warehouse_id
                WHERE $whereNoOrder AND batch_id $whereBatch";
        $ST1 = (new SafeMysql())->fetchOne($sql);
        $q = min($quantity, $ST0['quantity']);
        $batch_param = empty($batch_id) ? [] : ['batch_id' => $batch_id];
        $where_batch_p = empty($batch_id) ? 'IS NULL' : '= :batch_id';
        $no_order_params = array_merge(['SKU_id' => $SKU_id, 'location_id' => $location_id], $batch_param);
        $order_params = array_merge(['SKU_id' => $SKU_id, 'location_id' => $location_id, 'reason_order' => "2_$order_id"], $batch_param);
        $where_no_order_p = "SKU_id = :SKU_id AND location_id = :location_id AND type = 1 AND order_id IS NULL AND reason = '2'";
        $where_order_p = "SKU_id = :SKU_id AND location_id = :location_id AND type = 1 AND order_id IS NULL AND reason = :reason_order";
        $mysql = new SafeMysql();
        if ($quantity >= $ST0['quantity']) {
            if (!empty($ST1)) {
                $mysql->execute("UPDATE SKU_locations
                        SET quantity = quantity + :q
                        WHERE $where_no_order_p AND batch_id $where_batch_p", array_merge($no_order_params, ['q' => $q]));
                $mysql->execute("DELETE FROM SKU_locations
                        WHERE $where_order_p AND batch_id $where_batch_p", $order_params);
            } else
                $mysql->execute("UPDATE SKU_locations
                        SET reason = '2'
                        WHERE $where_order_p AND batch_id $where_batch_p", $order_params);
        } else {
            if (!empty($ST1))
                $mysql->execute("UPDATE SKU_locations
                        SET quantity = quantity + :q
                        WHERE $where_no_order_p AND batch_id $where_batch_p", array_merge($no_order_params, ['q' => $q]));
            else
                $mysql->execute("INSERT INTO SKU_locations (SKU_id, location_id, quantity, order_id, type, reason, batch_id)
                        VALUES (:SKU_id, :location_id, :q, NULL, 1, '2', :batch_id_val)", [
                    'SKU_id' => $SKU_id,
                    'location_id' => $location_id,
                    'q' => $q,
                    'batch_id_val' => empty($batch_id) ? null : $batch_id,
                ]);
            $mysql->execute("UPDATE SKU_locations
                    SET quantity = quantity - :q
                    WHERE $where_order_p AND batch_id $where_batch_p", array_merge($order_params, ['q' => $q]));
        }
        return [true, $q];
    }

    private static function reserveInPF($warehouse_id, SKU $SKU, Location $location, $quantity, $order_id, $batch_id = -1)
    {
        $SKU_id = $SKU->getId();
        $location_id = $location->getId();
        $quantity = (int) $quantity;
        $order_id = (int) $order_id;
        $whereBatch = $batch_id == -1 ? '' : ('AND batch_id ' . (empty($batch_id) ? 'IS NULL' : "= $batch_id"));
        $where = "SKU_id = $SKU_id AND location_id = $location_id AND type = 1";
        $whereOrderNULL = "$where AND order_id IS NULL AND reason = '2'";
        $whereOrder = "$where AND order_id = $order_id AND reason = '1'";
        $sql = "SELECT quantity, batch_id 
                FROM SKU_locations
                    JOIN locations l ON location_id = id AND type_id = '" . Location::PF . "' AND warehouse = $warehouse_id
                WHERE $whereOrderNULL $whereBatch";
        $PFs = (new SafeMysql())->fetchAll($sql);
        if (empty($PFs))
            return [false, 'Destination location is empty'];
        if (count($PFs) != 1)
            return [false, 'In destination location is mixed batches (order is null)'];
        if ($batch_id == -1)
            $batch_id = $PFs[0]['batch_id'];
        $batch = Batches::getById($batch_id);
        $whereBatch = empty($batch_id) ? 'IS NULL' : "= $batch_id";
        $sql = "SELECT quantity, batch_id 
                FROM SKU_locations
                    JOIN locations l ON location_id = id AND type_id = '" . Location::PF . "' AND warehouse = $warehouse_id
                WHERE $whereOrder AND batch_id $whereBatch";
        $PF = (new SafeMysql())->fetchAll($sql);
        if (!empty($PF) && count($PF) != 1)
            return [false, "In destination location is mixed batches (order #{$order_id})"];
        $q = min($quantity, $PFs[0]['quantity']);
        OrderHistories::replenishSKU($order_id, $SKU, $batch, $q, $location->getLocation(Warehouses::getLabel($warehouse_id)));
        $batch_param = empty($batch_id) ? [] : ['batch_id' => $batch_id];
        $where_batch_p = empty($batch_id) ? 'IS NULL' : '= :batch_id';
        $null_params = array_merge(['SKU_id' => $SKU_id, 'location_id' => $location_id], $batch_param);
        $order_params = array_merge(['SKU_id' => $SKU_id, 'location_id' => $location_id, 'order_id' => $order_id], $batch_param);
        $where_order_null_p = "SKU_id = :SKU_id AND location_id = :location_id AND type = 1 AND order_id IS NULL AND reason = '2'";
        $where_order_p = "SKU_id = :SKU_id AND location_id = :location_id AND type = 1 AND order_id = :order_id AND reason = '1'";
        $mysql = new SafeMysql();
        if ($quantity >= $PFs[0]['quantity']) {
            if (!empty($PF)) {
                $mysql->execute("UPDATE SKU_locations
                        SET quantity = quantity + :q
                        WHERE $where_order_p AND batch_id $where_batch_p", array_merge($order_params, ['q' => $q]));
                $mysql->execute("DELETE FROM SKU_locations
                        WHERE $where_order_null_p AND batch_id $where_batch_p", $null_params);
            } else
                $mysql->execute("UPDATE SKU_locations
                        SET order_id = :order_id, reason = '1'
                        WHERE $where_order_null_p AND batch_id $where_batch_p", array_merge($null_params, ['order_id' => $order_id]));
        } else {
            if (!empty($PF))
                $mysql->execute("UPDATE SKU_locations
                        SET quantity = quantity + :q
                        WHERE $where_order_p AND batch_id $where_batch_p", array_merge($order_params, ['q' => $q]));
            else
                $mysql->execute("INSERT INTO SKU_locations (SKU_id, location_id, quantity, order_id, type, reason, batch_id)
                        VALUES (:SKU_id, :location_id, :q, :order_id, 1, '1', :batch_id_val)", [
                    'SKU_id' => $SKU_id,
                    'location_id' => $location_id,
                    'q' => $q,
                    'order_id' => $order_id,
                    'batch_id_val' => empty($batch_id) ? null : $batch_id,
                ]);
            $mysql->execute("UPDATE SKU_locations
                    SET quantity = quantity - :q
                    WHERE $where_order_null_p AND batch_id $where_batch_p", array_merge($null_params, ['q' => $q]));
        }
        return [true, $q];
    }

    public static function isLarge($warehouse_id, $SKUs, $largeSKUs): bool
    {
        if (Arr::includeAll(array_keys($SKUs), $largeSKUs))
            return true;
        foreach ($SKUs as $SKU_id => $qty)
            if (SKUPreffLocs::existST($warehouse_id, $SKU_id) && ($qty > SKUPreffLocs::getMaxCapacityForSKU($warehouse_id, $SKU_id, null, [Location::PF])))
                return true;
        return false;
    }

    /**
     * @throws Exception
     */
    public static function isOrderLarge($order_id): bool
    {
        $order = Orders::getById($order_id);
        if (empty($order))
            throw new Exception('Order #' . $order_id . ' not found');
        $SKUs = OrderSKUs::getSKUsByOrder($order_id);
        foreach ($SKUs as $SKU_id => $SKU) {
            $pref_locs = SKUPreffLocs::getSKU($order->getWarehouseId(), $SKU_id);
            if (empty($pref_locs[Location::PF]) && !empty($pref_locs[Location::ST]))
                return true;

            if (!empty($pref_locs[Location::PF])
                    && !empty($pref_locs[Location::ST])
                        && $SKU['quantity'] > SKUPreffLocs::getMaxCapacityForSKU($order->getWarehouseId(), $SKU_id, null, [Location::PF])
            )
                return true;
        }
        return false;
    }

    public static function resolveSKULocation($loc_from_id, $loc_to_id, $SKU_id, $order_id, $batch_id)
    {
        $loc_from_id = (int)$loc_from_id;
        $loc_to_id = (int)$loc_to_id;
        $SKU_id = (int)$SKU_id;
        $order_id = (int)$order_id;
        $batch_id = (int)$batch_id;
        $SKU_locations = (new SafeMysql())->fetchAssocWithKey("SELECT * FROM SKU_locations
                                                            WHERE location_id IN (:loc_ids)
                                                                AND SKU_id = :SKU_id
                                                                AND order_id = :order_id
                                                                AND batch_id = :batch_id",
            ['loc_ids' => [$loc_from_id, $loc_to_id], 'SKU_id' => $SKU_id, 'order_id' => $order_id, 'batch_id' => $batch_id], 'location_id');

        if (count($SKU_locations) !== 2)
            return;

        $old_qty = (int)$SKU_locations[$loc_from_id]['quantity'];
        self::removeByIndex($SKU_id, $loc_from_id, $order_id, $batch_id);
        (new SafeMysql())->execute("UPDATE SKU_locations SET quantity = (quantity + :old_qty)
                                                        WHERE SKU_id = :SKU_id 
                                                            AND location_id = :loc_to_id
                                                            AND order_id = :order_id
                                                            AND batch_id = :batch_id", [
            'old_qty' => $old_qty,
            'SKU_id' => $SKU_id,
            'loc_to_id' => $loc_to_id,
            'order_id' => $order_id,
            'batch_id' => $batch_id,
        ]);
    }

    public static function getExpired($warehouse_id, $supplier_id, $date_expired, $limit = 0)
    {
        $where_limit = $limit ? "LIMIT $limit" : '';
        return (new SafeMysql())->fetchAll("
            SELECT sl.*, b.number, b.expired
            FROM SKU_locations sl
                JOIN locations l ON sl.location_id = l.id AND l.warehouse = :warehouse_id
                JOIN SKUs s ON sl.SKU_id = s.id AND s.supplier_id = :supplier_id AND s.ignore_WBE <> 1
                JOIN batches b ON s.id = b.SKU_id AND sl.batch_id = b.id AND DATE_FORMAT(b.expired, '%Y-%m-%d') <= :date_expired
            WHERE sl.type <> 4 AND sl.batch_id IS NOT NULL
            $where_limit", ['warehouse_id' => (int)$warehouse_id, 'supplier_id' => (int)$supplier_id, 'date_expired' => $date_expired]);
    }

    public static function clearExpired($warehouses, $supplier_id, $SKU_id, $batch_id)
    {
        $where_warehouses = array_map('intval', array_keys(array_filter($warehouses, function ($item) {
            return !$item;
        })));
        if (empty($where_warehouses))
            return 0;
        // execute() returns the affected rows; a separate prepared SELECT ROW_COUNT() is not reliable
        return (new SafeMysql())->execute("
            UPDATE SKU_locations sl_res,
                   (SELECT SKU_id, location_id, batch_id
                    FROM SKU_locations sl
                        JOIN SKUs s ON sl.SKU_id = s.id AND s.supplier_id = :supplier_id
                        JOIN locations l ON sl.location_id = l.id AND l.warehouse IN (:where_warehouses)
                    WHERE sl.SKU_id = :SKU_id AND sl.batch_id = :batch_id AND sl.type = 4 AND sl.order_id IS NULL AND sl.reason = '') change_expired
            SET sl_res.type = 1, sl_res.reason = '2'
            WHERE sl_res.SKU_id = change_expired.SKU_id AND
                  sl_res.location_id = change_expired.location_id AND
                  sl_res.order_id IS NULL AND
                  sl_res.batch_id = change_expired.batch_id", [
            'supplier_id' => $supplier_id,
            'where_warehouses' => $where_warehouses,
            'SKU_id' => $SKU_id,
            'batch_id' => $batch_id,
        ]);
    }

    public static function destroyExpired($loc_exp_id, $SKU_id, $batch_id)
    {
        return (new SafeMysql())->execute("
            DELETE FROM SKU_locations
            WHERE SKU_id = :SKU_id AND
                  location_id = :loc_exp_id AND
                  order_id IS NULL AND
                  type = 4 AND
                  reason = '' AND
                  batch_id = :batch_id", [
            'SKU_id' => $SKU_id,
            'loc_exp_id' => $loc_exp_id,
            'batch_id' => $batch_id,
        ]);
    }

    public static function getQuantitySKUsForBundles(array $SKU_ids, int $warehouse_id): array
    {
        $SKU_ids_to_q_min = $SKU_ids_to_q = $SKU_ids_bundle_info = $errors_type_1 = $errors_type_2 = $errors_type_3 = [];
        if (!$SKU_ids)
            return ['SKU_ids_to_q_min' => [], 'SKU_ids_to_q' => [], 'SKU_ids_bundle_info' => [], 'free_SKUs' => [], 'errors_type_1' => [], 'errors_type_2' => [], 'errors_type_3' => []];
        $items = SKUs::getSKUsWithBundles($SKU_ids);
        $SKU_ids_to_bundle_ids = [];
        foreach ($items as $item) {
            $SKU_id = (int)$item['SKU_id'];
            $state = (int)$item['state'];
            $state_name = SKU::getStateName($state);
            $bundle_id = (int)$item['bundle_id'];
            if ($state === SKU::BUNDLE_NUM && !$bundle_id) {
                $errors_type_1[] = "SKU #$SKU_id state $state_name but have not bundle";
                continue;
            }
            if ($state !== SKU::BUNDLE_NUM && $bundle_id) {
                $errors_type_2[] = "SKU #$SKU_id state $state_name have bundle #$bundle_id";
                continue;
            }
            if (isset($SKU_ids_to_bundle_ids[$SKU_id])) {
                $errors_type_3[] = "SKU #$SKU_id state $state_name have more than one bundle #$bundle_id";
                continue;
            }
            $SKU_ids_to_bundle_ids[$SKU_id] = $bundle_id;
        }
        if (!$SKU_ids_to_bundle_ids)
            return ['SKU_ids_to_q_min' => $SKU_ids_to_q_min, 'SKU_ids_to_q' => $SKU_ids_to_q, 'SKU_ids_bundle_info' => [], 'free_SKUs' => [], 'errors_type_1' => $errors_type_1, 'errors_type_2' => $errors_type_2, 'errors_type_3' => $errors_type_3];
        $items = Bundles::getBundlesByIds($SKU_ids_to_bundle_ids);
        $bundles = $SKU_ids = [];
        foreach ($items as $item) {
            $bundles[(int)$item['bundle_id']][(int)$item['SKU_id']] = (int)$item['quantity'];
            $SKU_ids[(int)$item['SKU_id']] = (int)$item['SKU_id'];
        }
        $free_SKUs = SKULocations::getAvailableSKUsQty($SKU_ids, $warehouse_id);
        foreach ($bundles as $bundle_id => $bundle) {
            foreach ($SKU_ids_to_bundle_ids as $SKU_id_main => $bundle_id_main) {
                if ($bundle_id !== $bundle_id_main)
                    continue;
                $min_q = null;
                $SKU_ids_to_q[$SKU_id_main] = $SKU_ids_bundle_info[$SKU_id_main] = [];
                foreach ($bundle as $SKU_id => $bundle_quantity) {
                    $free_quantity = $free_SKUs[$SKU_id] ?? 0;
                    $min_q_next = (int)floor($free_quantity / $bundle_quantity);
                    $SKU_ids_to_q[$SKU_id_main][$SKU_id] = $min_q_next;
                    $SKU_ids_bundle_info[$SKU_id_main][$SKU_id] = $bundle_quantity;
                    if ($min_q === null || $min_q > $min_q_next)
                        $min_q = $min_q_next;
                }
                $SKU_ids_to_q_min[$SKU_id_main] = $min_q ?? 0;
            }
        }
        return ['SKU_ids_to_q_min' => $SKU_ids_to_q_min, 'SKU_ids_to_q' => $SKU_ids_to_q, 'SKU_ids_bundle_info' => $SKU_ids_bundle_info, 'free_SKUs' => $free_SKUs, 'errors_type_1' => $errors_type_1, 'errors_type_2' => $errors_type_2, 'errors_type_3' => $errors_type_3];
    }

    public static function hasOrderSKUsINLocations($order_id): bool
    {
        return !!(new SafeMysql())->fetchScalar("SELECT 1 FROM SKU_locations WHERE order_id = :order_id OR reason = :reason", ['order_id' => $order_id, 'reason' => '2_' . $order_id]);
    }

    public static function getQuantityInLocationBySKUId(int $location_id, int $SKU_id): int
    {
        $sql = "SELECT SUM(quantity) FROM SKU_locations WHERE SKU_id = :SKU_id AND location_id = :location_id";
        return (int)(new SafeMysql())->fetchScalar($sql, ['SKU_id' => $SKU_id, 'location_id' => $location_id]);
    }

    public static function preReserveSKUByRules(Order $order, array $SKUs, array $SKU_objs, bool $large = false): ?bool
    {
        $candidate_for_reserve = self::getCandidateForReserve((int)$order->getId(), $SKUs);
        if ($candidate_for_reserve === null)
            return null;
        if (empty($candidate_for_reserve))
            return true;
        $locs = self::getLocations4ReservedMass($order->getWarehouseId(), array_keys($candidate_for_reserve));
        if (empty($locs))
            return false;
        $locs_by_SKU_ids = $free_in_PF = [];
        foreach ($locs as $loc) {
            $locs_by_SKU_ids[$loc['SKU_id']][] = $loc;
            if ($loc['type_id'] === Location::PF)
                $free_in_PF[$loc['SKU_id']] = $loc['quantity'] + ($free_in_PF[$loc['SKU_id']] ?? 0);
        }
        $high_prio_locs = [];
        if ($large) {
            foreach ($candidate_for_reserve as $SKU_id => $qty_need) {
                $high_prio_locs[$SKU_id] = [];
                if (($free_in_PF[$SKU_id] ?? 0) >= $qty_need)
                    continue;
                foreach ($locs_by_SKU_ids[$SKU_id] ?? [] as $loc) {
                    if ($loc['type_id'] === Location::ST)
                        $high_prio_locs[$loc['location_id']] = $loc['location_id'];
                }
            }
        }
        foreach ($locs_by_SKU_ids as $SKU_id => $SKU_locs) {
            self::sortLocationRules($SKU_locs, [Location::PF => 2, Location::ST => 1], $high_prio_locs);
            $res = self::reserveBySortLocationsV2($order, $SKU_objs[$SKU_id], $candidate_for_reserve[$SKU_id], $SKU_locs);
            if ($res === false)
                return false;
        }
        $candidate_for_reserve = self::getCandidateForReserve((int)$order->getId(), $SKUs);
        if ($candidate_for_reserve === null)
            return null;
        if (empty($candidate_for_reserve))
            return true;
        return false;
    }

    public static function getCandidateForReserve(int $order_id, array $SKUs): ?array
    {
        $sql = "
        SELECT sl.SKU_id, sl.location_id, sl.quantity, sl.batch_id, l.type_id 
        FROM SKU_locations sl 
            JOIN locations l ON l.id = sl.location_id
        WHERE sl.order_id = :order_id OR sl.reason IN (:reason) AND SKU_id IN (:SKU_ids);";
        $in_order = (new SafeMysql())->fetchAll($sql, ['order_id' => $order_id, 'reason' => ['2_' . $order_id], 'SKU_ids' => array_keys($SKUs)]);
        $qty_exist_in_order = [];
        foreach ($in_order as $item)
            $qty_exist_in_order[$item['SKU_id']] = $item['quantity'] + ($qty_exist_in_order[$item['SKU_id']] ?? 0);
        $candidates = [];
        foreach ($SKUs as $SKU_id => $qty_need) {
            $qty_exist = $qty_exist_in_order[$SKU_id] ?? 0;
            if ($qty_exist > $qty_need)
                return null;
            if ((int)$qty_exist === (int)$qty_need)
                continue;
            $candidates[$SKU_id] = $qty_need - $qty_exist;
        }
        return $candidates;
    }

    public static function getCandidateForReplane(int $order_id, array $SKU_ids)
    {
        $sql = "
        SELECT sl.SKU_id, sl.location_id, sl.quantity, sl.batch_id, l.type_id 
        FROM SKU_locations sl 
            JOIN locations l ON l.id = sl.location_id AND l.type_id = 'ST'
        WHERE sl.order_id = :order_id OR sl.reason IN (:reason) AND SKU_id IN (:SKU_ids);";
        $in_order = (new SafeMysql())->fetchAll($sql, ['order_id' => $order_id, 'reason' => ['2_' . $order_id], 'SKU_ids' => $SKU_ids]);
        $qty_exist_in_order = [];
        foreach ($in_order as $item)
            $qty_exist_in_order[$item['SKU_id']] = $item['quantity'] + ($qty_exist_in_order[$item['SKU_id']] ?? 0);
        return $qty_exist_in_order;
    }

    public static function getExistOrderSKUsInCart(int $order_id, array $SKU_ids): bool
    {
        $sql = "
        SELECT 1
        FROM SKU_locations sl 
            JOIN locations l ON l.id = sl.location_id AND l.type_id = 'CA'
        WHERE sl.order_id = :order_id OR sl.reason IN (:reason) AND SKU_id IN (:SKU_ids)
        LIMIT 1;";
        return (bool)(new SafeMysql())->fetchAll($sql, ['order_id' => $order_id, 'reason' => ['2_' . $order_id], 'SKU_ids' => $SKU_ids]);
    }

    private static function reserveBySortLocationsV2(Order $order, SKU $SKU, int $qty, array $locs, string $reason_prefix = '2_'): bool
    {
        $order_id = $order->getId();
        $warehouse_id = $order->getWarehouseId();
        $label = Warehouses::getLabel($warehouse_id);
        $SKU_id = $SKU->getId();
        $res_order = $reason_prefix . $order_id;
        $num = 0;
        $mysql = new SafeMysql();
        while (true) {
            $mysql->beginTransaction();
            try {
                foreach ($locs as $loc) {
                    $batch_id = $loc['batch_id'] ? (int)$loc['batch_id'] : null;
                    $batch = $batch_id ? Batches::getById($batch_id) : null;
                    $batch_where = $batch_id === null ? 'IS NULL' : '= :batch_id';
                    $batch_param = $batch_id === null ? [] : ['batch_id' => $batch_id];
                    $location = Locations::getById($loc['location_id']);
                    $qty_in_location = (int)$loc['quantity'];
                    $qty_for_reserve = (int)min($qty, $loc['quantity']);
                    //Создаем запись с нулевым количеством товаров в предварительном резерве, если ее не было
                    $sql = "
                    INSERT INTO SKU_locations (SKU_id, location_id, quantity, order_id, type, reason, batch_id)
                    SELECT SL_from.SKU_id, SL_from.location_id, 0, null, 1, :reason, SL_from.batch_id
                    FROM SKU_locations SL_from
                        LEFT JOIN SKU_locations SL_to ON SL_to.SKU_id = SL_from.SKU_id AND SL_to.location_id = SL_from.location_id 
                                                      AND SL_to.order_id IS NULL AND SL_to.batch_id $batch_where
                                                      AND SL_to.type = 1 AND SL_to.reason = :reason
                    WHERE SL_from.SKU_id = :SKU_id AND SL_from.location_id = :location_id AND
                          SL_from.order_id IS NULL AND SL_from.batch_id $batch_where AND
                          SL_from.type = 1 AND SL_from.reason = '2' AND
                          SL_to.SKU_id IS NULL;";
                    $mysql->execute($sql, array_merge(['reason' => $res_order, 'SKU_id' => $SKU_id, 'location_id' => $location->getId()], $batch_param));
                    //Обновляем количество товаров в предварительном резерве
                    $sql = "
                    UPDATE SKU_locations SL_to
                        INNER JOIN SKU_locations SL_from ON SL_from.SKU_id = SL_to.SKU_id AND SL_from.location_id = SL_to.location_id 
                                                         AND SL_from.order_id IS NULL AND SL_from.type = 1 
                                                         AND SL_from.reason = '2' AND SL_from.batch_id $batch_where
                                                         AND SL_from.quantity = :qty_in_location
                        SET SL_to.quantity = SL_to.quantity + :qty_for_reserve
                    WHERE SL_to.SKU_id = :SKU_id AND SL_to.location_id = :location_id AND SL_to.order_id IS NULL AND SL_to.batch_id $batch_where AND SL_to.type = 1 AND SL_to.reason = :reason;";
                    $rows = $mysql->execute($sql, array_merge(['SKU_id' => $SKU_id, 'location_id' => $location->getId(), 'reason' => $res_order, 'qty_in_location' => $qty_in_location, 'qty_for_reserve' => $qty_for_reserve], $batch_param));
                    if(!$rows)
                        throw new \Exception('Free SKUs were changed. Order #' . $order_id);
                    //Обновляем количество свободных товаров
                    $sql = "
                    UPDATE SKU_locations
                    SET quantity = quantity - :qty_for_reserve
                    WHERE SKU_id = :SKU_id AND location_id = :location_id AND order_id IS NULL AND batch_id $batch_where AND type = 1 AND reason = '2' AND quantity = :qty_in_location;";
                    $rows2 = $mysql->execute($sql, array_merge(['SKU_id' => $SKU_id, 'location_id' => $location->getId(), 'qty_in_location' => $qty_in_location, 'qty_for_reserve' => $qty_for_reserve], $batch_param));
                    if(!$rows2)
                        throw new \Exception('Free SKUs were changed. Order #' . $order_id);
                    //Удаляем свободные товары если локация пустая
                    $sql = "
                    DELETE FROM SKU_locations
                    WHERE SKU_id = :SKU_id AND location_id = :location_id AND order_id IS NULL AND batch_id $batch_where AND type = 1 AND reason = '2' AND quantity = 0;";
                    $mysql->execute($sql, array_merge(['SKU_id' => $SKU_id, 'location_id' => $location->getId()], $batch_param));
                    OrderHistories::reserveSKU($order_id, $SKU, $batch, $qty_for_reserve, $location->getLocation($label));
                    $qty -= $qty_for_reserve;
                    if (!$qty)
                        break;
                }
            } catch (\Exception $e) {
                $mysql->rollback();
                if ($e instanceof DBException && $e->isDeadlock($num++))
                    continue;
                Debug::sendDebugInfo('Reserve SKU', var_export([$e->getCode(), $e->getMessage()], 1));
                return false;
            }
            ShopInventoryQueues::addSKU($SKU->getId(), $warehouse_id, InventorySync::SYNC_ORDER_RESERVED, $order_id);
            $mysql->commit();
            break;
        }
        return true;
    }

    protected static function sortLocationRules(array &$locs, array $loc_type_weight, array $high_prio_locs = [])
    {
        usort($locs, function ($a, $b) use ($loc_type_weight, $high_prio_locs) {
            $high_prio_a = (bool)($high_prio_locs[$a['location_id']] ?? false);
            $high_prio_b = (bool)($high_prio_locs[$b['location_id']] ?? false);
            if ($high_prio_a !== $high_prio_b)
                return $high_prio_b <=> $high_prio_a;
            if ($a['type_id'] !== $b['type_id']) {
                $weight_a = $loc_type_weight[$a['type_id']] ?? 0;
                $weight_b = $loc_type_weight[$b['type_id']] ?? 0;
                return $weight_b <=> $weight_a;
            }
            if ($a['expired'] !== $b['expired']) {
                $expired_a = $a['expired'] === null ? PHP_INT_MAX : strtotime($a['expired']);
                $expired_b = $b['expired'] === null ? PHP_INT_MAX : strtotime($b['expired']);
                return $expired_a <=> $expired_b;
            }
            if ($a['priority'] !== $b['priority'])
                return (int)$a['priority'] <=> (int)$b['priority'];
            return 0;
        });
    }

    public static function addQtyToSKUsArray(array &$SKUs, int $warehouse_id): void
    {
        $SKUs_qty_in_types = self::getSKUsQtyInTypes(array_column($SKUs, 'id'), $warehouse_id);
        foreach ($SKUs as $key => $record) {
            $SKUs[$key]['available'] = $SKUs_qty_in_types['available_SKUs'][(int)$record['id']] + $SKUs_qty_in_types['available_bundle_SKUs'][(int)$record['id']];
            $SKUs[$key]['allocated'] = $SKUs_qty_in_types['allocated_SKUs'][(int)$record['id']] + $SKUs_qty_in_types['free_but_not_available_SKUs'][(int)$record['id']];
            $SKUs[$key]['backorder_replenish'] = $SKUs_qty_in_types['backorder_replenish_SKUs'][(int)$record['id']];
        }
    }

    public static function getSKUsQtyInTypes(array $SKU_ids, int $warehouse_id): array
    {
        //Спорный момент. Возможно вместо отдельных запросов, лучше один запрос и на стороне PHP по типам разбрасывать.
        //Но не упад ли по памяти на стороне PHP при таком подходе !!!
        return [
            'available_SKUs' => self::getAvailableSKUsQty($SKU_ids, $warehouse_id),
            'available_bundle_SKUs' => self::getAvailableBundleSKUsQty($SKU_ids, $warehouse_id),
            'allocated_SKUs' => self::getAllocatedSKUsQty($SKU_ids, $warehouse_id),
            'free_but_not_available_SKUs' => self::getFreeButNotAvailableSKUsQty($SKU_ids, $warehouse_id),
            'backorder_replenish_SKUs' => self::getBackorderReplenishSKUsQty($SKU_ids, $warehouse_id),
        ];
    }

    public static function getAvailableSKUsQty(array $SKU_ids, int $warehouse_id): array
    {
        if (!$SKU_ids)
            return [];
        $result = [];
        foreach ($SKU_ids as $SKU_id)
            $result[$SKU_id] = 0;
        $params = ['SKU_ids' => array_map('intval', $SKU_ids), 'type' => 1, 'warehouse_id' => $warehouse_id, 'types' => [Location::PF, Location::ST]];
        $where_exclude_locations = '';
        if ($exclude_ST_locations_ids = self::getExcludeSTLocations($warehouse_id)) {
            $where_exclude_locations = "AND l.id NOT IN (:where_exclude_locations)";
            $params['where_exclude_locations'] = $exclude_ST_locations_ids;
        }
        $sql = "
        SELECT Sl.SKU_id, SUM(Sl.quantity) qty
        FROM SKU_locations Sl
            JOIN locations l ON l.id = Sl.location_id AND l.warehouse = :warehouse_id AND l.type_id IN (:types) $where_exclude_locations
        WHERE Sl.SKU_id IN (:SKU_ids) AND Sl.order_id IS NULL AND Sl.type = :type AND Sl.reason = '2'
        GROUP BY Sl.SKU_id";
        $items = (new SafeMysql())->fetchAll($sql, $params);
        foreach ($items as $item)
            $result[(int)$item['SKU_id']] = (int)$item['qty'];
        return $result;
    }

    public static function getAvailableBundleSKUsQty(array $SKU_ids, int $warehouse_id): array
    {
        $SKU_ids_to_q_min = self::getQuantitySKUsForBundles($SKU_ids, $warehouse_id);
        if (Environment::isProduction())
            foreach (array_merge($SKU_ids_to_q_min['errors_type_2'], $SKU_ids_to_q_min['errors_type_3']) as $message)
                Debug::sendDebugInfo('getAvailableBundleSKUsQty', $message, Debug::TEST);
        $result = [];
        foreach ($SKU_ids as $SKU_id)
            $result[(int)$SKU_id] = $SKU_ids_to_q_min['SKU_ids_to_q_min'][(int)$SKU_id] ?? 0;
        return $result;
    }

    public static function getAllocatedSKUsQty(array $SKU_ids, int $warehouse_id): array
    {
        if (!$SKU_ids)
            return [];
        $result = [];
        foreach ($SKU_ids as $SKU_id)
            $result[$SKU_id] = 0;
        $params = ['SKU_ids' => array_map('intval', $SKU_ids), 'type' => 1, 'type_exp' => 4, 'warehouse_id' => $warehouse_id, 'types' => [Location::PA, Location::DH]];
        $where_exclude_locations = '';
        if ($exclude_ST_locations_ids = self::getExcludeSTLocations($warehouse_id)) {
            $where_exclude_locations = "AND l.id NOT IN (:where_exclude_locations)";
            $params['where_exclude_locations'] = $exclude_ST_locations_ids;
        }
        $sql = "
        SELECT Sl.SKU_id, SUM(Sl.quantity) qty
        FROM SKU_locations Sl
            JOIN locations l ON l.id = Sl.location_id AND l.warehouse = :warehouse_id AND l.type_id NOT IN (:types) $where_exclude_locations
            JOIN SKUs S ON S.id = Sl.SKU_id
        WHERE Sl.SKU_id IN (:SKU_ids) AND NOT (type = :type AND reason = '2') AND NOT (type = :type_exp AND reason = '')
        GROUP BY Sl.SKU_id";
        $items = (new SafeMysql())->fetchAll($sql, $params);
        foreach ($items as $item)
            $result[(int)$item['SKU_id']] = (int)$item['qty'];
        return $result;
    }

    public static function getFreeButNotAvailableSKUsQty(array $SKU_ids, int $warehouse_id): array
    {
        if (!$SKU_ids)
            return [];
        $result = [];
        foreach ($SKU_ids as $SKU_id)
            $result[$SKU_id] = 0;
        $params = ['SKU_ids' => array_map('intval', $SKU_ids), 'type' => 1, 'warehouse_id' => $warehouse_id, 'types' => [Location::PF, Location::ST, Location::PA, Location::DH]];
        $where_exclude_locations = '';
        if ($exclude_ST_locations_ids = self::getExcludeSTLocations($warehouse_id)) {
            $where_exclude_locations = "AND l.id NOT IN (:where_exclude_locations)";
            $params['where_exclude_locations'] = $exclude_ST_locations_ids;
        }
        $sql = "
        SELECT Sl.SKU_id, SUM(Sl.quantity) qty
        FROM SKU_locations Sl
            JOIN locations l ON l.id = Sl.location_id AND l.warehouse = :warehouse_id AND l.type_id NOT IN (:types) $where_exclude_locations
            JOIN SKUs S ON S.id = Sl.SKU_id
        WHERE Sl.SKU_id IN (:SKU_ids) AND Sl.type = :type AND Sl.reason = '2'
        GROUP BY Sl.SKU_id";
        $items = (new SafeMysql())->fetchAll($sql, $params);
        foreach ($items as $item)
            $result[(int)$item['SKU_id']] = (int)$item['qty'];
        return $result;
    }

    public static function getBackorderReplenishSKUsQty(array $SKU_ids, int $warehouse_id): array
    {
        if (!$SKU_ids)
            return [];
        $result = [];
        foreach ($SKU_ids as $SKU_id)
            $result[$SKU_id] = 0;
        $params = ['SKU_ids' => array_map('intval', $SKU_ids), 'type' => 1, 'status' => Order::BACKORDER_NUM, 'warehouse_id' => $warehouse_id];
        $where_reason = "sl.reason = CONCAT('2_', os.order_id)";
        $sql = "
        SELECT os.order_id, os.SKU_id, os.quantity - SUM(IFNULL(sl.quantity, 0)) qty
        FROM order_SKUs os
            JOIN orders o ON os.order_id = o.id AND o.status = :status AND o.warehouse_id = :warehouse_id
            JOIN SKUs S ON S.id = os.SKU_id
            LEFT JOIN SKU_locations sl ON os.SKU_id = sl.SKU_id AND (os.order_id = sl.order_id OR (sl.order_id IS NULL AND sl.type = :type AND $where_reason))
        WHERE os.SKU_id IN (:SKU_ids)
        GROUP BY os.order_id, os.SKU_id";
        $items = (new SafeMysql())->fetchAll($sql, $params);
        foreach ($items as $item)
            $result[(int)$item['SKU_id']] = ($result[(int)$item['SKU_id']] ?? 0) + (int)$item['qty'];
        return $result;
    }

    public static function getExcludeSTLocations(int $warehouse_id): array
    {
        $cancel = Location::SPECIAL_LOCATION__CANCEL;
        $exp = Location::SPECIAL_LOCATION__EXPIRED;
        $order_cancel = Locations::getByLocation($warehouse_id, $cancel[0], $cancel[1], $cancel[2], $cancel[3]);
        $loc_exp = Locations::getByLocation($warehouse_id, $exp[0], $exp[1], $exp[2], $exp[3]);
        $exclude_ST_locations_ids = [];
        if (!empty($order_cancel) && count($order_cancel) == 1)
            $exclude_ST_locations_ids[] = $order_cancel[0]->getId();
        if (!empty($loc_exp) && count($loc_exp) == 1)
            $exclude_ST_locations_ids[] = $loc_exp[0]->getId();
        return $exclude_ST_locations_ids;
    }

    public static function getAvailableSKUsByFilters(int $warehouse_id, array $filters): array
    {
        $where = [];
        $params = ['type' => 1, 'warehouse_id' => $warehouse_id, 'types' => [Location::PF, Location::ST]];
        $where_exclude_locations = '';
        if ($exclude_ST_locations_ids = self::getExcludeSTLocations($warehouse_id)) {
            $where_exclude_locations = "AND l.id NOT IN (:where_exclude_locations)";
            $params['where_exclude_locations'] = $exclude_ST_locations_ids;
        }
        self::setFiltersForAvailable($filters, $params, $where);
        $SKUs_where_filters = $where ? "AND " . implode(" AND ", $where) : '';
        $sql = "
        SELECT DISTINCT Sl.SKU_id
        FROM SKU_locations Sl
            JOIN locations l ON l.id = Sl.location_id AND l.warehouse = :warehouse_id AND l.type_id IN (:types) $where_exclude_locations
            JOIN SKUs s ON s.id = Sl.SKU_id $SKUs_where_filters
        WHERE Sl.type = :type AND Sl.reason = '2' ";
        return array_map('intval', (new SafeMysql())->fetchArrayOneField($sql, $params));
    }

    public static function getAvailableBundleSKUsByFilters(int $warehouse_id, array $filters): array
    {
        $where = [];
        $params = ['state' => SKU::BUNDLE_NUM];
        self::setFiltersForAvailable($filters, $params, $where);
        $SKUs_where_filters = $where ? "AND " . implode(" AND ", $where) : '';
        $sql = "
        SELECT DISTINCT s.id original_id, bS.SKU_id bundle_id, bS.quantity
        FROM SKUs s
            JOIN bundles b ON b.supplier_id = s.supplier_id AND b.name = s.customer_ref
            JOIN bundles_SKUs bS ON bS.bundle_id = b.id
        WHERE s.state = :state $SKUs_where_filters;";
        $bundles = (new SafeMysql())->fetchAll($sql, $params);
        $bundle_SKU_ids = array_column($bundles, 'bundle_id');
        $available_SKUs_qty_bundles = self::getAvailableSKUsQty($bundle_SKU_ids, $warehouse_id);
        $SKU_ids = [];
        foreach ($bundles as $bundle)
            $SKU_ids[(int)$bundle['original_id']] = ($SKU_ids[(int)$bundle['original_id']] ?? true) && $available_SKUs_qty_bundles[(int)$bundle['bundle_id']] >= $bundle['quantity'];
        $result = [];
        foreach ($SKU_ids as $SKU_id => $state)
            if ($state)
                $result[] = $SKU_id;
        return $result;
    }

    public static function getAllocatedSKUsByFilters(int $warehouse_id, array $filters): array
    {
        $where = [];
        $params = ['type' => 1, 'type_exp' => 4, 'warehouse_id' => $warehouse_id, 'types' => [Location::PA, Location::DH]];
        $where_exclude_locations = '';
        if ($exclude_ST_locations_ids = self::getExcludeSTLocations($warehouse_id)) {
            $where_exclude_locations = "AND l.id NOT IN (:where_exclude_locations)";
            $params['where_exclude_locations'] = $exclude_ST_locations_ids;
        }
        self::setFiltersForAvailable($filters, $params, $where);
        $SKUs_where_filters = $where ? "AND " . implode(" AND ", $where) : '';
        $sql = "
        SELECT DISTINCT Sl.SKU_id
        FROM SKU_locations Sl
            JOIN locations l ON l.id = Sl.location_id AND l.warehouse = :warehouse_id AND l.type_id NOT IN (:types) $where_exclude_locations
            JOIN SKUs s ON s.id = Sl.SKU_id $SKUs_where_filters
        WHERE NOT (type = :type AND reason = '2') AND NOT (type = :type_exp AND reason = '') ";
        return array_map('intval', (new SafeMysql())->fetchArrayOneField($sql, $params));
    }

    public static function getFreeButNotAvailableSKUsByFilters(int $warehouse_id, array $filters)
    {
        $where = [];
        $params = ['type' => 1, 'warehouse_id' => $warehouse_id, 'types' => [Location::PF, Location::ST, Location::PA, Location::DH]];
        $where_exclude_locations = '';
        if ($exclude_ST_locations_ids = self::getExcludeSTLocations($warehouse_id)) {
            $where_exclude_locations = "AND l.id NOT IN (:where_exclude_locations)";
            $params['where_exclude_locations'] = $exclude_ST_locations_ids;
        }
        self::setFiltersForAvailable($filters, $params, $where);
        $SKUs_where_filters = $where ? "AND " . implode(" AND ", $where) : '';
        $sql = "
        SELECT DISTINCT Sl.SKU_id
        FROM SKU_locations Sl
            JOIN locations l ON l.id = Sl.location_id AND l.warehouse = :warehouse_id AND l.type_id NOT IN (:types) $where_exclude_locations
            JOIN SKUs s ON s.id = Sl.SKU_id $SKUs_where_filters
        WHERE Sl.type = :type AND Sl.reason = '2' ";
        return array_map('intval', (new SafeMysql())->fetchArrayOneField($sql, $params));
    }

    public static function getBackorderReplenishSKUsByFilters(int $warehouse_id, array $filters): array
    {
        $where = [];
        $params = ['type' => 1, 'status' => Order::BACKORDER_NUM, 'warehouse_id' => $warehouse_id];
        $regexp = SKULocations::getMySQLIsOrderReason('os.order_id');
        self::setFiltersForAvailable($filters, $params, $where);
        $SKUs_where_filters = $where ? "AND " . implode(" AND ", $where) : '';
        $sql = "
        SELECT DISTINCT IF(os.quantity > SUM(IFNULL(sl.quantity, 0)), os.SKU_id, NULL)
        FROM order_SKUs os
            JOIN orders o ON os.order_id = o.id AND o.status = :status AND o.warehouse_id = :warehouse_id
            JOIN SKUs s ON s.id = os.SKU_id $SKUs_where_filters
            LEFT JOIN SKU_locations sl ON os.SKU_id = sl.SKU_id AND (os.order_id = sl.order_id OR (sl.order_id IS NULL AND sl.type = :type AND sl.reason REGEXP $regexp))
        GROUP BY os.order_id, os.SKU_id";
        return array_map('intval', array_filter((new SafeMysql())->fetchArrayOneField($sql, $params)));
    }

    public static function setFiltersForAvailable(array $filters, array &$params, array &$where, string $table_name = 's', bool $started = false): void
    {
        if (!empty($filters['like_name'])) {
            $where[] = "$table_name.name LIKE :s_name";
            $params['s_name'] = '%' . $filters['like_name'] . '%';
        }
        if (isset($filters['barcode']) && trim($filters['barcode']) !== '')
            $barcode_SKU_ids = SKUBarcodes::getSKUIdsByBarcode($filters['barcode'], $started);
        if (isset($filters['customer_ref']) && trim($filters['customer_ref']) !== '') {
            $where[] = "$table_name.customer_ref LIKE :customer_ref";
            $params['customer_ref'] = '%' . $filters['customer_ref'] . '%';
        }
        if (isset($barcode_SKU_ids)) {
            $SKU_ids = $barcode_SKU_ids;
            if ($SKU_ids) {
                $where[] = "$table_name.id IN (:SKU_ids)";
                $params['SKU_ids'] = array_unique($SKU_ids);
            } else
                $where[] = "false";
        }
        if (isset($filters['state']) && $filters['state'] != -1) {
            $where[] = "$table_name.state = :s_state";
            $params['s_state'] = (int)$filters['state'];
        }
        if (!empty($filters['supplier_id'])) {
            $where[] = "$table_name.supplier_id IN (:s_supplier_id)";
            $params['s_supplier_id'] = is_array($filters['supplier_id']) ? array_map('intval', $filters['supplier_id']) : (int)$filters['supplier_id'];
        }
        if (!empty($filters['id'])) {
            $where[] = "$table_name.id IN (:s_id)";
            $params['s_id'] = (int)$filters['id'];
        }
        if (!empty($filters['max_item_id'])) {
            $where[] = "$table_name.id > :s_max_item_id";
            $params['s_max_item_id'] = (int)$filters['max_item_id'];
        }
    }

    public static function getMySQLIsOrderReason($order_id = null, array $reason_prefix = [1, 2]): string
    {
        if ($order_id && is_string($order_id))
            $order_id = addslashes($order_id);
        elseif ($order_id && is_array($order_id))
            $order_id = "'(?:" . implode('|', array_map('intval', $order_id)) . ")'";
        elseif ($order_id === null)
            $order_id = "'[0-9]+'";
        elseif (!is_int($order_id))
            throw new \Exception("Wrong order id " . $order_id);
        $prefix = implode('|', array_map('intval', $reason_prefix));
        return "CONCAT('^(?:$prefix)_', $order_id, '$')";
    }

    public static function removeOrdersSKUsForCounting(int $location_id, int $SKU_id, int $batch_id, int $type, string $reason, int $order_id, int $old_quantity, int $del_quantity): bool
    {
        $batch_where = $batch_id ? "= $batch_id" : 'IS NULL';
        $order_where = $order_id ? "= $order_id" : 'IS NULL';
        $sql = "
        UPDATE SKU_locations Sl
        SET Sl.quantity = Sl.quantity - :del_quantity
        WHERE Sl.SKU_id = :SKU_id AND Sl.location_id = :location_id AND Sl.order_id $order_where AND Sl.type = :type AND Sl.reason = :reason AND Sl.batch_id $batch_where AND Sl.quantity = :old_quantity";
        $is_update = (bool)(new SafeMysql())->execute($sql, ['SKU_id' => $SKU_id, 'location_id' => $location_id, 'type' => $type, 'old_quantity' => $old_quantity, 'del_quantity' => $del_quantity, 'reason' => $reason, 'order_id' => $order_id]);
        if (!$is_update)
            return false;
        $sql = "
        DELETE FROM SKU_locations
        WHERE SKU_id = :SKU_id AND location_id = :location_id AND order_id $order_where AND type = :type AND reason = :reason AND batch_id $batch_where AND quantity = 0";
        (new SafeMysql())->execute($sql, ['SKU_id' => $SKU_id, 'location_id' => $location_id, 'type' => $type, 'reason' => $reason]);
        return true;
    }

    public static function addSKUsToDHForCounting(int $location_id, int $SKU_id, int $DH_qty): void
    {
        //1 Создаем запись с нулевым количеством товаров в DH minus, если ее не было
        $sql = "INSERT INTO SKU_locations (location_id, SKU_id, quantity, order_id, type, reason, batch_id)
                    SELECT :location_id, :SKU_id, 0, NULL, :type_pick, '2', NULL
                    WHERE NOT EXISTS(SELECT *
                        FROM SKU_locations
                        WHERE SKU_id = :SKU_id
                            AND location_id = :location_id
                            AND order_id IS NULL
                            AND type = :type_pick
                            AND reason = '2'
                            AND batch_id IS NULL
                            LIMIT 1
                    )";
        (new SafeMysql())->execute($sql, [
            'location_id' => $location_id,
            'SKU_id' => $SKU_id,
            'type_pick' => 1,
        ]);
        //2 Добавляем использованные SKUs в DH minus
        $sql = "UPDATE SKU_locations
                    SET quantity = quantity + :quantity
                WHERE SKU_id = :SKU_id
                    AND location_id = :location_id
                    AND order_id IS NULL
                    AND type = :type_pick
                    AND reason = '2'
                    AND batch_id IS NULL";
        (new SafeMysql())->execute($sql, [
            'location_id' => $location_id,
            'SKU_id' => $SKU_id,
            'type_pick' => 1,
            'quantity' => $DH_qty,
        ]);
    }

    public static function getDataForReconciliationWithQuery($SKU_ids, int $loc_id, int $warehouse_id): array
    {
        if (!$SKU_ids)
            return [];
        $params = ['type' => 1, 'warehouse_id' => $warehouse_id, 'types' => [Location::PF, Location::ST], 'SKU_ids' => $SKU_ids, 'location_id' => $loc_id];
        $where_exclude_locations = '';
        if ($exclude_ST_locations_ids = self::getExcludeSTLocations($warehouse_id)) {
            $where_exclude_locations = "AND l.id NOT IN (:where_exclude_locations)";
            $params['where_exclude_locations'] = $exclude_ST_locations_ids;
        }
        $sql = "
        SELECT Sl.*
        FROM SKU_locations Sl
            JOIN locations l ON l.id = Sl.location_id AND l.warehouse = :warehouse_id AND l.type_id IN (:types) $where_exclude_locations
            JOIN SKUs s ON s.id = Sl.SKU_id
        WHERE Sl.type = :type AND Sl.reason = '2' AND Sl.SKU_id IN (:SKU_ids) AND Sl.location_id = :location_id";
        return (new SafeMysql())->fetchAll($sql, $params);
    }

    public static function removeFreeSKUsForCounting(int $location_id, int $SKU_id, int $batch_id, int $old_quantity, $del_from_free): bool
    {
        $batch_where = $batch_id ? "= $batch_id" : 'IS NULL';
        $sql = "
        UPDATE SKU_locations
        SET quantity = quantity - :del_quantity
        WHERE SKU_id = :SKU_id AND location_id = :location_id AND order_id IS NULL AND type = :type_pick AND reason = '2' AND batch_id $batch_where AND quantity = :old_quantity";
        $is_update = (bool)(new SafeMysql())->execute($sql, ['location_id' => $location_id, 'SKU_id' => $SKU_id, 'type_pick' => 1, 'old_quantity' => $old_quantity, 'del_quantity' => $del_from_free]);
        if (!$is_update)
            return false;
        $sql = "
        DELETE FROM SKU_locations
        WHERE SKU_id = :SKU_id AND location_id = :location_id AND order_id IS NULL AND type = :type_pick AND reason = '2' AND batch_id $batch_where AND quantity = 0";
        (new SafeMysql())->execute($sql, ['SKU_id' => $SKU_id, 'location_id' => $location_id, 'type_pick' => 1]);
        return true;
    }
}
