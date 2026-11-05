<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ASN;
use app\core\entities\ASNSKU;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\backend\models\Suppliers as SuppliersModel;
use app\core\helpers\Filter;

class ASNs
{
    const LIMIT_PER_PAGE = 10;

    public static function getContainerList()
    {
        return [
            ASN::CONTAINER_20FT => "20 Foot Container",
            ASN::CONTAINER_40FT => "40 Foot Container",
            ASN::PALLET => "Pallet(s)",
            ASN::BOX => "Box(es)",
            ASN::MAILBAG => "Mailbag(s)",
            ASN::CAGE => "Cage(s)",
            ASN::YOKE => "Yoke(s)",
        ];
    }

    public static function getByDate($warehouse_id, $suppliers, $month, $year)
    {
        if (empty($suppliers) || !is_array($suppliers))
            return [];

        $binds = [
            'warehouse_id' => (int)$warehouse_id,
            'month' => (int)$month,
            'year' => (int)$year,
            'suppliers' => $suppliers,
        ];

        $whereSuppliers = 'AND a.supplier_id IN (:suppliers)';
        $sql = "SELECT a.id,
                       DAY(a.arrival_date) `day`,
                       s.name,
                       COUNT(sk.name) skus,
                       SUM(ask.quantity) units,
                       a.user_id,
                       a.ref,
                       a.order_id,
                       o.ref oref 
                FROM ASNs a
                    JOIN suppliers s ON a.supplier_id = s.id
                    JOIN ASN_SKUs ask ON a.id = ask.ASN_id
                    JOIN SKUs sk ON ask.SKU_id = sk.id
                    LEFT JOIN orders o ON a.order_id = o.id
                WHERE a.status > 0
                    AND 
                    MONTH(a.arrival_date) = :month
                    AND YEAR(a.arrival_date) = :year
                    AND a.warehouse_id = :warehouse_id
      $whereSuppliers
                GROUP BY a.id, name, `day`
                ORDER BY `day`, a.id";
        return (new SafeMysql())->fetchAll($sql, $binds);
    }

    public static function getAll4User($user, $current, $search)
    {
        $binds = [
            'user_id' => (int)$user,
        ];
        $search_sql = '';

        if (!empty($search)) {
            $binds['search'] = addcslashes($search, '%_');
            $search_sql = "AND (a.ref LIKE CONCAT('%', :search, '%') OR a.id LIKE CONCAT('%', :search, '%'))";
        }
        $sql = "SELECT SQL_CALC_FOUND_ROWS a.*, a.id, 
               (select create_date from ASN_history where ASN_id=a.id order by 1 limit 1) create_date, 
               a.ref, a.unscheduled, a.arrival_date, a.arrived_date, 
               COUNT(sk.name) skus, SUM(ask.quantity) units, a.status
        FROM ASNs a
            JOIN suppliers s on a.supplier_id=s.id
            JOIN ASN_SKUs ask on a.id=ask.ASN_id
            JOIN SKUs sk on ask.SKU_id=sk.id
        WHERE a.status > 0
          AND a.user_id = :user_id
          $search_sql
        GROUP BY a.id
        ORDER BY a.id DESC
        LIMIT " . RECORD_PAGE . " OFFSET " . (RECORD_PAGE * ($current - 1));
        
        $res = (new SafeMysql())->fetchAll($sql, $binds);
        $resNum = (new SafeMysql())->fetchOne('SELECT FOUND_ROWS() count');
        return [$res, $resNum['count']];
    }

