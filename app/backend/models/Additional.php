<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\entities\Order;

class Additional
{
    const NUMS_ORDER = 5;
    const NUMS_OLDEST_ORDER = 20;

    private static function initArrayOB($row, $mezzanine, $state, $order_id = null): array
    {
        return [
            'row' => $row,
            'mezzanine' => $mezzanine,
            'state' => $state,
            'order_id' => $order_id,
            'orders' => [],
            'order_arr' => [],
            'consist' => []
        ];
    }

    public static function getOrderBin($warehouse_id): array
    {
        $warehouse_id = (int) $warehouse_id;
        $locations = [];
        $shortage_orders = self::getOrderShortage($warehouse_id);
        $shortage_SKUs = self::getSKULocationsShortage($warehouse_id);
        foreach ($shortage_orders as $location_id => $shortage_order) {
            if (!array_key_exists($location_id, $locations)) {
                $locations[$location_id] = self::initArrayOB(
                    $shortage_order['row'],
                    $shortage_order['mezzanine'],
                    $shortage_order['state'],
                    $shortage_order['order_id'] ?? null
                );
            }
            $locations[$location_id]['order_arr'] = array_merge($locations[$location_id]['order_arr'] ?? [], $shortage_order['order_arr']);
            $locations[$location_id]['consist'] = array_merge($locations[$location_id]['consist'] ?? [], $shortage_order['consist']);
        }

        foreach ($shortage_SKUs as $location_id => $shortage_SKU) {
            if (!array_key_exists($location_id, $locations)) {
                $locations[$location_id] = self::initArrayOB(
                    $shortage_SKU['row'],
                    $shortage_SKU['mezzanine'],
                    $shortage_SKU['state'],
                    $shortage_SKU['order_id'] ?? null
                );
            }
            $locations[$location_id]['consist'] = array_merge($locations[$location_id]['consist'] ?? [], $shortage_SKU['consist']);
        }

        array_walk($locations, function(&$loc) {
            if (!empty($loc['order_arr'])) {
                $order_temp = [];
                foreach ($loc['order_arr'] as $order_item) {
                    $order_temp[] = '#' . $order_item['id'] . ' (' . $order_item['status'] . ')<br>';
                }
                $loc['orders'] = implode(' ', $order_temp);
            }
            $minus = false;
            if (!empty($loc['consist']) && is_array($loc['consist'])) {
                $suppliers = [];
                $consist = [];
                foreach ($loc['consist'] as $item) {
                    $consist[] = $item['SKU_id'] . ' (#' . $item['supplier_id'] . ') ' . (!empty($item['qty']) ? $item['qty'] : '') . (!is_null($item['type_id']) ? (' ' . $item['type_id']) : '') . '<br>';
                    if (!empty($item['supplier_id'])) {
                        $suppliers[$item['supplier_id']] = 1;
                    }
                    if (!empty($item['qty'])) {
                        $minus = true;
                    }
                }
                if (count($suppliers) != 1 && !$minus)
                    $minus = true;

                $loc['consist'] = implode(' ', $consist);
            }
            $loc['minus'] = $minus;
        });

        $res = [];

        foreach ($locations as $loc_id => $location) {
            $mezzanine = $location['mezzanine'];
            $location['id'] = $loc_id;
            unset($location['mezzanine']);
            $res[$mezzanine][] = $location;
        }
        return $res;
    }

//    public static function getOrderBin($warehouse_id)
//    {
//        $warehouse_id = (int) $warehouse_id;
//        Mysql::runQuery("SET SESSION group_concat_max_len = 1000000;");
//        $sql = "SELECT un_l.id,
//                       un_l.row,
//                       un_l.mezzanine,
//                       un_l.state,
//                       un_l.order_id,
//                       GROUP_CONCAT(un_l.orders SEPARATOR ' ') orders,
//                       GROUP_CONCAT(un_l.consist SEPARATOR ' ') consist
//                FROM (
//                    (SELECT l.id,
//                            l.row,
//                            l.mezzanine,
//                            l.state,
//                            o.id order_id,
//                            GROUP_CONCAT(DISTINCT CONCAT('#', ol.order_id, '&nbsp;(', IFNULL(o.status, '-'), ')') SEPARATOR ' ') orders,
//                            GROUP_CONCAT(skus.consist SEPARATOR ' ') consist
//                     FROM locations l
//                         LEFT JOIN order_location ol ON ol.location_id = l.id
//                         LEFT JOIN orders o ON o.id = ol.order_id AND o.warehouse_id = $warehouse_id
//                         LEFT JOIN (SELECT os.order_id, GROUP_CONCAT(CONCAT(sl.SKU_id, '&nbsp;(#', s.supplier_id, '):&nbsp;', os.quantity - IFNULL(sl.quantity, 0), '&nbsp;', lw.type_id) SEPARATOR ' ') consist
//                                    FROM order_SKUs os
//                                        JOIN SKUs s ON os.SKU_id = s.id
//                                        LEFT JOIN SKU_locations sl ON os.order_id = sl.order_id AND os.SKU_id = sl.SKU_id
//                                        LEFT JOIN locations lw ON sl.location_id = lw.id
//                                    GROUP BY os.order_id, sl.SKU_id) skus ON ol.order_id = skus.order_id
//                     WHERE l.warehouse = $warehouse_id AND l.type_id = 'OB'
//                     GROUP BY l.id)
//                    UNION
//                    (SELECT l.id,
//                            l.row,
//                            l.mezzanine,
//                            l.state,
//                            NULL,
//                            NULL orders,
//                            GROUP_CONCAT(CONCAT(sl.SKU_id, '&nbsp;(#', s.supplier_id, '):&nbsp;', sl.quantity) SEPARATOR ' ') consist
//                     FROM locations l
//                         LEFT JOIN order_location ol ON ol.location_id = l.id
//                         LEFT JOIN order_SKUs os ON ol.order_id = os.order_id
//                         LEFT JOIN SKU_locations sl ON l.id = sl.location_id AND os.SKU_id = sl.SKU_id AND (os.order_id <> sl.order_id OR sl.order_id IS NULL)
//                         LEFT JOIN SKUs s ON sl.SKU_id = s.id
//                     WHERE l.warehouse = $warehouse_id AND l.type_id = 'OB'
//                     GROUP BY l.id, sl.SKU_id)) un_l
//                GROUP BY un_l.id
//                ORDER BY un_l.mezzanine, LPAD(un_l.row, 5, ' ')";
//        $locs = Mysql::fetchAssoc($sql);
//        $res = [];
//        foreach ($locs as $loc) {
//            $mezzanine = $loc['mezzanine'];
//            $minus = false;
//            if (!empty($loc['consist'])) {
//                $consist = explode(' ', $loc['consist']);
//                $suppliers = [];
//                foreach ($consist as $items) {
//                    $item = explode('&nbsp;', $items);
//                    $suppliers[$item[1]] = 1;
//                    if (!empty($item[2])) {
//                        $minus = true;
//                        break;
//                    }
//                }
//                if (count($suppliers) != 1 && !$minus)
//                    $minus = true;
//            }
//            $loc['minus'] = $minus;
//            unset($loc['mezzanine']);
//            $res[$mezzanine][] = $loc;
//        }
//        return $res;
//    }

