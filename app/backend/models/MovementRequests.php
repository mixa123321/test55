<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\db\QueryBuilder;
use app\core\entities\Location;
use app\core\entities\MovementRequest;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\helpers\Filter;
use app\core\helpers\Request;
use app\core\models\MovementRequestHistories;
use app\core\models\SKUBarcodes;

class MovementRequests extends \app\core\models\MovementRequests
{
    /** @throws */
    public static function getByFilters(int $warehouse_id, $filters = []): array
    {
        $query = new QueryBuilder();
        $location_select_name = Locations::getLocationNameSelect('l', 'w');
        $params = [
            'warehouse_id' => $warehouse_id,
        ];
        $query->setSelectFrom("
            SELECT SQL_CALC_FOUND_ROWS mr.*, 
                    s.id SKU_id, s.name,
                    CASE 
                        WHEN l.type_id = 'US' 
                            THEN CONCAT(lu.login, ' #', lu.id)
                            ELSE $location_select_name
                    END AS loc_name

            FROM movement_requests mr");
        $query->addJoin("LEFT JOIN SKUs s ON s.id = mr.SKU_id");
        $query->addJoin("JOIN locations l ON l.id = mr.location_id AND l.warehouse = :warehouse_id");
        $query->addJoin("JOIN warehouses w ON l.warehouse = w.id");
        $query->addJoin("LEFT JOIN users lu ON lu.id = l.row");
        if (!empty($filters['barcode'])) {
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode($filters['barcode'], Filter::getFilterTypeFromRequest($filters, 'barcode'));
            if (!empty($SKU_ids)) {
                $query->addWhere('s.id IN (:SKU_ids)');
                $params['SKU_ids'] = $SKU_ids;
            } else
                return ['records' => [], 'total' => 0, 'current_page' => 1];
        }
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->addWhere('mr.status = (:status)');
            $params['status'] = $filters['status'];
        }
        if (isset($filters['type_location']) && $filters['type_location']) {
            $query->addWhere('mr.type_location = :type_location');
            $params['type_location'] = $filters['type_location'];
        }
        if (isset($filters['type']) && $filters['type']) {
            if (Location::isCart($filters['type'])) {
                $query->addWhere("l.type_id IN (:type_ids)");
                $params['type_ids'] = Location::CART_TYPES;
            } else {
                $query->addWhere("l.type_id = :type_id");
                $params['type_id'] = $filters['type'];
            }
        }
        $query->setOrderBy('id DESC');
        $current_page = Request::getPage($filters);
        $offset = Pagination::LIMIT_PER_PAGE * ($current_page - 1);
        $query->setLimit("{$offset}, " . Pagination::LIMIT_PER_PAGE);
        $records_raw = (new SafeMysql())->fetchAll($query->build(), $params);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        SKUBarcodes::addBarcodesToArray($records_raw, 'SKU_id');
        return ['records' => $records_raw, 'total' => $total, 'current_page' => $current_page];
    }

    public static function getTypeLocation(string $type_location): string
    {
        $arrow = '<i class="fas fa-arrow-right"></i>';
        if (!in_array($type_location, MovementRequest::ALL_TYPES)) {
            $badge_color = 'danger';
            $type_location = 'error';
        } else {
            $badge_color = $type_location === MovementRequest::TYPE_LOCATION_FROM ? 'primary' : 'success';
            $type_location = $type_location === MovementRequest::TYPE_LOCATION_FROM ? "$type_location $arrow" : "$arrow $type_location";
        }
        return "<div class=\"badge badge-$badge_color\">$type_location</div>";
    }

    public static function cancelRequest(int $id, User $user)
    {
        $request = self::getById($id);
        if (empty($request))
            return;
        self::changeStatus($id, MovementRequest::CANCELLED);
        MovementRequestHistories::cancel($id, $user->getId());
    }

    public static function createRequest(Location $location, string $type_location, SKU $SKU, int $qty, User $user)
    {
        $request_id = self::add($location->getId(), $type_location, $SKU->getId(), $qty);
        MovementRequestHistories::create($request_id, $user->getId());
    }

    public static function processingRequest(MovementRequest $request, int $movement_id, int $qty, User $user)
    {
        $fields = ['id', 'accepted_quantity', 'status'];
        $request->setAcceptedQuantity($request->getAcceptedQuantity() + $qty);
        MovementRequestHistories::movement($request->getId(), $movement_id, $qty, $user->getId());
        if ($request->getQuantity() === $request->getAcceptedQuantity()) {
            $request->setStatus(MovementRequest::DONE);
            MovementRequestHistories::close($request->getId(), $user->getId());
        } else {
            if (!$request->inProgress())
                $request->setStatus(MovementRequest::IN_PROGRESS);
            else
                array_pop($fields);
        }
        $request->save($fields);
    }
}
