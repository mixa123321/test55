<?php

namespace app\core\models;

use app\backend\models\BarCodes;
use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\helpers\Request;
use app\core\statical\TypeLocation;
use app\core\helpers\Filter;
use FPDF;

class Locations
{
    const LIMIT_PER_PAGE = 20;
    const STANDARD_PREFIX = 'W';            // Location::ST

    public static $colorLocation = [
        Location::DH => 'MistyRose',
        Location::CA => 'OldLace',
        Location::US => 'OldLace',
        Location::OB => 'Beige',
        Location::PA => 'Honeydew',
        Location::PR => 'Honeydew',
        Location::PF => 'Azure',
        Location::ST => 'AliceBlue',
        Location::TP => 'Snow',
        Location::LP => 'Snow',
    ];

    public static $allowed_type_stock = [Location::PF, Location::ST, Location::CA, Location::US];

    /**
     * @param $id
     * @return null|Location
     */
    public static function getById($id)
    {
        static $locations = [];
        if (!array_key_exists($id, $locations)) {
            $data = (new SafeMysql())->fetchOne('SELECT * FROM locations WHERE id = :id', [
                'id' => $id
            ]);
            $locations[$id] = $data ? new Location($data) : null;
        }
        return $locations[$id];
    }

    public static function getWarehouseById($id)
    {
        $loc = self::getById((int)$id);
        if (empty($loc))
            return 0;
        return $loc->getWarehouse();
    }

    public static function getLocationNameSelect($location_alias, $warehouse_alias = null)
    {
        $prefix = !empty($warehouse_alias)
            ? sprintf('IF(%1$s.label = "" OR %1$s.label IS NULL, %2$s.warehouse, %1$s.label)', $warehouse_alias, $location_alias)
            : sprintf('%s.warehouse', $location_alias);
        return sprintf(
            'CONCAT(%1$s, "-", %2$s.type_id, "-", %2$s.zone, "-", %2$s.`column`, "-", %2$s.row)',
            $prefix,
            $location_alias,
        );
    }

    public static function getAliasSelect($location_alias)
    {
        return sprintf(
            'IF(%1$s.alias IS NULL OR %1$s.alias = \'\', CONCAT(%1$s.zone, %1$s.`column`, %1$s.`row`), %1$s.alias)',
            $location_alias,
        );
    }

    public static function getByIds($ids, $fields = null)
    {
        if (!count($ids))
            return [];
        $ids = array_map('intval', $ids);
        if (empty($fields))
            $fields = self::getLocationNameSelect('l', 'w') . ' as name';
        $sql = "SELECT l.id, $fields
                FROM locations l
                    JOIN warehouses w ON l.warehouse = w.id
                WHERE l.id IN (:ids)";
        return (new SafeMysql())->fetchAssocWithKey($sql, ['ids' => $ids], 'id');
    }

    private static function getWhereLocations(int $warehouse, string $type_id, string $zone, string $column, string $row): string
    {
        $where = [];
        if ($warehouse !== 0)
            $where[] = "warehouse = " . (int)$warehouse;
        if (!empty($type_id))
            $where[] = "type_id = '" . addslashes($type_id) . "'";
        if (!empty($zone))
            $where[] = "zone = '" . addslashes($zone) . "'";
        if (!empty($column))
            $where[] = "`column` = '" . addslashes($column) . "'";
        if (!empty($row))
            $where[] = "`row` = '" . addslashes($row) . "'";
        return implode(" AND ", $where);
    }

    /**
     * @param int $warehouse
     * @param string $type_id
     * @param string $zone
     * @param string $column
     * @param string $row
     * @return Location[]
     */
    public static function getByLocation($warehouse = 0, $type_id = '', $zone = '', $column = '', $row = '')
    {
        $where = self::getWhereLocations($warehouse, $type_id, $zone, $column, $row);
        $sql = 'SELECT * FROM locations' . (!empty($where) ? " WHERE $where" : '') . ' ORDER BY warehouse, type_id, zone, `column`, `row`';
        $loc_info = (new SafeMysql())->fetchAll($sql);
        if (!empty($loc_info)) {
            $locs = [];
            foreach ($loc_info as $loc_id => $loc)
                $locs[$loc_id] = new Location($loc);
            return $locs;
        }
        return [];
    }

