<?php

namespace app\core\models;

use app\core\entities\Movement;
use app\core\entities\MovementHistory;
use app\core\entities\User;
use app\core\statical\WithDate;
use app\core\dbal\SafeMysql;

class  MovementHistories
{
    protected static function initEvent($movement_id, $user_id, $SKU_id, $warehouse_id, $reason, $addition = []): MovementHistory
    {
        return new MovementHistory([
            'user_id' => $user_id,
            'movement_id' => $movement_id,
            'addition' => json_encode($addition),
            'SKU_id' => $SKU_id,
            'warehouse_id' => $warehouse_id,
            'reason' => $reason
        ]);
    }

    protected static function storeEvent(MovementHistory $movement_history, $event)
    {
        $movement_history->setEvent($event);
        $movement_history->save(['event', 'user_id', 'movement_id', 'addition', 'SKU_id', 'warehouse_id', 'reason']);
    }

    private static function getWarehouseId(Movement $movement)
    {
        $loc_from = Locations::getById($movement->getLocFromId());
        if (!empty($loc_from) && $loc_from->getWarehouse())
            return $loc_from->getWarehouse();
        $loc_to = Locations::getById($movement->getLocToId());
        if (!empty($loc_to) && $loc_to->getWarehouse())
            return $loc_to->getWarehouse();
        return null;
    }

    public static function create($movement_id, $user_id, $reason, $addition = [])
    {
        $movement = Movements::getById($movement_id);
        if (!empty($movement)) {
            $addition['movement'] = $movement->toArray();
            self::storeEvent(self::initEvent($movement_id, $user_id, $movement->getSKUId(), self::getWarehouseId($movement), $reason, $addition), MovementHistory::EVENT_CREATE);
        }
    }

    public static function update($movement_id, $user_id, $reason, $addition = [])
    {
        $movement = Movements::getById($movement_id);
        $SKU_id = !empty($movement) ? $movement->getSKUId() : null;
        $warehouse_id = !empty($movement) ? self::getWarehouseId($movement) : null;
        if (is_null($warehouse_id))
            if (!empty($addition['loc_from_id'])) {
                $loc_from = Locations::getById($addition['loc_from_id']);
                if (!empty($loc_from) && $loc_from->getWarehouse())
                    $warehouse_id = $loc_from->getWarehouse();
            }
        if (!empty($movement) && empty($addition['movement']))
            $addition['movement'] = $movement->toArray();
        self::storeEvent(self::initEvent($movement_id, $user_id, $SKU_id, $warehouse_id, $reason, $addition), MovementHistory::EVENT_UPDATE);
    }

    public static function delete(Movement $movement, User $user, $reason)
    {
        $movement->addComment('Deleted ' . date(WithDate::DATE_FRONT_FORMAT) . ' (UTC) by ' . $user->getLogin());
        self::storeEvent(self::initEvent($movement->getId(), empty($user) ? 0 : $user->getId(), $movement->getSKUId(), self::getWarehouseId($movement), $reason, ['movement' => $movement->toArray()]), MovementHistory::EVENT_DELETE);
    }

    public static function merge(Movement $movement, User $user, $reason)
    {
        self::storeEvent(self::initEvent($movement->getId(), empty($user) ? 0 : $user->getId(), $movement->getSKUId(), self::getWarehouseId($movement), $reason, ['movement' => $movement->toArray()]), MovementHistory::EVENT_MERGE);
    }

    public static function restore(Movement $movement, $user_id, $addition = [])
    {
        $addition['movement'] = $movement->toArray();
        self::storeEvent(self::initEvent($movement->getId(), $user_id, $movement->getSKUId(), self::getWarehouseId($movement), 'restore', $addition), MovementHistory::EVENT_RESTORE);
    }

    public static function getRecord($id): ?MovementHistory
    {
        $data = (new SafeMysql())->fetchOne('SELECT * FROM movement_history WHERE id = :id', ['id' => (int)$id]);
        return !empty($data) ? new MovementHistory($data) : null;
    }
}