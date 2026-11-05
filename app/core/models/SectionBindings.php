<?php

namespace app\core\models;

use app\backend\models\Locations;
use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\entities\Order;
use app\core\entities\SectionBinding;
use app\core\statical\Numbers;

class SectionBindings
{
    private static function getWorkers($warehouse_id, $type_id, $nums)
    {
        $warehouse_id = (int)$warehouse_id;
        $sql = "SELECT sb.id, sb.num, sb.object_id, u.login, p.name
                FROM section_binding sb
                JOIN users u ON u.id = sb.object_id
                JOIN persons p ON p.user_id = sb.object_id
                WHERE sb.type_id = :type_id AND sb.warehouse_id = :warehouse_id";
        $sections = (new SafeMysql())->fetchAll($sql, ['type_id' => $type_id, 'warehouse_id' => $warehouse_id]);
        $res = [];
        foreach ($sections as $section) {
            if (empty($res[$section['num']]))
                $res[$section['num']]['id'] = $section['id'];
            $res[$section['num']][$type_id][$section['object_id']] = ['login' => $section['login'], 'name' => $section['name']];
        }
        $sections = [];
        for ($key = 0; $key < $nums; $key++) {
            $num = Numbers::getSymb($key + 1);
            $sections[$num] = empty($res[$num]) ? ['id' => 0, $type_id => []] : $res[$num];
        }
        return $sections;
    }

    public static function getWorkers4OrderBin($warehouse_id)
    {
        $warehouse = Warehouses::getById($warehouse_id);
        return self::getWorkers($warehouse_id, SectionBinding::WORKERS, $warehouse->getOB()['nums'] + $warehouse->getOB()['nums_mez']);
    }

    public static function getWorkers4PackStation($warehouse_id)
    {
        $warehouse = Warehouses::getById($warehouse_id);
        return self::getWorkers($warehouse_id, SectionBinding::TABLES, $warehouse->getTP()['nums']);
    }

    public static function getWorkers4LargePack($warehouse_id)
    {
        $warehouse = Warehouses::getById($warehouse_id);
        return self::getWorkers($warehouse_id, SectionBinding::LARGES, $warehouse->getTP()['LP']);
    }

    public static function getPlacesByPackerSingle($id, $warehouse_id)
    {
        $id = (int)$id;
        $warehouse_id = (int)$warehouse_id;
        $tp_loc = Location::SPECIAL_LOCATION__TABLE_PACK;
        $sql = "SELECT l.id, sb.num
                FROM section_binding sb
                JOIN locations l ON l.row = sb.num AND l.warehouse = :warehouse_id AND l.type_id = :loc_type AND l.zone = :loc_zone AND l.`column` = :loc_column
                WHERE sb.object_id = :id AND sb.type_id = :sb_type AND sb.warehouse_id = :warehouse_id
                UNION
                SELECT object_id id, -1 num
                FROM section_binding
                WHERE num IN (SELECT DISTINCT num
                              FROM section_binding
                              WHERE object_id = :id AND type_id = :sb_type AND warehouse_id = :warehouse_id
                    ) AND type_id = :sb_bind AND warehouse_id = :warehouse_id
                ORDER BY 1";
        return (new SafeMysql())->fetchAssocWithKey($sql, [
            'id' => $id,
            'warehouse_id' => $warehouse_id,
            'loc_type' => $tp_loc[0],
            'loc_zone' => $tp_loc[1],
            'loc_column' => $tp_loc[2],
            'sb_type' => SectionBinding::TABLES,
            'sb_bind' => SectionBinding::TYPE_BIND_SINGLE_TABLE,
        ], 'id');
    }

