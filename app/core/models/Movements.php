<?php

namespace app\core\models;

use app\backend\models\Housekeeping;
use app\backend\models\MovementHistories;
use app\backend\models\SKULocations;
use app\backend\models\SKULocations as SKULocationsModel;
use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\entities\Movement;
use app\core\entities\Order;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\backend\models\Movements as MovementsBackend;
use app\core\helpers\Request;
use app\core\statical\Timezones;
use app\core\helpers\Debug;
use app\core\helpers\Filter;
use app\core\statical\WithDate;

class Movements
{
    const ORDER_BY_NONE = 0;
    const ORDER_BY_QTY_OF_ORDERS = 1;
    const ORDER_BY_PRIORITY = 2;
    const ORDER_BY_DATE_CREATED_ASCENDING = 3;
    const ORDER_BY_DATE_CREATED_DESCENDING = 4;

    public static $order_by = [
        self::ORDER_BY_NONE => 'none',
        self::ORDER_BY_QTY_OF_ORDERS => 'Quantity of orders',
        self::ORDER_BY_PRIORITY => 'Priority',
        self::ORDER_BY_DATE_CREATED_ASCENDING => 'Date created (ascending)',
        self::ORDER_BY_DATE_CREATED_DESCENDING => 'Date created (descending)',
    ];

    public static function isMovement($id, $loc_to_id)
    {
        $unique = (new SafeMysql())->checkUniqueFields('movements', [
            'SKU_id' => $id,
            'loc_to_id' => $loc_to_id,
        ]);
        if ($unique) {
            $sql = "SELECT id, quantity, priority
                    FROM movements
                    WHERE SKU_id = :id AND loc_to_id = :loc_to_id AND type";
            $res = (new SafeMysql())->fetchAll($sql, ['id' => (int)$id, 'loc_to_id' => (int)$loc_to_id]);
            return [
                'quantity' => array_sum(Arr::column($res, 'quantity')),
                'id_priority_map' => Arr::map($res, 'id', 'priority')
            ];
        }
        return false;
    }

    public static function insertMovement($id, $qty, $loc_from_id, $loc_to_id, $user_id, $comment = '', $priority = 0, $status = Movement::NEW_MOVEMENT, $type = Movement::TYPE_STANDARD)
    {
        $id = (int)$id;
        $loc_from_id = (int) $loc_from_id;
        $loc_to_id = (int) $loc_to_id;
        $sql = "
            SELECT id, quantity, `comment`, `priority`, `status`, `reason`
            FROM movements
            WHERE loc_from_id = :loc_from_id AND loc_to_id = :loc_to_id AND SKU_id = :id
            LIMIT 1";
        $movement_data = (new SafeMysql())->fetchOne($sql, ['loc_from_id' => $loc_from_id, 'loc_to_id' => $loc_to_id, 'id' => $id]);
        if (empty($movement_data))
            $movement_id = self::update([
                'SKU_id' => $id,
                'quantity' => $qty,
                'loc_from_id' => $loc_from_id,
                'loc_to_id' => $loc_to_id,
                'create_date' => date(WithDate::DATE_SQL_FORMAT, time()),
                'create_user_id' => $user_id,
                'status' => $status,
                'comment' => $comment,
                'priority' => $priority,
                'type' => $type,
            ]);
        else {
            $data = [
                'id' => $movement_data['id'],
                'quantity' => (Movement::isExpired($status) ? 0 : $movement_data['quantity']) + $qty,
                'comment' => $movement_data['comment'],
                'priority' => max($movement_data['priority'], $priority),
            ];
            if (Movement::isExpired($status)) {
                $data['status'] = $status;
                $data['reason'] = '';
            }
            if ($type === Movement::TYPE_UPLOAD)
                $data['quantity'] = max($movement_data['quantity'], $qty);
            $movement = new Movement($data);
            $movement->addComments($comment);
            if ($movement->getComment() == $movement_data['comment']
                && $movement->getQuantity() == $movement_data['quantity']
                && $movement->getPriority() == $movement_data['priority']
                && (!isset($data['status']) || $movement->getStatus() == $movement_data['status'])
                && (!isset($data['reason']) || $movement->getReason() == $movement_data['reason'])
            ) {
                // nothing to do here...
                return ['not updated', $movement->getId()];
            }
            if ($type === Movement::TYPE_UPLOAD)
                $movement->addComment('Updated by upload');
            $movement->save(array_keys($data));
            $movement_id = $movement_data['id'];
        }
        if (!empty($movement_data)) {
            MovementHistories::update($movement_id, $user_id, 'update exist movement|' . MovementHistories::getReason($priority));
            return ['updated', $movement_id];
        }
        MovementHistories::create($movement_id, $user_id, MovementHistories::getReason($priority));
        return ['created', $movement_id];
    }

