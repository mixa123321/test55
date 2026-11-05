<?php

namespace app\core\models;

use app\backend\models\Cart;
use app\backend\models\Drop;
use app\backend\models\Pack;
use app\backend\models\ShippingServices;
use app\core\dbal\SafeMysql;
use app\core\entities\Order;
use app\core\entities\OrderHistory;
use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\OrderHistories;
use app\backend\models\Orders as OrdersModel;
use app\backend\models\Pick;
use app\core\entities\Shipping;
use app\core\entities\User;
use app\core\entities\UserJob;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\backend\models\SKULocations as SKULocationsM;
use app\core\entities\Location;
use app\core\entities\SectionBinding;
use app\core\helpers\Debug;
use app\core\Queue;
use app\core\statical\Sources;
use app\core\helpers\TimeZonesConvertor;
use app\core\entities\File;
use app\core\helpers\FileWrapper;
use app\core\statical\WithDate;

class Orders
{

    public static function isApprove($order_id)
    {
        $sql = "SELECT IFNULL(sw.is_orders_approval_required, 0)
                FROM orders o
                    LEFT JOIN shop_warehouses sw ON o.shop_id = sw.shop_id AND o.warehouse_id = sw.warehouse_id
                WHERE o.id = :order_id";
        return (bool)(new SafeMysql())->fetchScalar($sql, ['order_id' => (int)$order_id]);
    }

    public static function getChannel($order_id)
    {
        $sql = "SELECT TRIM(c.name)
                FROM orders o
                    LEFT JOIN shops s ON o.shop_id = s.id
                    LEFT JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id
                    LEFT JOIN channels c ON cs.channel_id = c.id
                WHERE o.id = :order_id";
        return (new SafeMysql())->fetchScalar($sql, ['order_id' => (int)$order_id]);
    }

    public static function getShipper($order_id)
    {
        $sql = "SELECT c.code, ss.code scode
                FROM orders o
                    LEFT JOIN carriers c ON o.carrier_id = c.id
                    LEFT JOIN shipping_services ss ON o.shipping_services_id = ss.id
                WHERE o.id = :order_id";
        return (new SafeMysql())->fetchOne($sql, ['order_id' => (int)$order_id]);
    }

    public static function getPacking($warehouse_id, $carriers, $packing, $packing_type, $mezzanine = null, $services = 0, $clients = [])
    {
        $warehouse_id = (int)$warehouse_id;
        if (empty($carriers))
            return [];
        $whereCarriers = is_array($carriers) ? ('IN (' . implode(',', $carriers) . ')') : "= $carriers";
        $where_client = '';
        if (!empty($clients) && is_array($clients)) {
            $filteredClients = array_filter(array_map('intval', $clients));
            if (!empty($filteredClients))
                $where_client = ' AND s.id IN (' . implode(',', $filteredClients) . ')';
        }
        $packing = is_array($packing) ? ('IN (' . implode(',', $packing) . ')') : "= $packing";
        $wherePacking = '';
        if ($packing_type == 1)
            $wherePacking = "AND l.type_id = '" . Location::TP . "'";
        elseif ($packing_type == 2)
            $wherePacking = "AND l.type_id = '" . Location::OB . "'";
        elseif ($packing_type == 3)
            $wherePacking = "AND l.type_id = '" . Location::LP . "'";
        $mezzanineSQL = '';
        if (!is_null($mezzanine))
            $mezzanineSQL = ' AND l.mezzanine = ' . (int)(bool)$mezzanine;
        $shipping_services = 'JOIN shipping_services ss ON o.shipping_services_id = ss.id';
        $is_customer = false;
        $where_services = $where_shipping_services = '';
        if ($services != -1) {
            $service_ids = ShippingServices::getSelectedServicesIds($services);
            $where_shipping_services = 'AND o.shipping_services_id IN (' . implode(',', $service_ids) . ')';
            $pos = array_search(0, $service_ids);
            if ($pos !== false) {
                unset($service_ids[$pos]);
                $is_customer = true;
            }
            $where_services = empty($service_ids)
                ? $shipping_services
                : ("$shipping_services AND ss.id IN (" . implode(',', $service_ids) . ')');
        }
        if (empty($where_services))
            $where_services = "LEFT $shipping_services";
        if ($is_customer)
            $where_services = "LEFT $where_services";
        $sql = "SELECT o.id, s.name, s.id sid, o.despatch_date, c.name cname, 
                    ol.location_id, IF(w.label = '' OR w.label IS NULL, l.warehouse, w.label) warehouse, l.type_id, l.zone, l.`column`, 
                    l.row, COUNT(os.SKU_id) SKUs, SUM(os.quantity) units,
                    o.priority, IFNULL(ss.next_day, false) as next_day
                FROM orders o
                    JOIN order_location ol ON o.id = ol.order_id 
                    JOIN supplier_users su ON o.user_id = su.user_id 
                    JOIN suppliers s ON o.supplier_id = s.id 
                    JOIN carriers c ON o.carrier_id = c.id
                    JOIN locations l ON ol.location_id = l.id AND l.warehouse = $warehouse_id $wherePacking $mezzanineSQL
                    JOIN warehouses w ON l.warehouse = w.id
                    JOIN order_SKUs os ON o.id = os.order_id
                    $where_services
                WHERE o.status $packing AND o.carrier_id $whereCarriers $where_shipping_services $where_client
                GROUP BY o.id
                ORDER BY o.priority = 0, o.priority ASC, o.despatch_date, l.warehouse, l.type_id, LPAD(l.zone, 5, ' '), LPAD(l.`column`, 5, '0'), LPAD(l.row, 5, ' '), o.id";
        return (new SafeMysql())->fetchAll($sql);
    }

