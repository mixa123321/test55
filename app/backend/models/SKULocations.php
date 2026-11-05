<?php

namespace app\backend\models;

use app\core\db\DBException;
use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\entities\Order;
use app\core\entities\SKU;
use app\core\entities\SKULocation;
use app\core\helpers\TimeZonesConvertor;
use app\core\entities\Movement;
use app\core\helpers\Arr;
use app\core\helpers\Debug;
use app\core\helpers\Validator;
use app\core\helpers\View;
use app\core\models\Batches;
use app\core\models\InventoryLogs;
use app\core\models\perms\Roles;
use app\core\models\SKUPreffLocs;
use app\core\models\Warehouses;
use app\core\statical\WithDate;
use Exception;

class SKULocations extends \app\core\models\SKULocations
{
    /**
     * @param $temp SKULocation
     * @return array
     */
    public static function validate($temp)
    {
        $rules = [
            'SKU_id' => [
                'val' => $temp->getSKUId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'location_id' => [
                'val' => $temp->getLocationId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'quantity' => [
                'val' => $temp->getQuantity(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors)) {
            return $errors;
        }
        return $validator->getErrors();
    }

    public static function addPutaway($warehouse_id, $SKU_id, $loc_id, $quan, $batch_id = null)
    {
        if ($quan > 0)
            return self::addSKU2Location($warehouse_id, $SKU_id, $loc_id, $quan, null, 1, '2', $batch_id);
        return false;
    }

    public static function getByOrder($order_id)
    {
        $order_id = (int)$order_id;
        $sql = "SELECT sl.*, l.type_id
                FROM SKU_locations sl
                    JOIN locations l ON l.id = sl.location_id
                WHERE sl.order_id = :order_id OR (sl.order_id IS NULL AND POSITION('2_' IN sl.reason) = 1 AND SUBSTRING_INDEX(sl.reason, '_', -1) = :order_id)";
        return (new SafeMysql())->fetchAll($sql, [
            'order_id' => $order_id
        ]);
    }

    public static function getSKUsBySupplierFromWarehouse($supplier_id, $warehouse_id)
    {
        $sql = "SELECT sl.SKU_id, SUM(sl.quantity) quantity
                FROM SKU_locations sl
                    JOIN SKUs s ON sl.SKU_id = s.id AND s.supplier_id = :supplier_id
                    JOIN locations l ON sl.location_id = l.id AND l.warehouse = :warehouse_id AND l.type_id NOT IN (:types)
                GROUP BY sl.SKU_id    
                ORDER BY sl.SKU_id";
        return Arr::map((new SafeMysql())->fetchAll($sql, [
            'supplier_id' => $supplier_id,
            'warehouse_id' => $warehouse_id,
            'types' => Location::NOT_COUNTED_TYPES
        ]), 'SKU_id', 'quantity');
    }

    public static function getMezzanine($order_id, $warehouse_id)
    {
        $order_id = (int)$order_id;
        $warehouse_id = (int)$warehouse_id;
        $sql = "SELECT DISTINCT l.mezzanine
                FROM SKU_locations sl
                    JOIN locations l ON l.id = sl.location_id AND l.warehouse = :warehouse_id
                WHERE sl.order_id = :order_id AND sl.type = 1 AND sl.reason = 1
                ORDER BY l.mezzanine";
        $res = (new SafeMysql())->fetchArrayOneField($sql, [
            'warehouse_id' => $warehouse_id,
            'order_id' => $order_id
        ]);
        return (int)(count($res) == 1 && $res[0] == 1);
    }

    public static function getVirtual($warehouse_id, $SKU_ids)
    {
        if (empty($SKU_ids) || !is_array($SKU_ids))
            return [];
        $warehouses = array_keys(Warehouses::getLocations($warehouse_id));
        if (empty($warehouses) || !is_array($warehouses))
            return [];
        $sql = "SELECT sl.SKU_id, l.row, SUM(sl.quantity) quantity
                FROM SKU_locations sl
                    JOIN locations l ON l.id = sl.location_id AND l.warehouse = :warehouse_id AND l.type_id = :type_id AND l.row IN (:warehouses)
                WHERE sl.SKU_id IN (:SKU_ids)
                GROUP BY sl.SKU_id, l.row";
        $res = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'type_id' => Location::ST,
            'warehouses' => $warehouses,
            'SKU_ids' => $SKU_ids
        ]);
        if (empty($res))
            return [];
        $SKUs = [];
        foreach ($res as $SKU)
            $SKUs[$SKU['SKU_id']][$SKU['row']] = $SKU['quantity'];
        return $SKUs;
    }

    public static function cancelOrder($id)
    {
        $locations = self::getByOrder($id);
        $res = [];
        $mysql = new SafeMysql();
        $id = (int)$id;
        foreach ($locations as $location) {
            $where_base = "SKU_id = :SKU_id AND location_id = :location_id AND batch_id " . (empty($location['batch_id']) ? 'IS NULL' : '= :batch_id');
            $params = [
                'SKU_id' => $location['SKU_id'],
                'location_id' => $location['location_id'],
                'batch_id' => empty($location['batch_id']) ? null : $location['batch_id'],
                'id' => $id,
            ];
            if (Location::isPickAndPack($location['type_id']))
                $res[] = $location;
            $exist = $mysql->fetchOne("SELECT * FROM SKU_locations WHERE $where_base AND order_id IS NULL AND type = 1 AND reason = '2'", $params);
            if ($exist) {
                $mysql->execute("UPDATE SKU_locations SET quantity = quantity + :quantity WHERE $where_base AND order_id IS NULL AND type = 1 AND reason = '2'",
                    array_merge($params, ['quantity' => $location['quantity']]));
                if (empty($location['order_id']))
                    $mysql->execute("DELETE FROM SKU_locations WHERE $where_base AND order_id IS NULL AND type = 1 AND reason = :reason",
                        array_merge($params, ['reason' => "2_$id"]));
                else
                    $mysql->execute("DELETE FROM SKU_locations WHERE $where_base AND order_id = :id", $params);
                continue;
            }
            if (empty($location['order_id']))
                $mysql->execute("UPDATE SKU_locations SET reason = '2' WHERE $where_base AND order_id IS NULL AND type = 1 AND reason = :reason",
                    array_merge($params, ['reason' => "2_$id"]));
            else
                $mysql->execute("UPDATE SKU_locations SET order_id = NULL, type = 1, reason = '2' WHERE $where_base AND order_id = :id", $params);
        }
        return $res;
    }

    public static function isDropMulti($user_id, $cart_id)
    {
        $sql = 'SELECT COUNT(*) FROM SKU_locations WHERE type IN (3, 22) AND reason= :user_id AND location_id= :cart_id';
        return (int)(new SafeMysql())->fetchArrayOneField($sql, ['user_id' => (int)$user_id, 'cart_id' => (int)$cart_id])[0];
    }

    public static function isDropSingle($user_id, $cart_id)
    {
        $sql = 'SELECT COUNT(*) FROM SKU_locations WHERE type IN (3, 21) AND reason= :user_id AND location_id= :cart_id';
        return (int)(new SafeMysql())->fetchArrayOneField($sql, ['user_id' => (int)$user_id, 'cart_id' => (int)$cart_id])[0];
    }

    /**
     * @param array|int $cart_id
     */
    public static function getWeight($warehouse_id, $cart_id)
    {
        $params = [
            'warehouse_id' => (int)$warehouse_id,
            'cart_id' => $cart_id,
        ];
        if (empty($cart_id))
            return [];
        if (is_array($cart_id))
            $where_cart = 'l.id IN (:cart_id)';
        else
            $where_cart = 'l.id = :cart_id';
        $location_name = Locations::getLocationNameSelect('l');
        $sql = "SELECT l.id,
                       $location_name name, 
                       COUNT(sl.SKU_id) skus, 
                       SUM(sl.quantity) units, 
                       SUM(sl.quantity * s.weight) weight,
                       sl.type
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id AND l.warehouse = :warehouse_id
                    JOIN SKUs s ON sl.SKU_id = s.id
                WHERE $where_cart
                GROUP BY l.id, sl.type, sl.reason
                ORDER BY sl.type, l.type_id, LPAD(l.row, 5, '0')";
        return !is_array($cart_id)
            ? (new SafeMysql())->fetchAssocWithKey($sql, $params, 'type')
            : (new SafeMysql())->fetchAll($sql, $params);
    }

    public static function emptyCart($id)
    {
        $sql = "SELECT sum(quantity) FROM SKU_locations WHERE location_id= :id";
        return !(int)(new SafeMysql())->fetchArrayOneField($sql, ['id' => $id])[0];
    }

    /**
     * @param $location Location
     * @param $SKU SKU
     * @param $qty
     * @return bool
     */
    public static function getQuantityFree($location, $SKU, $qty)
    {
        $sql = "SELECT IFNULL(SUM(sl.quantity), 0) summa, l.size_id
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id 
                WHERE sl.SKU_id = :SKU_id AND sl.location_id = :location_id AND sl.type = 1";
        $res = (new SafeMysql())->fetchOne($sql,
            [
                'SKU_id' => $SKU->getId(),
                'location_id' => $location->getId()
            ]
        );
        $sizes = $SKU->getMaxCapacity();
        $size_id = $res['size_id'];
        if (empty($sizes[$size_id]))
            return false;
        if ($res['summa'] == 0) {
            if ($qty <= $sizes[$size_id])
                return $qty;
            else
                return $sizes[$size_id];
        }
        if ($sizes[$size_id] <= $res['summa'])
            return false;
        return $sizes[$size_id] - $res['summa'];
    }

    /**
     * @param $SKU
     * @param int $batch_id
     * @param int $warehouse
     * @return array
     */
    public static function getLocationsFree($warehouse_id, $SKU, $batch_id = null)
    {
        $warehouse_id = (int)$warehouse_id;
        if ($batch_id)
            $batchesLocationInfo = SKULocations::getLocationsBySKUAndBatch($warehouse_id, $SKU->getId(), $batch_id, true);
        $location_name_select = Locations::getLocationNameSelect('l', 'w');
        $sql = "SELECT l.id,
                       $location_name_select name,
                       l.type_id,
                       l.size_id,
                       spl.priority,
                       sum_skus,
                       batch_number,
                       batch_expired
                FROM SKU_preff_locs spl
                    JOIN locations l ON location_id = l.id AND l.warehouse = :warehouse_id AND l.state = :active AND l.type_id in (:types)
                    JOIN warehouses w ON l.warehouse = w.id
                    LEFT JOIN (SELECT SKU_locations.SKU_id, location_id, SUM(quantity) sum_skus, batches.number as batch_number, batches.expired as batch_expired
                               FROM SKU_locations
                                   JOIN locations ON location_id = id AND warehouse = :warehouse_id
                                   LEFT JOIN batches ON batches.id = SKU_locations.batch_id 
                               GROUP BY SKU_id, location_id) sSKU ON sSKU.SKU_id = spl.SKU_id AND sSKU.location_id = spl.location_id
                WHERE spl.SKU_id = :SKU_id
                ORDER BY l.type_id, spl.priority";
        $locs = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'types' => Location::STORAGE_TYPES,
            'active' => Location::ACTIVE,
            'SKU_id' => $SKU->getId(),
        ]);
        list(, $qties) = SKUPreffLocs::getPercentST($warehouse_id, $SKU->getId());
        $sizes = $SKU->getMaxCapacity();
        $res = [];
        foreach ($locs as $loc) {
            if (!empty($batchesLocationInfo) && in_array($loc['id'], $batchesLocationInfo))
                continue;
            if (!empty($sizes[$loc['size_id']])) {
                $nums = (int)($sizes[$loc['size_id']] * (1 - (empty($qties[$loc['id']]) ? 0 : $qties[$loc['id']]))) - $loc['sum_skus'];
                if ($nums > 0)
                    $res[$loc['type_id']][$loc['name']] = [$loc['id'], $nums, $loc['batch_number'], self::convertDateExpired($loc['batch_expired'])];
            } else
                $res[$loc['type_id']][$loc['name']] = [$loc['id'], 'not established', $loc['batch_number'], self::convertDateExpired($loc['batch_expired'])];
        }
        return $res;
    }

    public static function getAvailableLocationsForAllSKUsInLocation($warehouse_id, Location $origin)
    {
        $sql = 'SELECT sl.SKU_id, sl.batch_id, SUM(sl.quantity) quantity, l.size_id, s.max_capacity
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id AND l.warehouse = :warehouse_id
                    JOIN SKUs s ON sl.SKU_id = s.id
                WHERE sl.location_id = :location_id
                GROUP BY sl.SKU_id';
        $SKUs_in_origin = (new SafeMysql())->fetchAssocWithKey($sql, [
            'warehouse_id' => $warehouse_id,
            'location_id' => $origin->getId(),
        ], 'SKU_id');
        $SKUs_in_origin = array_column($SKUs_in_origin, null, 'SKU_id');
        if (empty($SKUs_in_origin))
            return [];
        $SKU_ids = array_keys($SKUs_in_origin);
        array_walk($SKUs_in_origin, function (&$SKU_info) {
            $SKU_info['max_capacity'] = json_decode($SKU_info['max_capacity'], true);
        });
        // only ST
        // if (count($SKU_ids) == 1) {
        //     return self::getLocationsFree($warehouse_id, SKUs::getById($SKU_ids[0]), $SKUs_in_origin[$SKU_ids[0]]['batch_id']);
        $types = Location::STORAGE_TYPES;
        // get types that can contain mixed SKUs
        $types = array_diff($types, Location::UNIQUE_SKU_TYPES);
        $label_select = Locations::getLocationNameSelect('l', 'w');
        // get all SKUs and their quantities in locations assigned to SKUs
        $sql = "SELECT l.id,
                       spl.SKU_id,
                       s.max_capacity,
                       $label_select name,
                       l.type_id,
                       l.size_id,
                       spl.priority,
                       quantity,
                       batch_id,
                       batch_number,
                       batch_expired
                FROM SKU_preff_locs spl
                    JOIN locations l ON location_id = l.id AND l.warehouse = :warehouse_id AND l.state = 1 AND l.type_id IN (:types)
                    JOIN warehouses w ON l.warehouse = w.id
                    JOIN SKUs s ON spl.SKU_id = s.id
                    LEFT JOIN (SELECT SKU_locations.SKU_id, location_id, SUM(quantity) quantity, batches.number as batch_number, batches.expired as batch_expired, batch_id
                               FROM SKU_locations
                                   JOIN locations ON location_id = id AND warehouse = :warehouse_id
                                   LEFT JOIN batches ON batches.id = SKU_locations.batch_id 
                               GROUP BY SKU_id, location_id) sSKU ON sSKU.SKU_id = s.id AND sSKU.location_id = spl.location_id
                WHERE spl.location_id IN (SELECT DISTINCT location_id FROM SKU_preff_locs WHERE SKU_id IN (:SKU_ids))
                    AND spl.location_id <> :location_id
                ORDER BY l.type_id, spl.priority";
        $locs = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'types' => $types,
            'SKU_ids' => $SKU_ids,
            'location_id' => $origin->getId(),
        ]);
        $locs = Arr::groupBy($locs, 'id');
        $res = [];
        $sizes = Sizes::getAllSizesName($warehouse_id);
        foreach ($locs as $id => $SKUs_in_loc) {
            $SKU_ids_in_loc = array_column($SKUs_in_loc, 'SKU_id');
            // location must contain all(!) SKUs
            if (count(array_intersect($SKU_ids, $SKU_ids_in_loc)) != count($SKU_ids))
                continue;
            // check if location has enough space for all SKUs
            $loc = reset($SKUs_in_loc);
            $size_id = (int)$loc['size_id'];
            if (empty($sizes[$size_id][1])) {
                $res[$loc['type_id']][$loc['name']] = [$id, 'unknown location size', $loc['batch_number'], self::convertDateExpired($loc['batch_expired'])];
                continue;
            }
            // should I use volume or max capacity?...
            // $max_volume = $sizes[$size_id][1];
            $used_percents = 0.0;
            $needed_volume = 0;
            $mixed_batches = false;
            foreach ($SKUs_in_loc as $SKU_info) {
                $SKU_id = $SKU_info['SKU_id'];
                if ($SKU_info['quantity'] > 0) {
                    $max_capacity = json_decode($SKU_info['max_capacity'], true);
                    if (empty($max_capacity[$size_id])) {
                        $res[$loc['type_id']][$loc['name']] = [$id, 'contains not established SKUs', $loc['batch_number'], self::convertDateExpired($loc['batch_expired'])];
                        continue 2;
                    }
                    $used_percents += $SKU_info['quantity'] / $max_capacity[$size_id];
                }
                if (empty($SKUs_in_origin[$SKU_id]))
                    continue;
                // check batch id
                $origin_SKU = $SKUs_in_origin[$SKU_id];
                if ($origin_SKU['batch_id'] != $SKU_info['batch_id'] && $SKU_info['quantity'] > 0) {
                    $mixed_batches = true;
                    continue;
                }
                if (empty($origin_SKU['max_capacity'][$size_id])) {
                    $res[$loc['type_id']][$loc['name']] = [$id, 'not established', $loc['batch_number'], self::convertDateExpired($loc['batch_expired'])];
                    continue 2;
                }
                $needed_volume += $origin_SKU['quantity'] / $origin_SKU['max_capacity'][$size_id];
            }
            if ($mixed_batches) {
                $res[$loc['type_id']][$loc['name']] = [$id, 'mixed batches', $loc['batch_number'], self::convertDateExpired($loc['batch_expired'])];
                continue;
            }
            if (($used_percents + $needed_volume) > 1) {
                $res[$loc['type_id']][$loc['name']] = [$id, 'not enough space (' . round($used_percents * 100) . '% used)', $loc['batch_number'], self::convertDateExpired($loc['batch_expired'])];
                continue;
            }
            $res[$loc['type_id']][$loc['name']] = [$id, round($used_percents * 100) . '% used', $loc['batch_number'], self::convertDateExpired($loc['batch_expired'])];
        }
        return $res;
    }

    private static function convertDateExpired($batch_expired)
    {
        if ($batch_expired) {
            $expired_object = (new TimeZonesConvertor())->setDateByString($batch_expired)->getDate()->setTime(0, 0);
            return $expired_object->format(WithDate::DATE_FRONT_FORMAT_WITHOUT_TIME);
        }

        return null;
    }

    public static function getAvailable($warehouse_id, $SKU_id, $type_id = Location::PF)
    {
        $sql = "SELECT SUM(quantity)
                FROM SKU_locations
                    JOIN locations ON location_id = id AND type_id = :type_id AND warehouse = :warehouse_id
                WHERE SKU_id = :SKU_id";
        return (new SafeMysql())->fetchScalar($sql, [
            'warehouse_id' => (int) $warehouse_id,
            'type_id' => $type_id,
            'SKU_id' => $SKU_id
        ]);
    }

    public static function getBusy(int $warehouse_id, $SKU_id, $locs)
    {
        $sql = "SELECT SKU_id, size_id, location_id, SUM(quantity) qty
                FROM SKU_locations
                    JOIN locations ON location_id = id AND warehouse = :warehouse_id
                WHERE SKU_id <> :SKU_id AND location_id IN (:locs)
                GROUP BY SKU_id, size_id, location_id";

        return (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'SKU_id' => $SKU_id,
            'locs' => $locs
        ]);
    }

    public static function getLocationWithSKU($warehouse_id, $SKU_id, $cart_id = false)
    {
        $location_name_select = Locations::getLocationNameSelect('l', 'w');
        $sql = "SELECT sl.location_id, l.type_id, $location_name_select name, SUM(sl.quantity) quantity,
                CONCAT(u.login, ' #', u.id) user_name
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id AND l.warehouse = :warehouse_id AND l.type_id NOT IN (:types) AND l.state = :active
                    JOIN warehouses w ON l.warehouse = w.id
                    left JOIN users u ON u.id = l.row and u.role_id in (:role)
                WHERE sl.SKU_id = :SKU_id AND (l.type_id != 'US' OR u.id IS NOT NULL)
                and sl.order_id IS NULL AND ((sl.type = 1 AND sl.reason = 2) OR sl.type = 4)
                GROUP BY sl.location_id
                ORDER BY l.type_id, name";
        $locs = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'types' => [Location::DH, Location::OB, Location::TP, Location::LP],
            'active' => Location::ACTIVE,
            'SKU_id' => $SKU_id,
            'role' => [Roles::WORKER, Roles::MANAGER]
        ]);
        $res = [];
        foreach ($locs as $loc) {
            if ($cart_id !== false)
                if (Location::isCA($loc['type_id']) && $loc['location_id'] != $cart_id)
                    continue;
            $nameLocation = $loc['type_id'] == 'US' ? $loc['user_name'] : $loc['name'];
            $res[$loc['type_id']][$nameLocation] = [$loc['location_id'], $loc['quantity']];
        }
        return $res;
    }

    public static function checkAssigned($warehouse_id, array $SKU_ids)
    {
        if (empty($SKU_ids))
            return false;
        $sql = "SELECT SUM(count)
                FROM (SELECT COUNT(DISTINCT SKU_id) as count
                      FROM SKU_preff_locs
                          JOIN locations ON location_id = id AND warehouse = :warehouse_id
                      WHERE SKU_id IN (:SKU_ids)
                      GROUP BY SKU_id) as tmp";
        $result = (new SafeMysql())->fetchScalar($sql, [
            'warehouse_id' => $warehouse_id,
            'SKU_ids' => array_map('intval', $SKU_ids)
        ]);
        return count($SKU_ids) == $result;
    }

    public static function getStoragePalletSpaceUtilized($supplier_id, $warehouse_id)
    {
        $supplier_id = (int)$supplier_id;
        return (int)(new SafeMysql())->fetchScalar("SELECT SUM(s.width * s.height * s.depth * 0.000001 * sl.quantity)
                                  FROM SKU_preff_locs spl
                                      JOIN SKUs s ON spl.SKU_id = s.id AND s.supplier_id = :supplier_id
                                      JOIN locations l ON spl.location_id = l.id AND l.type_id = :type_id AND l.warehouse = :warehouse_id
                                      JOIN SKU_locations sl ON spl.SKU_id = sl.SKU_id AND spl.location_id = sl.location_id", [
            'supplier_id' => $supplier_id,
            'type_id' => Location::ST,
            'warehouse_id' => $warehouse_id
        ]);
    }

    // Предназначена только для перемещения между разными локациями (не для picking, dropping и т.д.), т.е. без изменения type и reason

    /**
     * @param $loc_from
     * @param $loc_to
     * @param $SKU
     * @param $batch_id
     * @param $qty_user
     * @param $reason
     * @param $user_id
     * @param  bool  $reserve
     * @return array|bool[]
     */
    public static function createMovement(Location $loc_from, Location $loc_to, SKU $SKU, $batch_id, $qty_user, $reason, $user_id, $reserve = false, $expired = false)
    {
        if ($expired) {
            $type_sl = 4;
            $reason_sl = null;
        } else {
            $type_sl = 1;
            $reason_sl = '2';
        }
        if (empty($loc_from))
            return [false, 'Source location not found'];
        if (empty($loc_to))
            return [false, 'Destination location not found'];
        if (empty($SKU))
            return [false, 'SKU not found'];
        if (!SKULocation::isMoving($loc_from->getTypeId(), $loc_to->getTypeId(), $type_sl, $reason_sl))
            return [false, "Movement is impossible. SKU can not be taken or put"];
        $place_size = 0;
        if (!empty($SKU->getMaxCapacity()))
            $place_size = (int)$SKU->getMaxCapacity()[$loc_to->getSizeId()] ?? 0;
        if ($place_size <= 0)
            return [false, "SKU has not size for this location"];
        if (!Location::isCart($loc_to->getTypeId()) && !Location::isNotCounted($loc_to->getTypeId()) && SKULocations::getCountFreeInLocation($loc_to->getWarehouse(), $SKU, $loc_to) < $qty_user)
            return [false, "Not enough space in destination location"];
        $loc_spec = Locations::getByCancelLocation((int)$loc_to->getWarehouse());
        $loc_exp = Locations::getByExpiredLocation((int)$loc_to->getWarehouse());
        if ((empty($loc_spec) || (int)$loc_spec[0]->getId() !== (int)$loc_to->getId())
            && (empty($loc_exp) || (int)$loc_exp[0]->getId() !== (int)$loc_to->getId())
            && SKUPreffLocs::checkExistPreffLoc((int)$loc_to->getWarehouse(), (int)$SKU->getId(), (int)$loc_to->getId(), $loc_to->getTypeId()) === false)
            return [false, Movement::ERROR_NOT_LOC_IN_SKU];
        $num = 0;
        $mysql = new SafeMysql();
        while (true) {
            $mysql->beginTransaction();
            try {
                if (!($batches = SKULocations::subLocation($SKU->getId(), $loc_from->getId(), $qty_user, $type_sl, $reason_sl, 0, $batch_id, $reserve, $loc_from->DH()))) {
                    $mysql->rollback();
                    return [false, 'Invalid source location. Contact system administrator'];
                }
                $batches_unique = array_unique(Arr::column($batches, 'batch_id'));
                if (count($batches_unique) > 1 && ($loc_to->PF() || ($loc_to->getTypeId() == Location::ST && !$expired))) {
                    $mysql->rollback();
                    return [false, 'Cannot mix batches in destination location'];
                }
                if ($loc_from->DH()) {
                    $loc = Locations::getByName(Locations::getWarehouseById($loc_from->getId()), LOC_DH_P[0], LOC_DH_P[1], LOC_DH_P[2], LOC_DH_P[3]);
                    if (!SKULocations::addLocation($loc->getId(), $SKU->getId(), $batches)) {
                        $mysql->rollback();
                        return [false, 'Invalid destination location. Contact system administrator'];
                    }

                } elseif ($loc_to->DH()) {
                    $loc = Locations::getByName(Locations::getWarehouseById($loc_to->getId()), LOC_DH_M[0], LOC_DH_M[1], LOC_DH_M[2], LOC_DH_M[3]);
                    $loc_to = $loc;
                }

                if (!SKULocations::addLocation($loc_to->getId(), $SKU->getId(), $batches)) {
                    $mysql->rollback();
                    return [false, 'Invalid destination location. Contact system administrator'];
                }
                $move_id = Movements_::movement($loc_from->getId(), $loc_to->getId(), $SKU->getId(), $batch_id, $qty_user, $user_id);
                SKUHistories::movement($loc_from->getId(), $loc_to->getId(), $SKU->getId(), $qty_user, $reason, $move_id, $user_id, $loc_from->getWarehouse());
                SKUHistories::changeStockTwoLocation($loc_from->getId(), $loc_to->getId(), $SKU->getId(), $loc_from->getWarehouse());
            } catch (\Exception $e) {
                $mysql->rollback();
                if ($e instanceof DBException && $e->isDeadlock($num++))
                    continue;
                Debug::sendDebugInfo('Create movement confirmation', var_export([$e->getCode(), $e->getMessage()], 1));
                return [false, 'Operation is not complete. Try again'];
            }
            $mysql->commit();
            break;
        }
        return [true];
    }

    public static function createUnrestrictedMovement(Location $loc_from, Location $loc_to, SKU $SKU, $qty_user, $reason, $user_id/*, $reserve = false, $expired = false*/)
    {
        if (empty($loc_from))
            return [false, 'Source location not found'];
        if (empty($loc_to))
            return [false, 'Destination location not found'];
        if (empty($SKU))
            return [false, 'SKU not found'];
        if (!($loc_from->allowed() || $loc_from->PR()) || !($loc_to->notCounted() || $loc_to->cart()))
            return [false, "Movement is impossible. SKU can not be taken or put"];
        $num = 0;
        $mysql = new SafeMysql();
        while (true) {
            $mysql->beginTransaction();
            try {
                if (!($batches = SKULocations::subLocation($SKU->getId(), $loc_from->getId(), $qty_user, 1, '2', 0, -1, false, $loc_from->DH()))) {
                    $mysql->rollback();
                    return [false, 'Invalid source location. Contact system administrator'];
                }
                if ($loc_from->DH()) {
                    $loc = Locations::getByName(Locations::getWarehouseById($loc_from->getId()), LOC_DH_P[0], LOC_DH_P[1], LOC_DH_P[2], LOC_DH_P[3]);
                    if (!SKULocations::addLocation($loc->getId(), $SKU->getId(), $batches)) {
                        $mysql->rollback();
                        return [false, 'Invalid destination location. Contact system administrator'];
                    }

                } elseif ($loc_to->DH()) {
                    $loc = Locations::getByName(Locations::getWarehouseById($loc_to->getId()), LOC_DH_M[0], LOC_DH_M[1], LOC_DH_M[2], LOC_DH_M[3]);
                    $loc_to = $loc;
                }

                if (!SKULocations::addLocation($loc_to->getId(), $SKU->getId(), $batches)) {
                    $mysql->rollback();
                    return [false, 'Invalid destination location. Contact system administrator'];
                }
                $move_id = Movements_::movement($loc_from->getId(), $loc_to->getId(), $SKU->getId(), -1, $qty_user, $user_id);
                SKUHistories::movement($loc_from->getId(), $loc_to->getId(), $SKU->getId(), $qty_user, $reason, $move_id, $user_id, $loc_from->getWarehouse());
                SKUHistories::changeStockTwoLocation($loc_from->getId(), $loc_to->getId(), $SKU->getId(), $loc_from->getWarehouse());
            } catch (\Exception $e) {
                $mysql->rollback();
                if ($e instanceof DBException && $e->isDeadlock($num++))
                    continue;
                Debug::sendDebugInfo('Create processing movement confirmation', var_export([$e->getCode(), $e->getMessage()], 1));
                return [false, 'Operation is not complete. Try again'];
            }
            $mysql->commit();
            break;
        }
        return [true];
    }

    public static function getCountNotOrder($loc_id, $SKU_id)
    {
        $sql = "SELECT IFNULL(SUM(quantity), 0) quantity
                FROM SKU_locations
                WHERE SKU_id = :SKU_id AND
                      location_id = :location_id AND
                      order_id IS NULL AND
                      type = 1 AND
                      reason = '2'
                LIMIT 1";
        return (new SafeMysql())->fetchScalar($sql, ['SKU_id' => $SKU_id, 'location_id' => $loc_id]);
    }

    public static function getUnreservedCount($SKU_id, $warehouse_id = null): int
    {
        $SKU_id = (int)$SKU_id;
        $sql = "SELECT SUM(sl.quantity)
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id AND l.type_id IN (:types) " . ($warehouse_id ? 'AND l.warehouse = :warehouse_id' : '') . "
                WHERE sl.SKU_id = :SKU_id AND
                      sl.order_id IS NULL AND
                      sl.type = 1 AND 
                      sl.reason = '2'";
        $params = [
            'types' => Location::STORAGE_TYPES,
            'SKU_id' => $SKU_id
        ];
        if ($warehouse_id) {
            $params['warehouse_id'] = $warehouse_id;
        }
        return (int)(new SafeMysql())->fetchScalar($sql, $params);
    }

    public static function isReserve($loc_id)
    {
        $sql = "SELECT COUNT(*)
                FROM SKU_locations
                WHERE location_id = :location_id AND (type <> 1 OR reason <> '2')";
        $nums = (new SafeMysql())->fetchScalar($sql, ['location_id' => $loc_id]);
        return (bool)$nums;
    }

    public static function fixedDimensions($warehouse_id)
    {
        $warehouse_id = (int)$warehouse_id;
        $locs = (new SafeMysql())->fetchAll("SELECT sl.location_id, l.size_id, sl.SKU_id, SUM(sl.quantity) quantity, s.max_capacity, CONCAT(l.warehouse, '-', l.type_id, '-', l.zone, '-', l.`column`, '-', l.row) name
                                  FROM SKU_locations sl
                                      JOIN locations l ON sl.location_id = l.id AND l.warehouse = :warehouse_id
                                      JOIN SKUs s ON s.id = sl.SKU_id
                                  GROUP BY sl.location_id, sl.SKU_id
                                  ORDER BY sl.location_id", ['warehouse_id' => $warehouse_id]);
        $names = [];
        foreach ($locs as &$loc) {
            $max_capacity = json_decode($loc['max_capacity'], 1);
            $loc['max_capacity'] = $max_capacity[$loc['size_id']];
            $names[$loc['location_id']] = $loc['name'];
        }
        unset($loc);
        $tmp = [];
        foreach ($locs as $loc) {
            $tmp[$loc['location_id']][$loc['SKU_id']] = empty($loc['max_capacity']) ? false : ($loc['quantity'] / $loc['max_capacity']);
        }
        $res = [];
        foreach ($tmp as $loc_id => $loc) {
            $measure = 0;
            $sum = 0;
            foreach ($loc as $SKU_id => $percent)
                if ($percent === false)
                    $measure++;
                else
                    $sum += $percent;
            if ($sum > 1 || $measure)
                $res[$loc_id] = [$sum, $measure];
        }
        foreach ($res as $loc_id => $percent)
            if ($percent[1] > 0)
                echo "$loc_id: {$percent[0]}; {$percent[1]}\n";
        echo "\n\n";
        foreach ($res as $loc_id => $percent)
            if ($percent[0] > 1)
                echo "$loc_id: {$percent[0]}; {$percent[1]}\n";
        echo "\n\n";
        foreach ($res as $loc_id => $percent)
            if ($percent[0] > 1)
                echo "{$names[$loc_id]}\n";
    }

    public static function setCompleted($order_id, $user_id)
    {
        $order_id = (int)$order_id;
        $user_id = (int)$user_id;
        (new SafeMysql())->execute("UPDATE SKU_locations
                        SET type = 31, reason='$user_id'
                        WHERE type = 3 AND order_id = $order_id");
    }

    public static function getOrdersBySKU($warehouse_id, $SKU_id, $order_id, $available_suppliers = [])
    {
        // Rows are joined with RS (\x1e) and batch fields with US (\x1f) instead of HTML, so DB values
        // can be HTML-escaped below before the '<b>Batch#:</b> ...<br/>' markup is rebuilt.
        $sql = "SELECT CONCAT(o.ref, ' (#', o.id, ')') ref,
                       o.status,
                       GROUP_CONCAT(IFNULL(CONCAT(b.number, '\x1f', sl.quantity, '\x1f', DATE_FORMAT(b.expired, '%D %b %Y')), '') SEPARATOR '\x1e') batches,
                       IFNULL(os.quantity, 0) quantity,
                       GROUP_CONCAT(IFNULL(CONCAT(IF(w.label = '' OR w.label IS NULL, l.warehouse, w.label), '-', l.type_id, '-', l.zone, '-', l.`column`, '-', l.row, IF(sl.order_id IS NULL, ' (reserved)', ''), IF(sl.quantity <> os.quantity, CONCAT(' (', sl.quantity, ' item' , IF(sl.quantity > 1, 's', ''), ')'), '')), 'Not yet allocated') SEPARATOR '\x1e') consist
                FROM orders o
                    JOIN order_SKUs os ON o.id = os.order_id AND os.SKU_id = :SKU_id
                    LEFT JOIN SKU_locations sl ON os.SKU_id = sl.SKU_id AND (os.order_id = sl.order_id OR (sl.order_id IS NULL AND sl.type = 1 AND POSITION('2_' IN sl.reason) = 1 AND SUBSTRING_INDEX(sl.reason, '_', -1) = os.order_id))  
                    LEFT JOIN locations l ON sl.location_id = l.id AND l.warehouse = :warehouse_id
                    LEFT JOIN warehouses w ON l.warehouse = w.id 
                    LEFT JOIN batches b ON sl.batch_id = b.id 
                WHERE o.status NOT IN (:not_status) AND o.warehouse_id = :warehouse_id " . ($order_id ? 'AND o.id <> :order_id' : '') . " AND
                      o.supplier_id IN (:available_suppliers)
                GROUP BY o.id";
        $params = [
            'SKU_id' => $SKU_id,
            'not_status' => [Order::CANCELLED_NUM, Order::DESPATCHED_NUM, Order::RETURNED_NUM],
            'warehouse_id' => $warehouse_id,
            'available_suppliers' => array_map('intval', $available_suppliers),
            'order_id' => $order_id
        ];

        $orders = (new SafeMysql())->fetchAll($sql, $params);
        foreach ($orders as &$order) {
            $order['batches'] = implode('<br/>', array_map(function ($batch) {
                if ($batch === '')
                    return '';
                $parts = explode("\x1f", $batch);

                return '<b>Batch#:</b> ' . View::toHtml($parts[0]) . ' (' . View::toHtml($parts[1] ?? '') . ' units)<br/><b>Exp#:</b> ' . View::toHtml($parts[2] ?? '');
            }, explode("\x1e", (string)$order['batches'])));
            $order['consist'] = implode('<br/>', array_map([View::class, 'toHtml'], explode("\x1e", (string)$order['consist'])));
        }
        unset($order);

        return $orders;
    }

    public static function cancelOrderReserve($SKU, Order $order)
    {
        $SKU_id = $SKU->getId();
        $order_id = $order->getId();
        $sql = "SELECT sl.*, type_id
                FROM SKU_locations sl
                    JOIN locations ON location_id = id AND warehouse = :warehouse_id
                WHERE SKU_id = :SKU_id AND
                      type = 1 AND ((order_id IS NULL AND reason = :reason_reserved) OR
                                    (order_id IS NULL AND reason = :reason_free) OR (order_id = :order_id2  AND reason = '1'))";
        $locsRES = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $order->getWarehouseId(),
            'SKU_id' => $SKU_id,
            'reason_reserved' => '2_' . $order_id,
            'reason_free' => '2',
            'order_id2' => $order_id
        ]);
        $free = [];
        $reserved = [];
        $in_order = [];
        foreach ($locsRES as $locRES)
            if (is_null($locRES['order_id']) && $locRES['reason'] == '2')
                $free[] = $locRES;
            elseif (is_null($locRES['order_id']) && $locRES['reason'] == '2_' . $order_id)
                $reserved[] = $locRES;
            else
                $in_order[] = $locRES;

        $existFreeInLoc = function ($l) use ($free) {
            return !empty(array_filter($free, function ($f_loc) use ($l) {
                return $f_loc['location_id'] == $l['location_id']
                    && $f_loc['batch_id'] == $l['batch_id'];
            }));
        };
        $batch_where = function ($b) {
            return $b === null ? 'IS NULL' : '= :batch_id';
        };
        $label = Warehouses::getLabel($order->getWarehouseId());
        $num = 0;
        $mysql = new SafeMysql();
        while (true) {
            $mysql->beginTransaction();
            try {
                foreach ($reserved as $res_loc) {
                    $loc_tmp = Locations::getById($res_loc['location_id']);
                    OrderHistories::cancelReservedSKUForLarge($order_id, $SKU, Batches::getById($res_loc['batch_id']), $res_loc['quantity'], $loc_tmp->getLocation($label), true);
                    $params = [
                        'SKU_id' => $SKU_id,
                        'location_id' => $res_loc['location_id'],
                        'quantity' => $res_loc['quantity'],
                        'batch_id' => $res_loc['batch_id'],
                        'reason_reserved' => "2_$order_id",
                    ];
                    if ($existFreeInLoc($res_loc)) {
                        $mysql->execute("
                            DELETE FROM SKU_locations
                            WHERE SKU_id = :SKU_id AND
                                  location_id = :location_id AND
                                  type = 1 AND
                                  reason = :reason_reserved AND
                                  order_id IS NULL AND
                                  batch_id " . $batch_where($res_loc['batch_id']), $params);
                        $mysql->execute("
                            UPDATE SKU_locations
                            SET quantity = quantity + :quantity
                            WHERE SKU_id = :SKU_id AND
                                  location_id = :location_id AND
                                  type = 1 AND
                                  reason = '2' AND
                                  batch_id " . $batch_where($res_loc['batch_id']), $params);
                    } else {
                        $exist = $mysql->fetchScalar("
                            SELECT 1
                            FROM SKU_locations
                            WHERE SKU_id = :SKU_id AND
                                  order_id IS NULL AND
                                  location_id = :location_id AND
                                  type = 1 AND
                                  reason = '2' AND
                                  batch_id " . $batch_where($res_loc['batch_id']), $params);
                        if (!$exist)
                            $mysql->execute("
                                UPDATE SKU_locations
                                SET reason = '2'
                                WHERE SKU_id = :SKU_id AND
                                      order_id IS NULL AND
                                      location_id = :location_id AND
                                      type = 1 AND
                                      reason = :reason_reserved AND
                                      batch_id " . $batch_where($res_loc['batch_id']), $params);
                        else {
                            $mysql->execute("
                                UPDATE SKU_locations
                                SET quantity = quantity + :quantity
                                WHERE SKU_id = :SKU_id AND
                                      order_id IS NULL AND
                                      location_id = :location_id AND
                                      type = 1 AND
                                      reason = '2' AND
                                      batch_id " . $batch_where($res_loc['batch_id']), $params);
                            $mysql->execute("
                                DELETE FROM SKU_locations
                                WHERE SKU_id = :SKU_id AND
                                      order_id IS NULL AND
                                      location_id = :location_id AND
                                      type = 1 AND
                                      reason = :reason_reserved AND
                                      batch_id " . $batch_where($res_loc['batch_id']), $params);
                        }
                    }
                }
                foreach ($in_order as $res_loc) {
                    if ((int)$res_loc['order_id'] > 0 && $res_loc['reason'] == '1') {
                        $params = [
                            'SKU_id' => $SKU_id,
                            'order_id' => $order_id,
                            'location_id' => $res_loc['location_id'],
                            'quantity' => $res_loc['quantity'],
                            'batch_id' => $res_loc['batch_id'],
                        ];
                        if ($existFreeInLoc($res_loc)) {
                            $mysql->execute("
                                DELETE FROM SKU_locations
                                WHERE SKU_id = :SKU_id AND
                                      order_id = :order_id AND
                                      location_id = :location_id AND
                                      type = 1 AND
                                      reason = '1' AND
                                      batch_id " . $batch_where($res_loc['batch_id']), $params);
                            $mysql->execute("
                                UPDATE SKU_locations
                                SET quantity = quantity + :quantity
                                WHERE SKU_id = :SKU_id AND
                                      location_id = :location_id AND
                                      type = 1 AND
                                      reason = '2' AND
                                      batch_id " . $batch_where($res_loc['batch_id']), $params);
                        } else {
                            $exist = $mysql->fetchScalar("
                                SELECT 1
                                FROM SKU_locations
                                WHERE SKU_id = :SKU_id AND
                                      order_id IS NULL AND
                                      location_id = :location_id AND
                                      type = 1 AND
                                      reason = '2' AND
                                      batch_id " . $batch_where($res_loc['batch_id']), $params);
                            if (!$exist)
                                $mysql->execute("
                                    UPDATE SKU_locations
                                    SET order_id = NULL, reason = '2'
                                    WHERE SKU_id = :SKU_id AND
                                          order_id = :order_id AND
                                          location_id = :location_id AND
                                          type = 1 AND
                                          reason = '1' AND
                                          batch_id " . $batch_where($res_loc['batch_id']), $params);
                            else {
                                $mysql->execute("
                                    UPDATE SKU_locations
                                    SET quantity = quantity + :quantity
                                    WHERE SKU_id = :SKU_id AND
                                          order_id IS NULL AND
                                          location_id = :location_id AND
                                          type = 1 AND
                                          reason = '2' AND
                                          batch_id " . $batch_where($res_loc['batch_id']), $params);
                                $mysql->execute("
                                    DELETE FROM SKU_locations
                                    WHERE SKU_id = :SKU_id AND
                                          order_id = :order_id AND
                                          location_id = :location_id AND
                                          type = 1 AND
                                          reason = '1' AND
                                          batch_id " . $batch_where($res_loc['batch_id']), $params);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $mysql->rollback();
                if ($e instanceof DBException && $e->isDeadlock($num++)) {
                    Debug::sendDebugInfo('cancelReserveSKU!', "Error is fixed! {$e->getMessage()}");
                    continue;
                }
                Debug::sendDebugInfo('cancelReserveSKU', var_export([$e->getCode(), $e->getMessage()], 1));
                return false;
            }
            $mysql->commit();
            break;
        }
        return true;
    }

    public static function reserveSKUInLocation($SKU_id, $location, $quantity, $order_id)
    {
        $res_order = "2_$order_id";
        $mysql = new SafeMysql();
        if ($quantity > 0) {
            $batch_id = $location['batch_id'] ? (int)$location['batch_id'] : null;
            $where = "SKU_id = :SKU_id AND location_id = :location_id AND order_id IS NULL AND type = 1 AND batch_id " . ($batch_id === null ? 'IS NULL' : '= :batch_id');
            $params = [
                'SKU_id' => $SKU_id,
                'location_id' => $location['location_id'],
                'batch_id' => $batch_id,
            ];
            if ($location['quantity'] > $quantity) {
                $mysql->execute("INSERT INTO SKU_locations (SKU_id, location_id, quantity, order_id, type, reason, batch_id)
                                    VALUES (:SKU_id, :location_id, :quantity, NULL, 1, :res_order, :batch_id)",
                    array_merge($params, ['quantity' => $quantity, 'res_order' => $res_order]));
                $mysql->execute("UPDATE SKU_locations
                                    SET quantity = quantity - :quantity
                                    WHERE $where AND reason = '2'",
                    array_merge($params, ['quantity' => $quantity]));
            }
            if ($quantity == $location['quantity']) {
                $mysql->execute("UPDATE SKU_locations
                                    SET reason = :res_order
                                    WHERE $where AND reason = '2'",
                    array_merge($params, ['res_order' => $res_order]));
            }
        }
    }

    public static function getReserveInTrolley($order_id, $SKU_id)
    {
        $order_id = (int)$order_id;
        $SKU_id = (int)$SKU_id;
        $sql = "SELECT SUM(quantity)
                FROM SKU_locations
                    JOIN locations ON location_id = id AND type_id IN (:types)
                WHERE SKU_id = :SKU_id AND 
                      order_id IS NULL AND
                      type = 1 AND 
                      reason = :reason";
        return (int)(new SafeMysql())->fetchScalar($sql, [
            'types' => Location::CART_TYPES,
            'SKU_id' => $SKU_id,
            'reason' => '2_' . $order_id
        ]);
    }

    // Prepare SKU for picking
    public static function prepare4PickingSKU($warehouse_id, $order_id, SKU $SKU, $quantity, $large = true)
    {
        $SKU_id = $SKU->getId();
        if ($large) {
            $q = self::moveToReserveSKU($warehouse_id, $order_id, $SKU, Location::STORAGE_TYPES);
            if ($q === false)
                return false;
            $q = (int)$q;
            if ($quantity < $q)
                return false;
            return $q;
        }
        $first = $quantity;
        $q = self::moveToReserveSKU($warehouse_id, $order_id, $SKU);
        if ($q === false)
            return false;
        $q0 = self::getReserveInTrolley($order_id, $SKU_id);
        if ($quantity == $q && !$q0)
            return $quantity;
        $quantity -= ($q + $q0);
        $sql = "SELECT SKU_locations.*
                FROM SKU_locations
                    JOIN locations ON location_id = id AND type_id = :type_id
                WHERE SKU_id = :SKU_id AND 
                      order_id IS NULL AND
                      type = 1 AND
                      POSITION('2_' IN reason) = 1 AND
                      SUBSTRING_INDEX(reason, '_', -1) = :order_id";
        $res_locs = (new SafeMysql())->fetchAll($sql, [
            'type_id' => Location::PF,
            'SKU_id' => $SKU_id,
            'order_id' => $order_id
        ]);
        $qty = array_sum(Arr::column($res_locs, 'quantity'));
        if ($qty != $quantity)
            return $qty;
        $quantity = $first - $q0;
        $label = Warehouses::getLabel($warehouse_id);
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        try {
            foreach ($res_locs as $loc) {
                $order_id = substr($loc['reason'], 2);
                $where_batch = $loc['batch_id'] === null ? 'IS NULL' : '= :batch_id';
                $params = [
                    'SKU_id' => $loc['SKU_id'],
                    'location_id' => $loc['location_id'],
                    'order_id' => $order_id,
                    'batch_id' => $loc['batch_id'],
                ];
                $exist = $mysql->fetchScalar("SELECT 1
                        FROM SKU_locations
                        WHERE SKU_id = :SKU_id AND
                              location_id = :location_id AND
                              type = 1 AND
                              reason = '1' AND
                              order_id = :order_id AND
                              batch_id $where_batch", $params);
                if ($exist) {
                    $mysql->execute("UPDATE SKU_locations
                            SET quantity = quantity + :quantity
                            WHERE SKU_id = :SKU_id AND
                                  location_id = :location_id AND
                                  type = 1 AND
                                  reason = '1' AND
                                  order_id = :order_id AND
                                  batch_id $where_batch",
                        array_merge($params, ['quantity' => $loc['quantity']]));
                    $mysql->execute("DELETE FROM SKU_locations
                            WHERE SKU_id = :SKU_id AND
                                  location_id = :location_id AND
                                  type = 1 AND
                                  reason = :reason AND
                                  order_id IS NULL AND
                                  batch_id $where_batch",
                        array_merge($params, ['reason' => $loc['reason']]));
                } else {
                    $mysql->execute("UPDATE SKU_locations
                            SET order_id = :order_id, reason = '1'
                            WHERE SKU_id = :SKU_id AND
                                  location_id = :location_id AND
                                  type = 1 AND
                                  reason = :reason AND
                                  order_id IS NULL AND
                                  batch_id $where_batch",
                        array_merge($params, ['reason' => $loc['reason']]));
                }
            }
        } catch (\Exception $e) {
            $mysql->rollback();
            return false;
        }
        $mysql->commit();
        return $quantity;
    }

    public static function getSKUBatchByBarcode($barcode, $locations_need, $suppliers_need, $batches_need, $type, $reason)
    {
        if (empty($locations_need))
            return false;
        if (empty($batches_need))
            return false;
        $params = [
            'type' => $type,
            'location_ids' => !is_array($locations_need) ? [(int)$locations_need] : array_map('intval', $locations_need),
        ];
        $where_suppliers = '';
        if (!empty($suppliers_need)) {
            $params['supplier_ids'] = !is_array($suppliers_need) ? [(int)$suppliers_need] : array_map('intval', $suppliers_need);
            $where_suppliers = ' AND s.supplier_id IN (:supplier_ids)';
        }
        $batches = array_map('intval', Arr::cleanEmptyVal($batches_need));
        $batches_null = count($batches_need) != count($batches);
        $where_reason = empty($reason) ? '' : " AND sl.reason = '" . addslashes($reason) . "'";
        $where_batches[0] = (!empty($batches) ? ('sl.batch_id IN (' . implode(',', $batches) . ')') : '');
        $where_batches[1] = ($batches_null ? 'sl.batch_id IS NULL' : '');
        $where_batches = Arr::cleanEmptyVal($where_batches);
        switch (count($where_batches)) {
            case 0:
                $where_batches = '';
                break;
            case 1:
                $where_batches = 'AND ' . implode('', $where_batches);
                break;
            case 2:
                $where_batches = 'AND (' . implode(' OR ', $where_batches) . ')';
                break;
        }
        $SKU_ids = SKUBarcodes::getSKUIdsByBarcode(trim($barcode));
        $where_barcode = $SKU_ids ? "s.id IN (" . implode(', ', $SKU_ids) . ")" : "FALSE";
        $sql = "SELECT DISTINCT CONCAT(sl.SKU_id, '_', IFNULL(sl.batch_id, '.')) id,
                                CONCAT(sup.name, IFNULL(CONCAT(' (Batch#: ', b.number, '; Exp#: ', DATE_FORMAT(b.expired, '%D %b %Y'), ')'), '')) name,
                                sl.location_id, CONCAT(IF(w.label > '', w.label, w.id), '-', l.type_id, '-', l.zone, '-', l.`column`, '-', l.row) loc_name
                FROM SKU_locations sl
                    JOIN SKUs s ON sl.SKU_id = s.id $where_suppliers AND $where_barcode
                    JOIN suppliers sup ON s.supplier_id = sup.id
                    JOIN locations l ON sl.location_id = l.id
                    JOIN warehouses w ON w.id = l.warehouse
                    LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
                WHERE sl.location_id IN (:location_ids)
                      $where_batches AND
                      sl.type = :type $where_reason";
        return (new SafeMysql())->fetchAll($sql, $params);
    }

    public static function getInfoType($type, $reason, $order_id)
    {
        if ($type == 1) {
            if ($reason == 1)
                return "Order #$order_id";
            elseif ($reason == 2 && $reason != '2')
                return 'Reserved<br/>for order #' . substr($reason, 2);
        } elseif (substr($type, 0, 1) == 2)
            return 'Picking (' . Pick::getMode(substr($type, 1)) . ")<br/>for order #$order_id";
        elseif (substr($type, 0, 1) == 3) {
            if ($type == '3')
                $str = 'Dropped';
            elseif ($type == '31')
                $str = 'Packing';
            return "$str<br/>for order #$order_id";
        } elseif ($type == 4)
            return '<span class="text-danger font-weight-bold">Expired</span>';
        return '-';
    }

    public static function getSKULocationsBySizes($warehouse_id, $SKUId, array $sizes): array
    {
        if (empty($sizes))
            return [];
        $sql = "SELECT MAX(quantity) quantity, size_id, size_name, location_name
                FROM (
                      SELECT SUM(sl.quantity) as quantity,
                             l.size_id,
                             s.name as size_name,
                             CONCAT(IF(w.label = '' OR w.label IS NULL, l.warehouse, w.label), '-', l.type_id, '-', l.zone, '-', l.column, '-', l.row) as location_name
                      FROM SKU_preff_locs spl
                          JOIN SKU_locations sl ON spl.location_id = sl.location_id AND spl.SKU_id = sl.SKU_id
                          INNER JOIN locations l ON l.id = sl.location_id AND l.warehouse = :warehouse_id AND l.type_id IN (:types)
                          INNER JOIN sizes s ON s.id = l.size_id
                          JOIN warehouses w ON l.warehouse = w.id
                      WHERE spl.SKU_id = :SKU_id AND l.size_id IN (:sizes)
                      GROUP BY sl.location_id, l.size_id
                      ORDER BY l.size_id, SUM(sl.quantity) DESC
                     ) tmp_table
                GROUP BY size_id";
        return (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'types' => Location::STORAGE_TYPES,
            'SKU_id' => $SKUId,
            'sizes' => array_map('intval', $sizes)
        ]);
    }

    public static function getSKUBatchesByLocation($location_id)
    {
        $sql = "SELECT DISTINCT sl.batch_id, b.number, b.expired, sl.SKU_id
                FROM SKU_locations sl
                    LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id 
                WHERE sl.location_id = :location_id";
        return (new SafeMysql())->fetchAll($sql, ['location_id' => $location_id]);
    }

    public static function handleSKUBatchInLocation(
        SKU $SKU,
        Location $location,
        $batch_number,
        callable $on_failure,
        array $location_types = Location::STORAGE_TYPES
    ) {
        if (!in_array($location->getTypeId(), $location_types)) {
            return;
        }
        $batches = self::getBatchesInLocation($SKU->getId(), $location->getId());

        if (!empty($batches)) {
            $batches = array_column($batches, 'number');
            if (!in_array($batch_number, $batches)) {
                $on_failure();
            }
        }
    }

    public static function handleBatchMovement(SKU $SKU, Location $location_from, Location $location_to, callable $on_failure)
    {
        if (Locations::isCancelLocation($location_to) || Locations::isExpiredLocation($location_to))
            return;
        $batches_from = self::getSKUBatchesByLocation($location_from->getId());
        $batches_to = self::getBatchesInLocation($SKU->getId(), $location_to->getId());
        foreach ($batches_to as $batch_to)
            foreach ($batches_from as $batch_from)
                if ($SKU->getId() == $batch_from['SKU_id'])
                    if ($batch_from['number'] != $batch_to['number']) {
                        $on_failure();
                        return;
                    }
    }

    public static function getStock($SKU_id, $warehouse_id)
    {
        $sql = "SELECT IFNULL(SUM(quantity), 0) quantity
                FROM SKU_locations
                    JOIN locations ON location_id = id AND warehouse = :warehouse_id AND type_id IN (:allowed_types)
                WHERE SKU_id = :SKU_id";
        return (new SafeMysql())->fetchScalar($sql, [
            'warehouse_id' => $warehouse_id,
            'allowed_types' => Locations::$allowed_type_stock,
            'SKU_id' => $SKU_id
        ]);
    }

    public static function getSKUsFromWarehouse($warehouse_id, $supplier_id)
    {
        $sql = "SELECT sl.SKU_id, sl.location_id, sl.quantity, sl.batch_id, l.row
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id AND l.warehouse = :warehouse_id
                    JOIN SKUs s ON sl.SKU_id = s.id AND s.supplier_id = :supplier_id";
        $res = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'supplier_id' => $supplier_id
        ]);
        if (empty($res))
            return [];
        $SKUs = [];
        foreach ($res as $SKU_data)
            $SKUs[$SKU_data['row']][$SKU_data['SKU_id']][$SKU_data['batch_id'] ?? '-'] = [
                'location_id' => $SKU_data['location_id'],
                'quantity' => $SKU_data['quantity'],
            ];
        return $SKUs;
    }

    public static function changeInventory($SKU_id, $location_id, $batch_id, $quantity)
    {
        $mysql = new SafeMysql();
        $where = "SKU_id = :SKU_id AND
                  location_id = :location_id AND
                  order_id IS NULL AND
                  type = 1 AND
                  reason = '2' AND
                  batch_id " . ($batch_id == '-' ? 'IS NULL' : '= :batch_id');
        $params = [
            'SKU_id' => $SKU_id,
            'location_id' => $location_id,
            'batch_id' => $batch_id
        ];
        $qty = $mysql->fetchScalar("SELECT quantity FROM SKU_locations WHERE $where", $params);
        if (is_null($qty)) {
            if ($quantity)
                $mysql->execute("INSERT INTO SKU_locations (SKU_id, location_id, quantity, order_id, type, reason, batch_id) VALUES (:SKU_id, :location_id, :quantity, NULL, 1, '2', " . ($batch_id == '-' ? 'NULL' : ':batch_id') . ")", array_merge($params, ['quantity' => $quantity]));
            return;
        }
        if (!$quantity) {
            $mysql->execute("DELETE FROM SKU_locations WHERE $where", $params);
            return;
        }
        if ($quantity != $qty)
            $mysql->execute("UPDATE SKU_locations SET quantity = :quantity WHERE $where", array_merge($params, ['quantity' => $quantity]));
    }

    public static function getInventoryFromWarehouse($SKU_id, $locations)
    {
        $sql = "SELECT location_id, quantity, IFNULL(batch_id, '-') batch_id
                FROM SKU_locations
                WHERE SKU_id = :SKU_id AND location_id IN (:locations)";
        $inventory = (new SafeMysql())->fetchAll($sql, [
            'SKU_id' => $SKU_id,
            'locations' => $locations
        ]);
        $res = [];
        foreach ($inventory as $SKU_info)
            $res[$SKU_info['location_id']][$SKU_info['batch_id']] = $SKU_info['quantity'];
        return $res;
    }

    public static function setInventory($inventory, $locations, $inventory_log_id)
    {
        if (empty($inventory['SKU_id']))
            return 'SKU is not found';
        $SKU_obj = SKUs::getById($inventory['SKU_id']);
        if (empty($SKU_obj))
            return "{$inventory['SKU_id']}: SKU is not found";
        $SKUs = self::getInventoryFromWarehouse($SKU_obj->getId(), $locations);
        InventoryLogs::changeRecord($inventory_log_id, [
            'SKU_id' => $SKU_obj->getId(),
            'start' => $SKUs,
        ]);
        unset($inventory['SKU_id']);
        foreach ($inventory as $warehouse_name => $warehouse) {
            $units = $warehouse['units'];
            unset($warehouse['units']);
            if (empty($warehouse)) {
                self::changeInventory($SKU_obj->getId(), $locations[$warehouse_name], '-', $units);
                unset($SKUs[$locations[$warehouse_name]]['-']);
                continue;
            }
            foreach ($warehouse as $batch_info => $quantity) {
                list($number, $expire) = explode('_', $batch_info);
                $batch_id = '-';
                if (!empty($number)) {
                    $batch = Batches::getBatchBySKUAndNumber($SKU_obj->getId(), $number);
                    $batch_id = empty($batch) ? Batches::addBatch($number, $SKU_obj->getId(), date('Y-m-d', strtotime($expire))) : $batch['id'];
                }
                self::changeInventory($SKU_obj->getId(), $locations[$warehouse_name], $batch_id, $quantity);
                unset($SKUs[$locations[$warehouse_name]][$batch_id]);
            }
            if (empty($SKUs[$locations[$warehouse_name]]))
                unset($SKUs[$locations[$warehouse_name]]);
        }
        $mysql = new SafeMysql();
        foreach ($SKUs as $location_id => $SKU_arr)
            foreach ($SKU_arr as $batch_id => $quantity)
                $mysql->execute("
                    DELETE FROM SKU_locations
                    WHERE SKU_id = {$SKU_obj->getId()} AND
                          location_id = $location_id AND
                          quantity = $quantity AND
                          order_id IS NULL AND
                          type = 1 AND
                          reason = '2' AND
                          batch_id " . ($batch_id == '-' ? 'IS NULL' : "= $batch_id"));
        return 'success';
    }

    public static function changeWorkerLocation($loc_from_id, $loc_to_id, $modes)
    {
        $loc_from_id = (int)$loc_from_id;
        $loc_to_id = (int)$loc_to_id;
        $whereModes = implode(',', array_map(function ($el) {
            switch ($el) {
                case 'move':
                    return 1;
                case 'pack':
                    return 31;
                case 'expired':
                    return 4;
                default:
                    return (int)('2' . Pick::$mode_val[$el][0]);
            }
        }, $modes));
        $count_in_loc_from = (int)(new SafeMysql())->fetchScalar("SELECT count(1) FROM SKU_locations
                                                                WHERE location_id = :location_id AND type IN (:modes)", [
            'location_id' => $loc_from_id,
            'modes' => explode(',', $whereModes)
        ]);
        $nums_de = 0;
        $nums_dl = 0;
        while(true) {
            try {
                (new SafeMysql())->execute("UPDATE SKU_locations
                        SET location_id = $loc_to_id
                        WHERE location_id = $loc_from_id AND type IN ($whereModes)");
                break;
            } catch (Exception $e) {
                if ($e instanceof DBException) {
                    if ($e->isDeadlock($nums_dl++))
                        continue;
                    $uniq_res = $e->getDuplicateEntry();
                    if (!empty($uniq_res) && count($uniq_res) === 4 && $nums_de <= $count_in_loc_from) {
                        $nums_dl = 0;
                        $nums_de++;
                        self::resolveSKUlocation($loc_from_id, $loc_to_id, $uniq_res[0], $uniq_res[2], $uniq_res[3]);
                        continue;
                    }
                }
                Debug::sendDebugInfo('changeWorkerLocation', var_export([$e->getCode(), $e->getMessage()], 1));
            }
        }
    }

    public static function toExpired($SKU_id, $location_id, $quantity, $order_id, $type, $reason, $batch_id)
    {
        $mysql = new SafeMysql();
        $old_cond = "quantity = :quantity AND
                     order_id " . (empty($order_id) ? 'IS NULL' : '= :order_id') . " AND
                     type = :type AND
                     reason = :reason";
        $new_cond = "order_id IS NULL AND
                     type = 4 AND
                     reason = ''";
        $common_cond = "SKU_id = :SKU_id AND
                        location_id = :location_id AND
                        batch_id = :batch_id";
        $params = [
            'SKU_id' => $SKU_id,
            'location_id' => $location_id,
            'batch_id' => $batch_id,
            'quantity' => $quantity,
            'order_id' => $order_id,
            'type' => $type,
            'reason' => $reason,
        ];
        $q = $mysql->fetchScalar("SELECT 1 FROM SKU_locations WHERE $common_cond AND $new_cond", $params);
        if (empty($q))
            $mysql->execute("UPDATE SKU_locations SET order_id = NULL, type = 4, reason = '' WHERE $common_cond AND $old_cond", $params);
        else {
            $mysql->execute("UPDATE SKU_locations SET quantity = quantity + :quantity WHERE $common_cond AND $new_cond", $params);
            $mysql->execute("DELETE FROM SKU_locations WHERE $common_cond AND $old_cond", $params);
        }
    }

    public static function getExpiredSKUsBySupplierId($supplier_id, $warehouses)
    {
        $warehouse_ids = array_keys(array_filter($warehouses, function ($item) {
            return !$item;
        }));
        if (!$warehouse_ids)
            return [];
        return (new SafeMysql())->fetchAll("
            SELECT sl.SKU_id, sl.batch_id
            FROM SKU_locations sl
                JOIN SKUs s ON sl.SKU_id = s.id AND s.supplier_id = :supplier_id
                JOIN locations l ON sl.location_id = l.id AND l.warehouse IN (:warehouses) 
            WHERE sl.type = 4 AND sl.order_id IS NULL AND sl.reason = ''", [
            'supplier_id' => $supplier_id,
            'warehouses' => $warehouse_ids
        ]);
    }

    public static function restoreExpired($supplier_id, $warehouses_all, $supplier_data, $warehouse_id, $user_id)
    {
        $SKUs = self::getExpiredSKUsBySupplierId($supplier_id, $warehouses_all);
        foreach ($SKUs as $SKU_data) {
            $nums = SKULocations::clearExpired($warehouses_all, $supplier_id, $SKU_data['SKU_id'], $SKU_data['batch_id']);
            if ($nums)
                SKUHistories::updateExpiredSettings($SKU_data['SKU_id'], $supplier_data, 'Unexpired', $warehouse_id, $user_id);
        }
    }

    public static function getBatchesExpired($location_id, $SKU_id)
    {
        $sql = "SELECT GROUP_CONCAT(b.number SEPARATOR ', ') numbers 
                FROM SKU_locations sl
                    LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id 
                WHERE sl.location_id = :location_id AND sl.SKU_id = :SKU_id";
        return (new SafeMysql())->fetchScalar($sql, [
            'location_id' => $location_id,
            'SKU_id' => $SKU_id
        ]);
    }

    public static function getExpiredSKUInfoInLocation($location_id, $SKU_id, $batch_id)
    {
        $sql = "SELECT s.id, s.name, b.number, b.expired, sl.quantity
                FROM SKU_locations sl
                    JOIN SKUs s ON sl.SKU_id = s.id 
                    JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id 
                WHERE sl.location_id = :location_id AND sl.SKU_id = :SKU_id AND sl.batch_id = :batch_id AND sl.order_id IS NULL AND type = 4 AND reason = ''
                LIMIT 1";
        $items = (new SafeMysql())->fetchAll($sql, [
            'location_id' => $location_id,
            'SKU_id' => $SKU_id,
            'batch_id' => $batch_id
        ]);
        SKUBarcodes::addBarcodesToArray($items);
        return $items[0] ?? null;
    }
}