    public static function insertUnrestrictedMovement(int $SKU_id, int $qty, int $loc_from_id, int $loc_to_id, int $user_id)
    {
        $sql = "
            SELECT id, quantity, status
            FROM movements
            WHERE loc_from_id = :loc_from_id AND
                  loc_to_id = :loc_to_id AND
                  SKU_id = :SKU_id AND 
                  type = :type
            LIMIT 1";
        $movement_data = (new SafeMysql())->fetchOne($sql, [
            'loc_from_id' => $loc_from_id,
            'loc_to_id' => $loc_to_id,
            'SKU_id' => $SKU_id,
            'type' => Movement::TYPE_UNRESTRICTED,
        ]);
        if (empty($movement_data))
            $movement_id = self::update([
                'SKU_id' => $SKU_id,
                'quantity' => $qty,
                'loc_from_id' => $loc_from_id,
                'loc_to_id' => $loc_to_id,
                'create_date' => date(WithDate::DATE_SQL_FORMAT, time()),
                'create_user_id' => $user_id,
                'status' => Movement::NEW_MOVEMENT,
                'type' => Movement::TYPE_UNRESTRICTED
            ]);
        else {
            if ($movement_data['status'] != Movement::NEW_MOVEMENT)
                return 'The movement already exists and is being processed';
            if ($movement_data['quantity'] >= $qty)
                return 'The movement already exists';
            $data = [
                'id' => $movement_data['id'],
                'quantity' => $qty
            ];
            $movement = new Movement($data);
            $movement->save(array_keys($data));
            $movement_id = $movement_data['id'];
        }
        if (!empty($movement_data))
            MovementHistories::update($movement_id, $user_id, 'processing movement');
        else
            MovementHistories::create($movement_id, $user_id, 'processing movement');
        return true;
    }

    public static function cancelSKUByOrder(SKU $SKU_obj, $qty, $location_id, $batch_id, $comment, $user_id, $warehouse_id)
    {
        $locs = SKUPreffLocs::getLocationsForSKU($warehouse_id, $SKU_obj->getId(), $batch_id);
        if (!empty($locs))
            foreach ($locs as $loc) {
                $loc_obj = Locations::getById($loc);
                if (empty($loc_obj))
                    return [false, "location #{$loc} not found"];
                $num = SKULocationsModel::getCountFreeInLocation($warehouse_id, $SKU_obj, $loc_obj);
                if (empty($num))
                    continue;
                if ($qty <= $num) {
                    Movements::insertMovement($SKU_obj->getId(), $qty, $location_id, $loc_obj->getId(), $user_id, $comment, Movement::CANCEL_ORDER_PRIORITY);
                    $qty = 0;
                    break;
                }
                Movements::insertMovement($SKU_obj->getId(), $num, $location_id, $loc_obj->getId(), $user_id, $comment, Movement::CANCEL_ORDER_PRIORITY);
                $qty -= $num;
            }
        if (empty($locs) || $qty) {
            $loc_spec = Locations::getByCancelLocation($warehouse_id);
            if (empty($loc_spec) )
                return [false , 'There are no locations to return its items'];
            Movements::insertMovement($SKU_obj->getId(), $qty, $location_id, $loc_spec[0]->getId(), $user_id, $comment, Movement::CANCEL_ORDER_PRIORITY);
        }
        return [true];
    }

    public static function getById($id)
    {
        $sql = "SELECT * FROM movements WHERE id = :id";
        $res = (new SafeMysql())->fetchOne($sql, ['id' => (int)$id]);
        if (empty($res))
            return null;
        return new Movement($res);
    }

    public static function update($data)
    {
        $movement = new Movement($data);
        $movement->save(array_keys($data));
        return $movement->getId();
    }

    public static function updateAuto($data)
    {
        self::update($data);
        MovementHistories::update($data['id'], null, 'update status (auto)');
    }

    public static function getUnrestrictedMovementsFrom(int $warehouse_id): array
    {
        $sql = "
            SELECT m.id, m.quantity qty, m.status, m.SKU_id, m.loc_from_id, l.type_id
            FROM movements m
                JOIN locations l ON l.id = m.loc_from_id AND l.warehouse = :warehouse_id
            WHERE m.type = :type
            ORDER BY m.id";
        $rows = (new SafeMysql())->fetchAll($sql, ['warehouse_id' => $warehouse_id, 'type' => Movement::TYPE_UNRESTRICTED,]);
        $result = [];
        foreach ($rows as $item)
            $result[(int)$item['id']] = ['id' => $item['id'], 'qty' => (int)$item['qty'], 'status' => $item['status'], 'SKU_id' => (int)$item['SKU_id'], 'loc_from_id' => (int)$item['loc_from_id'], 'type_id' => $item['type_id']];
        return $result;
    }

    public static function getMovementsFrom(int $warehouse_id): array
    {
        $sql = "
        SELECT m.id, m.quantity qty, m.status, m.SKU_id, m.loc_from_id, l.type_id
        FROM movements m
            JOIN locations l ON l.id = m.loc_from_id AND l.warehouse = :warehouse_id
        WHERE m.status NOT IN (:SKIP_STATUSES) AND m.type
        ORDER BY m.id";
        $rows = (new SafeMysql())->fetchAll($sql, ['warehouse_id' => $warehouse_id, 'SKIP_STATUSES' => Movement::SKIP_STATUSES]);
        $result = [];
        foreach ($rows as $item)
            $result[(int)$item['id']] = ['id' => $item['id'], 'qty' => (int)$item['qty'], 'status' => $item['status'], 'SKU_id' => (int)$item['SKU_id'], 'loc_from_id' => (int)$item['loc_from_id'], 'type_id' => $item['type_id']];
        return $result;
    }

