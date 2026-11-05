<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\db\QueryBuilder;
use app\core\entities\Location;
use app\core\entities\Movement;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\Filter;

class Movements extends \app\core\models\Movements
{
    const LIMIT_PER_PAGE = 20;

    const CREATE_MOVEMENT               = 'movement';
    const CREATE_REQUEST                = 'request';

    public const FILTER_MOVEMENT_PARAM  = 'movements_filters';
    public const FILTER_REQUEST_PARAM   = 'request_filters';

    public static function canUploadMovements(User $user): bool
    {
        return $user->isRoleSA() || $user->isRoleManager();
    }

    public static function delMovement($id, User $user, $reason = 'manually')
    {
        $id = (int)$id;
        $mysql = new SafeMysql();
        $movements = $mysql->fetchOne("SELECT * FROM movements WHERE id = :id", ['id' => $id]);
        if (!empty($movements)) {
            $mysql->execute("DELETE FROM movements WHERE id = :id", ['id' => $id]);
            MovementHistories::delete(new Movement($movements), $user, $reason);
        }
    }

    public static function getSKUs4Search($SKUs)
    {
        $res = [];
        foreach (array_unique(Arr::column($SKUs, 'barcode')) as $barcode)
            $res[] = [
                'value' => $barcode,
                'label' => $barcode
            ];
        return $res;
    }

    public static function getInfoSKUs($SKUs)
    {
        $res = [];
        foreach ($SKUs as $SKU)
            $res[$SKU['barcode'] . '_' . $SKU['supplier_id']] = $SKU;
        return $res;
    }

    public static function colorMovement($movement)
    {
        if (Movement::isNonCompliant($movement['status']))
            return 'danger';
        switch ($movement['priority']) {
            case Movement::EXPIRED_PRIORITY:
                return 'danger';
            case Movement::ORDER_REPLEN_PRIORITY:
            case Movement::CANCEL_ORDER_PRIORITY:
                return 'warning';
            case Movement::MOVEMENT_IN_CART_PRIORITY:
                return 'success';
            case Movement::PF_REPLEN_PRIORITY:
                return 'primary';
            default:
                return 'secondary';
        }
    }

    public static function getAdminMovementStyle($movement)
    {
        $style = '';
        if (in_array($movement['priority'], [Movement::ORDER_REPLEN_PRIORITY, Movement::CANCEL_ORDER_PRIORITY]))
            $style = "bg-warning text-white";
        return $style;
    }

    public static function getByUniqueFields($SKU_id, $loc_from_id, $loc_to_id): ?Movement
    {
        $data = (new SafeMysql())->fetchOne('SELECT * FROM movements WHERE type AND SKU_id = :SKU_id AND loc_from_id = :loc_from_id AND loc_to_id = :loc_to_id', [
            'SKU_id' => (int)$SKU_id,
            'loc_from_id' => (int)$loc_from_id,
            'loc_to_id' => (int)$loc_to_id,
        ]);
        return !empty($data) ? new Movement($data) : null;
    }