    private static function getOrderShortage($warehouse_id): array
    {
        $order_shortage = (new SafeMysql())->fetchAll('SELECT os.order_id, lw.id as loc_id, lw.type_id, 0 as qty, sl.SKU_id, s.supplier_id,
                                                        os.quantity order_qty, IFNULL(sl.quantity, 0) loc_qty
                                            FROM order_SKUs os
                                                     JOIN SKUs s ON os.SKU_id = s.id
                                                     LEFT JOIN SKU_locations sl ON os.order_id = sl.order_id AND os.SKU_id = sl.SKU_id
                                                     LEFT JOIN locations lw ON sl.location_id = lw.id
                                                WHERE lw.id IS NOT NULL');

        $res_shortage = [];
        foreach ($order_shortage as $item) {
            if (!array_key_exists($item['order_id'], $res_shortage)) {
                $res_shortage[$item['order_id']] = [];
            }
            if (!isset($res_shortage[$item['order_id']][$item['SKU_id']])) {
                $res_shortage[$item['order_id']][$item['SKU_id']] = [
                    'SKU_id' => $item['SKU_id'],
                    'supplier_id' => $item['supplier_id'],
                    'order_qty' => (int)$item['order_qty'],
                    'loc_qty' => (int)$item['loc_qty'],
                    'type_id' => [$item['type_id']]
                ];
            } else {
                $res_shortage[$item['order_id']][$item['SKU_id']]['loc_qty'] += $item['loc_qty'];
                $res_shortage[$item['order_id']][$item['SKU_id']]['type_id'][] = $item['type_id'];
            }
        }

        array_walk($res_shortage, function(&$items) {
            $res = [];
            foreach ($items as $SKU) {
                $SKU['qty'] = $SKU['order_qty'] - $SKU['loc_qty'];
                $SKU['type_id'] = implode(' ', array_unique($SKU['type_id']));
                $res[] = $SKU;
            }
            $items = $res;
        });
        $locs = (new SafeMysql())->fetchAll("SELECT l.id,
                            l.row,
                            l.mezzanine,
                            l.state,
                            o.id order_id,
                            IFNULL(o.status, '-') status
                     FROM locations l
                         LEFT JOIN order_location ol ON ol.location_id = l.id
                         LEFT JOIN orders o ON o.id = ol.order_id AND o.warehouse_id = :warehouse_id
                     WHERE l.warehouse = :warehouse_id AND l.type_id = :type_id ORDER BY LPAD(l.row, 5, ' ')", [
                         'warehouse_id' => $warehouse_id,
                         'type_id' => Location::OB,
                     ]);

        $res_locs = [];
        foreach ($locs as $loc) {
            if (!array_key_exists($loc['id'], $res_locs)) {
                $res_locs[$loc['id']] = self::initArrayOB(
                    $loc['row'],
                    $loc['mezzanine'],
                    $loc['state'],
                    $loc['order_id']
                );
            }
            if (!is_null($loc['order_id'])) {
                $res_locs[$loc['id']]['order_arr'][] = [
                    'id' => (int)$loc['order_id'],
                    'status' => $loc['status'] ?? '-',
                ];
                if (array_key_exists($loc['order_id'], $res_shortage)) {
                    $res_locs[$loc['id']]['consist'] = array_merge($res_locs[$loc['id']]['consist'] ?? [], $res_shortage[$loc['order_id']]);
                }
            }
        }
        return $res_locs;
    }