    public static function getByCancelLocation($warehouse_id): array
    {
        return self::getByLocationArray($warehouse_id, Location::SPECIAL_LOCATION__CANCEL);
    }

    public static function getByExpiredLocation($warehouse_id): array
    {
        return self::getByLocationArray($warehouse_id, Location::SPECIAL_LOCATION__EXPIRED);
    }

    public static function getByDiscrepancyHoldSTLocation($warehouse_id): array
    {
        return self::getByLocationArray($warehouse_id, Location::SPECIAL_LOCATION__DH_ST);
    }

    public static function getByDiscrepancyHoldMLocation($warehouse_id): array
    {
        static $loc = null;
        if ($loc === null)
            $loc = self::getByLocationArray($warehouse_id, Location::SPECIAL_LOCATION__DH_M);
        return $loc;
    }

    public static function getByDiscrepancyHoldPLocation($warehouse_id): array
    {
        static $loc = null;
        if ($loc === null)
            $loc = self::getByLocationArray($warehouse_id, Location::SPECIAL_LOCATION__DH_P);
        return $loc;
    }

    public static function getByPALocation($warehouse_id): array
    {
        return self::getByLocationArray($warehouse_id, Location::SPECIAL_LOCATION__PA);
    }

    public static function getByNoVerifyLocation($warehouse_id): array
    {
        return self::getByLocationArray($warehouse_id, Location::SPECIAL_LOCATION__NOVERIFY);
    }

    private static function isLocation(Location $location, $standard): bool
    {
        if (empty($location))
            return false;
        return $location->getTypeId() == $standard[0] &&
            $location->getZone() == $standard[1] &&
            $location->getColumn() == $standard[2] &&
            $location->getRow() == $standard[3];
    }

    public static function isCancelLocation(Location $location): bool
    {
        return self::isLocation($location, Location::SPECIAL_LOCATION__CANCEL);
    }

    public static function isExpiredLocation(Location $location): bool
    {
        return self::isLocation($location, Location::SPECIAL_LOCATION__EXPIRED);
    }

    public static function isDHMLocation(Location $location): bool
    {
        return self::isLocation($location, Location::SPECIAL_LOCATION__DH_M);
    }

    public static function isDHPLocation(Location $location): bool
    {
        return self::isLocation($location, Location::SPECIAL_LOCATION__DH_P);
    }

    public static function isNoVerifyLocation(Location $location): bool
    {
        return self::isLocation($location, Location::SPECIAL_LOCATION__NOVERIFY);
    }

    /**
     * @param int $warehouse
     * @param string $type_id
     * @param string $zone
     * @param string $column
     * @param string $row
     * @return Location
     */
    public static function getByName($warehouse, $type_id, $zone, $column, $row)
    {
        if (empty($warehouse) || empty($type_id) || empty($zone) || empty($column) || empty($row))
            return null;
        $locs = self::getByLocation($warehouse, $type_id, $zone, $column, $row);
        if (count($locs) != 1)
            return null;
        return $locs[0];
    }

    public static function getByLocationChunk($chunk, $warehouse = 0, $type_id = '', $zone = '', $column = '', $row = '')
    {
        $where = self::getWhereLocations($warehouse, $type_id, $zone, $column, $row);
        if ($chunk === -1)
            $chunk = 1;
        $offset = LIMIT_BARCODES_PDF * ($chunk - 1);
        $limitSQL = "LIMIT {$offset}, " . LIMIT_BARCODES_PDF;
        $sql = 'SELECT SQL_CALC_FOUND_ROWS *
                FROM locations ' .
                (!empty($where) ? "WHERE $where" : '') . "
                ORDER BY warehouse, type_id, LPAD(zone, 5, ' '), LPAD(`column`, 5, '0'), LPAD(`row`, 5, ' ')
                $limitSQL";
        $loc_info = (new SafeMysql())->fetchAll($sql);
        $locs = [];
        if (!empty($loc_info)) {
            foreach ($loc_info as $loc_id => $loc)
                $locs[$loc_id] = new Location($loc);
        }
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS()');
        return [$locs, $total, ceil($total / LIMIT_BARCODES_PDF)];
    }

