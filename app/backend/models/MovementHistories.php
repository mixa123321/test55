<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Movement;
use app\core\entities\MovementHistory;
use app\core\entities\User;
use app\core\helpers\Request;
use app\core\statical\WithDate;

class MovementHistories extends \app\core\models\MovementHistories
{
    const LIMIT_PER_PAGE = 10;

    public static function getManuallyRemovedList(array $SKU_ids, $warehouse_id, $label, $current_page = 1): array
    {
        if (empty($SKU_ids))
            return [];

        $key = 'manually';

        $current_page = Request::getPage(null, $current_page);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);

        $res = (new SafeMysql())->fetchAll("SELECT SQL_CALC_FOUND_ROWS mh.*, u.login, sup.id as supplier_id, sup.name as supplier_name FROM movement_history mh 
                                            LEFT JOIN users u ON u.id = mh.user_id 
                                            JOIN SKUs s ON mh.SKU_id = s.id
                                            LEFT JOIN suppliers sup ON sup.id = s.supplier_id
                                                WHERE mh.reason like :key AND mh.event = :event 
                                                    AND mh.SKU_id IN (:SKU_ids) 
                                                        AND mh.warehouse_id = :warehouse_id
                                                    ORDER BY mh.create_date DESC 
                                                            LIMIT {$offset}," . self::LIMIT_PER_PAGE, [
            'key' => '%' . $key . '%',
            'event' => MovementHistory::EVENT_DELETE,
            'SKU_ids' => $SKU_ids,
            'warehouse_id' => $warehouse_id,
        ]);

        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        $data = [];
        foreach ($res as $index => &$item) {
            $addition = json_decode($item['addition'], true);
            if (empty($addition) || empty($addition['movement'])) {
                unset($res[$index]);
                continue;
            }
            $movement = new Movement($addition['movement']);
            $loc_from = Locations::getById($movement->getLocFromId());
            $loc_to = Locations::getById($movement->getLocToId());
            $create_user = Users::getById($movement->getCreateUserId());
            $data[] = [
                'movement_history_id' => $item['id'],
                'qty' => $movement->getQuantity(),
                'loc_from' => !empty($loc_from) ? $loc_from->getLocation($label) : null,
                'loc_to' => !empty($loc_to) ? $loc_to->getLocation($label) : null,
                'create_user' => !empty($create_user) ? $create_user->getLogin() : 'system',
                'create_date' => $movement->getCreateDate(),
                'remove_user' => $item['login'] ?? 'system',
                'remove_date' => $item['create_date'],
                'comment' => $movement->getComment(),
                'status' => $movement->getStatus(),
                'colorStatus' => Movement::getStatusColor($movement->getStatus()),
                'supplier_id' => $item['supplier_id'],
                'supplier_name' => $item['supplier_name']
            ];
        }
        return [$data, (int)$total, $current_page];
    }

    public static function handleRestore($id, User $user, $merge = false): array
    {
        $record = self::getRecord($id);
        if (empty($record))
            return [false, false, 'Record not found'];
        $addition = json_decode($record->getAddition(), TRUE);
        if (empty($addition) || empty($addition['movement']))
            return [false, false, 'Error data'];
        $movement = new Movement($addition['movement']);
        $movement->setComment('Restored ' . date(WithDate::DATE_FRONT_FORMAT) . ' (UTC) by ' . $user->getLogin() . (!empty($movement->getComment()) ? (': ' . $movement->getComment()) : ''));
        $exist_movement = Movements::getByUniqueFields($movement->getSKUId(), $movement->getLocFromId(), $movement->getLocToId());
        if (!empty($exist_movement)) {
            if ($merge) {
                self::restoreToMerge($exist_movement, $movement);
                self::fixRestoreManually($record);
                self::restore($movement, $user->getId(), ['exist_movement' => $exist_movement->toArray()]);
                return [true];
            } else
                return [false, true];
        }
        $movement->setId(null);
        if (!Movement::isExpired($movement->getStatus()))
            $movement->setStatus(Movement::NEW_MOVEMENT);
        $movement->setReason('');
        $data = $movement->toArray();
        unset($data['id']);
        $movement->save(array_keys($data));
        self::fixRestoreManually($record);
        self::restore($movement, $user->getId());
        return [true, false];
    }

    public static function restoreToMerge(Movement $exist_movement, Movement $movement): void
    {
        $exist_movement->setQuantity($exist_movement->getQuantity() + $movement->getQuantity());
        $exist_movement->setStatus(Movement::NEW_MOVEMENT);
        $exist_movement->addComments(json_decode($movement->getComment(), true));
        $exist_movement->save(['id', 'quantity', 'status', 'comment']);
    }

    public static function fixRestoreManually(MovementHistory $movementHistory)
    {
        $movementHistory->setReason('restored');
        $movementHistory->save(['id', 'reason']);
    }

    public static function getReason($priority)
    {
        if (empty($priority))
            return 'manually';
        switch ($priority) {
            case Movement::PF_REPLEN_PRIORITY:
                return 'replenish (SKU)';
            case Movement::MOVEMENT_IN_CART_PRIORITY:
                return 'working';
            case Movement::CANCEL_ORDER_PRIORITY:
                return 'cancel order';
            case Movement::ORDER_REPLEN_PRIORITY:
                return 'replenish (order)';
            case Movement::EXPIRED_PRIORITY:
                return 'expired batch';
            default:
                return 'not defined';
        }
    }
}
