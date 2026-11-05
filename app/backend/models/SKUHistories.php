<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Batch;
use app\core\entities\SKU;
use app\core\entities\Warehouse;
use app\core\helpers\Validator;
use app\core\entities\SKUHistory;
use app\core\helpers\View;
use app\core\helpers\TimeZonesConvertor;
use app\core\statical\WithDate;

class SKUHistories extends \app\core\models\SKUHistories
{
    /**
     * @param $SKU_history SKUHistory
     * @return array
     */
    public static function validate(SKUHistory $SKU_history)
    {
        $rules = [
            'SKU_id' => [
                'val' => $SKU_history->getSKUId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'adjustment' => [
                'val' => $SKU_history->getAdjustment(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [PHP_INT_MIN, PHP_INT_MAX]]
            ],
            'event' => [
                'val' => $SKU_history->getEvent(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [3, 25]]
            ],
            'additional_info' => [
                'val' => $SKU_history->getAdditionalInfo(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [2, 1000]]
            ],
            'warehouse_id' => [
                'val' => $SKU_history->getWarehouseId(),
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

    public static function restoreById($SKU_id): array
    {
        $sql = "SELECT additional_info FROM SKU_history WHERE SKU_id = :SKU_id AND event = :event";
        $additional_info = (new SafeMysql())->fetchScalar($sql, ['SKU_id' => $SKU_id, 'event' => self::EVENT_HARD_REMOVE]);
        $dump = json_decode($additional_info, 1)['dump'] ?? null;
        if (!empty($dump)) {
            return [new SKU($dump['SKU']), $dump['settings']];
        }
        return [null, null];
    }

//    public static function getStock($SKU_id, $warehouse_id)
//    {
//        $SKU_id = (int)$SKU_id;
//        $warehouse_id = (int)$warehouse_id;
//        $sql = "SELECT sh.id, IFNULL(sum(sl.quantity), 0) quantity
//                FROM SKU_history sh
//                    LEFT JOIN SKU_locations sl ON sh.SKU_id = sl.SKU_id
//                    JOIN locations l ON sl.location_id = l.id AND l.warehouse = $warehouse_id AND l.type_id IN ('" . implode("','", Locations::$allowed_type_stock) . "')
//                WHERE sh.SKU_id = $SKU_id AND sh.event = '" . self::EVENT_STOCK . "' AND sh.warehouse_id = $warehouse_id";
//        return Mysql::fetchOneAssoc($sql);
//    }

    public static function changeStockOneLocation($location_id, $SKU_id, $warehouse_id)
    {
        $location = Locations::getById($location_id);
        if (!empty($location) && in_array($location->getTypeId(), Locations::$allowed_type_stock))
            SKUHistories::updateStock($SKU_id, $warehouse_id);
    }

    public static function changeStockTwoLocation($A, $B, $SKU_id, $warehouse_id)
    {
        $location_A = Locations::getById($A);
        $location_B = Locations::getById($B);
        if (empty($location_A) || empty($location_B))
            return;
        $allowed_A = in_array($location_A->getTypeId(), Locations::$allowed_type_stock);
        $allowed_B = in_array($location_B->getTypeId(), Locations::$allowed_type_stock);
        if ($allowed_A != $allowed_B)
            SKUHistories::updateStock($SKU_id, $warehouse_id);
    }

    public static function clearStock($SKU_id, $warehouse_id)
    {
        (new SafeMysql())->execute("
            DELETE FROM SKU_history
            WHERE SKU_id = :SKU_id AND event = :event AND warehouse_id = :warehouse_id", [
            'SKU_id' => $SKU_id,
            'event' => self::EVENT_STOCK,
            'warehouse_id' => $warehouse_id,
        ]);
    }

    public static function updateStock($SKU_id, $warehouse_id, $quan = null)
    {
        $stock = self::getIdByStock($SKU_id, $warehouse_id);
        if (is_null($quan))
            $quan = SKULocations::getStock($SKU_id, $warehouse_id);
        if (empty($stock) || empty($stock['id']))
            (new SafeMysql())->execute("
                INSERT INTO SKU_history (id, SKU_id, event_date, adjustment, event, additional_info, warehouse_id)
                VALUES (NULL, :SKU_id, :event_date, :quan, :event, '', :warehouse_id)", [
                'SKU_id' => $SKU_id,
                'event_date' => date(WithDate::DATE_SQL_FORMAT),
                'quan' => $quan,
                'event' => self::EVENT_STOCK,
                'warehouse_id' => $warehouse_id,
            ]);
        elseif ($stock['adjustment'] != $quan)
            (new SafeMysql())->execute("
                UPDATE SKU_history
                SET adjustment = :quan, event_date = :event_date
                WHERE id = :id", [
                'quan' => $quan,
                'event_date' => date(WithDate::DATE_SQL_FORMAT),
                'id' => $stock['id'],
            ]);
    }

    public static function getDestroyed($from_date, $event, $warehouse_id)
    {
        $sql = "
            SELECT sh.SKU_id, s.name, sh.event_date, sh.adjustment, sup.name sname, b.number, b.expired 
            FROM SKU_history sh
                JOIN SKUs s ON sh.SKU_id = s.id
                JOIN suppliers sup ON s.supplier_id = sup.id
                JOIN batches b ON SUBSTRING_INDEX(SUBSTRING_INDEX(sh.additional_info, '\"batch_id\":', -1), ',\"', 1) = b.id AND sh.SKU_id = b.SKU_id
            WHERE sh.`event` = :event AND sh.warehouse_id = :warehouse_id AND DATE_FORMAT(sh.event_date,'%Y-%m-%d') >= :from_date
            ORDER BY sh.event_date DESC, sh.SKU_id, b.expired";
        $data = (new SafeMysql())->fetchAll($sql, ['event' => $event, 'warehouse_id' => $warehouse_id, 'from_date' => $from_date]);
        SKUBarcodes::addBarcodesToArray($data, 'SKU_id');
        if (empty($data))
            return [];
        $res = [];
        foreach ($data as $SKU_data) {
            $barcode = View::toHtmlConvertWithPrimaryBarcode($SKU_data['barcode']);
            $name = View::toHtml($SKU_data['name']);
            $SKU_id = (int)$SKU_data['SKU_id'];
            $res[] = [
                'event_date' => $SKU_data['event_date'],
                'SKU' => "{$name} (#{$SKU_id})<br/><b>{$barcode}</b>",
                'batch' => Batch::toBatchInfo($SKU_data['number'], $SKU_data['expired'], true, true),
                'supplier' => $SKU_data['sname'],
                'quantity' => $SKU_data['adjustment'],
            ];
        }
        return $res;
    }

    public static function addAutoAssigns(int $SKU_id, array $locs, int $user_id)
    {
        foreach ($locs as $warehouse_id => $location_id)
            self::updateLocations($SKU_id, $user_id, ['inserted' => [$location_id]], $warehouse_id);
    }

    public static function getSKUsWithEvent(array $SKU_ids, string $event, int $warehouse_id, string $additional_info_like = ''): array
    {
        if (!$SKU_ids)
            return $SKU_ids;
        $SKU_ids = array_map('intval', $SKU_ids);
        $params = ['SKU_ids' => $SKU_ids, 'event' => $event, 'warehouse_id' => $warehouse_id];
        $like = '';
        if ($additional_info_like) {
            $like = "AND additional_info LIKE :additional_info_like";
            $params['additional_info_like'] = $additional_info_like;
        }
        $sql = "
        SELECT DISTINCT SKU_id
        FROM SKU_history
        WHERE SKU_id IN (:SKU_ids) AND event = :event AND warehouse_id = :warehouse_id $like";
        return array_map('intval', (new SafeMysql())->fetchArrayOneField($sql, $params));
    }

    public static function getCountBySKUIdAndEvent(int $SKU_id, string $event, int $warehouse_id, string $additional_info_like = ''): int
    {
        $params = ['SKU_id' => $SKU_id, 'event' => $event, 'warehouse_id' => $warehouse_id];
        if ($additional_info_like) {
            $like = "AND additional_info LIKE :additional_info_like";
            $params['additional_info_like'] = $additional_info_like;
        } else
            $like = "";
        $sql = "
        SELECT count(*)
        FROM SKU_history
        WHERE SKU_id = :SKU_id AND event = :event AND warehouse_id = :warehouse_id $like";
        return (int)(new SafeMysql())->fetchScalar($sql, $params);
    }

    public static function getBySKUIdAndEvent(int $SKU_id, string $event, Warehouse $warehouse, int $start, int $count, string $additional_info_like = ''): array
    {
        $params = ['SKU_id' => $SKU_id, 'event' => $event, 'warehouse_id' => $warehouse->getId()];
        if ($additional_info_like) {
            $like = "AND additional_info LIKE :additional_info_like";
            $params['additional_info_like'] = $additional_info_like;
        } else
            $like = "";
        $sql = "
        SELECT event_date, additional_info
        FROM SKU_history
        WHERE SKU_id = :SKU_id AND event = :event AND warehouse_id = :warehouse_id $like
        ORDER BY event_date DESC
        LIMIT $start, $count";
        return (new SafeMysql())->fetchAll($sql, $params);
    }

    public static function getBySKUIdAndEvents(int $SKU_id, array $events, Warehouse $warehouse, int $start, int $count, string $additional_info_like = '')
    {
        $params = ['SKU_id' => $SKU_id, 'events' => $events, 'warehouse_id' => $warehouse->getId()];
        if ($additional_info_like) {
            $like = "AND additional_info LIKE :additional_info_like";
            $params['additional_info_like'] = $additional_info_like;
        } else
            $like = "";
        $sql = "
        SELECT event, event_date, adjustment, additional_info
        FROM SKU_history
        WHERE SKU_id = :SKU_id AND event IN (:events) AND warehouse_id = :warehouse_id $like
        ORDER BY event_date DESC
        LIMIT $start, $count";
        return (new SafeMysql())->fetchAll($sql, $params);
    }

    public static function prepareMovementHistory(array $history, Warehouse $warehouse): array
    {
        if (!$history)
            return [];
        $locs = Locations::getByDiscrepancyHoldMLocation($warehouse->getId());
        if (count($locs) != 1)
            return [];
        $DH_m = $locs[0]->getId();
        $locs = Locations::getByDiscrepancyHoldPLocation($warehouse->getId());
        if (count($locs) != 1)
            return [];
        $DH_p = $locs[0]->getId();
        $result = [];
        $locations_ids = $user_ids = [];
        foreach ($history as $item) {
            $additional_info = json_decode($item['additional_info'], true);
            $locations_ids[(int)$additional_info['From']] = true;
            $locations_ids[(int)$additional_info['To']] = true;
            $user_ids[(int)$additional_info['User']] = true;
            $result[] = [
                'event_date' => (new TimeZonesConvertor)->setDateByString($item['event_date'])->convert($warehouse->getTimezone())->getConvertedDateString(),
                'quantity' => $additional_info['Units'],
                'from' => $additional_info['From'],
                'to' => $additional_info['To'],
                'reason' => $additional_info['UserReason'] ?? '',
                'user_id' => (int)$additional_info['User'],
            ];
        }
        $locations = Locations::getByIds(array_keys($locations_ids));
        $users = Users::getByIds(array_keys($user_ids));
        foreach ($result as &$item) {
            if ($item['to'] === $DH_m) {
                $item['location'] = $locations[$item['from']]['name'] ?? '';
                $item['location_id'] = $item['from'];
                $item['quantity'] = -$item['quantity'];
            } elseif ($item['from'] === $DH_p) {
                $item['location'] = $locations[$item['to']]['name'] ?? '';
                $item['location_id'] = $item['to'];
            } else {
                $item['location'] = 'Error';
                $item['quantity'] = 'Error';
            }
            $item['user'] = $users[$item['user_id']] ?? 'Unknown';
        }
        unset($item);
        return $result;
    }
}