    public static function getArrival($warehouse_id, array $filter, int $page, bool $show_return_ASN = true)
    {
        if ($page < 1)
            $page = 1;
        $search_sup = $search_s = '';
        $barcodes = [];
        $binds = [
            'warehouse_id' => (int) $warehouse_id,
            'allowed_statuses' => [ASN::NEW, ASN::ARRIVED, ASN::PUTAWAY],
        ];
        if (!empty($filter['search'])) {
            $barcodes = SKUBarcodes::searchBarcode($filter['search'], Filter::getFilterTypeFromRequest($filter, 'search'));
            $SKU_ids = $barcodes ? array_keys($barcodes) : [];
            $search_s = $SKU_ids ? " AND s.id IN (:SKU_ids)" : " AND FALSE";
            if ($SKU_ids)
                $binds['SKU_ids'] = $SKU_ids;
            if (!empty($filter['supplier_id'])) {
                $search_sup .= "AND sup.id = :supplier_id ";
                $binds['supplier_id'] = (int) $filter['supplier_id'];
            }
        }
        $return_ASN = $show_return_ASN ? "OR (a.status = " . ASN::WAREHOUSE . " AND a.order_id IS NOT NULL)" : "";
        $sql = "SELECT SQL_CALC_FOUND_ROWS a.*,
                       a.id, a.ref, a.unscheduled, a.carrier, a.tracking_number,
                       CONCAT(sup.name, ' (', sup.id, ')') `name`,
                       a.arrival_date, a.arrived_date,
                       COUNT(s.name) skus,
                       SUM(ask.quantity) units,
                       GROUP_CONCAT(DISTINCT s.id) sku_ids,
                       a.status, a.user_id
                FROM ASNs a
                    JOIN suppliers sup ON a.supplier_id = sup.id $search_sup
                    JOIN ASN_SKUs ask ON a.id = ask.ASN_id
                    JOIN SKUs s ON ask.SKU_id = s.id $search_s 
                WHERE a.warehouse_id = :warehouse_id AND ((a.status IN (:allowed_statuses) AND a.order_id IS NULL) $return_ASN)
                GROUP BY a.id ORDER BY a.status DESC, a.id DESC LIMIT " . LIMIT_PER_TICKET_PAGE . " OFFSET " . (LIMIT_PER_TICKET_PAGE * ($page - 1));

        $res = (new SafeMysql())->fetchAll($sql, $binds);

        foreach ($res as &$row) {
            $ASN_SKU_ids = explode(',', $row['sku_ids']);
            $ASN_barcodes = [];
            foreach ($ASN_SKU_ids as $SKU_id)
                $ASN_barcodes[$SKU_id] = $barcodes[$SKU_id] ?? '';
            $row['barcodes'] = implode(', ', array_filter($ASN_barcodes));
        }
        $resNum = (new SafeMysql())->fetchOne('SELECT FOUND_ROWS() count');
        return [$res, $resNum['count']];
    }

    /**
     * @param $id
     * @return ASN|null
     */
    public static function getById($id)
    {
        static $ASNs = [];
        if (!array_key_exists($id, $ASNs)) {
            $data = (new SafeMysql())->fetchOne('SELECT * FROM ASNs WHERE id = :id', ['id' => (int) $id]);
            $ASNs[$id] = $data ? new ASN($data) : null;
        }
        return $ASNs[$id];
    }

    /**
     * @param $id int
     * @param $user User
     * @return ASN
     */
    public static function getByIdAndUser($id, $user)
    {
        $id = (int)$id;
        $suppliers_id = Arr::column(SuppliersModel::getByUser($user), 'id');
        if (empty($suppliers_id))
            return null;
        
        $binds = ['id' => $id];
        $placeholders = [];

        foreach ($suppliers_id as $k => $sup_id) 
            {
                $ph = "supplier_$k";
                $placeholders[] = ":$ph";
                $binds[$ph] = (int)$sup_id;
            }

        $sql = "SELECT *
                FROM ASNs
                WHERE id = :id AND supplier_id IN (" . implode(', ', $placeholders) . ")";
        $data = (new SafeMysql())->fetchOne($sql, $binds);
        if (empty($data))
            return null;
        return new ASN($data);
    }

    public static function canCompleted($id)
    {
        return ASNSKUs::getQuantityByASN($id) === ASNTemporaries::getQuantityByASN($id);
    }

    public static function getByRef($ref, $supplier_id, $warehouse_id)
    {
        $binds = [
            'supplier_id' => (int)$supplier_id,
            'warehouse_id' => (int)$warehouse_id,
            'ref' => $ref
        ];
        $sql = "SELECT id 
                FROM ASNs 
                WHERE supplier_id = :supplier_id AND ref = :ref AND warehouse_id = :warehouse_id";
        $ids = (new SafeMysql())->fetchArrayOneField($sql, $binds);
        if (empty($ids))
            return false;
        if (count($ids) > 1)
            return true;
        return $ids[0];
    }
}