    private static function getSKULocationsShortage($warehouse_id): array
    {
        $locs = (new SafeMysql())->fetchAll("SELECT l.id, l.row, l.mezzanine, l.state,
                                                    sl.SKU_id, s.supplier_id, sl.quantity
                     FROM locations l
                         LEFT JOIN order_location ol ON ol.location_id = l.id
                         LEFT JOIN order_SKUs os ON ol.order_id = os.order_id
                         LEFT JOIN SKU_locations sl ON l.id = sl.location_id AND (os.order_id <> sl.order_id OR sl.order_id IS NULL) 
                         LEFT JOIN SKUs s ON sl.SKU_id = s.id 
                     WHERE l.warehouse = :warehouse_id AND l.type_id = :type_id
                        GROUP BY s.id, l.id", [
                            'warehouse_id' => $warehouse_id,
                            'type_id' => Location::OB,
                        ]);
        $res_locs = [];

        foreach ($locs as $loc) {
            if (!array_key_exists($loc['id'], $res_locs)) {
                $res_locs[$loc['id']] = self::initArrayOB($loc['row'], $loc['mezzanine'], $loc['state']);
            }
            if (!is_null($loc['SKU_id']))
                $res_locs[$loc['id']]['consist'][] = [
                'SKU_id' => $loc['SKU_id'],
                'supplier_id' => $loc['supplier_id'],
                'qty' => $loc['quantity'],
                'type_id' => null
            ];

        }
        return $res_locs;
    }

    public static function getOldOrder($OBs): array
    {
        $ord_loc = [];
        for ($num = 0; $num < count($OBs); $num++)
            foreach ($OBs[$num] as $OB) {
                if (empty($OB['order_arr']))
                    continue;
                foreach ($OB['order_arr'] as $order) {
                    $order_id = (int)$order['id'];
                    $ord_loc[$order_id][] = $OB['id'];
                }
            }
        ksort($ord_loc);
        $res = [];
        $num = 0;
        foreach ($ord_loc as $order_id => $loc_ids) {
            $locs = [];
            foreach ($loc_ids as $loc_id)
                $locs[] = Locations::getById($loc_id);
            $res[] = ['order' => Orders::getById($order_id), 'location' => $locs];
            $num++;
            if ($num == self::NUMS_ORDER)
                break;
        }
        return $res;
    }

    public static function getTablePack($warehouse_id)
    {
        $warehouse_id = (int) $warehouse_id;
        $mysql = new SafeMysql();
        $mysql->execute("SET SESSION group_concat_max_len = 1000000;");
        $sql = "(" .
               "SELECT locs.num `row`,
                       locs.state,
                       CONCAT('#', sl.order_id, '&nbsp;(', IFNULL(o.status, '-'), ')') orders,
                       GROUP_CONCAT(CONCAT(sl.SKU_id, '&nbsp;(#', s.supplier_id, '):&nbsp;', sl.type, '&nbsp;(CA)') SEPARATOR '; ') consist
                FROM SKU_locations sl
                    JOIN (SELECT sb.object_id, l.id, sb.num, l.state
                          FROM section_binding sb
                              JOIN locations l ON l.row = sb.num AND l.warehouse = :warehouse_id AND l.type_id = :type_id
                          WHERE sb.type_id = 'C') locs ON sl.location_id = locs.object_id
                    JOIN orders o ON o.id = sl.order_id AND o.warehouse_id = :warehouse_id
                    JOIN SKUs s ON sl.SKU_id = s.id
                GROUP BY sl.location_id, sl.order_id".
               ") UNION (" .
               "SELECT l.row,
                       l.state,
                       CONCAT('#', sl.order_id, '&nbsp;(', IFNULL(o.status, '-'), ')') orders,
                       GROUP_CONCAT(CONCAT(sl.SKU_id, '&nbsp;(#', s.supplier_id, '):&nbsp;', sl.type) SEPARATOR '; ') consist
                FROM locations l
                    LEFT JOIN SKU_locations sl ON sl.location_id = l.id
                    LEFT JOIN orders o ON sl.order_id = o.id AND o.warehouse_id = :warehouse_id
                    LEFT JOIN SKUs s ON sl.SKU_id = s.id
                WHERE l.type_id = :type_id AND l.warehouse = :warehouse_id
                GROUP BY l.id, sl.order_id" .
               ") " .
               "ORDER BY `row`, orders";
        $locs = $mysql->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'type_id' => Location::TP,
        ]);
        $res = [];
        foreach ($locs as $loc) {
            $row = $loc['row'];
            $res[$row]['state'] = $loc['state'];
            unset($loc['row'], $loc['state']);
            $res[$row]['data'][] = $loc;
        }
        foreach ($res as &$table) {
            $exist = false;
            $minus = false;
            foreach ($table['data'] as $orders)
                if (!empty($orders['orders']) || !empty($orders['consist'])) {
                    $exist = true;
                    break;
                }
            if ($exist)
                foreach ($table['data'] as $orders) {
                    if (!empty($orders['orders']) && !empty($orders['consist'])) {
                        $ords = explode(' ', $orders['orders']);
                        if (count($ords) != 1) {
                            $minus = true;
                            break;
                        }
                        $SKUs = explode('; ', $orders['consist']);
                        if (count($SKUs) != 1) {
                            $minus = true;
                            break;
                        }
                        continue;
                    }
                    $minus = true;
                    break;
                }
            $table['exist'] = $exist;
            $table['minus'] = $minus;
        } unset($table);
        return $res;
    }

    public static function getOldOrderTables($tables)
    {
        $ord_loc = [];
        foreach ($tables as $num => $table)
            foreach ($table['data'] as $orders) {
                if (empty($orders['orders']))
                    continue;
                $order_info = explode('&nbsp;', $orders['orders']);
                $ord_loc[str_replace('#', '', $order_info[0])][] = $num;
            }
        ksort($ord_loc);
        $res = [];
        $num = 0;
        foreach ($ord_loc as $order_id => $loc_ids) {
            $locs = [];
            foreach ($loc_ids as $loc_id)
                $locs[] = $loc_id;
            $res[] = ['order' => Orders::getById($order_id), 'location' => $locs];
            $num++;
            if ($num == self::NUMS_ORDER)
                break;
        }
        return $res;
    }

    public static function getLargePack($warehouse_id)
    {
        $warehouse_id = (int) $warehouse_id;
        $mysql = new SafeMysql();
        $mysql->execute("SET SESSION group_concat_max_len = 1000000;");
        $sql = "(" .
            "SELECT locs.num `row`,
                    locs.state,
                    CONCAT('#', sl.order_id, '&nbsp;(', IFNULL(o.status, '-'), ')') orders,
                    GROUP_CONCAT(CONCAT(sl.SKU_id, '&nbsp;(#', s.supplier_id, '):&nbsp;', sl.type, '&nbsp;(CA)') SEPARATOR '; ') consist
             FROM SKU_locations sl
                 JOIN (SELECT sb.object_id, l.id, sb.num, l.state
                       FROM section_binding sb
                           JOIN locations l ON l.row = sb.num AND l.warehouse = :warehouse_id AND l.type_id = :type_id
                       WHERE sb.type_id = 'U') locs ON sl.location_id = locs.object_id
                 JOIN orders o ON o.id = sl.order_id AND o.warehouse_id = :warehouse_id
                 JOIN SKUs s ON sl.SKU_id = s.id
             GROUP BY sl.location_id, sl.order_id".
            ") UNION (" .
            "SELECT l.row,
                       l.state,
                       CONCAT('#', sl.order_id, '&nbsp;(', IFNULL(o.status, '-'), ')') orders,
                       GROUP_CONCAT(CONCAT(sl.SKU_id, '&nbsp;(#', s.supplier_id, '):&nbsp;', sl.type) SEPARATOR '; ') consist
                FROM locations l
                    LEFT JOIN SKU_locations sl ON sl.location_id = l.id
                    LEFT JOIN orders o ON sl.order_id = o.id AND o.warehouse_id = :warehouse_id
                    LEFT JOIN SKUs s ON sl.SKU_id = s.id
                WHERE l.type_id = :type_id AND l.warehouse = :warehouse_id
                GROUP BY l.id, sl.order_id" .
            ") " .
            "ORDER BY `row`, orders";
        $locs = $mysql->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'type_id' => Location::LP,
        ]);
        $res = [];
        foreach ($locs as $loc) {
            $row = $loc['row'];
            $res[$row]['state'] = $loc['state'];
            unset($loc['row'], $loc['state']);
            $res[$row]['data'][] = $loc;
        }
        foreach ($res as &$table) {
            $exist = false;
            $minus = false;
            foreach ($table['data'] as $orders)
                if (!empty($orders['orders']) || !empty($orders['consist'])) {
                    $exist = true;
                    break;
                }
            if ($exist)
                foreach ($table['data'] as $orders) {
                    if (!empty($orders['orders']) && !empty($orders['consist'])) {
                        $ords = explode(' ', $orders['orders']);
                        if (count($ords) != 1) {
                            $minus = true;
                            break;
                        }
                        continue;
                    }
                    $minus = true;
                    break;
                }
            $table['exist'] = $exist;
            $table['minus'] = $minus;
        } unset($table);
        return $res;
    }

    public static function getOldestOrders($warehouse_id, $num_orders, $backorder)
    {
        $statuses = [Order::CANCELLED_NUM, Order::DESPATCHED_NUM, Order::RETURNED_NUM];
        if (!$backorder)
            $statuses[] = Order::BACKORDER_NUM;

        $sql = "SELECT *
                FROM orders
                WHERE status NOT IN (:statuses) AND warehouse_id = :warehouse_id
                ORDER BY id
                LIMIT :num_orders";
        $res = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'num_orders' => $num_orders,
            'statuses' => $statuses
        ]);
        $orders = [];
        foreach ($res as $order_data)
            $orders[$order_data['id']] = new Order($order_data);
        return $orders;
    }
}