    public static function getWaitingOB($warehouse_id)
    {
        $ids = array_column(ShippingServices::getServicesByTime($warehouse_id, date('H:i')), 'id');
        if (!empty($ids)) {
            $ids_for_sort = array_flip($ids);
            $max = max($ids) + 1;
            $sql = "SELECT o.id, o.priority, o.shipping_services_id, (SELECT SUM(quantity)
                                  FROM order_SKUs
                                  WHERE order_id = o.id) skus
                    FROM orders o
                    WHERE o.status LIKE '" . Order::WAITING_FOR_PICK_NUM . "_0' AND o.warehouse_id = :warehouse_id
                    ORDER BY o.priority = 0, o.priority ASC, o.id";
            $orders = [];
            foreach ((new SafeMysql())->fetchAll($sql, ['warehouse_id' => (int)$warehouse_id]) as $item) {
                $id = (int)$item['id'];
                $priority = (int)$item['priority'];
                $shipping_services_id = (int)$item['shipping_services_id'];
                if (!isset($orders[$priority]))
                    $orders[$priority] = [];
                if (!isset($orders[$priority][$shipping_services_id]))
                    $orders[$priority][$shipping_services_id] = [];
                $orders[$priority][$shipping_services_id][$id] = $item['skus'];
            }
            foreach ($orders as $priority => &$shipping_items) {
                uksort($shipping_items, function ($a, $b) use ($ids_for_sort, $max) {
                    $weight_a = $ids_for_sort[$a] ?? $max;
                    $weight_b = $ids_for_sort[$b] ?? $max;
                    return $weight_a <=> $weight_b;
                });
            }
            unset($shipping_items);
            $all_orders = [];
            foreach ($orders as $priority => $shipping_items) {
                foreach ($shipping_items as $shipping_id => $orders_items)
                    foreach ($orders_items as $order_id => $sum)
                        $all_orders[] = ['id' => $order_id, 'skus' => $sum];
            }
            return $all_orders;
//            $sql_query = function($id, $scope = '') use($warehouse_id) {
//                return "SELECT o.id, (SELECT SUM(quantity)
//                                      FROM order_SKUs
//                                      WHERE order_id = o.id) skus
//                        FROM orders o
//                        WHERE o.status LIKE '" . Order::WAITING_FOR_PICK_NUM . "_0' AND
//                              o.warehouse_id = $warehouse_id AND
//                              o.shipping_services_id $scope IN ($id)
//                        ORDER BY o.priority = 0, o.priority ASC, o.id";
//            };
//            $sql_queries = [];
//            foreach ($ids as $id)
//                $sql_queries[] = $sql_query($id);
//            $sql_queries[] = $sql_query(implode(',', $ids), 'NOT');
//            $sql = '(' . implode(') UNION (', $sql_queries) . ')';
        } else
            $sql = "SELECT o.id, (SELECT SUM(quantity)
                                  FROM order_SKUs
                                  WHERE order_id = o.id) skus
                    FROM orders o
                    WHERE o.status LIKE '" . Order::WAITING_FOR_PICK_NUM . "_0' AND o.warehouse_id = :warehouse_id
                    ORDER BY o.priority = 0, o.priority ASC, o.id";
        return (new SafeMysql())->fetchAll($sql, ['warehouse_id' => (int)$warehouse_id]);
    }

    public static function getWaitingReplen($warehouse_id)
    {
        $sql = "SELECT o.id, SKU_id, quantity
                FROM orders o
                    JOIN order_SKUs os ON o.id = os.order_id
                    JOIN suppliers sup ON o.supplier_id = sup.id
                    JOIN users u ON sup.user_id = u.id
                WHERE o.status IN (" . implode(', ', Order::$allowed_update_statuses) . ") AND o.warehouse_id = :warehouse_id
                ORDER BY o.id";
        $orders = (new SafeMysql())->fetchAll($sql, ['warehouse_id' => (int)$warehouse_id]);
        $res = [];
        foreach ($orders as $order)
            $res[$order['id']][$order['SKU_id']] = $order['quantity'];
        return $res;
    }

    public static function getForSftpPdf(int $order_id): array
    {
        $data = (new SafeMysql())->fetchOne('SELECT ref, supplier_id, user_id FROM orders WHERE id = :order_id', ['order_id' => $order_id]);
        if ($data)
            return $data;
        return [];
    }

    /**
     * @param $id int
     * @return Order
     */
    public static function getById($id, bool $use_cache = true)
    {
        $data = (new SafeMysql())->fetchOne('SELECT * FROM orders WHERE id = :id', ['id' => (int)$id]);
        if ($data)
            return new Order($data);
        return null;
    }

    /**
     * @param $ref string
     * @param $supplier_id int
     * @return Order
     */
    public static function getByRef($ref, $supplier_id)
    {
        $data = (new SafeMysql())->fetchOne("SELECT * FROM orders
            WHERE supplier_id = :supplier_id
            AND ref = :ref
            LIMIT 1", ['supplier_id' => (int)$supplier_id, 'ref' => $ref]);
        if (!$data)
            return null;
        return new Order($data);
    }

    public static function getByRefCancel($ref, $supplier_id)
    {
        $data = (new SafeMysql())->fetchOne("SELECT * FROM orders
            WHERE supplier_id = :supplier_id
            AND ref LIKE CONCAT(:ref, '%')
            LIMIT 1", ['supplier_id' => (int)$supplier_id, 'ref' => $ref]);
        if (!$data)
            return null;
        return new Order($data);
    }

    public static function getByRefAndRef2($ref, $ref2, $shop_id, string $ref_with_warehouse = ''): ?Order
    {
        $params = ['shop_id' => (int)$shop_id, 'ref2' => $ref2, 'ref' => $ref];
        $where = "";
        if ($ref_with_warehouse) {
            $params['ref_wh'] = $ref_with_warehouse;
            $where = "OR
            ref = :ref_wh OR
            ref LIKE CONCAT(:ref_wh, '-" . Order::CANCELLED . "\_%') OR
            ref LIKE CONCAT(:ref_wh, '-recreated\_%') OR
            ref LIKE CONCAT(:ref_wh, '-shortship\_%')";
        }
        $data = (new SafeMysql())->fetchOne("
            SELECT * FROM orders
            WHERE shop_id = :shop_id AND
                  ref2 = :ref2 AND
                  (ref = :ref OR
                   ref LIKE CONCAT(:ref, '-" . Order::CANCELLED . "\_%') OR
                   ref LIKE CONCAT(:ref, '-recreated\_%') OR
                   ref LIKE CONCAT(:ref, '-shortship\_%') $where)
            LIMIT 1", $params);
        return $data ? new Order($data) : null;
    }

    public static function getTrackingNumbers($order_id)
    {
        $sql = "SELECT DISTINCT sh.tracking_number
                FROM orders o
                    LEFT JOIN orders_packages op ON o.id = op.order_id AND op.is_cancel = 0
                    LEFT JOIN order_packages_shipping ops ON ops.order_package_id = op.id
                    LEFT JOIN shipping sh ON ops.shipping_id = sh.id AND sh.status = 4 AND sh.tracking_number IS NOT NULL
                WHERE o.id = :order_id";
        return (new SafeMysql())->fetchArrayOneField($sql, ['order_id' => (int)$order_id]);
    }

    /**
     * @param $id int
     * @param $user User
     * @return Order
     */
    public static function getByIdAndUser($id, $user)
    {
        $suppliers_id = Arr::column(SuppliersModel::getByUser($user), 'id');
        if (empty($suppliers_id))
            return null;
        $sql = "SELECT *
                FROM orders
                WHERE id = :id AND supplier_id IN (:suppliers_id)";
        $data = (new SafeMysql())->fetchOne($sql, ['id' => (int)$id, 'suppliers_id' => array_map('intval', $suppliers_id)]);
        if (empty($data))
            return null;
        return new Order($data);
    }

    public static function getByRefAndUser($ref, User $user)
    {
        $suppliers_id = Arr::column(SuppliersModel::getByUser($user), 'id');
        if (empty($suppliers_id))
            return null;
        $sql = "SELECT *
                FROM orders
                WHERE ref = :ref AND supplier_id IN (:suppliers_id)";
        $data = (new SafeMysql())->fetchOne($sql, ['ref' => $ref, 'suppliers_id' => array_map('intval', $suppliers_id)]);
        if (empty($data))
            return null;
        return new Order($data);
    }

    /**
     * @param $supplier_id int
     * @param $order_ref string
     * @return Order
     */
    public static function getDespatchedByIdOrReference($supplier_id, $order_id, $warehouse_id, $equal_warehouse = true)
    {
        $op = $equal_warehouse ? '=' : '<>';
        $params = [
            'supplier_id' => (int)$supplier_id,
            'order_id' => $order_id,
            'warehouse_id' => (int)$warehouse_id
        ];
        $res = (new SafeMysql())->fetchOne("
            SELECT *
            FROM orders
            WHERE supplier_id = :supplier_id AND
                  ref = :order_id AND
                  status = " . Order::DESPATCHED_NUM . " AND warehouse_id $op :warehouse_id
            LIMIT 1", $params);
        if (!empty($res))
            return new Order($res);
        $res = (new SafeMysql())->fetchOne("
            SELECT *
            FROM orders
            WHERE supplier_id = :supplier_id AND
                  id = :order_id AND
                  status = " . Order::DESPATCHED_NUM . " AND warehouse_id $op :warehouse_id
            LIMIT 1", $params);
        if (!empty($res))
            return new Order($res);
        return null;
    }

    /**
     * @param $order_ref
     * @param $warehouse_id
     * @return Order
     */
    public static function getByIdOrReference($order_ref, $warehouse_id)
    {
        $params = ['order_ref' => $order_ref, 'warehouse_id' => (int)$warehouse_id];
        $sql = "SELECT *
                FROM orders
                WHERE ref = :order_ref AND warehouse_id = :warehouse_id LIMIT 1";
        $res = (new SafeMysql())->fetchOne($sql, $params);
        if (empty($res)) {
            $sql = "SELECT *
                FROM orders
                WHERE id = :order_ref AND warehouse_id = :warehouse_id LIMIT 1";
            $res = (new SafeMysql())->fetchOne($sql, $params);
            if (empty($res))
                return null;
        }
        return new Order($res);
    }

    public function getBySKUBarcode(string $barcode, array $order_sort_ids, int $warehouse_id)
    {
        $params = [
            'order_sort_ids' => array_map('intval', $order_sort_ids),
            'barcode' => $barcode,
            'warehouse_id' => $warehouse_id
        ];
        $sql = "SELECT orders.*
                FROM
                    orders LEFT JOIN order_SKUs o_SKU ON orders.id = o_SKU.order_id
                    LEFT JOIN SKU_barcodes ON o_SKU.SKU_id = SKU_barcodes.SKU_id
                WHERE
                orders.id IN (:order_sort_ids)
                AND SKU_barcodes.barcode = :barcode
                AND orders.warehouse_id = :warehouse_id
                ORDER BY FIELD(orders.id, :order_sort_ids)
                LIMIT 1";
        $res = (new SafeMysql())->fetchOne($sql, $params);
        if (empty($res))
            return null;

        return new Order($res);
    }

    public static function getOrdersForPacking($carriers, $locs, $priority = false, $mezzanine = null, $services = 0, ?array $statuses = null, $clients = [], bool $one_SKU = false, ?\DateTime $despatch_date = null)
    {
        $prioritySQL = [
            'row' => $priority ? ', o.priority' : '',
            'sort' => $priority ? ' o.priority = 0, o.priority ASC, ' : ''
        ];
        $mezzanineSQL = '';
        $where_client = '';
        $where_despatch_date = '';
        if (!is_null($mezzanine))
            $mezzanineSQL = ' AND l.mezzanine = ' . (int)(bool)$mezzanine;
        if (!count($locs))
            return [];
        $statuses = $statuses ?? Pack::getAllowedPackingStatuses();
        $allowed_statuses = implode(",", $statuses);
        $whereLocs = implode(',', array_map('intval', $locs));
        $whereCarriers = (!empty($carriers) && is_array($carriers)) ? ('AND o.carrier_id IN (' . implode(',', $carriers) . ')') : '';
        $shipping_services = 'JOIN shipping_services ss ON o.shipping_services_id = ss.id';
        $is_customer = false;
        $where_services = $where_shipping_services = '';
        if ($services != -1) {
            $service_ids = ShippingServices::getSelectedServicesIds($services);
            $where_shipping_services = 'AND o.shipping_services_id IN (' . implode(',', $service_ids) . ')';
            $pos = array_search(0, $service_ids);
            if ($pos !== false) {
                unset($service_ids[$pos]);
                $is_customer = true;
            }
            $where_services = empty($service_ids)
                ? $shipping_services
                : ("$shipping_services AND ss.id IN (" . implode(',', $service_ids) . ')');
        }
        if (empty($where_services))
            $where_services = "LEFT $shipping_services";
        if ($is_customer)
            $where_services = "LEFT $where_services";

        if (!empty($clients) && is_array($clients)) {
            $clientNews = array_map('intval', $clients);
            $filteredClients = array_filter($clientNews);

            if (!empty($filteredClients))
                $where_client = ' AND s.id IN (' . implode(',', $filteredClients) . ')';
        }
        $having = "";
        if ($one_SKU) {
            $having = "HAVING  SUM(os.quantity) = 1";
        }

        if ($despatch_date)
            $where_despatch_date = " AND o.despatch_date = '" . $despatch_date->format(WithDate::DATE_SIMPLE_FORMAT) . "'";


        $sql = "SELECT o.id, o.ref, s.id sid, s.name, o.despatch_date, c.code as cname, ss.name as shipping_service, o.shipping_services_id,
                        o.carrier_id, o.packing, ol.location_id,
                        CONCAT(IF(w.label = '' OR w.label IS NULL, l.warehouse, w.label), '-', l.type_id, '-', l.zone, '-', l.`column`, '-', l.row) location,
                        COUNT(os.SKU_id) SKUs, SUM(os.quantity) units, o.status,
                        oup.user_id as packing_user_id, s.order_inserts, IFNULL(ss.next_day, false) as next_day
                        {$prioritySQL['row']}, ss.max_weight
                FROM orders o
                    JOIN order_location ol ON o.id = ol.order_id AND ol.location_id IN ($whereLocs) 
                    JOIN supplier_users su ON su.user_id = o.user_id
                    JOIN suppliers s ON o.supplier_id = s.id
                    JOIN carriers c ON o.carrier_id = c.id
                    JOIN locations l ON ol.location_id = l.id $mezzanineSQL
                    JOIN warehouses w ON l.warehouse = w.id 
                    JOIN order_SKUs os ON o.id = os.order_id
                    LEFT JOIN orders_users_packing oup ON oup.order_id = o.id AND TIMESTAMPDIFF(MINUTE, FROM_UNIXTIME(oup.`time`), NOW()) <= " . Pack::PACKING_LOCK_TIME . "
                    $where_services
                WHERE o.status IN ($allowed_statuses) AND o.is_hash = 0 $whereCarriers $where_shipping_services $where_client $where_despatch_date
                GROUP BY o.id
                $having
                ORDER BY {$prioritySQL['sort']} o.despatch_date, ol.location_id, ol.order_id";
        $res = (new SafeMysql())->fetchAssocWithKey($sql, [], 'id');
        if (empty($res))
            return [];
        return $res;
    }

    public static function getPackingOrderById($id, $carriers)
    {
        if (empty($id))
            return null;
        $params = [
            'id' => (int)$id,
            'statuses' => Pack::getAllowedPackingStatuses(),
        ];
        $whereCarriers = '';
        if (!empty($carriers) && is_array($carriers)) {
            $whereCarriers = 'AND o.carrier_id IN (:carriers)';
            $params['carriers'] = $carriers;
        }
        $sql = "SELECT o.id, o.ref, o.ref2, o.user_id as order_user_id, o.type_create, s.id sid, s.name, o.despatch_date, c.code as cname, o.carrier_id,
                   o.packing, o.category, ol.location_id, o.currency, l.warehouse, l.type_id, l.zone, l.`column`, l.row, 
                   COUNT(os.SKU_id) SKUs, SUM(os.quantity) units, o.status, o.delivery_id,
                   o.shipping_services_id, s.order_inserts, o.shop_id, s.product_price, o.DDP shippingDDP, o.supplier_id, o.warehouse_id
            FROM orders o
                JOIN order_location ol ON o.id = ol.order_id
                JOIN suppliers s ON o.supplier_id = s.id
                JOIN carriers c ON o.carrier_id = c.id
                JOIN locations l ON ol.location_id = l.id
                JOIN order_SKUs os ON o.id = os.order_id
                LEFT JOIN channels_shipping_services css ON o.channels_shipping_services_id = css.id
            WHERE o.status IN (:statuses) AND o.id = :id $whereCarriers                     
            GROUP BY o.id
            ORDER BY o.despatch_date, o.id";
        $res = (new SafeMysql())->fetchOne($sql, $params);
        if (!empty($res))
            $res['DDP'] = Shippings::getTermsValue($res['shippingDDP']);
        return $res;
    }

    public static function getStatuses($warehouse_id, $tokens = [])
    {
        $sql = "SELECT DISTINCT o.status, (SELECT COUNT(*) FROM ASNs WHERE order_id = o.id) nums
                FROM orders o
                WHERE o.warehouse_id = :warehouse_id
                ORDER BY RPAD(status, 4, '0'), nums";
        $statuses = (new SafeMysql())->fetchAll($sql, ['warehouse_id' => (int)$warehouse_id]);
        $res = [];
        foreach ($statuses as $status)
            if (!$status['nums'])
                $res[$status['status']] = Order::getStateName($status['status'], $tokens);
            else
                $res['ret'] = TokensTranslations::getTranslate($tokens, 'despatched (returned)');
        if (count($res) > 1)
            $res = [TokensTranslations::getTranslate($tokens, 'all')] + $res;
        return $res;
    }

    public static function updateBin($warehouse_id)
    {
        $orders = self::getWaitingOB($warehouse_id);
        if (empty($orders))
            return;
        $warehouse = Warehouses::getById($warehouse_id);
        $OB = $warehouse->getOB();
        $size = $OB['cols'] * $OB['rows'];
        $zones = Locations::getZoneWithMostOrders($warehouse_id, $size, [$OB['nums'], empty($OB['nums_mez']) ? 0 : $OB['nums_mez']]);
        if (empty($zones))
            return;
        $num = [0, 0];
        $label = Warehouses::getLabel($warehouse_id);
        foreach ($orders as $order) {
            $orderOB = self::getById($order['id']);
            if (empty($orderOB))
                continue;
            if ($orderOB->waitingForLarge()) {
                self::changeStatus($orderOB->getId(), Order::AWAITING_LARGE_PICK, -1);
                // check if all SKUs are in one pallet on worker hands
                list(, $lefts) = SKULocations::getLeftByLargeOrder($warehouse_id, null, $orderOB->getShippingServicesId(), $orderOB->getSupplierId());
                if (empty($lefts))
                    continue;
                $locs = [];
                foreach ($lefts as $index => $orders)
                    if (!empty($orders[$orderOB->getId()])) {
                        $parts = explode('_', $index);
                        $loc_id = array_shift($parts);
                        $SKU_id = array_shift($parts);
                        $batch_id = array_shift($parts);
                        if ($batch_id == '.')
                            $batch_id = null;
                        $locs[$loc_id][] = [
                            'SKU_id' => $SKU_id,
                            'batch_id' => $batch_id,
                            'quantity' => $orders[$orderOB->getId()],
                            'size' => 1,
                            'total_quantity' => $orders[$orderOB->getId()],
                        ];

                    }
                // don't allow picking from multi pallets...
                if (empty($locs))
                    continue;
                $picked = true;
                $table = null;
                foreach ($locs as $loc_id => $SKU_infos) {
                    $loc = Locations::getById($loc_id);
                    // is pallet in hands
                    if (!$loc->isDetached()) {
                        $picked = false;
                        continue;
                    }
                    $user = Cart::getCartUser($loc->getId(), $warehouse_id);
                    if (empty($user)) {
                        $table = SectionBindings::getBinding($loc->getId());
                        if (!$table || $table['type_id'] != SectionBinding::TYPE_BIND_LARGE_TABLE) {
                            $picked = false;
                            continue;
                        }
                        $user = Users::system();
                    }
                    $res = Pick::pickSKUsFromLoc($user, $warehouse, $loc, $loc, 'large', null, $orderOB->getShippingServicesId(), $SKU_infos);
                    $picked = $picked && $res['result'];
                }
                // if all SKUs are picked, change status
                if ($picked !== false)
                    self::changeStatus($orderOB->getId(), Order::LARGE_PICKING, -1);
                // auto drop...
                if ($table && $table['type_id'] == SectionBinding::TYPE_BIND_LARGE_TABLE) {
                    $large_table = Locations::getByCoords($warehouse->getId(), Location::getLocationName(
                        $warehouse->getLabel() ?: $warehouse->getId(),
                        Location::LP,
                        Location::LP,
                        Location::LP,
                        $table['num']
                    ));
                    Drop::dropSKUs($warehouse, 'large', $loc, $user, $large_table->getId(), $orderOB->getId());
                }
            } elseif ($orderOB->waitingForSingle())
                self::changeStatus($orderOB->getId(), Order::AWAITING_SINGLE_PICK, -1);
            elseif ($orderOB->waitingForOrderBin()) {
                $mezzanine = SKULocationsM::getMezzanine($order['id'], $warehouse_id);
                if (empty($zones[$mezzanine]) || empty($zones[$mezzanine][$num[$mezzanine]]))
                    continue;
                if ($zones[$mezzanine][$num[$mezzanine]][1] == 0) {
                    $num[$mezzanine]++;
                    if ($num[$mezzanine] == count($zones[$mezzanine]))
                        continue;
                }
                $location_id = Locations::getFreeByZone($warehouse_id, $size, $zones[$mezzanine][$num[$mezzanine]][0] + ($mezzanine ? $OB['nums'] : 0), $mezzanine);
                if (empty($location_id))
                    continue;
                OrderLocations::reserveLocation($orderOB->getId(), $location_id);
                $zones[$mezzanine][$num[$mezzanine]][1]--;
                self::changeStatus($orderOB->getId(), Order::AWAITING_PICK, -1);
                $location_obj = Locations::getById($location_id);
                $order_history = new OrderHistory();
                $order_history->setUserId(-1);
                $order_history->setCreateDate(time());
                $order_history->setOrderId($orderOB->getId());
                $order_history->setComment("Order bin {$location_obj->getLocation($label)} reserved" . ($orderOB->isPriority() ? ' (priority)' : ''));
                $order_history->save(['order_id', 'create_date', 'user_id', 'comment']);
            }
        }
    }

    public static function getByLocation(int $location_id)
    {
        return (new SafeMysql())->fetchOne("
            SELECT o.*
            FROM orders o
                JOIN order_location ol ON o.id = ol.order_id AND ol.location_id = :location_id
            LIMIT 1", ['location_id' => $location_id]);
    }

    public static function hasReturnASNs($order_id): bool
    {
        return !empty((new SafeMysql())->fetchScalar('SELECT 1 FROM ASNs WHERE order_id = :order_id', ['order_id' => (int)$order_id]));
    }

    /**
     * Get package info with SKU list by order_id
     * Needed for "printing documents" orders
     * @param $order_id
     * @return array
     */
    public static function getPackages($order_id)
    {
        $res = (new SafeMysql())->fetchAll("
            SELECT op.id,
                   op.package_name as name,
                   op.package_type_id,
                   op.length,
                   op.width,
                   op.height,
                   op.weight,
                   op.order_inserts,
                   op.category,
                   sh.id as shipment_id
            FROM orders_packages op
                LEFT JOIN order_packages_shipping opsh ON op.id = opsh.order_package_id
                LEFT JOIN shipping sh ON sh.id = opsh.shipping_id AND sh.status = 4
            WHERE op.is_cancel = 0 AND op.order_id = :order_id
            ORDER BY op.id, sh.status DESC", ['order_id' => (int)$order_id]);
        $packages = [];
        if (empty($res))
            return $packages;
        foreach ($res as $item) {
            if (empty($packages[$item['id']])) {
                $item['skus'] = self::getSKUsForPackage($item['id']);
                $packages[$item['id']] = $item;
            }
        }
        return $packages;
    }

    public static function getSKUsForPackage($order_package_id): array
    {
        $items = (new SafeMysql())->fetchAll("
            SELECT DISTINCT s.id, s.name, ops.quantity, s.weight, s.width, s.height, s.depth, s.hs_code,
                   s.market_value as `value`, os.price, s.country_id, s.serial_number, s.DDS, s.exemption_TARIC
            FROM SKUs s
                JOIN orders_packages_SKUs ops ON s.id = ops.SKU_id AND ops.orders_packages_id = :order_package_id
                JOIN orders_packages op ON ops.orders_packages_id = op.id
                JOIN order_SKUs os ON op.order_id = os.order_id AND ops.SKU_id = os.SKU_id",
            ['order_package_id' => (int)$order_package_id]);
        SKUBarcodes::addBarcodesToArray($items);
        return $items;
    }

    public static function setOrderPackageShipping($order_package_id, $shipment_id)
    {
        $order_package_id = (int)$order_package_id;
        $shipment_id = (int)$shipment_id;
        if ($order_package_id > 0 && $shipment_id > 0)
            return (new SafeMysql())->execute("INSERT INTO order_packages_shipping (order_package_id, shipping_id) VALUES (:op_id, :ship_id)", [
                'op_id' => $order_package_id,
                'ship_id' => $shipment_id,
            ]);
        return false;
    }

    public static function setOrderPackageReturns($order_package_id, $return_id)
    {
        $order_package_id = (int)$order_package_id;
        $return_id = (int)$return_id;
        if ($order_package_id > 0 && $return_id > 0)
            return (new SafeMysql())->execute("INSERT INTO order_packages_returns (order_package_id, return_id) VALUES (:op_id, :ret_id)", [
                'op_id' => $order_package_id,
                'ret_id' => $return_id,
            ]);
        return false;
    }

    /**
     * Building a FULL TEXT indexed search table for orders with data from:
     * 1. Orders (reference)
     * 2. Delivery (name, contact, phone, email, address1, address2, address3, town, county, postcode
     * 3. SKU (name, barcode)
     */
    public static function handleSearchTable()
    {
        $orders_to_update = self::getOrdersForSearchUpdate();
        if (empty($orders_to_update))
            return;
        foreach ($orders_to_update as $order)
            self::setOrderDataToSearch($order);
    }

    public static function updateSearchTableByOrderId($order_id) // FOR QUEUE
    {
        $orders_to_update = self::getOrdersForSearchUpdate($order_id);
        $order_to_update = null;
        if (empty($orders_to_update))
            return ['error' => 'Not found order #' . $order_id];
        if (count($orders_to_update) > 1) {
            $order_to_update = $orders_to_update[0];
            $order_to_update['tracking_number'] = '';
            foreach ($orders_to_update as $order) {
                $order_to_update['tracking_number'] .= ' ' . $order['tracking_number'];
            }
        } else
            $order_to_update = $orders_to_update[0];
        self::setOrderDataToSearch($order_to_update);
        return ['success' => 'Global search updated for order #' . $order_id];
    }

    public static function getOrdersForSearchUpdate($order_id = null)
    {
        $sql = "SELECT o.id, o.ref, o.ref2, o.delivery_id, s.tracking_number
                FROM orders o
                    LEFT JOIN orders_packages op ON o.id = op.order_id AND op.is_cancel = 0
                    LEFT JOIN order_packages_shipping ops ON ops.order_package_id = op.id
                    LEFT JOIN shipping s ON s.id = ops.shipping_id AND s.status = 4
                WHERE o.id ";
        $mysql = new SafeMysql();
        if (is_null($order_id)) {
            $max_id = $mysql->fetchScalar("SELECT MAX(id) FROM orders_search") ?? 0;
            return $mysql->fetchAll($sql . '> :max_id LIMIT 1000', ['max_id' => (int)$max_id]);
        } else
            return $mysql->fetchAll($sql . '= :order_id', ['order_id' => (int)$order_id]);
    }

    public static function setOrderDataToSearch($order)
    {
        $delivery_info = Deliveries::getById($order['delivery_id']);
        $delivery_string = $delivery_info ? implode(" ", [
            $delivery_info->getName(),
            $delivery_info->getPhone(),
            $delivery_info->getContact(),
            $delivery_info->getEmail(),
            $delivery_info->getAddress1(),
            $delivery_info->getAddress2(),
            $delivery_info->getAddress3(),
            $delivery_info->getTown(),
            $delivery_info->getCounty(),
            $delivery_info->getPostcode()
        ]) : '';
        $skus_info = (new SafeMysql())->fetchAll("SELECT s.name, s.id FROM SKUs s
              JOIN order_SKUs os ON s.id = os.SKU_id
              WHERE os.order_id = :order_id", ['order_id' => (int)$order['id']]);
        SKUBarcodes::addBarcodesToArray($skus_info);
        $skus_string = $skus_info ? array_reduce($skus_info, function ($sku_str, $sku) {
            $sku_str .= implode(" ", [
                    $sku['name'], $sku['barcode'],
                ]) . ' ';
            return $sku_str;
        }, '') : '';
        $search_data = implode(" ", array_filter([
            $delivery_string,
            $skus_string,
            $order['ref'],
            $order['ref2'],
            $order['tracking_number'],
            $order['id']
        ], function ($item) {
            return !empty($item);
        }));
        $search_data = SafeMysql::escapeMatch($search_data);
        $mysql = new SafeMysql();
        $order_search_id = $mysql->fetchScalar("SELECT id FROM orders_search WHERE order_id = :order_id", ['order_id' => (int)$order['id']]);
        if (!empty($order_search_id)) {
            $mysql->execute("UPDATE orders_search SET data = :data WHERE id = :id", [
                'data' => $search_data,
                'id' => (int)$order_search_id,
            ]);
        } else
            $mysql->execute("INSERT INTO orders_search (order_id, data) VALUES (:order_id, :data)", [
                'order_id' => $order['id'],
                'data' => $search_data,
            ]);
    }

    public static function setPriority(Order $order, $user_id, $comment = null)
    {
        $order->setPriority();
        $order->save(['id', 'priority']);
        OrderHistories::changePriority($order->getId(), $user_id, $comment);
    }

    public static function changeStatus($order_id, $status, $user_id, $comment = null, $time = null, $spec_valid = true)
    {
        $order = self::getById($order_id);
        if (empty($order)) {
            Debug::sendDebugInfo('changeStatus', "Order #$order_id is not found", Debug::TEST);
            return;
        }
        $old_status = $order->getStatusName();
        if ($old_status === $status)
            return;
        if ($spec_valid && Order::isQuery($old_status) && Order::isAwaitingPack($status))
            return;
        $order->setStatusName($status);
        $order->save(['id', 'status']);
        Queue::addJobToWebhook(['webhook' => 'orderStatusChange', 'order_id' => $order_id, 'additional' => [$old_status, $status]]);
        OrderHistories::changeStatusOrder($order_id, $status, $user_id, $comment, $time);
    }

    public static function fixedOrder3249($warehouse_id, $order_id, $status = Order::PICKING_NUM)
    {
        $warehouse_id = (int)$warehouse_id;
        $order_id = (int)$order_id;
        $status = (int)$status;
        $num = (new SafeMysql())->fetchScalar("SELECT COUNT(*)
                                  FROM order_location
                                      JOIN orders ON id = order_id AND status = :status
                                  WHERE order_id = :order_id",
            ['status' => $status, 'order_id' => $order_id]);
        if ($num) {
            echo "Order with location ($num)!\n";
            return;
        }
        $OB = Warehouses::getById($warehouse_id)->getOB();
        $size = $OB['cols'] * $OB['rows'];
        $zones = Locations::getZoneWithMostOrders($warehouse_id, $size, [$OB['nums'], empty($OB['nums_mez']) ? 0 : $OB['nums_mez']]);
        $num = [0, 0];
        $orderOB = self::getById($order_id);
        $mezzanine = SKULocationsM::getMezzanine($order_id, $warehouse_id);
        if (empty($zones[$mezzanine])) {
            Debug::sendDebugInfo('No free locations' . ($mezzanine ? ' in mezzanine' : ''), var_export([$warehouse_id, $order_id, $status], 1), Debug::TEST);
            echo "Not free locations!\n";
            return;
        }
        $location_id = Locations::getFreeByZone($warehouse_id, $size, $zones[$mezzanine][$num[$mezzanine]][0] + ($mezzanine ? $OB['nums'] : 0), $mezzanine);
        if (empty($location_id)) {
            Debug::sendDebugInfo('No free locations' . ($mezzanine ? ' in mezzanine' : ''), var_export([$warehouse_id, $order_id, $status], 1), Debug::TEST);
            echo "Not free locations!\n";
            return;
        }
        OrderLocations::reserveLocation($orderOB->getId(), $location_id);
        self::changeStatus($orderOB->getId(), Order::AWAITING_PICK, -1);
        echo "Ok\n";
    }

    public static function getOrdersByDelivery($delivery_id, $exclude_order = 0)
    {
        $params = ['delivery_id' => (int)$delivery_id];
        $whereOrder = '';
        if (!empty($exclude_order)) {
            $whereOrder = 'id <> :exclude_order AND';
            $params['exclude_order'] = (int)$exclude_order;
        }
        $sql = "SELECT id
                FROM orders
                WHERE $whereOrder delivery_id = :delivery_id";
        return (new SafeMysql())->fetchArrayOneField($sql, $params);
    }

    public static function saveOriginalDespatched($order_id, $items, $delivery, $user_id)
    {
        (new SafeMysql())->execute("
            INSERT INTO orders_original_despatched (order_id, items, delivery, user_id)
            VALUES (:order_id, :items, :delivery, :user_id)", [
            'order_id' => $order_id,
            'items' => json_encode($items),
            'delivery' => json_encode($delivery),
            'user_id' => $user_id,
        ]);
    }

    public static function clearShippingService($order_id, int $user_id)
    {
        $shipping_service_id = Shipping::COLLECTION_BY_CUSTOMER_ID;
        $order = self::getById($order_id);
        $old_shipping_service = ShippingServices::getById($order->getShippingServicesId())->getCode();
        $order->setShippingServicesId($shipping_service_id);
        $order->save(['id', 'shipping_services_id']);
        OrderHistories::updateShippingServiceId((int)$order->getId(), $old_shipping_service, Shipping::COLLECTION_BY_CUSTOMER, $user_id);
        return $shipping_service_id;
    }

    public static function bulkCancel($user_job_id)
    {
        /** @var UserJob $user_job */
        $user_job = UserJobs::getById($user_job_id);
        if (empty($user_job))
            return ['error' => 'User queue not found'];
        if (in_array($user_job->getStatus(), [UserJob::STATUS_DONE, UserJob::STATUS_ERROR, UserJob::STATUS_ABORT]))
            return ['success' => 'User queue stopped'];
        $addition = json_decode($user_job->getAddition(), true);
        if (empty($addition) || empty($addition['order_ids'])) {
            $user_job->setStatus(UserJob::STATUS_ERROR);
            $user_job->setUpdateDate(time());
            $user_job->save(['id', 'status', 'update_date']);
            return ['error' => 'Orders are not found'];
        }
        $addition['progress'] = 0;
        $num = count($addition['order_ids']);
        while (!empty($addition['order_ids'])) {
            $order_id = array_pop($addition['order_ids']);
            $user_job->setStatus(UserJob::STATUS_PROGRESS);
            $user_job->setUpdateDate(time());
            $user_job->save(['id', 'status', 'update_date']);
            $order = self::getById($order_id);
            if (empty($order))
                UserJobNotifications::addNewDanger($user_job->getId(), "Order #$order_id is not found");
            else {
                $res = OrdersModel::cancelOrder($order, $user_job->getUserId());
                if (!$res[0])
                    UserJobNotifications::addNewWarning($user_job->getId(), "Order #{$order_id} cannot be cancelled. {$res[1]}!");
                else
                    UserJobNotifications::addNewSuccess($user_job->getId(), "Order #{$order_id} cancelled" . (!empty($res[1]) ? ". {$res[1]}" : ''));
            }
            $addition['progress'] = round(($num - count($addition['order_ids'])) * 100 / $num, 0);
            $user_job->setAddition(json_encode($addition));
            $user_job->setUpdateDate(time());
            $user_job->save(['id', 'update_date', 'addition']);
        }
        $user_job->setUpdateDate(time());
        unset($addition['order_ids']);
        $user_job->setAddition(json_encode($addition));
        $user_job->setStatus(UserJob::STATUS_DONE);
        $user_job->save(['id', 'status', 'update_date', 'addition']);
        return ['success' => 'All orders has cancelled'];
    }

    public static function getShortShipsOrders(Order $order): array
    {
        $sql = "
            SELECT *
            FROM orders
            WHERE create_date = :create_date AND external_id = :external_id AND type_create = :type_create";
        $orders = [];
        $bind = ['create_date' => $order->getCreateDate(), 'external_id' => $order->getExternalId(), 'type_create' => Sources::SOURCE_SHORT_SHIP];
        foreach ((new SafeMysql())->fetchAll($sql, $bind) as $data)
            $orders[] = new Order($data);
        return $orders;
    }

    public static function getByIds(array $ids): array
    {
        if (!$ids)
            return [];
        $ids = array_map('intval', $ids);
        $sql = "SELECT * FROM orders WHERE id IN (:ids);";
        $result = [];
        foreach ((new SafeMysql())->fetchAll($sql, ['ids' => $ids]) as $item)
            $result[(int)$item['id']] = new Order($item);
        return $result;
    }

    public static function createShipping(int $user_id, $shipping_func, $supplier, $consignee, $warehouse, array &$order, array &$packages, $shipping_class)
    {
        //If there are more than 1 package in order then we create additional references for each package
        //because each shipment reference must be unique
        Pack::addOrderReference($packages, $order['ref'], $shipping_class);
        //Create shipping labels for each package (box) if one of shipping services was used
        if (!Shippings::checkServiceIdExists($order['shipping_services_id'], $order['warehouse_id']))
            $order['shipping_services_id'] = Orders::clearShippingService($order['id'], $user_id);
        
        $statuses = [];
        foreach ($packages as $package_id => &$package) {
            $statuses[$package_id] = $new_status = $order['status'];
            if (!empty($order['shipping_services_id'])) {
                /** @var Shipping $shipping */
                $shipping = null;
                if (empty($package['shipment_id'])) {
                    $new_status = Order::PRINTING_DOCUMENTS_NUM;
                    $shipping = $shipping_func['createShipment']($order, $supplier, $consignee->toArray(), $package, $user_id);
                    if (empty($shipping))
                        $new_status = Order::PRINTING_DOCUMENTS_ISSUE_NUM;
                    else {
                        Orders::setOrderPackageShipping($package_id, $package['shipment_id'] = $shipping->getId());
                        if (!Shipping::isConfirmed($shipping->getStatus()))
                            $new_status = Order::PRINTING_DOCUMENTS_ISSUE_NUM;
                    }
                } elseif (!Order::isPrintingDocumentsIssue($order['status']))
                    $shipping = Shippings::getById($package['shipment_id']);
                if ($shipping)
                    $shipping->setCreateDate((new TimeZonesConvertor())->setDateByString($shipping->getCreateDate())->convert($warehouse->getTimezone())->getConvertedDateString());
                $package['shipping'] = $shipping ? $shipping->toArray() : [];
            }
            if (!empty($return_carrier)) {
                $returns = null;
                if (empty($package['return_id'])) {
                    $returns = ReturnSAPI::createReturns($order, $consignee->toArray(), $return_carrier, $warehouse, $package, $user_id);
                    if (empty($returns))
                        $new_status = Order::PRINTING_DOCUMENTS_ISSUE_NUM;
                    else {
                        Orders::setOrderPackageReturns($package_id, $returns->getId());
                        if (!$returns->isNew())
                            $new_status = Order::PRINTING_DOCUMENTS_ISSUE_NUM;
                    }
                } elseif (!Order::isPrintingDocumentsIssue($order['status']))
                    $returns = Returns::getById($package['return_id']);
                $package['returns'] = $returns ? $returns->toArray() : [];
            }
            $statuses[$package_id] = $new_status;
        } unset($package);
        $new_status = Order::PRINTING_DOCUMENTS_NUM;
        foreach ($statuses as $status)
            if (Order::isPrintingDocumentsIssue($status)) {
                $new_status = Order::PRINTING_DOCUMENTS_ISSUE_NUM;
                break;
            }
        if ($new_status != $order['status'])
            Orders::changeStatus($order['id'], Order::getStateName($order['status'] = $new_status), $user_id);
    }

    public static function getFileDataOrderDocFromSFTP(string $order_ref, int $supplier_id): array
    {
        $sftp = SFTPExports::getBySupplierId($supplier_id);
        if (!$sftp)
            return [];
        $clear_login = FTPUsers::clearLogin($sftp->getLogin(), $supplier_id);
        $file_sftp = null;
        $folder_with_files = SFTP_STORAGE. DIRECTORY_SEPARATOR . $clear_login . DIRECTORY_SEPARATOR . FTPUsers::DIR_UPLOAD . DIRECTORY_SEPARATOR . 'UploadDocuments' . DIRECTORY_SEPARATOR;
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($folder_with_files)));
        $files = $file_wrapper->glob($folder_with_files, '', "_$order_ref.pdf");
        foreach ($files as $file_path) {
            preg_match('/.+\/(.+)_' . preg_quote($order_ref) . '.pdf$/', $file_path, $match);
            $file_sftp = new File();
            $file_sftp->setError(0);
            $file_sftp->setType('application/pdf');
            $file_sftp->setName(($match[1] ?? 'doc') . '.pdf');
            $file_sftp->setTmpName($file_path);
            $file_sftp->setSize($file_wrapper->getFileSizeBlob($file_path));
            $file_sftp->setReceivingType(File::RECEIVING_TYPE_FILE_SYSTEM);
            break;
        }
        return $file_sftp ? $file_sftp->toArray() : [];
    }

    public static function reconciliationSKUInOrder(array $data, int $loc_to, int $user_id): string
    {
        $is_update = SKULocations::removeOrdersSKUsForCounting($data['location_id'], $data['SKU_id'], (int)$data['batch_id'], $data['type'], $data['reason'], (int)$data['order_id'], $data['old_quantity'], $data['del_quantity']);
        if (!$is_update)
            return 'The quantity has changed. Please try again.';
        SKULocations::addSKUsToDHForCounting($loc_to, $data['SKU_id'], $data['del_quantity']);
        OrderHistories::removeSKUsByReconciliation($data['real_order_id'], $data['SKU_id'], $data['batch'], $data['del_quantity'], $user_id, $data['loc_name']);
        return '';
    }

    public static function clearOrderTables(int $order_id, int $user_id): void
    {
        OrderLocations::cancelOrder($order_id, $user_id);
        OrdersPackagesSKUs::deleteByOrderId($order_id);
        OrderPackages::deleteByOrderId($order_id);
    }
}