    public static function getLocationsFromQuantity(array $movements_from): array
    {
        if (!$movements_from)
            return [];
        $where = $bind = [];
        foreach ($movements_from as $key => $mov) {
            $where[] = "(sl.SKU_id = :SKU_id_$key AND sl.location_id = :loc_from_id_$key)";
            $bind["SKU_id_$key"] = $mov['SKU_id'];
            $bind["loc_from_id_$key"] = $mov['loc_from_id'];
        }
        $sql = "
        SELECT sl.SKU_id, sl.location_id, sl.quantity
        FROM SKU_locations sl
        WHERE sl.order_id IS NULL AND ((sl.type = 1 AND sl.reason = 2) OR sl.type = 4) AND (" . implode(' OR ', $where) . ")";
        $items = (new SafeMysql())->fetchAll($sql, $bind);
        $result = [];
        foreach ($items as $item)
            $result[(int)$item['location_id']][(int)$item['SKU_id']] = ($result[(int)$item['location_id']][(int)$item['SKU_id']] ?? 0) + (int)$item['quantity'];
        return $result;
    }

    public static function getSkipMovements(array $movements_from): array
    {
        $skip_ids = [];
        foreach ($movements_from as $movement)
            if (!Location::isDH($movement['type_id']) && (empty($movement['quantity']) || $movement['qty'] > $movement['quantity']))
                $skip_ids[$movement['id']] = $movement['id'];
        return $skip_ids;
    }

    public static function getStatusMovementsFrom(array $movements_from): array
    {
        $result = [];
        foreach ($movements_from as $movement) {
            if (Location::isDH($movement['type_id']))
                continue;
            if (empty($movement['quantity']))
                $result[$movement['id']] = ['new_status' => Movement::NON_COMPLIANT, 'old_status' => $movement['status'], 'reason' => "SKUs aren't found in source location"];
            elseif ($movement['qty'] > $movement['quantity'])
                $result[$movement['id']] = ['new_status' => Movement::PARTIAL, 'old_status' => $movement['status'], 'reason' => 'Possible partial execution movement (from source location)'];
        }

        return $result;
    }

    public static function getMovementsTo(int $warehouse_id, array $skip_ids): array
    {
        // to
        $skip_ids_sql = empty($skip_ids) ? '' : ' AND m.id NOT IN (:skip_ids)';
        $sql = "
        SELECT m.id, m.SKU_id, m.loc_from_id, m.loc_to_id, m.quantity, l.type_id, m.status
        FROM movements m
            JOIN locations l ON l.id = m.loc_to_id AND l.warehouse = :warehouse_id
        WHERE m.status NOT IN (:SKIP_STATUSES) $skip_ids_sql AND m.type";
        $params = ['warehouse_id' => $warehouse_id, 'SKIP_STATUSES' => Movement::SKIP_STATUSES,];
        if (!empty($skip_ids))
            $params['skip_ids'] = array_map('intval', $skip_ids);
        return (new SafeMysql())->fetchAll($sql, $params);
    }