    public static function getIdByStandard($warehouse_id, array $name)
    {
        return (int) (new SafeMysql())->fetchScalar("
            SELECT id
            FROM locations
            WHERE warehouse = :warehouse_id AND type_id = :type_id AND zone = :zone AND `column` = :column AND `row` = :row
            LIMIT 1", [
                'warehouse_id' => $warehouse_id,
                'type_id' => $name[0],
                'zone' => $name[1],
                'column' => $name[2],
                'row' => $name[3],
        ]);
    }

    public static function getByCoords(int $warehouse_id, string $name): ?Location
    {
        if (!Location::isLocationName($name))
            return null;
        $parts = explode('-', $name);
        $locs_info = (new SafeMysql())->fetchAll(
            'SELECT *
            FROM locations
            WHERE warehouse = :warehouse_id AND type_id = :parts_1 AND zone = :parts_2 AND `column` = :parts_3 AND `row` = :parts_4',
            ['warehouse_id' => $warehouse_id, 'parts_1' => $parts[1], 'parts_2' => $parts[2], 'parts_3' => $parts[3], 'parts_4' => $parts[4]]
        );
        if (empty($locs_info) || count($locs_info) != 1)
            return null;
        return new Location($locs_info[0]);
    }

    public static function getByAlias(int $warehouse_id, string $name): ?Location
    {
        $locs_info = (new SafeMysql())->fetchAll(
            'SELECT *
            FROM locations
            WHERE warehouse = :warehouse_id AND IF(alias IS NULL OR alias = "", CONCAT(zone, `column`, `row`), alias) = :name',
            ['warehouse_id' => $warehouse_id, 'name' => $name]
        );
        if (empty($locs_info) || count($locs_info) != 1)
            return null;
        return new Location($locs_info[0]);
    }

    public static function getByFilters($filters = [], $warehouse_id)
    {
        $allowed_filters = ['warehouse', 'type_id', 'zone', 'column', 'row'];
        $query = new QueryBuilder();
        $query->setSelectFrom("SELECT SQL_CALC_FOUND_ROWS * FROM locations");
        $query->addWhere("type_id <> '" . Location::US . "'");
        $query->addWhere("warehouse = " . (int)$warehouse_id);
        foreach ($allowed_filters as $allowed_filter)
            if (!empty($filters[$allowed_filter]))
                $query->addWhere("`{$allowed_filter}` = '" . addslashes(trim($filters[$allowed_filter])) . "'");
        if (!empty($filters['floor']))
            $query->addWhere("mezzanine = '" . ($filters['floor'] < 0 ? 0 : 1) . "'");
        $query->setOrderBy("warehouse, type_id, LPAD(zone, 10, ' '), LPAD(`column`, 10, '0'), LPAD(`row`, 10, ' ')");
        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);
        $query->setLimit("{$offset}, " . self::LIMIT_PER_PAGE);

        $records_raw = (new SafeMysql())->fetchAll($query->build());
        $record_objects = [];
        foreach ($records_raw as $item)
            $record_objects[] = new Location($item);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS()');
        return ['records' => $record_objects, 'total' => $total, 'current_page' => $current_page];
    }

    public static function getSize($id)
    {
        $sql = "SELECT size_id FROM locations WHERE id = :id AND state = 1";
        return (int)(new SafeMysql())->fetchOne($sql, ['id' => $id])['size_id'];
    }

    public static function getFromSizes($sizes, $warehouse = 1)
    {
        if (!is_array($sizes) || !count($sizes))
            return [];
        $sizes = '(' . implode(', ', array_map('intval', $sizes)) . ')';
        $sql = "SELECT id, type_id, `zone`, `column`, `row`, size_id, 0 summa
                FROM locations
                WHERE size_id in $sizes AND warehouse = :warehouse AND state=1 AND type_id IN (:storage_types)";
        $res = (new SafeMysql())->fetchAssocWithKey($sql, ['warehouse' => $warehouse, 'storage_types' => Location::STORAGE_TYPES], 'id');
        return $res;
    }

    public static function getAllTypeId($warehouse_id)
    {
        $data = (new SafeMysql())->fetchAll("SELECT distinct type_id FROM locations WHERE warehouse = :warehouse_id AND type_id<>'" . Location::US . "' ORDER BY 1", ['warehouse_id' => $warehouse_id]);
        if (!empty($data)) {
            $types = [];
            foreach ($data as $type_id)
                $types[$type_id['type_id']] = TypeLocation::getNameLocation($type_id['type_id']);
            return $types;
        }
        return [];
    }

    public static function getAllZone($warehouse_id)
    {
        $data = (new SafeMysql())->fetchAll("SELECT distinct zone FROM locations WHERE warehouse = :warehouse_id AND type_id<>'" . Location::US . "' ORDER BY 1", ['warehouse_id' => $warehouse_id]);
        if (!empty($data)) {
            $zones = [];
            foreach ($data as $zone)
                $zones[] = $zone['zone'];
            return $zones;
        }
        return [];
    }

    public static function getAllColumn($warehouse_id)
    {
        $data = (new SafeMysql())->fetchAll("SELECT distinct `column` FROM locations WHERE warehouse = :warehouse_id AND type_id<>'" . Location::US . "' ORDER BY 1", ['warehouse_id' => $warehouse_id]);
        if (!empty($data)) {
            $columns = [];
            foreach ($data as $column)
                $columns[] = $column['column'];
            return $columns;
        }
        return [];
    }

    public static function getAllRow($warehouse_id)
    {
        $data = (new SafeMysql())->fetchAll("SELECT distinct `row` FROM locations WHERE warehouse = :warehouse_id AND type_id<>'" . Location::US . "' ORDER BY 1", ['warehouse_id' => $warehouse_id]);
        if (!empty($data)) {
            $rows = [];
            foreach ($data as $row)
                $rows[] = $row['row'];
            return $rows;
        }
        return [];
    }

    public static function getFreeByZone($warehouse_id, $size, $nums, $mezzanine)
    {
        $warehouse_id = (int) $warehouse_id;
        $size = (int) $size;
        $A = ((int) $nums - 1) * $size + 1;
        $B = (int) $nums * $size;
        $mezzanine = (int) $mezzanine;
        $ob_loc = Location::SPECIAL_LOCATION__ORDER_BIN;
        $sql = "SELECT id
                FROM locations
                WHERE warehouse = :warehouse_id AND
                      type_id='{$ob_loc[0]}' AND
                      zone='{$ob_loc[1]}' AND
                      `column` = '{$ob_loc[2]}' AND 
                      mezzanine = :mezzanine AND 
                      `row` BETWEEN $A AND $B AND
                      state = 1 AND
                      id NOT IN (SELECT location_id FROM order_location)
                ORDER BY LPAD(`row`, 5, '0')
                LIMIT 1";
        return (new SafeMysql())->fetchScalar($sql, ['warehouse_id' => $warehouse_id, 'mezzanine' => $mezzanine]);
    }

    private static function getFreeZone($A, $B, $warehouse_id, $mezzanine)
    {
        $A = (int) $A;
        $B = (int) $B;
        $warehouse_id = (int) $warehouse_id;
        $mezzanine = (int) $mezzanine;
        $ob_loc = Location::SPECIAL_LOCATION__ORDER_BIN;
        $sql = "SELECT COUNT(*)
                FROM locations
                WHERE warehouse = :warehouse_id AND
                      mezzanine = :mezzanine AND
                      type_id = '{$ob_loc[0]}' AND
                      zone = '{$ob_loc[1]}' AND
                      `column` = '{$ob_loc[2]}' AND
                      `row` BETWEEN $A AND $B AND
                      state = 1 AND
                      id NOT IN (SELECT location_id FROM order_location)";
        return (new SafeMysql())->fetchScalar($sql, ['warehouse_id' => $warehouse_id, 'mezzanine' => $mezzanine]);
    }

    public static function getZoneWithMostOrders($warehouse_id, $qty, $nums)
    {
        $warehouse_id = (int)$warehouse_id;
        $res = [[], []];
        $num = 1;
        for ($w = 0; $w < 2; $w++) {
            for ($i = 0; $i < $nums[$w]; $i++) {
                $end = $qty + $num - 1;
                $tmp = self::getFreeZone($num, $end, $warehouse_id, $w);
                if (!empty($tmp))
                    $res[$w][] = [$i + 1, $tmp];
                $num += $qty;
            }
            usort($res[$w], function ($el1, $el2) {
                if ($el1[1] == $el2[1])
                    return ($el1[0] < $el2[0]) ? -1 : 1;
                else
                    return ($el1[1] > $el2[1]) ? -1 : 1;
            });
        }
        return $res;
    }

    public static function printBarcodes($locations, $label, $top, $left, $right, $down, $in_row, $in_col, $chunk, $pages)
    {
        $barcodes = [];
        foreach ($locations as $location)
            if (!empty($barcode_text = $location->getLocationReal($label)))
                $barcodes[] = new BarCode128($barcode_text, 120, 0, ROOT_PATH . COMMON_FONT . '/consola.ttf');
        require(ROOT_PATH . VENDOR_PATH . '/FPDF/fpdf.php');
        $pdf = new FPDF();
        $pdf->SetTitle('Locations' . ($chunk != -1 ? " ($chunk of $pages)" : ''));
        $pdf->AddPage();
        BarCodes::showBarCodesTemplate($pdf, $barcodes,
            $top, $left,
            $right, $down,
            $in_row, $in_col);
        $pdf->Output();
    }

    public static function getLocationZones($warehouse_id, $type_id, $mezzanine)
    {
        $warehouse_id = (int)$warehouse_id;
        $mezzanine = (int)$mezzanine;
        $loc_zones = (new SafeMysql())->fetchArrayOneField("
            SELECT DISTINCT zone
            FROM locations
            WHERE type_id = :type_id AND state = 1 AND zone NOT IN ('EX', 'ORD') AND warehouse = :warehouse_id AND mezzanine = :mezzanine",['warehouse_id' => $warehouse_id, 'type_id' => $type_id, 'mezzanine' => $mezzanine]);
        if (empty($loc_zones))
            return [];
        $res = [];
        foreach ($loc_zones as $loc_zone)
            $res[$loc_zone] = ['m' => 0, 'u' => 0, 'w' => []];
        return $res;
    }

    public static function getAisles($zone, $mezzanine)
    {
        $zone_str = $zone;
        if (($mezzanine && strpos($zone, 'MZ') === 0) || (!$mezzanine && strpos($zone, self::STANDARD_PREFIX) === 0))
            $zone_str = substr($zone_str, strlen(self::STANDARD_PREFIX));
        return $zone_str;
    }

    public static function isItemsAisles($zone, $type_id, $mezzanine, $warehouse_id)
    {
        if ($zone == Filter::NOT_SELECTED)
            return true;
        if (strpos($zone, '|') !== false || strpos($zone, '%7C') !== false)
            return true;
        if (self::getAisles($zone, $mezzanine) != $zone)
            return true;
        if (in_array($zone, LocationTypeZones::getByWarehouseAndTypeId((int)$warehouse_id, $type_id)))
            return true;
        return false;
    }

    /**
     * @param array $ids
     * @return Location[]
     * @throws \app\core\db\DBException
     */
    public static function getByIdsLocations(array $ids): array
    {
        if (!$ids)
            return [];
        $ids = array_map('intval', $ids);
        $items = (new SafeMysql())->fetchAll('SELECT * FROM locations WHERE id IN (:ids)', ['ids' => $ids]);
        $data = [];
        foreach ($items as $item)
            $data[(int)$item['id']] = new Location($item);
        return $data;
    }

    private static function getByLocationArray($warehouse, array $location)
    {
        return self::getByLocation($warehouse, $location[0] ?? '', $location[1] ?? '', $location[2] ?? '', $location[3] ?? '');
    }

    public static function getReservedLocations(int $warehouse_id): array
    {
        $order_cancel = Locations::getByCancelLocation($warehouse_id);
        $expired = Locations::getByExpiredLocation($warehouse_id);
        return array_map(function (Location $l) {
            return $l->getId();
        }, array_merge($order_cancel, $expired));
    }

    public static function getIdsFromRanges(int $warehouse_id, string $type_id, array $ranges)
    {
        $range_values = [];
        $params = ['warehouse_id' => $warehouse_id, 'type_id' => $type_id];
        foreach ($ranges as $key => $range) {
            $zone_ph = "zone_$key";
            $params[$zone_ph] = $range['zone'];
            $condition = "(l.zone = :$zone_ph";
            if ($range['column']) {
                $column_ph = "column_$key";
                $params[$column_ph] = $range['column'];
                $condition .= " AND l.`column` = :$column_ph";
            }
            $range_values[] = $condition . ")";
        }
        $range_values = implode(' OR ', $range_values);
        return (new SafeMysql())->fetchArrayOneField("
            SELECT l.id
            FROM locations l
            WHERE l.warehouse = :warehouse_id AND l.type_id = :type_id AND ($range_values)", $params);
    }
}
