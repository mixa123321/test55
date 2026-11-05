<?php

namespace app\core\models;

use app\backend\models\BarCodes;
use app\backend\models\SKUBarcodes;
use app\core\channels\magento\MagentoConnector;
use app\core\channels\shopify\ShopifyConnector;
use app\core\channels\woocommerce\WooCommerceConnector;
use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\entities\Order;
use app\core\entities\SKU;
use app\backend\models\Locations;
use app\core\entities\SKULocation;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\Filter;
use FPDF;

class SKUs
{
    const LIMIT_PER_PAGE = 10;
    const TABLE_NAME = 'SKUs';
    const DEFAULT_BARCODE_FILTER = Filter::TYPE__STARTS_WITH;

    public static function colorStatus($status)
    {
        switch ($status) {
            case 1:
                return 'success';
            case 4:
                return 'primary';
            default:
                return 'danger';
        }
    }

    private static function getLocation($warehouse_id, $type_id)
    {
        $warehouse_id = (int)$warehouse_id;
        return "IFNULL((SELECT SUM(quantity)
                        FROM SKU_locations
                            JOIN locations ON location_id = id AND type_id = '$type_id' AND warehouse = $warehouse_id
                        WHERE SKU_id = s.id), 0) $type_id";
    }

    public static function getSKUs($warehouse_id, $current, $search, $sid, $measure, $status, $unassigned, $present_in_orders, $warehouses_virtual)
    {
        if (is_array($sid) && empty($sid))
            return [[], 0];
        $warehouse_id = (int)$warehouse_id;
        if (trim($search) !== '') {
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode($search, self::DEFAULT_BARCODE_FILTER);
            $search = $SKU_ids ? " AND s.id IN (" . implode(', ', $SKU_ids) . ")" : " AND FALSE";
        }
        if ($sid) {
            $search .= ' AND sup.id ';
            if (is_array($sid))
                $search .= 'IN (' . implode(',', array_map('intval', $sid)) . ')';
            else
                $search .= "= $sid";
        }
        if ($measure) {
            if ($measure == 1)
                $search .= ' AND (s.width*s.height*s.depth*s.weight) > 0 AND (s.max_capacity IS NOT NULL OR s.max_capacity <> \'\')';
            if ($measure == 2)
                $search .= ' AND ((s.width*s.height*s.depth*s.weight) = 0 OR (s.width*s.height*s.depth*s.weight) IS NULL) AND (s.max_capacity IS NULL OR s.max_capacity = \'\')';
        }
        if ($status > 0)
            $search .= ' AND s.state = ' . ($status - 1);
        $unassignedDB = '';
        if ($unassigned) {
            $unassignedDB = ", (SELECT id, IFNULL((SELECT COUNT(*) FROM SKU_preff_locs, locations subl WHERE SKU_id = s0.id AND location_id = subl.id AND subl.warehouse = $warehouse_id GROUP BY SKU_id), 0) qty FROM SKUs s0) unassigned";
            $search .= ' AND unassigned.id = s.id AND unassigned.qty = 0';
        }
        $from_order_skus = '';
        if($present_in_orders) {
            $from_order_skus = " , order_SKUs os, orders o ";
            $search .= " AND s.id = os.SKU_id AND os.order_id = o.id AND o.warehouse_id = $warehouse_id ";
        }
        $sql = "SELECT s.id, s.name,
                       sup.id sid, sup.name sname,
                       IFNULL((SELECT SUM(quantity) FROM SKU_locations, locations subl2 
                            WHERE SKU_id = s.id AND location_id = subl2.id 
                                                AND subl2.warehouse = {$warehouse_id}), 0) available, 
                       IFNULL(s.width * s.height * s.depth * s.weight, 0) measure,
                       s.max_capacity,
                       s.state
                FROM SKUs s, suppliers sup {$unassignedDB} {$from_order_skus}
                WHERE sup.id = s.supplier_id {$search}
                GROUP BY s.id
                ORDER BY s.id LIMIT " . RECORD_PAGE . " OFFSET " . (RECORD_PAGE * ($current - 1));
        $res = (new SafeMysql())->fetchAll($sql);
        SKUBarcodes::addBarcodesToArray($res);
        if (empty($res))
            return [[], 0];
        $virtual_field = '';
        $whereLocations = '';
        $groupby = 'type_id';
        if (!empty($warehouses_virtual)) {
            $location_types = $warehouses_virtual;
            $virtual_field = 'row';
            $whereLocations = "= '" . Location::ST . "' AND row";
            $groupby = 'row';
        } else
            $location_types = Location::ALLOWED_TYPES;
        foreach ($res as $key => $item) {
            $SKU_id = (int)$item['id'];
            $loc_data = array_fill_keys($location_types, 0);
            $loc_quantities = (new SafeMysql())->fetchAll("SELECT $virtual_field type_id, SUM(quantity) as quantity
                        FROM SKU_locations
                            JOIN locations ON location_id = id AND type_id $whereLocations IN (:location_types) AND warehouse = :warehouse_id
                        WHERE SKU_id = :SKU_id GROUP BY $groupby", [
                'location_types' => $location_types,
                'warehouse_id' => $warehouse_id,
                'SKU_id' => $SKU_id,
            ]);
            if (!empty($loc_quantities))
                array_walk($loc_quantities, function($loc) use (&$loc_data) {
                    $loc_data[$loc['type_id']] += $loc['quantity'];
                });
            $res[$key] += $loc_data;
        }
        $resNum = (new SafeMysql())->fetchScalar("SELECT count(1) FROM (SELECT count(1) FROM SKUs s, suppliers sup {$unassignedDB} {$from_order_skus}
                                                WHERE sup.id = s.supplier_id {$search} GROUP BY s.id)t");
        return [$res, (int)$resNum];
    }

    public static function getAll($warehouse_id, $status = null)
    {
        ini_set('memory_limit', '512M');

        $warehouse_id = (int) $warehouse_id;
        $where = empty($status) ? '' : "WHERE s.state = $status";
        $sql = "SELECT s.id, s.name, s.photo, s.state, s.supplier_id, sup.warehouses
                FROM SKUs s
                    JOIN suppliers sup ON s.supplier_id = sup.id
                $where
                ORDER BY s.id";
        $SKUs = (new SafeMysql())->fetchAll($sql);
        SKUBarcodes::addBarcodesToArray($SKUs);
        foreach ($SKUs as $key => $SKU) {
            $warehouses = json_decode($SKU['warehouses']);
            if (empty($warehouses) || !in_array($warehouse_id, $warehouses))
                unset($SKUs[$key]);
            unset($SKUs[$key]['warehouses']);
        }
        return $SKUs;
    }

    public static function getByIdAndWarehouse($id, $warehouse_id): ?SKU
    {
        $id = (int)$id;
        $warehouse_id = (int)$warehouse_id;
        $SKU = (new SafeMysql())->fetchOne("SELECT s.*, sup.warehouses
                FROM SKUs s
                    JOIN suppliers sup ON s.supplier_id = sup.id
                    WHERE s.id = :id LIMIT 1", ['id' => $id]);
        if (empty($SKU))
            return null;
        $warehouses = json_decode($SKU['warehouses']);
        if (empty($warehouses) || !in_array($warehouse_id, $warehouses))
            return null;
        unset($SKU['warehouses']);
        return new SKU($SKU);

    }

    public static function getBySupplier($id, $state = null, $ord = 2)
    {
        $state_sql = '';
        $state_params = [];
        if (!is_null($state)) {
            if (is_array($state) && !empty($state)) {
                $state_sql = ' AND state IN (:states)';
                $state_params['states'] = array_map('intval', $state);
            }
            if (is_string($state) || is_int($state)) {
                $state_sql = ' AND state = :state';
                $state_params['state'] = (int)$state;
            }
        }
        $sql = "SELECT * FROM SKUs WHERE supplier_id = :supplier_id" . $state_sql . " ORDER BY " . (int)$ord;
        $result = (new SafeMysql())->fetchAll($sql, array_merge(['supplier_id' => (int)$id], $state_params));
        SKUBarcodes::addBarcodesToArray($result);
        return $result;
    }

    public static function getByUserIdBarcodeKeys($id, $status = null): array
    {
        $id = (int) $id;
        $status = (int) $status;
        $sql = "SELECT s.id, s.state
                FROM SKUs s
                    JOIN supplier_users su ON s.supplier_id = su.supplier_id AND su.user_id = :id" .
                (is_null($status) ? " WHERE state = :status" : '');
        $items = (new SafeMysql())->fetchAll($sql, ['id' => $id, 'status' => $status]);
        SKUBarcodes::addBarcodesToArray($items);
        return $items;
    }

    public static function getLeftBySupplier($warehouse_id, $supplier_id, $state = SKU::ACTIVE_NUM)
    {
        $warehouse_id = (int) $warehouse_id;
        $wheres = [];
        if (is_array($supplier_id))
            $wheres[] = 's.supplier_id IN (' . implode(',', array_map('intval', $supplier_id)) . ')';
        else if (!empty($supplier_id))
            $wheres[] = 's.supplier_id = ' . (int)$supplier_id;

        $order_cancel = Locations::getByCancelLocation($warehouse_id);
        $whereCancel = '';
        if (!empty($order_cancel) && count($order_cancel) == 1)
            $whereCancel = 'AND location_id <> ' . $order_cancel[0]->getId();

        if (is_array($state))
            $wheres[] = 's.state IN (' . implode(',', array_map('intval', $state)) . ')';
        elseif ($state !== 'all')
            $wheres[] = 's.state = ' . (int) $state;
        $where = empty($wheres) ? '' : 'WHERE ' . implode(' AND ', $wheres);

        $sql = "SELECT s.id,
                       s.name,
                       s.customer_ref,
                       s.state,
                       s.market_value,
                       s.supplier_id,
                       s.photo,
                       (SELECT IFNULL(SUM(quantity), 0)
                        FROM SKU_locations
                            JOIN locations ON location_id = id AND
                                              warehouse = :warehouse_id AND
                                              type_id NOT IN (:not_counted_types) $whereCancel
                        WHERE SKU_id = s.id AND order_id IS NULL AND type = 1 AND reason = '2') `left`
                FROM SKUs s
                $where
                ORDER BY 1";
        $result = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'not_counted_types' => Location::NOT_COUNTED_TYPES,
        ]);
        SKUBarcodes::addBarcodesToArray($result);
        return $result;
    }

    public static function getLeftASN($supplier_id, $SKU_ids)
    {
        if (!is_array($SKU_ids))
            return [];
        $sql = "SELECT id, name, 0, photo, 'U'
                FROM SKUs
                WHERE supplier_id = :supplier_id AND id NOT IN (:SKU_ids)
                ORDER BY id";
        $result = (new SafeMysql())->fetchAll($sql, [
            'supplier_id' => $supplier_id,
            'SKU_ids' => $SKU_ids,
        ]);
        SKUBarcodes::addBarcodesToArray($result);
        return $result;
    }

    public static function getAvailableTotalByFilterV2(int $warehouse_id, array $filters, ?array $filters_SKU_ids): int
    {
        if ($filters_SKU_ids === null)
            return 0;
        $forIntegrations = self::getJoinForIntegrations($warehouse_id, $filters['product_id'] ?? null, $filters['variant_id'] ?? null);
        $params = $where = [];
        if ($filters_SKU_ids) {
            $where[] = "s.id IN (:ids)";
            $params['ids'] = $filters_SKU_ids;
        }
        SKULocations::setFiltersForAvailable($filters, $params, $where, 's', true);
        $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT count(*) FROM SKUs s $forIntegrations $where_sql";
        $safeParams = [];
        foreach ($params as $k => $v)
            $safeParams[ltrim($k, ':')] = $v;
        return (int)(new SafeMysql())->fetchScalar($sql, $safeParams);
    }

    public static function getAvailableByFilterV2(int $warehouse_id, $filters, ?array $filters_SKU_ids, int $offset, int $limit, string $SKUs_fields = '')
    {
        if ($filters_SKU_ids === null)
            return [];
        $params = $where = [];
        if ($filters_SKU_ids) {
            $where[] = "s.id IN (:ids)";
            $params['ids'] = $filters_SKU_ids;
        }
        SKULocations::setFiltersForAvailable($filters, $params, $where, 's', true);
        $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
        $allowed = ["s.*, s.id AS item_id, s.depth AS length", 's.id, s.name, s.unique_customer_ref, s.state'];
        $SKUs_f = in_array($SKUs_fields, $allowed) ? $SKUs_fields : "s.*, s.depth AS length";
        $forIntegrations = self::getJoinForIntegrations($warehouse_id, $filters['product_id'] ?? null, $filters['variant_id'] ?? null);
        $query = new QueryBuilder();
        $query->setSelectFrom("SELECT DISTINCT $SKUs_f
                                      FROM SKUs s
                                          $forIntegrations
                                          JOIN suppliers sup ON s.supplier_id = sup.id
                                          JOIN users u ON sup.user_id = u.id
                                      $where_sql");
        $query->setLimit("{$offset}, " . $limit);
        $query->setOrderBy('s.id');
        $safeParams = [];
        foreach ($params as $k => $v)
            $safeParams[ltrim($k, ':')] = $v;
        $records_raw = (new SafeMysql())->fetchAll($query->build(), $safeParams);
        SKUBarcodes::addBarcodesToArray($records_raw);
        return $records_raw;
    }

    public static function getStockLevel($warehouse_id, $id)
    {
        $whereLocations = Locations::getExcludeLocationsPartialQuery($warehouse_id);
        $params = [
            'warehouse_id' => (int)$warehouse_id,
            'id' => (int)$id,
            'status' => Order::BACKORDER_NUM,
            'pick' => SKULocation::TYPE_PICK,
            'expired' => SKULocation::TYPE_EXPIRED,
        ];
        return (new SafeMysql())->fetchOne("
            SELECT IFNULL(av.available, 0) available,
                   IFNULL(al.allocated, 0) allocated,
                   IFNULL(br.backorder_replenish, 0) backorder_replenish
            FROM SKUs s
                JOIN suppliers sup ON s.supplier_id = sup.id
                JOIN users u ON sup.user_id = u.id
                LEFT JOIN (
                    SELECT SKU_id, SUM(quantity) available
                    FROM SKU_locations
                        $whereLocations
                    WHERE type = :pick AND reason = '2'
                    GROUP BY SKU_id) av ON s.id = av.SKU_id
                LEFT JOIN (
                    SELECT SKU_id, SUM(quantity) allocated
                    FROM SKU_locations
                        $whereLocations
                    WHERE NOT (type = :pick AND reason = '2') AND type <> :expired
                    GROUP BY SKU_id) al ON s.id = al.SKU_id
                LEFT JOIN (
                    SELECT st.SKU_id, SUM(st.qty) backorder_replenish
                    FROM (
                        SELECT os.order_id, os.SKU_id, os.quantity - SUM(IFNULL(sl.quantity, 0)) qty
                        FROM order_SKUs os
                            JOIN orders o ON os.order_id = o.id AND o.status = :status AND o.warehouse_id = :warehouse_id
                            LEFT JOIN SKU_locations sl ON os.SKU_id = sl.SKU_id AND
                                                          (os.order_id = sl.order_id OR
                                                           (sl.order_id IS NULL AND 
                                                            sl.type = :pick AND 
                                                            POSITION('2_' IN sl.reason) = 1 AND 
                                                            SUBSTRING_INDEX(sl.reason, '_', -1) = os.order_id))
                        GROUP BY os.order_id, os.SKU_id) st
                    GROUP BY st.SKU_id) br ON s.id = br.SKU_id
            WHERE s.id = :id", $params);
    }

    public static function getPutaway($warehouse_id)
    {
        $sql = "SELECT sl.SKU_id, s.name, s.photo, SUM(sl.quantity) qty,
                       sl.location_id, 
                        CONCAT(IF(w.label > '', w.label, w.id), '-', l.type_id, '-', l.zone, '-', l.`column`, '-', l.row) loc_name,
                       s.supplier_id, s.state,
                       sl.batch_id, b.number, b.expired
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id AND l.warehouse = :warehouse_id AND l.type_id = :location_type AND l.state = 1
                    JOIN warehouses w on l.warehouse = w.id
                    JOIN SKUs s ON sl.SKU_id = s.id
                    LEFT JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id
                GROUP BY sl.SKU_id, sl.location_id, sl.batch_id
                ORDER BY sl.SKU_id";
        $result = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'location_type' => Location::PA
        ]);
        SKUBarcodes::addBarcodesToArray($result, 'SKU_id');
        return $result;
    }

    /**
     * @param $id
     * @return SKU|null
     */
    public static function getById($id)
    {
        static $SKUs = [];
        if (!array_key_exists($id, $SKUs)) {
            $data = (new SafeMysql())->fetchOne('SELECT * FROM SKUs WHERE id = :id LIMIT 1', [
                'id' => $id
            ]);
            $SKUs[$id] = $data ? new SKU($data) : null;
        }
        return $SKUs[$id];
    }

    public static function getInfoByIds($ids)
    {
        if (empty($ids))
            return [];
        return (new SafeMysql())->fetchAssocWithKey("SELECT SKUs.*,  (" . SKUBarcodes::getBarcodesSQL('SKUs.id') . ") barcode
          FROM SKUs WHERE id IN (:ids)", ['ids' => array_map('intval', $ids)], 'id');
    }

    public static function getByIdAndUser($id, $userId)
    {
        static $SKUs = [];
        if (!array_key_exists($id, $SKUs)) {
            $data = (new SafeMysql())->fetchOne('
                SELECT DISTINCT s.* FROM SKUs s
                    JOIN suppliers sp ON sp.id=s.supplier_id
                    LEFT JOIN supplier_users spu ON (sp.id=spu.supplier_id)
                WHERE s.id = :id AND (sp.user_id = :userId OR spu.user_id = :userId)', [
                'id' => (int)$id,
                'userId' => (int)$userId,
            ]);
            if ($data) {
                $SKUs[$id] = new SKU($data);
            } else {
                $SKUs[$id] = null;
            }
        }
        return $SKUs[$id];
    }

    public static function getByName($name)
    {
        $SKU_info = (new SafeMysql())->fetchOne("SELECT * FROM SKUs WHERE name = :name LIMIT 1", ['name' => $name]);
        if (!empty($SKU_info))
            return new SKU($SKU_info);
        return null;
    }

    public static function getByNameRefSupplier($name, $customer_ref, $supplier_id)
    {
        $SKU_info = (new SafeMysql())->fetchOne("SELECT * FROM SKUs
            WHERE name = :name
            AND customer_ref = :customer_ref
            AND supplier_id = :supplier_id
         LIMIT 1", [
            // channels may send a null SKU/name; SKUs are stored with '' (legacy compared with '')
            'name' => (string)$name,
            'customer_ref' => (string)$customer_ref,
            'supplier_id' => (int)$supplier_id,
        ]);
        if (!empty($SKU_info))
            return new SKU($SKU_info);
        return null;
    }

    public static function getByRefSupplier($customer_ref, $supplier_id)
    {
        $SKU_info = (new SafeMysql())->fetchAll("SELECT * FROM SKUs
            WHERE customer_ref = :customer_ref
            AND supplier_id = :supplier_id", [
            'customer_ref' => (string)$customer_ref,
            'supplier_id' => (int)$supplier_id,
        ]);
        if (empty($SKU_info))
            return null;
        SKUBarcodes::addBarcodesToArray($SKU_info);
        if (count($SKU_info) != 1)
            Channels::sendMessageToTelegram("SKU ($customer_ref)", implode('; ', Arr::column($SKU_info, 'barcode')));
        return new SKU($SKU_info[0]);
    }

    public static function getByBarcodeAndSupplier($barcode, $supplier_id, $filter_type = Filter::TYPE__EQUALS)
    {
        $barcode = Filter::getFilterValue($barcode, $filter_type);
        $operator = Filter::getFilterOperator($filter_type);
        $sql = "
            SELECT s.*
            FROM SKUs s
                JOIN SKU_barcodes sb ON s.id = sb.SKU_id AND sb.barcode $operator :barcode";
        if ($supplier_id)
            $sql .= " WHERE s.supplier_id = :supplier_id";
        $sql .= " LIMIT 1";
        $SKU_info = (new SafeMysql())->fetchOne($sql, [
            'barcode' => $barcode,
            'supplier_id' => (int) $supplier_id,
        ]);
        if (!empty($SKU_info))
            return new SKU($SKU_info);
        return null;
    }

    public static function getByUniqueCustomerRefAndSupplier($unique_customer_ref, $supplier_id)
    {
        $SKU_info = (new SafeMysql())->fetchOne("SELECT * FROM SKUs
                WHERE unique_customer_ref = :unique_customer_ref
                AND supplier_id = :supplier_id
                LIMIT 1", [
            'unique_customer_ref' => (string)$unique_customer_ref,
            'supplier_id' => (int)$supplier_id,
        ]);
        if (!empty($SKU_info))
            return new SKU($SKU_info);
        return null;
    }

    public static function getByIdAndSupplier($id, $supplier_id): ?SKU
    {
        $SKU_info = (new SafeMysql())->fetchOne("SELECT * FROM SKUs
                WHERE id = :id
                AND supplier_id = :supplier_id
                LIMIT 1", [
            'id' => (int)$id,
            'supplier_id' => (int)$supplier_id,
        ]);
        return empty($SKU_info) ? null : new SKU($SKU_info);
    }

    /**
     * @return array<array{id: int, name: string, barcodes: array<string>, barcode: string}>
     */
    public static function getByBarcodes(array $barcodes, ?int $supplier_id = null, $filter_type = Filter::TYPE__EQUALS, $add_key = false): array
    {
        if (!$barcodes)
            return [];
        $SKU_ids = SKUBarcodes::getSKUIdsByBarcodes($barcodes);
        if (!$SKU_ids)
            return [];
        $sql = 'SELECT id, state FROM SKUs WHERE id IN (:ids)';
        $params = ['ids' => $SKU_ids];
        if ($supplier_id) {
            $sql = $sql . ' AND supplier_id = :supplier_id';
            $params['supplier_id'] = $supplier_id;
        }
        $items = (new SafeMysql())->fetchAll($sql, $params);
        SKUBarcodes::addBarcodesToArray($items);
        if ($add_key && $supplier_id && $filter_type == Filter::TYPE__EQUALS) {
            $result = [];
            foreach ($barcodes as $barcode)
                foreach ($items as $i => $SKU) {
                    if (in_array($barcode, $SKU['barcodes'])) {
                        $result[$barcode] = $SKU;
                        // can contain few barcodes of one SKU
                        // unset($items[$i]);
                        break;
                    }
                }
            return $result;
        }
        return $items;
    }

    public static function getByIds($ids, $supplierId = null)
    {
        if (empty($ids))
            return [];
        $sql = 'SELECT id, name, state FROM SKUs WHERE id IN (:ids)';
        $params = ['ids' => array_map('intval', $ids)];
        if ($supplierId) {
            $sql = $sql . ' AND supplier_id = :supplier_id';
            $params['supplier_id'] = (int)$supplierId;
        }
        $items = (new SafeMysql())->fetchAll($sql, $params);
        SKUBarcodes::addBarcodesToArray($items);
        return $items;
    }

    public static function getSize($id, $size_id, $active = true)
    {
        $id = (int) $id;
        $size_id = (int) $size_id;
        $where_active = $active ? 'AND state = 1' : '';
        $sql = "SELECT max_capacity
                FROM SKUs
                WHERE id = :id $where_active LIMIT 1";
        $data = (new SafeMysql())->fetchScalar($sql, ['id' => $id]);
        if ($data && ($capacity = json_decode($data, true)))
            return !empty($capacity[$size_id]) ? $capacity[$size_id] : 0;
        return 0;
    }

    public static function getSizes($SKUs = null)
    {
        $whereSKUs = '';
        $params = [];
        if (!is_null($SKUs)) {
            $whereSKUs = 'WHERE id IN (:SKU_ids)';
            $params['SKU_ids'] = array_map('intval', $SKUs);
        }
        $sql = "SELECT id, max_capacity
                FROM SKUs
                $whereSKUs";
        $items = (new SafeMysql())->fetchAssocWithKey($sql, $params, 'id');
        SKUBarcodes::addBarcodesToArray($items, 'id');
        return $items;
    }

    public static function checkMeasuresConfigured($warehouse_id, array $SKU_ids)
    {
        if (empty($SKU_ids))
            return false;
        $warehouse_id = (int)$warehouse_id;
        static $sizes = [];
        if (empty($sizes[$warehouse_id]))
            $sizes[$warehouse_id] = Sizes::getIdsByWarehouse($warehouse_id);
        $capacities = (new SafeMysql())->fetchArrayOneField("SELECT max_capacity FROM SKUs WHERE id IN (:SKU_ids)", [
            'SKU_ids' => array_map('intval', $SKU_ids)
        ]);
        foreach ($capacities as $val) {
            if (empty($val))
                return false;
            $max_capacity = json_decode($val, TRUE);
            if (empty($max_capacity))
                return false;
            $has_capacity = false;
            foreach ($sizes[$warehouse_id] as $size_id)
                if (!empty($max_capacity[$size_id]) && (int)$max_capacity[$size_id] > 0)
                    $has_capacity = true;
            if ($has_capacity === false)
                return false;
        }
        return true;
    }

    public static function getMaxCapacity($SKU_id, $location_id)
    {
        $size_id = Locations::getSize($location_id);
        return self::getSize($SKU_id, $size_id);
    }

    public static function insertMultiRows(array $array, $insertIgnore = false)
    {
        return (new SafeMysql())->multiInsert(self::TABLE_NAME, $array, $insertIgnore);
    }

    public static function merge($original_SKU_id, $SKU_id_to_merge, $user_id, Warehouse $warehouse, array $selectedDiff = [])
    {
        $original_SKU_id = (int)$original_SKU_id;
        $SKU_id_to_merge = (int)$SKU_id_to_merge;
        $mysql = new SafeMysql();
        //Check if SKU to merge is already in stock
        $is_stock_exists = SKULocations::getQuantityInStockLocations([$SKU_id_to_merge]);
        if ($is_stock_exists)
            throw new \Exception('Cannot merge this SKU because its stock already exists');
        //To merge SKUs we have to update tables:
        //1. SKU_channel_suppliers
        //2. order_SKUs
        //3. ASN_SKUs
        //4. batches
        //5. SKU_locations
        //6. ASN_temporary
        //7. ASN_complete
        $merged_sku_shop = $mysql->fetchScalar("SELECT shop_id FROM SKU_channel_suppliers WHERE SKU_id = :SKU_id", ['SKU_id' => $SKU_id_to_merge]);
        if(!empty($merged_sku_shop))
            $mysql->execute("UPDATE SKU_channel_suppliers SET SKU_id = :original_SKU_id WHERE SKU_id = :SKU_id_to_merge", [
                'original_SKU_id' => $original_SKU_id,
                'SKU_id_to_merge' => $SKU_id_to_merge,
            ]);
        $orders_to_change = $mysql->fetchAll("SELECT order_id, quantity FROM order_SKUs WHERE SKU_id = :SKU_id", ['SKU_id' => $SKU_id_to_merge]);
        if (!empty($orders_to_change))
            foreach ($orders_to_change as $order) {
                $is_order_to_change = (int)$mysql->fetchScalar("
                    SELECT count(*) FROM order_SKUs
                    WHERE order_id = :order_id AND SKU_id = :SKU_id", [
                    'order_id' => (int)$order['order_id'],
                    'SKU_id' => $original_SKU_id,
                ]);
                if ($is_order_to_change) {
                    //We need to update quantity of original SKU
                    $mysql->execute("
                        UPDATE order_SKUs
                        SET quantity = quantity + :quantity
                        WHERE order_id = :order_id AND SKU_id = :original_SKU_id", [
                        'quantity' => $order['quantity'],
                        'order_id' => (int)$order['order_id'],
                        'original_SKU_id' => $original_SKU_id,
                    ]);
                    $mysql->execute("DELETE FROM order_SKUs WHERE order_id = :order_id AND SKU_id = :SKU_id_to_merge", [
                        'order_id' => (int)$order['order_id'],
                        'SKU_id_to_merge' => $SKU_id_to_merge,
                    ]);
                } else {
                    $mysql->execute("
                        UPDATE order_SKUs
                        SET SKU_id = :original_SKU_id
                        WHERE SKU_id = :SKU_id_to_merge AND order_id = :order_id", [
                        'original_SKU_id' => $original_SKU_id,
                        'SKU_id_to_merge' => $SKU_id_to_merge,
                        'order_id' => (int)$order['order_id'],
                    ]);
                }
            }
        $ASNs_to_change = $mysql->fetchAll("SELECT ASN_id, quantity, accepted_quantity, batch_number FROM ASN_SKUs WHERE SKU_id = :SKU_id", ['SKU_id' => $SKU_id_to_merge]);
        if (!empty($ASNs_to_change))
            foreach ($ASNs_to_change as $ASN) {
                $is_ASN_to_change = (int)$mysql->fetchScalar("
                    SELECT count(*) FROM ASN_SKUs
                    WHERE ASN_id = :ASN_id AND SKU_id = :SKU_id", [
                    'ASN_id' => (int)$ASN['ASN_id'],
                    'SKU_id' => $original_SKU_id,
                ]);
                if ($is_ASN_to_change) {
                    //We need to update quantity of original SKU
                    $mysql->execute("
                        UPDATE ASN_SKUs
                        SET quantity = quantity + :quantity,
                            accepted_quantity = accepted_quantity + :accepted_quantity,
                            batch_number = CONCAT(batch_number, ', ', :batch_number)
                        WHERE ASN_id = :ASN_id AND SKU_id = :original_SKU_id", [
                        'quantity' => (int)$ASN['quantity'],
                        'accepted_quantity' => (int)$ASN['accepted_quantity'],
                        // nullable column: CONCAT(..., NULL) would wipe the original SKU's batch_number
                        'batch_number' => (string)$ASN['batch_number'],
                        'ASN_id' => (int)$ASN['ASN_id'],
                        'original_SKU_id' => $original_SKU_id,
                    ]);
                    $mysql->execute(
                        "DELETE FROM ASN_SKUs WHERE ASN_id = :ASN_id AND SKU_id = :SKU_id_to_merge", [
                        'ASN_id' => (int)$ASN['ASN_id'],
                        'SKU_id_to_merge' => $SKU_id_to_merge,
                    ]);
                } else {
                    $mysql->execute("
                        UPDATE ASN_SKUs
                        SET SKU_id = :original_SKU_id
                        WHERE SKU_id = :SKU_id_to_merge AND ASN_id = :ASN_id", [
                        'original_SKU_id' => $original_SKU_id,
                        'SKU_id_to_merge' => $SKU_id_to_merge,
                        'ASN_id' => (int)$ASN['ASN_id'],
                    ]);
                }
            }
        // batches
        $batches_map = [];
        $batches_to_change = $mysql->fetchAll("SELECT id, number, expired FROM batches WHERE SKU_id = :SKU_id", ['SKU_id' => $SKU_id_to_merge]);
        if (!empty($batches_to_change)) {
            $original_batches = $mysql->fetchAll(
                'SELECT id, number, expired FROM batches WHERE (number IN (:numbers) OR expired IN (:expired))
                    AND SKU_id = :original_SKU_id',
                [
                    'numbers' => array_keys($batches_to_change),
                    'expired' => Arr::column($batches_to_change, 'expired'),
                    'original_SKU_id' => $original_SKU_id,
                ]
            );
            $original_batches = Arr::maps($original_batches, 'number', ['id', 'expired']);
            // $expired_dates = array_combine(Arr::column($original_batches, 'expired'), array_keys($original_batches));
            // throw new \Exception('<pre>'.var_export([
            //     'original_batches' => $original_batches,
            //     'expired_dates' => $expired_dates,
            //     'batches_to_change' => $batches_to_change,
            // ], true).'</pre>');
            $to_insert = [];
            foreach ($batches_to_change as $batch) {
                if (isset($original_batches[$batch['number']])) {
                    if ($original_batches[$batch['number']]['expired'] != $batch['expired'])
                        throw new \Exception('Cannot merge this SKU because its batch ' . $batch['number'] . ' has different expired date');
                    $batches_map[$batch['id']] = $original_batches[$batch['number']]['id'];
                }/** elseif (isset($expired_dates[$batch['expired']])) {
                    throw new \Exception('Cannot merge this SKU because its batch ' . $batch['number'] . ' has same expired date as ' . $expired_dates[$batch['expired']]);
                } */ else {
                    $to_insert[] = [
                        'SKU_id' => $original_SKU_id,
                        'number' => $batch['number'],
                        'expired' => $batch['expired'],
                    ];
                }
            }
            if (!empty($to_insert)) {
                $mysql->multiInsert('batches', $to_insert);
                $inserted = Arr::map($mysql->fetchAll(
                    'SELECT id, number FROM batches WHERE SKU_id = :original_SKU_id AND number IN (:numbers)',
                    [
                        'original_SKU_id' => $original_SKU_id,
                        'numbers' => Arr::column($to_insert, 'number'),
                    ]
                ), 'number', 'id');
                foreach ($batches_to_change as $batch) {
                    if (isset($inserted[$batch['number']]))
                        $batches_map[$batch['id']] = $inserted[$batch['number']];
                }
            }
            if (count($batches_map) != count($batches_to_change))
                throw new \Exception('Failed to merge SKU batches');
        }
        // locations
        $locations_to_change = $mysql->fetchAll("SELECT location_id, quantity, batch_id FROM SKU_locations WHERE SKU_id = :SKU_id", ['SKU_id' => $SKU_id_to_merge]);
        if (!empty($locations_to_change))
            foreach ($locations_to_change as $location) {
                $sql = 'SELECT 1 FROM SKU_locations WHERE location_id = :location_id AND SKU_id = :original_SKU_id';
                $params = [
                    'location_id' => $location['location_id'],
                    'original_SKU_id' => $original_SKU_id,
                ];
                if (!empty($location['batch_id'])) {
                    if (empty($batches_map[$location['batch_id']]))
                        throw new \Exception('Cannot merge this SKU because its batch is not merged');
                    $sql .= ' AND batch_id = :batch_id';
                    $params['batch_id'] = $batches_map[$location['batch_id']];
                } else
                    $sql .= ' AND batch_id IS NULL';
                $is_location_to_change = (int)$mysql->fetchScalar($sql, $params);
                if ($is_location_to_change) {
                    // update quantity of original SKU
                    $sql = 'UPDATE SKU_locations SET quantity = quantity + :quantity
                        WHERE location_id = :location_id AND SKU_id = :original_SKU_id';
                    $params['quantity'] = $location['quantity'];
                    if (!empty($location['batch_id']))
                        $sql .= ' AND batch_id = :batch_id';
                    else
                        $sql .= ' AND batch_id IS NULL';
                    $mysql->execute($sql, $params);
                    // delete merged SKU location
                    $sql = 'DELETE FROM SKU_locations WHERE location_id = :location_id AND SKU_id = :SKU_id_to_merge';
                    $params = [
                        'location_id' => $location['location_id'],
                        'SKU_id_to_merge' => $SKU_id_to_merge,
                    ];
                    if (!empty($location['batch_id'])) {
                        $sql .= ' AND batch_id = :batch_id';
                        $params['batch_id'] = $location['batch_id'];
                    } else
                        $sql .= ' AND batch_id IS NULL';
                    $mysql->execute($sql, $params);
                } else {
                    // update SKU_id and batch_id of merged SKU location
                    $sql = 'UPDATE SKU_locations SET SKU_id = :original_SKU_id, batch_id = :original_batch_id, quantity = :quantity
                        WHERE location_id = :location_id AND SKU_id = :SKU_id_to_merge';
                    $params = [
                        'original_SKU_id' => $original_SKU_id,
                        'location_id' => $location['location_id'],
                        'SKU_id_to_merge' => $SKU_id_to_merge,
                        'original_batch_id' => null,
                        'quantity' => $location['quantity'],
                    ];
                    if (!empty($location['batch_id'])) {
                        if (!isset($batches_map[$location['batch_id']]))
                            throw new \Exception('Cannot merge this SKU because its batch is not merged');
                        $sql .= ' AND batch_id = :batch_id_to_merge';
                        $params['batch_id_to_merge'] = $location['batch_id'];
                        $params['original_batch_id'] = $batches_map[$location['batch_id']];
                    } else
                        $sql .= ' AND batch_id IS NULL';
                    $mysql->execute($sql, $params);
                }
            }
        // delete all merged batches
        $sql = 'DELETE FROM batches WHERE SKU_id = :SKU_id_to_merge';
        $mysql->execute($sql, ['SKU_id_to_merge' => $SKU_id_to_merge]);
        // ASN_temporary
        // @TOOD: check if complete is the original SKU:
        //          search for ASNs where ASN_temporary contains SKU to merge
        //          and ASN_complete contains barcode of original SKU
        $mysql->execute('UPDATE ASN_temporary SET SKU_id = :original_SKU_id WHERE SKU_id = :SKU_id_to_merge', [
            'original_SKU_id' => $original_SKU_id,
            'SKU_id_to_merge' => $SKU_id_to_merge,
        ]);
        // ASN_complete
        $barcode_to_merge = $mysql->fetchScalar('SELECT barcode FROM SKU_barcodes WHERE SKU_id = :SKU_id', ['SKU_id' => $SKU_id_to_merge]);
        $complete_to_change = $mysql->fetchAll('SELECT id, barcode, quantity, location_id FROM ASN_complete WHERE barcode = :barcode_to_merge', [
            'barcode_to_merge' => $barcode_to_merge,
        ]);
        if (!empty($complete_to_change)) {
            $original_barcode = $mysql->fetchScalar('SELECT barcode FROM SKU_barcodes WHERE SKU_id = :SKU_id', ['SKU_id' => $original_SKU_id]);
            if (empty($original_barcode))
                throw new \Exception('Cannot merge this SKU because its original SKU has no barcode');
            foreach ($complete_to_change as $complete) {
                $is_complete_to_change = $mysql->fetchScalar('SELECT id FROM ASN_complete WHERE barcode = :original_barcode AND location_id = :location_id', [
                    'original_barcode' => $original_barcode,
                    'location_id' => $complete['location_id'],
                ]);
                if ($is_complete_to_change) {
                    // update quantity of original SKU
                    $mysql->execute('UPDATE ASN_complete SET quantity = quantity + :quantity WHERE id = :id', [
                        'quantity' => $complete['quantity'],
                        'id' => $is_complete_to_change,
                    ]);
                    // delete merged SKU location
                    $mysql->execute('DELETE FROM ASN_complete WHERE id = :id', ['id' => $complete['id']]);
                } else {
                    // update SKU_id of merged SKU location
                    $mysql->execute('UPDATE ASN_complete SET barcode = :original_barcode WHERE id = :id', [
                        'original_barcode' => $original_barcode,
                        'id' => $complete['id'],
                    ]);
                }
            }
        }
        // DONE!

        $merged_SKU = self::getById($SKU_id_to_merge);
        $merged_data = $merged_SKU ? SKUUsages::getDump($merged_SKU) : [];

        SKUUsages::remove($SKU_id_to_merge, $user_id, $warehouse->getId());
        // update SKU in virtual warehouse
        $SKU = self::getById($original_SKU_id);

        self::moveDataToNewSKU($merged_SKU, $SKU, $selectedDiff);

        foreach (Warehouses::getAllId($warehouse->getLCId()) as $w_id) {
            if (Warehouses::isVirtual($w_id)) {
                $processor = Warehouses::getVirtualProcessor($w_id, $SKU->getSupplierId());
                if (empty($processor))
                    continue;
                $processor->addSKU($SKU->toArray(), ['market_value' => $SKU->getMarketValue()]);
            }
        }
        SKUHistories::setMapping($original_SKU_id, $user_id, $merged_data, $warehouse->getId());
        return ['success' => true];
    }

    private static function moveDataToNewSKU(?SKU $merged_data, ?SKU $SKU, array $selectedDiff = []): void
    {
        $photo = $selectedDiff['photo'] ??
            ($merged_data->getPhoto() && empty($SKU->getPhoto()) ? $merged_data->getPhoto() : $SKU->getPhoto());
        $width = $selectedDiff['width'] ??
            ($merged_data->getWidth() && empty($SKU->getWidth()) ? $merged_data->getWidth() : $SKU->getWidth());
        $height = $selectedDiff['height'] ??
            ($merged_data->getHeight() && empty($SKU->getHeight()) ? $merged_data->getHeight() : $SKU->getHeight());
        $depth = $selectedDiff['depth'] ??
            ($merged_data->getDepth() && empty($SKU->getDepth()) ? $merged_data->getDepth() : $SKU->getDepth());
        $weight = $selectedDiff['weight'] ??
            ($merged_data->getWeight() && empty($SKU->getWeight()) ? $merged_data->getWeight() : $SKU->getWeight());

        $SKU->setPhoto($photo);
        $SKU->setWidth($width);
        $SKU->setHeight($height);
        $SKU->setDepth($depth);
        $SKU->setWeight($weight);

        $SKU->save(['id','photo', 'width', 'height', 'depth', 'weight']);

    }

    public static function getChannelsConnected($SKU_id)
    {
        $SKU_id = (int)$SKU_id;
        $sql = "SELECT s.shop_url, s.is_active, c.name as channel_name, scs.external_id,
                        scs.shop_id, scs.id as sku_channel_id
                FROM SKU_channel_suppliers scs
                    JOIN shops s ON scs.shop_id = s.id
                    JOIN channels_suppliers cs ON s.channel_supplier_id = cs.id
                    JOIN channels c ON cs.channel_id = c.id
                WHERE SKU_id = :SKU_id";
        $raw_data = (new SafeMysql())->fetchAssocWithKey($sql, ['SKU_id' => $SKU_id], 'sku_channel_id');
        $result = [];
        foreach ($raw_data as $sku_channel_id => $datum) {
            if (empty($result[$datum['channel_name']]))
                $result[$datum['channel_name']] = [];
            $shop_url = $datum['shop_url'];
            if (empty($result[$datum['channel_name']][$shop_url]))
                $result[$datum['channel_name']][$shop_url] = [];
            switch ($datum['channel_name']) {
                case ShopifyConnector::INTEGRATION_NAME:
                    [$variant_id, $inventory_item_id, $product_id] = explode('-', $datum['external_id']);
                    $result[$datum['channel_name']][$shop_url][] = [
                        'variant_id' => $variant_id,
                        'inventory_item_id' => $inventory_item_id,
                        'product_id' => $product_id,
                        'shop_id' => $datum['shop_id'],
                        'external_id' => $datum['external_id'],
                        'sku_channel_id' => $datum['sku_channel_id'],
                    ];
                    break;
                case WooCommerceConnector::INTEGRATION_NAME:
                    $external_info = explode("_", $datum['external_id']);
                    $result[$datum['channel_name']][$shop_url][] = [
                        'product_id' => $external_info[0],
                        'variant_id' => $external_info[1] ?? '',
                        'shop_id' => $datum['shop_id'],
                        'external_id' => $datum['external_id'],
                        'sku_channel_id' => $datum['sku_channel_id'],
                    ];
                    break;
                case MagentoConnector::INTEGRATION_NAME:
                    $result[$datum['channel_name']][$shop_url][] = [
                        'product_id' => $datum['external_id'],
                        'shop_id' => $datum['shop_id'],
                        'external_id' => $datum['external_id'],
                        'sku_channel_id' => $datum['sku_channel_id'],
                    ];
                    break;
            }
        }
        return $result;
    }

    public static function getProductExternalLink($channel, $SKU_id, $shop_id, $product_id, $variant_id)
    {
        $channel = addslashes($channel);
        $SKU_id = (int)$SKU_id;
        $shop_id = (int)$shop_id;
        $product_id = (int)$product_id;
        $variant_id = (int)$variant_id;
        if (empty(Channels::$allowed_channels[$channel]))
            return [false, 'Link not found'];
        $processor_class = Channels::getProcessorName(['name' => $channel]);
        $processor = new $processor_class();
        $external_link = $processor->getExternalLink($SKU_id, $shop_id, $product_id, $variant_id);
        if (empty($external_link))
            return [false, 'Link not found or channel is disabled'];
        return [true, $external_link];
    }

    public static function changeConnectedSKU($SKU_channel_id, $SKU_id, $shop_id, $product_id, $variant_id, $user_id = -1, $warehouse_id = 0): array
    {
        $SKU_channel_id = (int)$SKU_channel_id;
        $SKU_id = (int)$SKU_id;
        $shop_id = (int)$shop_id;
        $product_id = (int)$product_id;
        $variant_id = (int)$variant_id;
        $SKU = self::getById($SKU_id);
        if (empty($SKU))
            return [false, 'SKU not found'];
        $channel = Shops::getChannelName($shop_id);
        $external_id = SKUChannelSuppliers::getSqlExternalId($channel, $product_id, $variant_id);
        $SKU_connection_info = SKUChannelSuppliers::getConnection($shop_id, $SKU_id, $external_id);
        if (!empty($SKU_connection_info))
            return [true, 'Nothing changed'];
        $processor_class = Channels::getProcessorName(['name' => $channel]);
        $processor = new $processor_class();
        try {
            $product_info = $processor->getProductInfo($shop_id, $product_id, $variant_id);
        } catch (\Exception $e) {
            return [false, $e->getMessage()];
        }
        if (Channels::isShopify($channel))
            if (empty($variant_id) && !empty($product_info['variants']))
                return [
                    false,
                    'SKU has ' . count($product_info['variants']) . ' variants (' . implode(', ', array_map(function ($item) { return $item['id']; }, $product_info['variants'])) . ')'
                ];
        $updated_external_id = SKUChannelSuppliers::getSqlExternalId($channel, $product_id, $variant_id, $product_info['inventory_item_id'] ?? 0);
        SKUChannelSuppliers::changeExternalId($SKU_channel_id, $shop_id, $SKU_id, $updated_external_id);
        SKUHistories::changeExternalId($SKU_id, $user_id, $SKU_channel_id, $updated_external_id, $warehouse_id);
        return [true, 'Success'];
    }

    public static function deleteConnectedSKU($SKU_id, $id, $user_id = -1, $warehouse_id = 0)
    {
        (new SafeMysql())->execute("
            DELETE FROM SKU_channel_suppliers
            WHERE id = :id AND
                  SKU_id = :SKU_id
        ", [
            'id' => (int)$id,
            'SKU_id' => (int)$SKU_id,
        ]);
        SKUHistories::deleteChannelSKU($SKU_id, $user_id, $id, $warehouse_id);
    }

    protected static function applySearchFilters(QueryBuilder $query, array $filters): void
    {
        if (!empty($filters['available']))
            $query->addWhere("available > 0");
        if (!empty($filters['allocated']))
            $query->addWhere("allocated > 0");
        if (!empty($filters['backorder_replenish']))
            $query->addWhere("backorder_replenish > 0");
        if (!empty($filters['like_name']))
            $query->addWhere("s.name LIKE '%" . addslashes(addcslashes($filters['like_name'], '_%')) . "%'");
        if (!empty($filters['barcode'])) {
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode($filters['barcode'], Filter::getFilterTypeFromRequest($filters, 'barcode'));
            $SKU_ids ? $query->addWhere("s.id IN (" . implode(', ', $SKU_ids) . ")") : $query->addWhere("FALSE");
        }
        if (isset($filters['state']) && $filters['state'] != -1)
            $query->addWhere("s.state = " . (int)$filters['state']);
        if (!empty($filters['supplier_id'])) {
            if (is_array($filters['supplier_id']))
                $query->addWhere('s.supplier_id IN (' . implode(',', array_map('intval', $filters['supplier_id'])) . ')');
            else
                $query->addWhere('s.supplier_id = ' . (int)$filters['supplier_id']);
        }
        if (!empty($filters['id']))
            $query->addWhere("s.id = " . (int)$filters['id']);
        if (!empty($filters['customer_ref']))
            $query->addWhere("s.customer_ref LIKE '%" . addslashes(addcslashes($filters['customer_ref'], '_%')) . "%'");
        if (!empty($filters['max_item_id']))
            $query->addWhere("s.id > " . (int)$filters['max_item_id']);
//        $query->addWhere("s.supplier_id IN (" . implode(',', $filters['available_suppliers']) . ")");
    }

    public static function printBarcodes($SKUs, $chunk, $pages)
    {
        require (ROOT_PATH . VENDOR_PATH . '/FPDF/fpdf.php');
        $pdf = new FPDF('P', 'pt', 'A4');
        $pdf->SetTitle('SKUs' . ($chunk != -1 ? " ($chunk of $pages)" : ''));
        $pdf->AddPage();
        BarCodes::showBarCodes($pdf, $SKUs,4);
        $pdf->Output();
    }

    public static function getChanges(int $SKU_id, SKU $new_SKU)
    {
        $filter_unset = ['width', 'height', 'depth', 'weight', 'max_capacity', 'photo_src'];
        $changes = [];
        $old_SKU = self::getById($SKU_id);
        if (!empty($old_SKU)) {
            $old_SKU = $old_SKU->toArray();
            $new_SKU = array_filter($new_SKU->toArray(), function($k) use ($filter_unset) {
                return !in_array($k, $filter_unset);
            }, ARRAY_FILTER_USE_KEY);
            foreach ($new_SKU as $entry => $value) {
                if ($old_SKU[$entry] !== $value)
                    $changes[$entry] = [$old_SKU[$entry], $value];
            }
        }
        return $changes;
    }

    public static function usages(int $SKU_id): array
    {
        list($result, $usages) = SKUUsages::get($SKU_id);
        return [
            'result' => $result,
            'usages' => $usages,
        ];
    }

    public static function hardRemove(int $SKU_id, $user_id, $warehouse_id): array
    {
        return SKUUsages::remove($SKU_id, $user_id, $warehouse_id);
    }

    public static function getSuppliersByIds($SKU_ids): array
    {
        if (empty($SKU_ids))
            return [];
        if (!is_array($SKU_ids))
            $SKU_ids = [$SKU_ids];
        $SKU_data = (new SafeMysql())->fetchAll('SELECT DISTINCT id, supplier_id FROM SKUs WHERE id IN (:SKU_ids)', ['SKU_ids' => $SKU_ids]);
        if (empty($SKU_data))
            return [];
        $res = [];
        foreach ($SKU_data as $SKU) {
            if (empty($res[$SKU['supplier_id']]))
                $res[$SKU['supplier_id']] = [];
            $res[$SKU['supplier_id']][] = $SKU['id'];
        }
        return $res;
    }

    public static function getSKUsWithBundles(array $SKU_ids): array
    {
        if (!$SKU_ids)
            return [];
        $SKU_ids = array_map('intval', $SKU_ids);
        $sql = "
        SELECT s.id SKU_id, s.state, b.id bundle_id
        FROM SKUs s
            LEFT JOIN bundles b ON b.supplier_id = s.supplier_id AND (b.name = s.customer_ref OR b.name = s.name)
        WHERE s.id IN (:ids) AND (s.state = :bundle_num OR b.id IS NOT NULL)";
        return (new SafeMysql())->fetchAll($sql, ['ids' => $SKU_ids, 'bundle_num' => SKU::BUNDLE_NUM]);
    }

    protected static function getJoinForIntegrations(int $warehouse_id, $product_id, $variant_id): string
    {
        if (empty($product_id) && empty($variant_id))
            return '';
        $product_id = addslashes($product_id);
        $variant_id = addslashes($variant_id);
        $forIntegrations = "JOIN SKU_channel_suppliers scs ON s.id = scs.SKU_id ";
        if (!empty($product_id))
            $forIntegrations .= "AND (scs.external_id LIKE '%-$product_id' OR
                                      scs.external_id LIKE '$product_id\_%' OR
                                      scs.external_id = '$product_id' OR
                                      scs.external_id LIKE '%|$product_id') ";
        if (!empty($variant_id))
            $forIntegrations .= "AND (scs.external_id LIKE '$variant_id-%' OR
                                      scs.external_id LIKE '%\_$variant_id') ";
        return $forIntegrations . "JOIN shop_warehouses sw ON scs.shop_id = sw.shop_id AND sw.warehouse_id = $warehouse_id";
    }
}