    public static function getStatusMovementsTo(array $movements_to, int $warehouse_id): array
    {
        $result = [];
        foreach ($movements_to as $movement) {
            if (Movement::isExpired($movement['status']))
                continue;
            if (Location::isDH($movement['type_id'])) {
                $result[$movement['id']] = ['new_status' => Movement::NEW_MOVEMENT, 'old_status' => $movement['status'], 'reason' => ""];
                continue;
            }
            if (Location::isStorage($movement['type_id'])) {
                $SKU_obj = SKUs::getById($movement['SKU_id']);
                if (empty($SKU_obj)) {
                    $result[$movement['id']] = ['new_status' => Movement::NON_COMPLIANT, 'old_status' => $movement['status'], 'reason' => "SKU is not found"];
                    continue;
                }
                $location_to = Locations::getById($movement['loc_to_id']);
                if (empty($location_to)) {
                    $result[$movement['id']] = ['new_status' => Movement::NON_COMPLIANT, 'old_status' => $movement['status'], 'reason' => "Destination location is not found"];
                    continue;
                }
                $location_from = Locations::getById($movement['loc_from_id']);
                if (!$location_from) {
                    $result[$movement['id']] = ['new_status' => Movement::NON_COMPLIANT, 'old_status' => $movement['status'], 'reason' => "Source location is not found"];
                    continue;
                }
                $loc_spec = Locations::getByCancelLocation($warehouse_id);
                if ((empty($loc_spec) || (int)$loc_spec[0]->getId() !== (int)$location_to->getId()) && SKUPreffLocs::checkExistPreffLoc((int)$location_to->getWarehouse(), (int)$SKU_obj->getId(), (int)$location_to->getId(), $location_to->getTypeId()) === false) {
                    $result[$movement['id']] = ['new_status' => Movement::NON_COMPLIANT, 'old_status' => $movement['status'], 'reason' => Movement::ERROR_NOT_LOC_IN_SKU];
                    continue;
                }
                $updated = false;
                SKULocationsModel::handleBatchMovement($SKU_obj, $location_from, $location_to, function () use ($movement, &$updated) {
                    $updated = true;
                });
                if ($updated) {
                    $result[$movement['id']] = ['new_status' => Movement::NON_COMPLIANT, 'old_status' => $movement['status'], 'reason' => 'Different batches in source and destination locations'];
                    continue;
                }
                $checkEmptyMaxCapacity =  SKULocations::hasSkuWithEmptyMaxCapacity($warehouse_id, $location_to);
                if ($checkEmptyMaxCapacity) {
                    $result[$movement['id']] = ['new_status' => Movement::NON_COMPLIANT, 'old_status' => $movement['status'], 'reason' => 'Movement is impossible. There are items without specified dimensions in the destination location'];
                    continue;
                }

                $q = SKULocations::getCountFreeInLocation($warehouse_id, $SKU_obj, $location_to);

            } else
                $q = $movement['quantity'];

            if ($q >= $movement['quantity'])
                $result[$movement['id']] = ['new_status' => Movement::NEW_MOVEMENT, 'old_status' => $movement['status'], 'reason' => ''];
            else
                $result[$movement['id']] = ['new_status' => Movement::PARTIAL, 'old_status' => $movement['status'], 'reason' => 'Possible partial execution movement (to destination location)'];
        }
        
        return $result;
    }

    public static function updateMovements(int $warehouse_id)
    {
        $movements_from = self::getMovementsFrom($warehouse_id);
        $locations_from = self::getLocationsFromQuantity($movements_from);
        foreach ($movements_from as $mov_id => $mov_item)
            $movements_from[$mov_id]['quantity'] = $locations_from[$mov_item['loc_from_id']][$mov_item['SKU_id']] ?? 0;
        $skip_ids = self::getSkipMovements($movements_from);
        $movements_status = self::getStatusMovementsFrom($movements_from);
        self::setStatus($movements_status);
        $movements_to = self::getMovementsTo($warehouse_id, $skip_ids);
        $movements_status = self::getStatusMovementsTo($movements_to, $warehouse_id);
        self::setStatus($movements_status);
    }

    private static function setStatus($movements_status)
    {
        foreach ($movements_status as $movement_id => $movement_status)
            if ($movement_status['new_status'] !== $movement_status['old_status'])
                self::updateAuto(['id' => $movement_id, 'status' => $movement_status['new_status'], 'reason' => $movement_status['reason'], 'user_id' => -1]);
    }

    public static function updateUnrestictedMovements(int $warehouse_id)
    {
        $movements_from = self::getUnrestrictedMovementsFrom($warehouse_id);
        $locations_from = self::getLocationsFromQuantity($movements_from);
        foreach ($movements_from as $mov_id => $mov_item)
            $movements_from[$mov_id]['quantity'] = $locations_from[$mov_item['loc_from_id']][$mov_item['SKU_id']] ?? 0;
        $movements_status = self::getStatusMovementsFrom($movements_from);
        self::setStatus($movements_status);
    }

    public static function validationMovements(SKU $SKU, Location $location_from, Location $location_to, int $qty): string
    {
        $mov_id = 0;
        $movements_from = [
            $mov_id => ['id' => $mov_id, 'status' => null, 'SKU_id' => $SKU->getId(), 'loc_from_id' => $location_from->getId(), 'qty' => $qty, 'type_id' => $location_from->getTypeId()]
        ];
        $locations_from = self::getLocationsFromQuantity($movements_from);
        foreach ($movements_from as $mov_id => $mov_item)
            $movements_from[$mov_id]['quantity'] = $locations_from[$mov_item['loc_from_id']][$mov_item['SKU_id']] ?? 0;
        $movements_status = self::getStatusMovementsFrom($movements_from);
        if (isset($movements_status[$mov_id]) && in_array($movements_status[$mov_id]['new_status'], [Movement::NON_COMPLIANT, Movement::PARTIAL]))
            return $movements_status[$mov_id]['reason'];
        $movements_to = [
            $mov_id => ['id' => $mov_id, 'status' => null, 'SKU_id' => $SKU->getId(), 'loc_from_id' => $location_from->getId(), 'loc_to_id' => $location_to->getId(), 'quantity' => $qty, 'type_id' => $location_to->getTypeId()]
        ];
        $movements_status = self::getStatusMovementsTo($movements_to, $location_to->getWarehouse());
        if (isset($movements_status[$mov_id]) && in_array($movements_status[$mov_id]['new_status'], [Movement::NON_COMPLIANT, Movement::PARTIAL]))
            return $movements_status[$mov_id]['reason'];
        return '';
    }