    public static function getPlacesByPackerMulti($id, $warehouse_id)
    {
        $id = (int)$id;
        $warehouse_id = (int)$warehouse_id;
        $packed_statuses = Order::$allowed_for_packed_statutes;
        $packed_statuses[] = Order::AWAITING_PACK_NUM;
        $sql = "SELECT DISTINCT l.id
                FROM SKU_locations sl
                    JOIN orders o ON o.id = sl.order_id AND o.status IN (:statuses)
                    JOIN locations l ON l.id = sl.location_id AND l.type_id = :loc_type AND l.warehouse = :warehouse_id
                    JOIN section_binding sb ON sb.object_id = :id AND sb.type_id = :sb_type AND sb.warehouse_id = :warehouse_id
                ORDER BY 1";
        return (new SafeMysql())->fetchArrayOneField($sql, [
            'id' => $id,
            'warehouse_id' => $warehouse_id,
            'loc_type' => Location::OB,
            'sb_type' => SectionBinding::TABLES,
            'statuses' => $packed_statuses,
        ]);
    }

    public static function getPlacesByPackerLarge($id, $warehouse_id, $table_id)
    {
        $id = (int)$id;
        $warehouse_id = (int)$warehouse_id;
        $params = [
            ':id' => $id,
            ':warehouse_id' => $warehouse_id,
            ':loc_type' => Location::LP,
            ':cart_type' => Location::CA,
            ':sb_type' => SectionBinding::LARGES,
            ':sb_bind' => SectionBinding::TYPE_BIND_LARGE_TABLE,
        ];
        $whereTable1 = $whereTable2 = '';
        if (!empty($table_id)) {
            $whereTable1 = " AND l.id = :table_id";
            $whereTable2 = " JOIN locations l ON l.id = :table_id AND l.row = sb.num";
            $params[':table_id'] = (int)$table_id;
        }
        $sql = "SELECT l.id, l.type_id, l.zone, l.`column`, l.row
                FROM section_binding sb
                JOIN locations l ON l.row = sb.num AND l.warehouse = :warehouse_id AND l.type_id = :loc_type
                    WHERE sb.object_id = :id AND sb.type_id = :sb_type AND sb.warehouse_id = :warehouse_id$whereTable1
                UNION
                SELECT object_id id, :cart_type, '-', '-', '-'
                FROM section_binding
                WHERE num IN (SELECT DISTINCT sb.num
                              FROM section_binding sb$whereTable2 WHERE sb.object_id = :id AND sb.type_id = :sb_type AND sb.warehouse_id = :warehouse_id)
                    AND type_id = :sb_bind AND warehouse_id = :warehouse_id
                ORDER BY 1";
        $safeParams = [];
        foreach ($params as $k => $v)
            $safeParams[ltrim($k, ':')] = $v;
        return (new SafeMysql())->fetchAssocWithKey($sql, $safeParams, 'id');
    }