    public static function getMovementByWorker($warehouse_id, $user_id, $filters): array
    {
        $progress = Movement::IN_PROGRESS;
        $active_status = Location::ACTIVE;
        $warehouse_id = (int) $warehouse_id;
        $user_id = (int) $user_id;
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT m.id, m.SKU_id, s.name, s.photo, s.supplier_id, m.quantity,
                   m.loc_from_id, 
                   CASE 
                     WHEN lf.type_id = 'US' 
                     THEN CONCAT(lf_u.login, ' #', lf_u.id)  
                     ELSE CONCAT(IF(w.label = '' OR w.label IS NULL, lf.warehouse, w.label), '-', lf.type_id, '-', lf.zone, '-', lf.column, '-', lf.row)
                   END AS lf_name,
                   IF(lf.alias IS NULL OR lf.alias = '', CONCAT(lf.zone, lf.`column`, lf.`row`), lf.alias) lf_alias,
                   m.loc_to_id, 
                   CASE 
                     WHEN lt.type_id = 'US' 
                     THEN CONCAT(lt_u.login, ' #', lt_u.id)
                     ELSE CONCAT(IF(w.label = '' OR w.label IS NULL, lt.warehouse, w.label), '-', lt.type_id, '-', lt.zone, '-', lt.column, '-', lt.row) 
                   END AS lt_name,
                   IF(lt.alias IS NULL OR lt.alias = '', CONCAT(lt.zone, lt.`column`, lt.`row`), lt.alias) lt_alias,
                   m.create_date, m.create_user_id, uc.login clogin, m.user_id, u.login, m.status, m.priority, m.reason, m.type
            FROM movements m");
        $query->addJoin("JOIN SKUs s ON s.id = m.SKU_id");
        $query->addJoin("JOIN suppliers sup ON s.supplier_id = sup.id");
        $query->addJoin("JOIN users uw ON sup.user_id = uw.id");
        $query->addJoin("JOIN locations lf ON lf.id = m.loc_from_id AND lf.warehouse = :warehouse_id AND lf.state = :active_status");
        $query->addJoin("JOIN locations lt ON lt.id = m.loc_to_id AND lt.warehouse = :warehouse_id AND lt.state = :active_status");
        $query->addJoin("JOIN warehouses w ON lf.warehouse = w.id");
        $query->addJoin("LEFT JOIN users uc ON uc.id = m.create_user_id");
        $query->addJoin("LEFT JOIN users u ON u.id = m.user_id");
        $query->addJoin("LEFT JOIN users lt_u ON lt_u.id = lt.row");
        $query->addJoin("LEFT JOIN users lf_u ON lf_u.id = lf.row");
        $query->addWhere("(m.status = :progress AND m.user_id = :user_id) OR (m.status IN (:worker_statuses))");
        $params = [
            'warehouse_id' => $warehouse_id,
            'active_status' => $active_status,
            'progress' => $progress,
            'user_id' => $user_id,
            'worker_statuses' => Movement::WORKER_STATUSES,
        ];
        if (!empty($filters['order_by']))
            self::handleOrderByQuery($query, (int)$filters['order_by'], $warehouse_id);
        if (!empty($filters['comment_filters']) && $filters['comment_filters'] != Filter::NOT_SELECTED) {
            $comment_conditions = [];
            if (is_array($filters['comment_filters'])) {
                foreach ($filters['comment_filters'] as $comment_filter)
                    if (array_key_exists($comment_filter, Movement::COMMENT_FILTERS)) {
                        $comment = Movement::COMMENT_FILTERS[$comment_filter];
                        $comment_conditions[] = "m.comment LIKE :comment_{$comment_filter}";
                        $params["comment_{$comment_filter}"] = '%"' . $comment . '%';
                    }
                if (!empty($comment_conditions))
                    $query->addWhere('(' . implode(' OR ', $comment_conditions) . ')');
            } elseif ($filters['comment_filters'] != Filter::NOT_SELECTED) {
                $query->addWhere("m.comment LIKE :comment_filter");
                $params['comment_filter'] = '%"' . $filters['comment_filters'] . '%';
            }
        }
        $items = (new SafeMysql())->fetchAll($query->build(), $params);
        SKUBarcodes::addBarcodesToArray($items, 'SKU_id');
        return $items;
    }

    public static function getUnrestrictedMovementByWorker(int $warehouse_id, int $user_id): array
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT m.id, m.SKU_id, s.name, s.photo, s.supplier_id, m.quantity,
                   m.loc_from_id, CONCAT(IF(w.label = '' OR w.label IS NULL, lf.warehouse, w.label), '-', lf.type_id, '-', lf.zone, '-', lf.column, '-', lf.row) lf_name,
                   IF(lf.alias IS NULL OR lf.alias = '', CONCAT(lf.zone, lf.`column`, lf.`row`), lf.alias) lf_alias,
                   m.loc_to_id, CONCAT(IF(w.label = '' OR w.label IS NULL, lt.warehouse, w.label), '-', lt.type_id, '-', lt.zone, '-', lt.column, '-', lt.row) lt_name,
                   IF(lt.alias IS NULL OR lt.alias = '', CONCAT(lt.zone, lt.`column`, lt.`row`), lt.alias) lt_alias,
                   m.create_date, m.create_user_id, uc.login clogin, m.user_id, u.login, m.status, m.type
            FROM movements m");
        $query->addJoin("JOIN SKUs s ON m.SKU_id = s.id");
        $query->addJoin("JOIN locations lf ON m.loc_from_id = lf.id AND lf.warehouse = :warehouse_id");
        $query->addJoin("JOIN locations lt ON m.loc_to_id = lt.id AND lt.warehouse = :warehouse_id");
        $query->addJoin("JOIN warehouses w ON lf.warehouse = w.id");
        $query->addJoin("LEFT JOIN users uc ON m.create_user_id = uc.id");
        $query->addJoin("LEFT JOIN users u ON m.user_id = u.id");
        $query->addWhere('m.type = :type_unrestricted');
        $query->addWhere("(m.status = :new_status) OR (m.status = :in_progress_status AND m.user_id = :user_id)");
        $params = [
            'warehouse_id' => $warehouse_id,
            'type_unrestricted' => Movement::TYPE_UNRESTRICTED,
            'new_status' => Movement::NEW_MOVEMENT,
            'in_progress_status' => Movement::IN_PROGRESS,
            'user_id' => $user_id,
        ];
        $items = (new SafeMysql())->fetchAll($query->build(), $params);
        SKUBarcodes::addBarcodesToArray($items, 'SKU_id');
        return $items;
    }
}