    public static function validatePalletMovement(Location $pallet, Location $destination)
    {
        $movements = [];
        $SKUs = SKULocations::getSKUsFromLocation($pallet->getId());
        foreach ($SKUs as $i => $SKU) {
            $movements[$i] = [
                'id' => $i,
                'status' => null,
                'SKU_id' => $SKU['SKU_id'],
                'loc_from_id' => $pallet->getId(),
                'loc_to_id' => $destination->getId(),
                'quantity' => $SKU['summa'],
            ];
        }
        $movements_status = self::getStatusMovementsTo($movements, $destination->getWarehouse());
        $errors = [];
        foreach ($movements_status as $movement_id => $movement_status)
            if (in_array($movement_status['new_status'], [Movement::NON_COMPLIANT, Movement::PARTIAL]))
                $errors[] = $movement_status['reason'];
        return empty($errors) ? true : $errors;
    }

    public static function updateExpiredMovements($warehouse_id)
    {
        $warehouse_id = (int) $warehouse_id;
        /** @var array $loc */
        $loc = Locations::getByExpiredLocation($warehouse_id);
        if (empty($loc))
            return;
        $expired_id = $loc[0]->getId();
        $res = (new SafeMysql())->fetchAll("
            SELECT sl.SKU_id, sl.location_id, SUM(sl.quantity) quantity, b.number, b.expired
            FROM SKU_locations sl
                JOIN locations l ON sl.location_id = l.id AND l.warehouse = :warehouse_id
                LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
            WHERE sl.type = 4 AND sl.reason = '' AND sl.location_id <> :expired_id
            GROUP BY sl.SKU_id, sl.location_id", [
                'warehouse_id' => $warehouse_id,
                'expired_id' => $expired_id,
        ]);
        $SKUs = [];
        foreach ($res as $datum)
            $SKUs[$datum['SKU_id']][$datum['location_id']] = ['quantity' => $datum['quantity'], 'number' => $datum['number'], 'expired' => $datum['expired']];
        $res = (new SafeMysql())->fetchAll("
            SELECT m.id, m.SKU_id, m.loc_from_id, m.loc_to_id, m.quantity
            FROM movements m
                JOIN locations l ON m.loc_from_id = l.id AND l.warehouse = :warehouse_id
            WHERE m.status = :status AND m.type", [
                'warehouse_id' => $warehouse_id,
                'status' => Movement::EXPIRED_BATCH,
        ]);
        $movements = [];
        foreach ($res as $datum)
            $movements[$datum['SKU_id']][$datum['loc_from_id']][] = ['id' => $datum['id'], 'location_id' => $datum['loc_to_id'], 'quantity' => $datum['quantity']];
        foreach ($SKUs as $SKU_id => $locations) {
            if (empty($movements[$SKU_id]))
                continue;
            foreach ($locations as $location_id => $SKU_data) {
                if (empty($movements[$SKU_id][$location_id]))
                    continue;
                if (count($movements[$SKU_id][$location_id]) != 1) {
                    Debug::sendDebugInfo('updateExpiredMovements', "Not unique movement (SKU #$SKU_id, Location #$location_id)", Debug::TEST);
                    continue;
                }
                $data = $movements[$SKU_id][$location_id][0];
                if ($data['location_id'] != $expired_id) {
                    Debug::sendDebugInfo('updateExpiredMovements', "Expired movement is wrong (SKU #$SKU_id, Location #$location_id)", Debug::TEST);
                    continue;
                }
                if ($data['quantity'] != $SKU_data['quantity'])
                    self::updateAuto(['id' => $data['id'], 'quantity' => $SKU_data['quantity'], 'reason' => 'Automatical update', 'user_id' => -1]);
                unset($movements[$SKU_id][$location_id], $SKUs[$SKU_id][$location_id]);
            }
            if (empty($SKUs[$SKU_id]))
                unset($SKUs[$SKU_id]);
            if (empty($movements[$SKU_id]))
                unset($movements[$SKU_id]);
        }
        foreach ($SKUs as $SKU_id => $locations)
            foreach ($locations as $location_id => $SKU_data)
                self::insertMovement(
                    $SKU_id,
                    $SKU_data['quantity'],
                    $location_id,
                    $expired_id,
                    0,
                    ["Expired (Batch#: {$SKU_data['number']}; Exp#: " . date('jS M Y', strtotime($SKU_data['expired'])) . ')'],
                    Movement::EXPIRED_PRIORITY,
                    Movement::EXPIRED_BATCH);
        foreach ($movements as $SKU_id => $locations)
            foreach ($locations as $location_id => $SKU_data)
                self::updateAuto([
                    'id' => $SKU_data[0]['id'],
                    'status' => Movement::IMPOSSIBLE,
                    'reason' => 'Movement is not actual',
                    'user_id' => -1]);
    }

    /** @throws \Exception */
    public static function getByFilters(Warehouse $warehouse, $filters = []): array
    {
        $warehouse_id = (int)$warehouse->getId();
        $equalFilters = [
            'SKU_id' => 'm.SKU_id',
            'loc_from_id' => 'm.loc_from_id',
            'loc_to_id' => 'm.loc_to_id',
        ];
        $query = new QueryBuilder();
        $loc_from_select_name = Locations::getLocationNameSelect('lf', 'w');
        $loc_to_select_name = Locations::getLocationNameSelect('lt', 'w');
        $loc_from_select_alias = Locations::getAliasSelect('lf');
        $loc_to_select_alias = Locations::getAliasSelect('lt');
        $params = [
            'warehouse_id' => $warehouse_id,
        ];
        $query->setSelectFrom("SELECT SQL_CALC_FOUND_ROWS m.id, m.SKU_id, s.name, s.photo, s.supplier_id, m.quantity,
                                             m.loc_from_id, 
                                             m.loc_to_id,
                                             $loc_from_select_alias lf_alias,
                                             $loc_to_select_alias lt_alias,
                                             m.create_date, m.create_user_id, uc.login clogin,
                                             m.user_id, u.login,
                                             m.comment, m.status, m.priority, m.reason, m.type,
                                             CASE 
                                                WHEN lt.type_id = 'US' 
                                                THEN CONCAT(lt_u.login, ' #', lt_u.id)
                                                ELSE $loc_to_select_name
                                             END AS lt_name,
                                             CASE 
                                                WHEN lf.type_id = 'US' 
                                                THEN CONCAT(lf_u.login, ' #', lf_u.id)
                                                ELSE $loc_from_select_name 
                                             END AS lf_name
                                      FROM movements m");
        $query->addJoin("LEFT JOIN SKUs s ON s.id = m.SKU_id");
        $query->addJoin("LEFT JOIN suppliers sup ON s.supplier_id = sup.id");
        $query->addJoin("LEFT JOIN users uw ON sup.user_id = uw.id");
        $query->addJoin("JOIN locations lf ON lf.id = m.loc_from_id AND lf.warehouse = :warehouse_id");
        $query->addJoin("JOIN locations lt ON lt.id = m.loc_to_id AND lt.warehouse = :warehouse_id");
        $query->addJoin("JOIN warehouses w ON lf.warehouse = w.id");
        $query->addJoin("LEFT JOIN users uc ON uc.id = m.create_user_id");
        $query->addJoin("LEFT JOIN users u ON u.id = m.user_id");
        $query->addJoin("LEFT JOIN users lt_u ON lt_u.id = lt.row");
        $query->addJoin("LEFT JOIN users lf_u ON lf_u.id = lf.row");
        foreach ($filters as $key => $filter)
            if (in_array($key, array_keys($equalFilters)) && !empty($filter) && $filter != Filter::NOT_SELECTED) {
                $query->addWhere("{$equalFilters[$key]} = :{$key}");
                $params[$key] = $filter;
            }

        $time_diff = Timezones::getDiff($warehouse->getTimezone());
        $date_format = '%Y-%m-%d %H:%i:%s';
        if (!empty($filters['date_created_from']) && $filters['date_created_from'] != Filter::NOT_SELECTED && strtotime($filters['date_created_from']) > 0) {
            $date_from = $filters['date_created_from'] . ' 00:00:00';
            $date_from = empty($time_diff) ? $date_from : Timezones::getDiff($warehouse->getTimezone(), $date_from, TRUE);
            $query->addWhere("DATE_FORMAT(m.create_date, '{$date_format}') >= :date_created_from");
            $params['date_created_from'] = $date_from;
        }
        if (!empty($filters['date_created_to']) && $filters['date_created_to'] != Filter::NOT_SELECTED && strtotime($filters['date_created_to']) > 0) {
            $date_to = $filters['date_created_to'] . ' 23:59:59';
            $date_to = empty($time_diff) ? $date_to : Timezones::getDiff($warehouse->getTimezone(), $date_to, TRUE);
            $query->addWhere("DATE_FORMAT(m.create_date, '{$date_format}') <= :date_created_to");
            $params['date_created_to'] = $date_to;
        }
        if (!empty($filters['barcode'])) {
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode($filters['barcode'], Filter::getFilterTypeFromRequest($filters, 'barcode'));
            if (!empty($SKU_ids)) {
                $query->addWhere("s.id IN (:SKU_ids)");
                $params['SKU_ids'] = $SKU_ids;
            } else
                return ['records' => [], 'total' => 0, 'current_page' => 1];
        }
        
        if (!empty($filters['statuses'])) {
            $query->addWhere("m.status IN (:statuses)");
            $params['statuses'] = $filters['statuses'];
        }
        
        if (!empty($filters['completed'])) {
            $query->addWhere('((m.status = :status_in_progress AND m.user_id = :user_id_completed) OR (m.status <> :status_in_progress))');
            $params['status_in_progress'] = Movement::IN_PROGRESS;
            $params['user_id_completed'] = $filters['completed'];
        }
        
        if (!empty($filters['type']) && $filters['type'] != Filter::NOT_SELECTED) {
            $query->addWhere("m.type IN (:types)");
            $params['types'] = $filters['type'];
        }
        
        if (!empty($filters['type_from']) && $filters['type_from'] != Filter::NOT_SELECTED) {
            if (is_array($filters['type_from'])) {
                $query->addWhere("lf.type_id IN (:type_from)");
                $params['type_from'] = $filters['type_from'];
            } else {
                $query->addWhere("lf.type_id = :type_from");
                $params['type_from'] = $filters['type_from'];
            }
        }
        
        if (!empty($filters['type_to']) && $filters['type_to'] != Filter::NOT_SELECTED) {
            if (is_array($filters['type_to'])) {
                $query->addWhere("lt.type_id IN (:type_to)");
                $params['type_to'] = $filters['type_to'];
            } else {
                $query->addWhere("lt.type_id = :type_to");
                $params['type_to'] = $filters['type_to'];
            }
        }
        
        if (isset($filters['status']) && !empty($filters['status']) && $filters['status'] != Filter::NOT_SELECTED) {
            if (is_array($filters['status'])) {
                $query->addWhere("m.status IN (:status)");
                $params['status'] = $filters['status'];
            } else {
                $query->addWhere("m.status = :status");
                $params['status'] = $filters['status'];
            }
        }
        
        if (!empty($filters['order_by']))
            self::handleOrderByQuery($query, (int)$filters['order_by'], $warehouse_id);
        $current_page = Request::getPage($filters);
        $offset = MovementsBackend::LIMIT_PER_PAGE * ($current_page - 1);
        $query->setLimit("{$offset}, " . MovementsBackend::LIMIT_PER_PAGE);
        $mysql = new SafeMysql();
        $records_raw = $mysql->fetchAll($query->build(), $params);
        $total = $mysql->fetchScalar('SELECT FOUND_ROWS() count');
        SKUBarcodes::addBarcodesToArray($records_raw, 'SKU_id');
        return ['records' => $records_raw, 'total' => $total, 'current_page' => $current_page];
    }

    public static function fixPriorities()
    {
        $priority = Movement::PF_REPLEN_PRIORITY;
        (new SafeMysql())->execute("
            UPDATE movements SET `priority` = IF(`priority` > :priority, `priority`, :priority) 
            WHERE SKU_id IN (
                SELECT SKU_id FROM order_SKUs WHERE order_id IN (
                    SELECT id FROM orders where status = :status
                )
            )", [
            'priority' => $priority,
            'status' => Order::WAITING_FOR_REPLEN_NUM,
        ]);
    }

    public static function createMovementTrolley(Warehouse $warehouse, $SKU_id, $quantity, $partial, $loc_to_id, $movement_id, Location $cart, User $user)
    {
        $loc = Locations::getById($loc_to_id);
        $new_status = Locations::isExpiredLocation($loc) ? Movement::EXPIRED_BATCH : Movement::NEW_MOVEMENT;
        $new_priority = Locations::isExpiredLocation($loc) ? Movement::EXPIRED_PRIORITY : Movement::MOVEMENT_IN_CART_PRIORITY;
        $movement_data = Movements::getByFilters($warehouse, [
            'SKU_id' => $SKU_id,
            'loc_from_id' => $cart->getId(),
            'loc_to_id' => $loc_to_id,
        ]);
        $movement  = Movements::getById($movement_id);
        if (empty($movement_data['records'])) {
            if ($loc->getId() != $cart->getId()) {
                Movements::insertMovement(
                    $SKU_id,
                    $quantity,
                    $cart->getId(),
                    $loc_to_id,
                    $user->getId(),
                    'Intermediate state',
                    $new_priority,
                    $new_status,
                    $movement->getType()
                );
            }
        } else {
            self::update([
                'id' => $movement_data['records'][0]['id'],
                'quantity' => $movement_data['records'][0]['quantity'] + $quantity,
                'status' => $new_status
            ]);
        }
        $reason = 'create trolley';
        if (!$partial)
            MovementsBackend::delMovement($movement_id, $user, $reason);
        else {
            $movement = self::getById($movement_id);
            $movement->setQuantity($movement->getQuantity() - $quantity);
            $movement->setStatus($new_status);
            $movement->setUserId(null);
            $movement->addComment('Partial execution');
            $movement->save(['id', 'quantity', 'status', 'user_id', 'comment']);
            MovementHistories::update($movement_id, $user->getId(), $reason);
        }
    }

    public static function createUnrestrictedMovementTrolley($SKU_id, $quantity, $partial, $loc_to_id, $movement_id, Location $cart, User $user)
    {
        self::insertUnrestrictedMovement($SKU_id, $quantity, $cart->getId(), $loc_to_id, $user->getId());
        if (!$partial)
            MovementsBackend::delMovement($movement_id, $user, 'create trolley');
        else {
            $movement = self::getById($movement_id);
            $movement->setQuantity($movement->getQuantity() - $quantity);
            $movement->setStatus(Movement::NEW_MOVEMENT);
            $movement->setUserId(null);
            $movement->addComment('Partial execution');
            $movement->setType(Movement::TYPE_UNRESTRICTED);
            $movement->save(['id', 'quantity', 'status', 'user_id', 'comment', 'type']);
            MovementHistories::update($movement_id, $user->getId(), 'create trolley');
        }
    }

    public static function createPalletMovement(Location $pallet, Location $destination, User $user)
    {
        $warehouse = Warehouses::getById($pallet->getWarehouse());
        $SKUs = SKULocations::getSKUsFromLocation($pallet->getId());
        if (empty($SKUs))
            return ['error' => 'No SKUs in pallet'];
        $SKUs_quantity = array_map(function ($item) {
            return [
                'SKU_id' => $item['SKU_id'],
                'quantity' => $item['summa'],
            ];
        }, $SKUs);
        $data = [
            'loc_from_id' => $pallet->getId(),
            'loc_to' => $destination->getAlternativeAlias(),
            'SKUs' => $SKUs_quantity,
        ];
        $res = Housekeeping::prepareDestination($data, $warehouse->getId(), $warehouse->getLabel());
        if (!empty($res['error']))
            return $res;
        return Housekeeping::completeDestination($pallet->getId(), $res['success'][0], $res['success'][1], $res['success'][2], $user, $warehouse->getId());
    }

    /** @return true|string */
    public static function canPalletBeMoved(Location $pallet, Location $destination)
    {
        if (!$pallet->isActive() || !$pallet->isMoveable())
            return 'Pallet can not be moved';
        if ($pallet->getId() == $destination->getId())
            return 'Pallet and destination are the same';
        if (!$destination->isActive())
            return 'Destination is not active';
        $warehouse = Warehouses::getById($pallet->getWarehouse());
        $SKUs = SKULocations::getSKUsFromLocation($pallet->getId());
        if (empty($SKUs))
            return 'No SKUs in pallet';
        $SKUs_quantity = array_map(function ($item) {
            return [
                'SKU_id' => $item['SKU_id'],
                'quantity' => $item['summa'],
            ];
        }, $SKUs);
        
        $data = [
            'loc_from_id' => $pallet->getId(),
            'loc_to' => $destination->getAlternativeAlias(),
            'SKUs' => $SKUs_quantity,
        ];
        $res = Housekeeping::prepareDestination($data, $warehouse->getId(), $warehouse->getLabel());
        return empty($res['error']) ? true : $res['error'];
    }

    public static function take2Work($movement_id, $user_id)
    {
        $movement = self::getById($movement_id);
        $movement->setUserId($user_id);
        $movement->setStatus(Movement::IN_PROGRESS);
        $movement->save(['id', 'user_id', 'status']);
        MovementHistories::update($movement_id, $user_id, 'set status: ' . Movement::IN_PROGRESS);
    }

    public static function clearMovement($quantity, bool $partial, $movement_id, User $user = null, $reason = null)
    {
        if ($partial) {
            $movement = self::getById($movement_id);
            if (empty($movement))
                return;
            $loc = Locations::getById($movement->getLocToId());
            $movement->setQuantity($movement->getQuantity() - (int)$quantity);
            $movement->setStatus(Locations::isExpiredLocation($loc) ? Movement::EXPIRED_BATCH : Movement::NEW_MOVEMENT);
            $movement->setUserId($user->getId());
            $movement->save(['id', 'quantity', 'status', 'user_id']);
            MovementHistories::update($movement_id, empty($user) ? 0 : $user->getId(), $reason);
        } else
            MovementsBackend::delMovement($movement_id, $user, $reason);
    }

    public static function changePriority($id, $priority)
    {
        self::update(['id' => (int)$id, 'priority' => (int)$priority]);
        MovementHistories::update($id, null, 'change priority');
    }

    public static function changeCommentForMovementWithoutOrder($id, $comment)
    {
        $movement = Movements::getById($id);
        if (empty($movement))
            return;
        if (strpos((string)$movement->getComment(), 'order') === false) {
            $movement->addComments($comment);
            $movement->save(['id', 'comment']);
        }
    }

    protected static function handleOrderByQuery(QueryBuilder $query, $order_by, $warehouse_id)
    {
        if ($order_by === self::ORDER_BY_NONE) {
            $ids = Housekeeping::getListMaximumOrders($warehouse_id);
            $ids = array_reverse($ids);
            $query->setOrderBy((empty($ids) ? '' : ('FIELD(m.id,' . implode(',', $ids) . ') DESC, ')) . "m.priority DESC, m.create_date ASC");
        } elseif ($order_by === self::ORDER_BY_PRIORITY)
            $query->setOrderBy("m.priority DESC, m.create_date ASC");
        elseif ($order_by === self::ORDER_BY_DATE_CREATED_ASCENDING)
            $query->setOrderBy("m.create_date ASC, m.priority DESC");
        elseif ($order_by === self::ORDER_BY_DATE_CREATED_DESCENDING)
            $query->setOrderBy("m.create_date DESC, m.priority DESC");
    }
}