    public static function getPlacesByPackers($warehouse_id, $ids = 0)
    {
        $users_where = $sb_where = '';
        $packed_statuses = Order::$allowed_for_packed_statutes;
        $packed_statuses[] = Order::AWAITING_PACK_NUM;
        $tp_loc = Location::SPECIAL_LOCATION__TABLE_PACK;
        $lp_loc = Location::SPECIAL_LOCATION__LARGE_TABLE_PACK;
        $params = [
            ':statuses' => $packed_statuses,
            ':status' => Order::AWAITING_PACK_NUM,
            ':loc_type' => Location::OB,
            ':tp_loc_type' => $tp_loc[0],
            ':tp_loc_zone' => $tp_loc[1],
            ':tp_loc_column' => $tp_loc[2],
            ':sb_type_tp' => SectionBinding::TABLES,
            ':sb_bind_tp' => SectionBinding::TYPE_BIND_SINGLE_TABLE,
            ':lp_loc_type' => $lp_loc[0],
            ':lp_loc_zone' => $lp_loc[1],
            ':lp_loc_column' => $lp_loc[2],
            ':sb_type_lp' => SectionBinding::LARGES,
            ':sb_bind_lp' => SectionBinding::TYPE_BIND_LARGE_TABLE,
            ':warehouse_id' => $warehouse_id,
        ];
        if (is_array($ids)) {
            if (count($ids) == 0)
                return [];
            $params[':ids'] = $ids;
            $users_where = " AND u.id IN (:ids)";
            $sb_where = " AND sb.object_id IN (:ids)";
        }
        $sql = <<<SQL
                -- OB
                SELECT l.id, u.id packer_id, u.login
                FROM SKU_locations sl
                    JOIN orders o ON o.id = sl.order_id AND o.status IN (:statuses)
                    JOIN locations l ON l.id = sl.location_id AND l.type_id = :loc_type AND l.warehouse = :warehouse_id
                    LEFT JOIN users u ON u.id = SUBSTRING_INDEX(sl.reason, '_', 1) AND POSITION('_' IN sl.reason) > 0 AND o.status > :status$users_where
                UNION ALL
                -- TP
                SELECT l.id, sb.object_id packer_id, u.login
                FROM section_binding sb
                    JOIN users u ON sb.object_id = u.id
                    JOIN locations l ON l.row = sb.num AND l.warehouse = :warehouse_id
                        AND l.type_id = :tp_loc_type AND l.zone = :tp_loc_zone AND l.`column` = :tp_loc_column
                WHERE sb.type_id = :sb_type_tp AND sb.warehouse_id = :warehouse_id$sb_where
                -- CA TP
                UNION ALL
                SELECT sb.object_id id, tabl.object_id packer_id, u.login
                FROM section_binding sb,
                     (SELECT DISTINCT num, object_id
                      FROM section_binding
                      WHERE type_id = :sb_type_tp AND warehouse_id = :warehouse_id) tabl
                    JOIN users u ON u.id = tabl.object_id
                WHERE sb.num = tabl.num AND sb.type_id = :sb_bind_tp AND sb.warehouse_id = :warehouse_id$sb_where
                UNION ALL
                -- LP
                SELECT l.id, sb.object_id packer_id, u.login
                FROM section_binding sb
                    JOIN users u ON sb.object_id = u.id
                    JOIN locations l ON l.row = sb.num AND l.warehouse = :warehouse_id
                        AND l.type_id = :lp_loc_type AND l.zone = :lp_loc_zone AND l.`column` = :lp_loc_column
                WHERE sb.type_id = :sb_type_lp AND sb.warehouse_id = :warehouse_id$sb_where
                UNION ALL
                -- CA LP
                SELECT sb.object_id id, tabl.object_id packer_id, u.login
                FROM section_binding sb,
                     (SELECT DISTINCT num, object_id
                      FROM section_binding
                      WHERE type_id = :sb_type_lp AND warehouse_id = :warehouse_id) tabl
                    JOIN users u ON u.id = tabl.object_id
                WHERE sb.num = tabl.num AND sb.type_id = :sb_bind_lp AND sb.warehouse_id = :warehouse_id$sb_where
                ORDER BY 1
SQL;
        $safeParams = [];
        foreach ($params as $k => $v)
            $safeParams[ltrim($k, ':')] = $v;
        $places = (new SafeMysql())->fetchAll($sql, $safeParams);
        $res = [];
        foreach ($places as $place)
            if (!empty($place['packer_id']))
                $res[$place['id']][$place['packer_id']] = $place['login'];
        return $res;
    }

    public static function getLTables($user_id, $warehouse_id)
    {
        $location_name = Locations::getLocationNameSelect('l', 'w');
        $sql = "SELECT l.id, $location_name name
                FROM section_binding sb
                    JOIN locations l ON l.warehouse = :warehouse_id AND l.type_id = :loc_type AND l.row = sb.num
                    JOIN warehouses w ON l.warehouse = w.id
                WHERE sb.object_id = :user_id AND sb.type_id = :sb_type AND sb.warehouse_id = :warehouse_id";
        return (new SafeMysql())->fetchAll($sql, [
            'user_id' => (int)$user_id,
            'warehouse_id' => (int)$warehouse_id,
            'loc_type' => Location::LP,
            'sb_type' => SectionBinding::LARGES,
        ]);
    }

