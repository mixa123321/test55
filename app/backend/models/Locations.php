<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\BookLocationHistory;
use app\core\entities\Location;
use app\core\entities\SectionBinding;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\Validator;
use app\core\models\BookLocationHistories;
use app\core\models\SuppliersWarehouses;
use app\core\statical\Numbers;
use app\core\statical\TypeLocation;
use app\core\models\perms\Roles;

class Locations extends \app\core\models\Locations
{
    const ZONE_MIN_LEN = 1;
    const ZONE_MAX_LEN = 10;
    const REGEXP_LAT_NUM = '^[a-zA-Z0-9\(\)]+$';
    const FILTER_PARAM = 'locations_filters';
    const LIMIT_IMPORT = 100;

    public static function validate(Location $loc)
    {
        $rules = [
            'warehouse' => [
                'val' => $loc->getWarehouse(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'type_id' => [
                'val' => $loc->getTypeId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 2]]
            ],
            'zone' => [
                'val' => $loc->getZone(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [self::ZONE_MIN_LEN, self::ZONE_MAX_LEN]]
            ],
            'column' => [
                'val' => $loc->getColumn(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 10]]
            ],
            'row' => [
                'val' => $loc->getRow(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 10]]
            ],
            'mezzanine' => [
                'val' => $loc->getMezzanine(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [0, 1]]
            ],
            'state' => [
                'val' => $loc->getState(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => Location::ALL_STATUSES]
            ],
        ];
        $validator = new Validator($rules);
        if ($loc->getSizeId())
            $rules['size_id'] = [
                'val' => $loc->getSizeId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ];
        $checkSymbols = function($str) { return preg_match("/" . self::REGEXP_LAT_NUM . "/", $str); };
        $char_error = 'Only printable characters are allowed in ';
        if (!$checkSymbols($loc->getZone()))
            $validator->addError("not_correct", $char_error . 'zone', 'zone');
        if (!$checkSymbols($loc->getColumn()))
            $validator->addError("not_correct", $char_error . 'column', 'column');
        if (!$checkSymbols($loc->getRow()))
            $validator->addError("not_correct", $char_error . 'row', 'row');
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        $location = self::getByLocation($loc->getWarehouse(), $loc->getTypeId(), $loc->getZone(), $loc->getColumn(), $loc->getRow());
        $alias = $loc->getAlias();
        if (empty($alias))
            $alias = $loc->getAlternativeAlias();
        $scan = self::getByScan($loc->getWarehouse(), $alias);
        if (!empty($loc->getId())) {
            if (count($location) > 1 || (count($location) == 1 && $location[0]->getId() != $loc->getId()))
                $validator->addError("unique_name", "Location is not unique");
            if (count($location) == 1 && !empty($scan) && $loc->getId() != $scan->getId() && ($alias == $scan->getAlternativeAlias() || $alias == $scan->getAlias()))
                $validator->addError("unique_alias", "Alias is not unique");
        } else {
            if (count($location) > 0)
                $validator->addError("unique_name", "Location is not unique");
            if (!empty(self::getByScan($loc->getWarehouse(), $alias)))
                $validator->addError("unique_alias", "Alias is not unique");
        }
        return $validator->getErrors();
    }

    public static function getTable($warehouse_id, $type = Location::TP)
    {
        switch ($type) {
            case Location::TP:
                $loc = Location::SPECIAL_LOCATION__TABLE_PACK;
                $sb_type = SectionBinding::TABLES;
                $sb_bind = SectionBinding::TYPE_BIND_SINGLE_TABLE;
                break;
            case Location::LP:
                $loc = Location::SPECIAL_LOCATION__LARGE_TABLE_PACK;
                $sb_type = SectionBinding::LARGES;
                $sb_bind = SectionBinding::TYPE_BIND_LARGE_TABLE;
                break;
            default:
                return [];
        }
        $sql = "SELECT l.*, cart
                FROM locations l, 
                    (SELECT s.num, IFNULL(sb.object_id, -1) cart
                        FROM (SELECT DISTINCT num
                            FROM section_binding
                            WHERE type_id = :table_type AND warehouse_id = :warehouse_id
                        ) s
                        LEFT JOIN section_binding sb ON sb.num = s.num AND sb.type_id = :single_table_type AND warehouse_id = :warehouse_id
                    ) as `leave`
                WHERE l.warehouse = :warehouse_id AND l.type_id = :loc_type AND l.zone = :loc_zone AND l.`column` = :loc_col
                    AND l.`row` = `leave`.num AND l.state = :state";
        return (new SafeMysql())->fetchAll($sql, [
            'table_type' => $sb_type,
            'single_table_type' => $sb_bind,
            'warehouse_id' => $warehouse_id,
            'loc_type' => $loc[0],
            'loc_zone' => $loc[1],
            'loc_col' => $loc[2],
            'state' => Location::ACTIVE,
        ]);
    }

    public static function getLargeTable($warehouse_id)
    {
        return self::getTable($warehouse_id, Location::LP);
    }

    public static function getNameLocations($name, $warehouse_id)
    {
        $sql = "SELECT id, CONCAT(warehouse, '-', type_id, '-', zone, '-', `column`, '-', row) name
                FROM locations
                WHERE warehouse=:warehouse_id AND type_id=:name";
        return (new SafeMysql())->fetchAssocWithKey($sql, [
            'warehouse_id' => $warehouse_id,
            'name' => $name,
        ], 'name');
    }

    private static function getStandardLocation($warehouse_id, $loc)
    {
        /** @var Location[] $locs */
        $locs = Locations::getByLocation($warehouse_id, $loc[0], $loc[1], $loc[2], $loc[3]);
        if (count($locs) != 1)
            return "{$loc[0]} locations not created. Contact system administrator";
        $_SESSION[$loc[0]] = $locs[0]->getId();
        return true;
    }

    public static function getDHLocation($warehouse_id)
    {
        return self::getStandardLocation($warehouse_id, Location::SPECIAL_LOCATION__DH_ST);
    }

    public static function getPALocation($warehouse_id)
    {
        return self::getStandardLocation($warehouse_id, Location::SPECIAL_LOCATION__PA);
    }

    public static function createVirtualTrolley($warehouse_id, $user_id, $size_id)
    {
        $warehouse_id = (int)$warehouse_id;
        $user_id = (int)$user_id;
        $size_id = (int)$size_id;
        $us_loc = Location::SPECIAL_LOCATION__VIRTUAL_CART;
        (new SafeMysql())->execute("INSERT IGNORE INTO locations (id, warehouse, type_id, `zone`, `column`, `row`, size_id, mezzanine, state) VALUES (NULL, :warehouse_id, :type_id, :zone, :column, :row, :size_id, 0, 1)", [
            'warehouse_id' => $warehouse_id,
            'type_id' => $us_loc[0],
            'zone' => $us_loc[1],
            'column' => $us_loc[2],
            'row' => $user_id,
            'size_id' => $size_id,
        ]);
    }

    public static function getVirtualTrolley($user)
    {
        /** @var User $user */
        if (empty($user))
            return 0;
        $us_loc = Location::SPECIAL_LOCATION__VIRTUAL_CART;
        $sql = "SELECT id
                FROM locations
                WHERE warehouse = :warehouse_id AND type_id = :type_id AND zone = :zone AND `column` = :column AND `row` = :user_id
                LIMIT 1";
        return (new SafeMysql())->fetchScalar($sql, [
            'warehouse_id' => $user->getWarehouseId(),
            'type_id' => $us_loc[0],
            'zone' => $us_loc[1],
            'column' => $us_loc[2],
            'user_id' => $user->getId()
        ]);
    }

    public static function getExcludeLocationsPartialQuery($warehouse_id, $alias = '', array $exclude = Location::NOT_COUNTED_TYPES): string
    {
        $warehouse_id = (int)$warehouse_id;
        $order_cancel = Locations::getByCancelLocation($warehouse_id);
        $whereCancel = '';
        if (count($order_cancel) == 1)
            $whereCancel = (!empty($exclude) ? ' AND ' : '') . "{$alias}location_id <> {$order_cancel[0]->getId()}";
        $excludeLocation = (!empty($exclude) ? "l.type_id NOT IN (" . "'" . implode("', '", array_map('addslashes', $exclude)) . "'" . ")"  : '') .  $whereCancel;
        return "JOIN locations l ON {$alias}location_id = l.id AND l.warehouse = $warehouse_id AND $excludeLocation";
    }

    public static function getNonCountedTypesLocations($warehouse_id)
    {
        $location_name_select = Locations::getLocationNameSelect('l', 'w');

        $sql = 'SELECT l.id, '.$location_name_select.' as name
                FROM locations as l
                JOIN warehouses w ON l.warehouse = w.id
                WHERE warehouse = :warehouse_id and type_id = :type
               ';

        return (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'type' => Location::PR,
        ]);
    }

    public static function getAllTrolleys($warehouse_id)
    {
        $location_name_select = Locations::getLocationNameSelect('l', 'w');
        $sql = "SELECT l.id,
                       l.row,
                       l.type_id,
                       $location_name_select name,
                       CONCAT(u.login, ' #', u.id, '') user_name
                FROM locations l JOIN warehouses w ON l.warehouse = w.id
                LEFT JOIN users u ON u.id = l.row and u.role_id in (:role)
                WHERE 
                l.warehouse = :warehouse_id 
                AND l.type_id in (:type)
                AND (l.type_id != 'US' OR u.id IS NOT NULL)
                ORDER BY l.type_id, user_name
        ";
        return (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'type' => [Location::CA, Location::US],
            'role' => [Roles::WORKER, Roles::MANAGER]
        ]);
    }

    public static function setSearchLocation(&$params, Location $loc = null)
    {
        $_GET['type_id'] = empty($loc) ? '' : $loc->getTypeId();
        $_GET['zone'] = empty($loc) ? '' : $loc->getZone();
        $_GET['column'] = empty($loc) ? '' : $loc->getColumn();
        $_GET['row'] = empty($loc) ? '' : $loc->getRow();
        $_GET['floor'] = empty($loc) ? 0 : ($loc->getMezzanine() ? 1 : -1);
    }

    public static function prepareLocations($warehouse_id, $locations)
    {
        $locationSKUs = [];
        /** @var Location $val */
        foreach ($locations as $val)
            if ($val->getState())
                $locationSKUs[] = $val->getId();
        $sizes = SKULocations::getQuantities($locationSKUs);
        $locationSKUs = [];
        foreach ($locations as $val)
            $locationSKUs[$val->getId()] = (isset($sizes[$val->getId()]) && $sizes[$val->getId()] > 0);
        $sizes = Sizes::getAllSizesName($warehouse_id);
        return [$locationSKUs, $sizes];
    }

    /**
     * @param Location $loc
     * @return float|int
     */
    public static function getFullnessPercent(Location $loc, $active = true)
    {
        return array_sum(array_map(function ($SKU) use ($loc, $active) {
            $size = SKUs::getSize($SKU['SKU_id'], $loc->getSizeId(), $active);
            if (!$size)
                throw new \Exception("SKU #{$SKU['SKU_id']}: max capacity is undefined");
            return round((intval($SKU['summa']) * 100) / $size, 3);
        }, SKULocations::getSKUsFromLocation($loc->getId())));
    }

    /**
     * @param Location $loc
     * @param $ASN_id
     * @return float|int
     */
    public static function calcFullnessPercentForASN(Location $loc, $ASN_id)
    {
        return array_sum(array_map(function ($SKU) use ($loc) {
            return round((intval($SKU['quantity']) * 100) / SKUs::getSize($SKU['id'], $loc->getSizeId()), 3);
        }, ASNSKUs::getByASNId($ASN_id)));
    }

    public static function getConstantLocationIds($warehouse_id): array
    {
        static $res = [];
        if (!empty($res[$warehouse_id]))
            return $res[$warehouse_id];
        $locs = array_merge(
            self::getByCancelLocation($warehouse_id),
            self::getByExpiredLocation($warehouse_id),
            self::getByDiscrepancyHoldSTLocation($warehouse_id),
            self::getByDiscrepancyHoldMLocation($warehouse_id),
            self::getByDiscrepancyHoldPLocation($warehouse_id),
            self::getByPALocation($warehouse_id),
            self::getByNoVerifyLocation($warehouse_id),
        );
        if (empty($locs))
            return [];
        /** @var Location $loc */
        foreach ($locs as $loc)
            $res[$warehouse_id][] = $loc->getId();
        return $res[$warehouse_id];
    }

    public static function createLocation($data)
    {
        $location = new Location($data);
        $errors = Locations::validate($location);
        if (!empty($errors))
            return $errors;
        $location->save(array_keys($data));
        return true;
    }

    public static function createStdLocation($warehouse_id, $data, $size_id)
    {
        return self::createLocation([
            'warehouse' => $warehouse_id,
            'type_id' => $data[0],
            'zone' => $data[1],
            'column' => $data[2],
            'row' => $data[3],
            'size_id' => $size_id,
            'mezzanine' => 0,
            'state' => Location::ACTIVE,
        ]);
    }

    public static function createMinimumLocations(Warehouse $warehouse, $id_unlimited)
    {
        $cart_loc = Location::SPECIAL_LOCATION__CART;
        for ($i = 1; $i <= $warehouse->getCA()['nums']; $i++)
            Locations::createLocation([
                'warehouse' => $warehouse->getId(),
                'type_id' => $cart_loc[0],
                'zone' => $cart_loc[1],
                'column' => $cart_loc[2],
                'row' => $i,
                'size_id' => $warehouse->getCA()['size_id'],
                'mezzanine' => 0,
                'state' => Location::ACTIVE,
            ]);
        for ($j = 0; $j < (empty($warehouse->getCA()['nums_mez']) ? 0 : $warehouse->getCA()['nums_mez']); $j++)
            Locations::createLocation([
                'warehouse' => $warehouse->getId(),
                'type_id' => $cart_loc[0],
                'zone' => $cart_loc[1],
                'column' => $cart_loc[2],
                'row' => $i + $j,
                'size_id' => $warehouse->getCA()['size_id'],
                'mezzanine' => 1,
                'state' => Location::ACTIVE,
            ]);
        Locations::createStdLocation($warehouse->getId(), Location::SPECIAL_LOCATION__DH_ST, $id_unlimited);
        Locations::createStdLocation($warehouse->getId(), Location::SPECIAL_LOCATION__DH_M, $id_unlimited);
        Locations::createStdLocation($warehouse->getId(), Location::SPECIAL_LOCATION__DH_P, $id_unlimited);
        Locations::createStdLocation($warehouse->getId(), Location::SPECIAL_LOCATION__PA, $id_unlimited);
        Locations::createStdLocation($warehouse->getId(), Location::SPECIAL_LOCATION__NOVERIFY, $id_unlimited);
        Locations::createStdLocation($warehouse->getId(), Location::SPECIAL_LOCATION__CANCEL, $id_unlimited);
        Locations::createStdLocation($warehouse->getId(), Location::SPECIAL_LOCATION__EXPIRED, $id_unlimited);
        $tp_loc = Location::SPECIAL_LOCATION__TABLE_PACK;
        for ($i = 1; $i <= $warehouse->getTP()['nums']; $i++)
            Locations::createLocation([
                'warehouse' => $warehouse->getId(),
                'type_id' => $tp_loc[0],
                'zone' => $tp_loc[1],
                'column' => $tp_loc[2],
                'row' => Numbers::getSymb($i),
                'size_id' => $warehouse->getTP()['size_id'],
                'mezzanine' => 0,
                'state' => Location::ACTIVE,
            ]);
        $lp_loc = Location::SPECIAL_LOCATION__LARGE_TABLE_PACK;
        for ($i = 1; $i <= $warehouse->getTP()['LP']; $i++)
            Locations::createLocation([
                'warehouse' => $warehouse->getId(),
                'type_id' => $lp_loc[0],
                'zone' => $lp_loc[1],
                'column' => $lp_loc[2],
                'row' => Numbers::getSymb($i),
                'size_id' => $warehouse->getTP()['size_id'],
                'mezzanine' => 0,
                'state' => Location::ACTIVE,
            ]);
        $OB = $warehouse->getOB();
        $num = 1;
        $ob_loc = Location::SPECIAL_LOCATION__ORDER_BIN;
        for ($i = 1; $i <= $OB['nums']; $i++)
            for ($j = 1; $j <= $OB['cols']; $j++)
                for ($k = 1; $k <= $OB['rows']; $k++) {
                    Locations::createLocation([
                        'warehouse' => $warehouse->getId(),
                        'type_id' => $ob_loc[0],
                        'zone' => $ob_loc[1],
                        'column' => $ob_loc[2],
                        'row' => $num,
                        'size_id' => $warehouse->getOB()['size_id'],
                        'mezzanine' => 0,
                        'state' => Location::ACTIVE,
                    ]);
                    $num++;
                }
        if (!empty($OB['nums_mez']))
            for ($i1 = 0; $i1 < $OB['nums_mez']; $i1++)
                for ($j = 1; $j <= $OB['cols']; $j++)
                    for ($k = 1; $k <= $OB['rows']; $k++) {
                        Locations::createLocation([
                            'warehouse' => $warehouse->getId(),
                            'type_id' => $ob_loc[0],
                            'zone' => $ob_loc[1],
                            'column' => $ob_loc[2],
                            'row' => $num,
                            'size_id' => $warehouse->getOB()['size_id'],
                            'mezzanine' => 1,
                            'state' => Location::ACTIVE,
                        ]);
                        $num++;
                    }
    }

    public static function uploadLocations(Warehouse $warehouse, $name)
    {
        $locs = Arr::csv2array($name);
        if (!count($locs))
            return 'Data is absent';
        if (count($locs[0]) != 6 || !Arr::equal(array_keys($locs[0]), ['Location name', 'Type', 'Zone', 'Column', 'Row', 'Size']))
            return 'Header is wrong (Location name, Type, Zone, Column, Row, Size)';
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $sizes = $messages = [];
        $nums = 0;
        foreach ($locs as $key => $loc) {
            if (empty($sizes[$loc['Size']]))
                $sizes[$loc['Size']] = Sizes::getByName($warehouse->getId(), $loc['Size']);
            if (empty($sizes[$loc['Size']])) {
                $messages[$loc['Size']] = 1;
                continue;
            }
            if (Locations::createLocation([
                'warehouse' => $warehouse->getId(),
                'type_id' => $loc['Type'],
                'zone' => $loc['Zone'],
                'column' => $loc['Column'],
                'row' => $loc['Row'],
                'size_id' => $sizes[$loc['Size']]->getId(),
                'mezzanine' => (int)$warehouse->getMezzanine(),
                'state' => Location::ACTIVE,
            ]) === true)
                $nums++;
        }
        $mysql->commit();
        if (!empty($messages))
            return 'Size(s) (' . implode(', ', array_keys($messages)) . ") not found. Added $nums location(s)";
        if (!$nums)
            return 'Locations have not been added';
        return true;
    }

    public static function createLoc($data, $warehouse_id = null, $size_id = null)
    {
        if (is_null($warehouse_id) && is_null($size_id)){
            $loc_name = "{$data['warehouse']}-{$data['type_id']}-{$data['zone']}-{$data['column']}-{$data['row']}";
            $errors = self::createLocation($data);
        } else {
            $loc_name = "$warehouse_id-{$data[0]}-{$data[1]}-{$data[2]}-{$data[3]}";
            $errors = self::createStdLocation($warehouse_id, $data, $size_id);
        }
        if ($errors !== true)
            return "$loc_name\n\t" . implode("\n\t", array_map(function($elem) { return "{$elem['message']} ({$elem['code']})"; }, $errors)) . "\n";
        return true;
    }

    public static function getByScan($warehouse_id, $name)
    {
        if (empty($warehouse_id) || empty($name))
            return null;
        $loc = self::getByAlias($warehouse_id, $name);
        if (is_null($loc))
            $loc = self::getByCoords($warehouse_id, $name);
        if (empty($loc))
            return null;
        return $loc;
    }

    public static function getRanges(int $warehouse_id, string $type_id)
    {
        if (empty($warehouse_id) || empty($type_id))
            return [];
        $exclude_locations = '';
        if (Location::isST($type_id)) {
            $exclude = Locations::getReservedLocations($warehouse_id);
            if (!empty($exclude))
                $exclude_locations = 'AND id NOT IN (' . implode(',', $exclude) . ')';
        }
        $data = (new SafeMysql())->fetchAll("
            SELECT DISTINCT zone, `column`
            FROM locations
            WHERE warehouse = :warehouse_id AND type_id = :type_id $exclude_locations", [
            'warehouse_id' => $warehouse_id,
            'type_id' => $type_id,
        ]);
        if (empty($data))
            return [];
        $res = [];
        foreach ($data as $datum) {
            if (empty($res[$datum['zone']]))
                $res[$datum['zone']] = [];
            $res[$datum['zone']][] = $datum['column'];
        }
        return $res;
    }

    public static function addRange(int $warehouse_id, int $supplier_id, string $type_id, string $zone, string $column, int $user_id)
    {
        if (!$warehouse_id || !$supplier_id || !$type_id || !$zone)
            return [false, 'Wrong parameters'];
        $supplier_warehouse = SuppliersWarehouses::getSupplierWarehouse($warehouse_id, $supplier_id);
        if (empty($supplier_warehouse))
            return [false, 'Wrong parameters'];
        $remove_locations = $add_ranges = $messages = [];
        $data = BookLocations::getLocationsFromRange($warehouse_id, $type_id, $zone, $column);
        if ($data) {
            if (!in_array($supplier_id, array_keys($data)) || count($data) != 1) {
                unset($data[$supplier_id]);
                return [false, 'Locations from this range are used by: ' . implode(', ', array_column($data, 'name'))];
            }
            $remove_locations = $data[$supplier_id]['locations'];
            $add_ranges[] = [$zone, $column];
        }
        $remove_ranges = [];
        $data = BookRangeLocations::getSelectedRange($warehouse_id, $type_id, $zone);
        if ($data) {
            if (!in_array($supplier_id, array_keys($data)) || count($data) != 1) {
                unset($data[$supplier_id]);
                $suppliers = [];
                foreach ($data as $supplier_id => $datum)
                    if ((!$column && count($datum['ranges'])) || ($column && in_array($column, $datum['ranges'])))
                        $suppliers[] = $datum['name'];
                if (!empty($suppliers))
                    return [false, 'This range or part of it are used by: ' . implode(', ', array_column($data, 'name'))];
            }
            $data = $data[$supplier_id]['ranges'];
            $full_range = count(self::getRanges($warehouse_id, $type_id)[$zone]);
            if ((!$column && count($data) == $full_range) || ($column && in_array($column, $data)))
                return [false, 'This range is already in use'];
            if (!$column || ($column && count($data) + 1 == $full_range))
                $remove_ranges = $data;
        }
        if (!in_array([$zone, $column], $add_ranges))
            $add_ranges[] = [$zone, $column];
        $histories = [];
        if (!empty($remove_locations)) {
            BookLocations::remove($supplier_warehouse['id'], array_keys($remove_locations));
            $messages[] = 'Removed locations: ' . implode(', ', array_values($remove_locations));
            $histories[] = new BookLocationHistory([
                'supplier_warehouse_id' => $supplier_warehouse['id'],
                'type_id' => $type_id,
                'event' => BookLocationHistory::EVENT_REMOVE,
                'type' => BookLocationHistory::TYPE_LOCATIONS,
                'user_id' => $user_id,
                'addition' => array_keys($remove_locations)
            ]);
        }
        if (!empty($remove_ranges)) {
            BookRangeLocations::remove($supplier_warehouse['id'], $type_id, $zone, $remove_ranges);
            $messages[] = 'Removed ranges: ' . implode(', ', array_map(function ($item) use($zone) {
                return $zone . ($item ? " - $item" : '');
            }, $remove_ranges));
            $histories[] = new BookLocationHistory([
                'supplier_warehouse_id' => $supplier_warehouse['id'],
                'type_id' => $type_id,
                'event' => BookLocationHistory::EVENT_REMOVE,
                'type' => BookLocationHistory::TYPE_RANGES,
                'user_id' => $user_id,
                'addition' => array_map(function ($item) use($zone) {
                    return [$zone , $item ?: "0"];
                }, $remove_ranges)
            ]);
        }
        if (!empty($add_ranges)) {
            BookRangeLocations::add($supplier_warehouse['id'], $type_id, $add_ranges);
            $messages[] = 'Added ranges: ' . implode(', ', array_map(function ($item) {
                return $item[0] . ($item[1] ? " - {$item[1]}" : '');
            }, $add_ranges));
            $histories[] = new BookLocationHistory([
                'supplier_warehouse_id' => $supplier_warehouse['id'],
                'type_id' => $type_id,
                'event' => BookLocationHistory::EVENT_ADD,
                'type' => BookLocationHistory::TYPE_RANGES,
                'user_id' => $user_id,
                'addition' => $add_ranges
            ]);
        }
        BookLocationHistories::add($histories);
        return [true, $messages];
    }

    public static function removeRange(int $warehouse_id, int $supplier_id, string $type_id, string $zone, string $column, int $user_id)
    {
        if (!$warehouse_id || !$supplier_id || !$type_id || !$zone)
            return [false, 'Wrong parameters'];
        $supplier_warehouse = SuppliersWarehouses::getSupplierWarehouse($warehouse_id, $supplier_id);
        if (empty($supplier_warehouse))
            return [false, 'Wrong parameters'];
        BookRangeLocations::remove($supplier_warehouse['id'], $type_id, $zone, [$column]);
        BookLocationHistories::add([new BookLocationHistory([
            'supplier_warehouse_id' => $supplier_warehouse['id'],
            'type_id' => $type_id,
            'event' => BookLocationHistory::EVENT_REMOVE,
            'type' => BookLocationHistory::TYPE_RANGE,
            'user_id' => $user_id,
            'addition' => [$zone, $column]
        ])]);
        return [true, "Removed range: $zone" . ($column ? " - $column" : '')];
    }

    public static function removeLocation(int $warehouse_id, string $warehouse_label, int $supplier_id, string $type_id, Location $location, int $user_id)
    {
        if (!$warehouse_id || !$supplier_id || !$type_id || !$location)
            return [false, 'Wrong parameters'];
        $supplier_warehouse = SuppliersWarehouses::getSupplierWarehouse($warehouse_id, $supplier_id);
        if (empty($supplier_warehouse))
            return [false, 'Wrong parameters'];
        BookLocations::remove($supplier_warehouse['id'], [$location->getId()]);
        BookLocationHistories::add([new BookLocationHistory([
            'supplier_warehouse_id' => $supplier_warehouse['id'],
            'type_id' => $type_id,
            'event' => BookLocationHistory::EVENT_REMOVE,
            'type' => BookLocationHistory::TYPE_LOCATION,
            'user_id' => $user_id,
            'addition' => $location->getId()
        ])]);
        return [true, "Removed location: {$location->getLocationReal($warehouse_label)}"];
    }

    /** @throws */
    private static function upload($files, &$errors)
    {
        if (empty($files)) {
            $errors[] = 'Wrong data';
            return false;
        }
        if (!is_file($files['tmp_name'])) {
            $errors[] = 'File is not found';
            return false;
        }
        if (!$files['size']) {
            $errors[] = 'File is empty';
            return false;
        }
        $locs = Arr::csv2array($files['tmp_name']);
        if (!count($locs)) {
            $errors[] = 'Data is absent';
            return false;
        }
        return $locs;
    }

    private static function checkLocations(int $warehouse_id, string $type_id, array $locs)
    {
        $res_suc = $res_err = [];
        foreach ($locs as $loc_name) {
            $location = self::getByScan($warehouse_id, $loc_name['Name']);
            if (empty($location)) {
                $res_err[] = "{$loc_name['Name']} - wrong name";
                continue;
            }
            if (!empty($res_suc[$location->getId()])) {
                $res_err[] = "{$loc_name['Name']} - duplicate";
                continue;
            }
            if ($location->getTypeId() !== $type_id) {
                $res_err[] = "{$loc_name['Name']} - wrong type";
                continue;
            }
            $res_suc[$location->getId()] = ['location' => $location, 'alias' => $loc_name['Name']];
        }
        return [$res_suc, $res_err];
    }

    public static function getStaticticsForBookLocations(int $warehouse_id, string $type_id, array $loc_ids, &$errors)
    {
        if (!$warehouse_id || !$type_id)
            return [];
        $mysql = new SafeMysql();
        $locs = $mysql->fetchAll("
            SELECT zone, `column`, COUNT(*) qty
            FROM locations
            WHERE warehouse = :warehouse_id AND type_id = :type_id
            GROUP BY zone, `column`", [
            'warehouse_id' => $warehouse_id,
            'type_id' => $type_id,
        ]);
        if (empty($locs))
            return [];
        $res = [];
        foreach ($locs as $loc_data) {
            if (empty($res[$loc_data['zone']]))
                $res[$loc_data['zone']] = [];
            if (empty($res[$loc_data['zone']][$loc_data['column']]))
                $res[$loc_data['zone']][$loc_data['column']] = ['book' => 0, 'max' => 0];
            $res[$loc_data['zone']][$loc_data['column']]['max'] = $loc_data['qty'];
        }
        if (empty($loc_ids))
            return $res;
        $books = $mysql->fetchAll("
            SELECT zone, `column`, COUNT(*) qty
            FROM locations
            WHERE id IN (:loc_ids)
            GROUP BY zone, `column`", [
            'loc_ids' => $loc_ids,
        ]);
        foreach ($books as $book)
            if (!empty($res[$book['zone']][$book['column']]))
                $res[$book['zone']][$book['column']]['book'] = $book['qty'];
            else
                $errors[] = "{$book['zone']}-{$book['column']} - range is absent";
        return $res;
    }

    public static function importLocations(int $warehouse_id, string $warehouse_label, int $supplier_id, string $type_id, array $files, int $user_id)
    {
        if (!$warehouse_id || !$supplier_id || !$type_id || !$files)
            return [false, 'Wrong parameters'];
        $supplier_warehouse = SuppliersWarehouses::getSupplierWarehouse($warehouse_id, $supplier_id);
        if (empty($supplier_warehouse))
            return [false, 'Wrong parameters'];
        $errors = [];
        if (($locs = self::upload($files, $errors)) === false)
            return [false, implode('; ', $errors)];
        if (!isset($locs[0]['Name']) || count($locs[0]) != 1)
            return [false, 'Wrong file format'];
        if (count($locs) > self::LIMIT_IMPORT)
            return [false, 'The quantity of locations in the file is more than ' . self::LIMIT_IMPORT];
        [$res_suc, $res_err] = self::checkLocations($warehouse_id, $type_id, $locs);
        $locations = BookLocations::getUsedLocations($warehouse_id, $type_id);
        foreach ($res_suc as $location_id => $loc)
            if (!empty($locations[$location_id])) {
                $res_err[] = "{$loc['alias']} - used" . ($supplier_id != $locations[$location_id]['supplier_id'] ? " by {$locations[$location_id]['name']}" : '');
                unset($res_suc[$location_id]);
            }
        $ranges = BookRangeLocations::getUsedRanges($warehouse_id, $type_id);
        foreach ($res_suc as $location_id => $location_data) {
            /** @var Location $location */
            $location = $location_data['location'];
            if (empty($ranges[$location->getZone()]))
                continue;
            foreach ($ranges[$location->getZone()] as $sup_id => $sup_data) {
                if (empty($sup_data['columns'])) {
                    $res_err[] = "{$location_data['alias']} - used" . ($supplier_id != $sup_id ? " by {$sup_data['name']}" : '');
                    unset($res_suc[$location_id]);
                    continue;
                }
                if (in_array($location->getColumn(), $sup_data['columns'])) {
                    $res_err[] = "{$location_data['alias']} - used" . ($supplier_id != $sup_id ? " by {$sup_data['name']}" : '');
                    unset($res_suc[$location_id]);
                    continue;
                }
            }
        }
        if (empty($res_suc))
            return [false, implode('; ', $res_err)];
        $locations = array_filter($locations, function ($location) use ($supplier_id) {
            return $location['supplier_id'] == $supplier_id;
        });
        $books = self::getStaticticsForBookLocations($warehouse_id, $type_id, array_merge(array_keys($locations), array_keys($res_suc)), $res_err);
        $loc_add = $range_add = $loc_del = $range_del = [];
        foreach ($res_suc as $location_id => $location_data) {
            /** @var Location $location */
            $location = $location_data['location'];
            $columns = $books[$location->getZone()];
            $column = $columns[$location->getColumn()];
            if ($column['book'] < $column['max']) {
                $loc_add[$location->getId()] = $location;
                continue;
            }
            $sum_book = $sum_max = 0;
            array_map(function ($item) use(&$sum_book, &$sum_max) {
                $sum_book += $item['book'];
                $sum_max += $item['max'];
            }, $columns);
            if ($sum_book < $sum_max) {
                $range_add[] = [$location->getZone(), $location->getColumn()];
                $loc_del[] = [$location->getZone(), $location->getColumn()];
            } else {
                $range_add[] = [$location->getZone(), null];
                $range_del[] = [$location->getZone(), $location->getColumn()];
            }
        }
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $histories = [];
        $res_suc = [];
        if (!empty($loc_add)) {
            BookLocations::add($supplier_warehouse['id'], array_keys($loc_add));
            $histories[] = new BookLocationHistory([
                'supplier_warehouse_id' => $supplier_warehouse['id'],
                'type_id' => $type_id,
                'event' => BookLocationHistory::EVENT_ADD,
                'type' => BookLocationHistory::TYPE_LOCATIONS,
                'user_id' => $user_id,
                'addition' => array_keys($loc_add)
            ]);
            $res_suc[] = "Added locations: " . implode(', ', array_map(function (Location $location) use($warehouse_label) {
                return $location->getLocationReal($warehouse_label);
            }, $loc_add));
        }
        if (!empty($loc_del)) {
            BookLocations::removeByRanges($supplier_warehouse['id'], $type_id, $loc_del);
            $histories[] = new BookLocationHistory([
                'supplier_warehouse_id' => $supplier_warehouse['id'],
                'type_id' => $type_id,
                'event' => BookLocationHistory::EVENT_REMOVE,
                'type' => BookLocationHistory::TYPE_LOCATIONS,
                'user_id' => $user_id,
                'addition' => $loc_del
            ]);
            $res_suc[] = 'Removed locations: ' . implode(', ', array_map(function ($item) {
                return $item[0] . ($item[1] ? " - {$item[1]}" : '');
            }, $loc_del));
        }
        if (!empty($range_add)) {
            BookRangeLocations::add($supplier_warehouse['id'], $type_id, $range_add);
            $histories[] = new BookLocationHistory([
                'supplier_warehouse_id' => $supplier_warehouse['id'],
                'type_id' => $type_id,
                'event' => BookLocationHistory::EVENT_ADD,
                'type' => BookLocationHistory::TYPE_RANGES,
                'user_id' => $user_id,
                'addition' => $range_add
            ]);
            $res_suc[] = 'Added ranges: ' . implode(', ', array_map(function ($item) {
                return $item[0] . ($item[1] ? " - {$item[1]}" : '');
            }, $range_add));
        }
        if (!empty($range_del)) {
            BookRangeLocations::removeByRanges($supplier_warehouse['id'], $type_id, $range_del);
            $histories[] = new BookLocationHistory([
                'supplier_warehouse_id' => $supplier_warehouse['id'],
                'type_id' => $type_id,
                'event' => BookLocationHistory::EVENT_REMOVE,
                'type' => BookLocationHistory::TYPE_RANGES,
                'user_id' => $user_id,
                'addition' => $range_del
            ]);
            $res_suc[] = 'Removed ranges: ' . implode(', ', array_map(function ($item) {
                return $item[0] . ($item[1] ? " - {$item[1]}" : '');
            }, $range_del));
        }
        BookLocationHistories::add($histories);
        $mysql->commit();
        return [true, [$res_suc, $res_err]];
    }

    public static function getIdFromEstimated(array $locations)
    {
        $location_assignment = (new SafeMysql())->fetchOne("
            SELECT l.id, SUM(spl.SKU_id IS NOT NULL) nums 
            FROM locations l
                LEFT JOIN SKU_preff_locs spl ON l.id = spl.location_id
            WHERE l.id IN (:locations) AND l.state = :state
            GROUP BY l.id ORDER BY 2 LIMIT 1", [
            'locations' => $locations,
            'state' => Location::ACTIVE,
        ]);
        if (empty($location_assignment))
            return 0;
        return (int)$location_assignment['id'];
    }

    public static function getBookLocationIds(int $warehouse_id, int $supplier_id, string $type_id)
    {
        $locations = BookLocations::getLocations($warehouse_id, $supplier_id, $type_id);
        $ranges = BookRangeLocations::getRanges($warehouse_id, $supplier_id, $type_id);
        if (!$locations && !$ranges)
            return [];
        $locations_from_ranges = $ranges ? self::getIdsFromRanges($warehouse_id, $type_id, $ranges) : [];
        return array_merge(array_map(function (Location $location) {
            return $location->getId();
        }, $locations), $locations_from_ranges);
    }

    public static function getBookedZonesAndColumns(int $warehouse_id, int $supplier_id, string $type_id, $exclude_ids = [])
    {
        $zones = BookLocations::getZonesAndColumns($warehouse_id, $supplier_id, $type_id, $exclude_ids);
        $ranges = BookRangeLocations::getRanges($warehouse_id, $supplier_id, $type_id);
        if (!$zones && !$ranges)
            return [];
        $res = [];
        foreach ($zones as $zone => $columns) {
            if (empty($res[$zone]))
                $res[$zone] = [];
            $res[$zone] = array_merge($res[$zone], $columns);
        }
        foreach ($ranges as $range) {
            $zone = $range['zone'];
            $column = $range['column'];
            if (empty($res[$zone]))
                $res[$zone] = [];
            $res[$zone][] = $column;
        }
        return array_map('array_filter', $res);
    }

    public static function getAvailableDestinationForMovement(Location $location)
    {
        $standard_locations = [
            Location::SPECIAL_LOCATION__DH_ST,
            Location::SPECIAL_LOCATION__PA,
        ];
        $result = [];
        foreach ($standard_locations as $loc)
            $result = array_merge($result, self::getByLocation($location->getWarehouse(), $loc[0], $loc[1], $loc[2]));
        $SKUs = SKULocations::getSKUsFromLocation($location->getId());
        if (empty($SKUs))
            return $result;
        
        return $result;
    }

    public static function getLocationView(Location $location, array $sizes = [], $warehouse_label = null)
    {
        $badge = 'secondary';
        $state_label = $location->getState();
        switch ($location->getState()) {
            case Location::ACTIVE:
                $state_label = 'Active';
                $badge = 'success';
                break;
            case Location::LOCKED:
                $state_label = 'Locked';
                $badge = 'danger';
                break;
            case Location::DETACHED:
                $state_label = 'Detached';
                $badge = 'info';
                break;
        }
        $badges = [];
        if ($location->getMezzanine())
            $badges[] = ['text' => 'M', 'color' => 'info'];

        return [
            'name' => $location->getLocation($warehouse_label ?: $location->getWarehouse()),
            'state' => $state_label,
            'state_badge' => $badge,
            'alias' => $location->getAlias() ?: $location->getAlternativeAlias(),
            'size' => $sizes[$location->getSizeId()][0] ?? '-',
            'badges' => $badges,
            'type' => TypeLocation::getNameLocation($location->getTypeId()),
        ];
    }
}
