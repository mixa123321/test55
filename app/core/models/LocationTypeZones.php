<?php

namespace app\core\models;

use app\backend\models\Locations;
use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\entities\LocationTypeZone;
use app\core\helpers\View;
use app\core\statical\TypeLocation;

class LocationTypeZones
{
    const ALLOWED_TYPES_ZONES = Location::STORAGE_TYPES;

    public static function getTypes(): array
    {
        $types = TypeLocation::getAll();
        //Для удобства оставляем 2 типа, остальные пока что не нужны
        $types =  array_filter($types, function ($type) {
            return in_array($type, self::ALLOWED_TYPES_ZONES);
        }, ARRAY_FILTER_USE_KEY);
        krsort($types);
        return $types;
    }

    public static function validationZone(string $zone): string
    {
        $str_len = mb_strlen($zone);
        if ($str_len > Locations::ZONE_MAX_LEN) {
            return 'Zone is too long';
        } elseif ($str_len < Locations::ZONE_MIN_LEN) {
            return 'Zone is too short';

        } elseif (!mb_ereg(Locations::REGEXP_LAT_NUM, $zone)) {
            return 'Only printable characters are allowed in zone';
        }
        return '';
    }

    public static function getAllGroupByTypeId(int $warehouse_id): array
    {
        $sql = "
        SELECT
            type_id, zone
        FROM
            location_type_zones
        WHERE
            warehouse_id = :warehouse_id";
        $location_type_zones = [];
        foreach ((new SafeMysql())->fetchAll($sql, ['warehouse_id' => $warehouse_id]) as $item) {
            $location_type_zones[$item['type_id']][] = $item['zone'];
        }
        krsort($location_type_zones);
        return $location_type_zones;
    }

    public static function getByWarehouseAndTypeId(int $warehouse_id, string $type_id): array
    {
        static $location_type_zones = [];
        if (isset($location_type_zones[$warehouse_id])) {
            return $location_type_zones[$warehouse_id][$type_id] ?? [];
        }
        $sql = "
        SELECT
            zone, type_id
        FROM
            location_type_zones
        WHERE
            warehouse_id = :warehouse_id";
        $location_type_zones[$warehouse_id] = [];
        foreach ((new SafeMysql())->fetchAll($sql, ['warehouse_id' => $warehouse_id]) as $item) {
            $location_type_zones[$warehouse_id][$item['type_id']][] = $item['zone'];
        }
        return $location_type_zones[$warehouse_id][$type_id] ?? [];
    }

    public static function deleteByWarehouse(int $warehouse_id) {
        (new SafeMysql())->execute("DELETE FROM location_type_zones WHERE warehouse_id = :warehouse_id", ['warehouse_id' => $warehouse_id]);
    }

    /**
     * @param LocationTypeZone[] $rows
     */
    public static function multiInsert(array $rows)
    {
        if (!$rows) {
            return;
        }
        $for_write = [];
        foreach ($rows as $row) {
            if (!($row instanceof LocationTypeZone)) {
                throw new \InvalidArgumentException('Invalid argument');
            }
            $for_write[] = $row->toArray();
        }
        (new SafeMysql())->multiInsert('location_type_zones', $for_write, true);
    }
}