    public static function getTables($user_id, $warehouse_id)
    {
        $location_name = Locations::getLocationNameSelect('l', 'w');
        $sql = "SELECT l.id, $location_name name
                FROM section_binding sb
                    JOIN locations l ON l.warehouse = :warehouse_id AND l.type_id = :loc_type AND l.row = sb.num
                    JOIN warehouses w ON l.warehouse = w.id
                WHERE sb.object_id = :user_id AND sb.type_id = :sb_type AND sb.warehouse_id = :warehouse_id";
        return (new SafeMysql())->fetchAll($sql, [
            'user_id' => (int)$user_id,
            'warehouse_id' => (int)$warehouse_id,
            'loc_type' => Location::TP,
            'sb_type' => SectionBinding::TABLES,
        ]);
    }

    public static function setFreeCart($user_id, $cart_id, $mode)
    {
        $user_id = (int) $user_id;
        $cart_id = (int) $cart_id;
        $warehouse_id = Users::getWarehouseIdById($user_id);
        $mysql = new SafeMysql();
        $nums = $mysql->fetchArrayOneField("SELECT num
                                                    FROM section_binding
                                                    WHERE object_id = :user_id
                                                        AND type_id = :sb_type
                                                        AND warehouse_id = :warehouse_id", [
            'user_id' => $user_id,
            'warehouse_id' => $warehouse_id,
            'sb_type' => $mode === 'single' ? SectionBinding::TABLES : ($mode === 'large' ? SectionBinding::LARGES : ''),
        ]);
        if (empty($nums))
            return;
        $mysql->execute("DELETE FROM section_binding
                                WHERE num IN (:nums)
                                    AND object_id = :cart_id
                                    AND type_id = :sb_type
                                    AND warehouse_id = :warehouse_id", [
            'nums' => $nums,
            'cart_id' => $cart_id,
            'sb_type' => $mode === 'single' ? SectionBinding::TYPE_BIND_SINGLE_TABLE : ($mode === 'large' ? SectionBinding::TYPE_BIND_LARGE_TABLE : ''),
            'warehouse_id' => $warehouse_id,
        ]);
    }

    public static function removeByCartId($cart_id)
    {
        $cart_id = (int)$cart_id;
        $binds = [SectionBinding::TYPE_BIND_SINGLE_TABLE, SectionBinding::TYPE_BIND_LARGE_TABLE];
        return (new SafeMysql())->execute("DELETE FROM section_binding 
                                        WHERE object_id = :cart_id AND type_id IN (:binds)", [
            'cart_id' => $cart_id,
            'binds' => $binds,
        ]);
    }

    public static function getCartIdByName(int $warehouse_id, $cart_name)
    {
        $location = Locations::getByScan($warehouse_id, $cart_name);
        if (is_null($location))
            return 0;
        $binds = [SectionBinding::TYPE_BIND_SINGLE_TABLE, SectionBinding::TYPE_BIND_LARGE_TABLE];
        return (new SafeMysql())->fetchScalar("SELECT object_id
            FROM section_binding 
            WHERE type_id IN (:binds) AND object_id = :loc_id", [
            'binds' => $binds,
            'loc_id' => $location->getId(),
        ]);
    }

    public static function getBindingType($object_id)
    {
        return (new SafeMysql())->fetchScalar('SELECT type_id FROM section_binding WHERE object_id = :object_id', [
            'object_id' => $object_id,
        ]);
    }

    public static function getBinding($object_id)
    {
        return (new SafeMysql())->fetchOne('SELECT num, type_id FROM section_binding WHERE object_id = :object_id', [
            'object_id' => $object_id,
        ]);
    }
}
