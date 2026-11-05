<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Order;
use app\core\entities\SubstitutionCondition;
use app\core\helpers\Filter;
use app\core\helpers\Request;
use app\core\models\OrdersSubstitutions;

class Substitutions extends \app\core\models\Substitutions
{
    const LIMIT_PER_PAGE = 10;

    public static function getAll($warehouse_id, array $filters = []): array
    {
        $filters['warehouse_id'] = $warehouse_id;
        $sql = "SELECT SQL_CALC_FOUND_ROWS sub.substitution_id, s_sub.id sub_id, s.id s_id, sub.quantity, s.supplier_id,
                       (SELECT COUNT(*)
                        FROM orders_substitutions osw 
                            JOIN orders o ON osw.order_id = o.id AND o.warehouse_id = :warehouse_id
                        WHERE sub.substitution_id = osw.substitution_id) as exist_warehouse_orders,
                       (SELECT COUNT(*) FROM orders_substitutions os WHERE sub.substitution_id = os.substitution_id) as exist_orders,
                       IFNULL(sc.is_quantity, 0) is_quantity, IFNULL(sc.status, 0) status    
                FROM substitutions sub
                    JOIN SKUs s_sub ON sub.substitution_id = s_sub.id
                    JOIN SKUs s ON sub.SKU_id = s.id
                    LEFT JOIN substitution_conditions sc ON sub.substitution_id = sc.substitution_id";
        $where = [];
        if (!empty($filters['supplier_id']))
            $where[] = 's.supplier_id = :supplier_id';
        elseif (!empty($filters['supplier_ids']))
            $where[] = 's.supplier_id IN (:supplier_ids)';
        if (!empty($filters['barcode'])) {
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode(trim($filters['barcode']), Filter::getFilterTypeFromRequest($filters, 'barcode'));
            $filters['SKU_ids'] = $SKU_ids;
            $where[] = $SKU_ids ? "s_sub.id IN (:SKU_ids)" : "FALSE";
        }
        if (!empty($where))
            $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " GROUP BY sub.substitution_id, s_sub.supplier_id ORDER BY sub.id DESC";
        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);
        $sql .= " LIMIT {$offset}, " . self::LIMIT_PER_PAGE;
        $records_raw = (new SafeMysql())->fetchAll($sql, $filters);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        SKUBarcodes::addBarcodesToArray($records_raw, 'sub_id', 'sub_barcodes', 'sub_barcode');
        SKUBarcodes::addBarcodesToArray($records_raw, 's_id');
        foreach ($records_raw as &$record)
            $record['SKUs'] = $record['barcode'] . ' x ' . $record['quantity'];
        unset($record);
        return ['records' => $records_raw, 'total' => $total, 'current_page' => $current_page];
    }

    public static function getSubstitutions($warehouse_id, $substitution_id, $quantity, $exist_SKUs = [])
    {
        if (!$quantity)
            return [];
        if (!SubstitutionConditions::checkConditions($substitution_id, $quantity)) {
            return false;
        }
        $qty = SKULocations::getQuantityExist($warehouse_id, $substitution_id, -1, 0, 1, '2', -1);
        if ($qty >= $quantity)
            return true;
        $res = [];
        if ($qty > 0) {
            $res[] = ['id' => $substitution_id, 'quantity' => $qty];
            $quantity -= $qty;
        }
        $exist_SKUs[$substitution_id] = 1;
        $SKUs = self::getById($substitution_id, true);
        if (empty($SKUs))
            return false;

        foreach ($SKUs as $SKU)
            if (!empty($exist_SKUs[$SKU['id']]))
                return false;
            else
                $exist_SKUs[$SKU['id']] = 1;
        foreach ($SKUs as $SKU) {
            $qty = $SKU['quantity']*$quantity;
            $subs = self::getSubstitutions($warehouse_id, $SKU['id'], $qty, $exist_SKUs);
            if ($subs === true)
                $res[] = ['id' => $SKU['id'], 'quantity' => $qty];
            elseif ($subs === false)
                return false;
            elseif (!empty($subs))
                $res = array_merge($res, $subs);
        }
        return $res;
    }

    public static function checkSubstitutions($substitution_id, $exist_SKUs = [])
    {
        $exist_SKUs[$substitution_id] = 1;
        $SKUs = self::getById($substitution_id);
        if (empty($SKUs))
            return true;
        foreach ($SKUs as $SKU)
            if (!empty($exist_SKUs[$SKU['id']]))
                return false;
            else
                $exist_SKUs[$SKU['id']] = 1;
        foreach ($SKUs as $SKU)
            if (!self::checkSubstitutions($SKU['id'], $exist_SKUs))
                return false;
        return true;
    }

    public static function applySubstitutions(Order $order, &$SKUs)
    {
        $res = [];
        $price_db = [];
        foreach ($SKUs as $SKU_id => $SKU_data) {
            $substitution = Substitutions::getById($SKU_id, true);
            if (empty($substitution)) {
                $res[$SKU_id][] = $SKU_data;
                continue;
            }
            $subst = Substitutions::getSubstitutions($order->getWarehouseId(), $SKU_id, $SKU_data['qty']);
            if (!is_array($subst)) {
                $res[$SKU_id][] = $SKU_data;
                continue;
            }
            $history_id = SubstitutionsHistories::addOrder($order->getId(), $SKU_id, $subst, $order->getUserId());
            OrdersSubstitutions::add($order->getId(), $SKU_id, $SKU_data['qty'], $history_id);
            OrderHistories::substitutions($order->getId(), $SKU_id, $subst, $order->getUserId());
            foreach ($subst as $subst_data)
                if (!isset($price_db[$subst_data['id']])) {
                    $SKU = SKUs::getById($subst_data['id']);
                    if (empty($SKU))
                        return false;
                    $price_db[$SKU->getId()] = $SKU->getMarketValue();
                }
            $koef = $SKU_data['price'] / array_sum(array_map(function ($item) use($price_db, $SKU_data) { return $item['quantity']*$price_db[$item['id']]/$SKU_data['qty']; }, $subst));
            foreach ($subst as $subst_data)
                $res[$subst_data['id']][] = [
                    'qty' => $subst_data['quantity'],
                    'price' => $price_db[$subst_data['id']]*$koef,
                ];
        }
        foreach ($res as $SKU_id => $SKU_data) {
            $qty = array_sum(array_map(function ($item) { return $item['qty']; }, $SKU_data));
            $price = array_sum(array_map(function ($item) { return $item['qty']*$item['price']; }, $SKU_data));
            $res[$SKU_id] = [
                'qty' => $qty,
                'price' => $price / $qty,
            ];
        }
        $SKUs = $res;
        return true;
    }

    public static function validateSKUs($SKUs, $supplier_id)
    {
        $res = [];
        foreach ($SKUs as $SKU_id => $qty) {
            $SKU = SKUs::getById($SKU_id);
            if (empty($SKU))
                return 'One of the added SKUs doesn\'t exist';
            if ($SKU->getSupplierId() != $supplier_id)
                return 'One of the selected SKUs does not belong to this account';
            if ((int)$qty < 1)
                return 'SKU quantity must equal or greater than 1';
            $barcode = SKUBarcodes::getBarcodesBySKUIdString($SKU->getId());
            $res[$barcode] = [
                'name' => $SKU->getName(),
                'barcode' => $barcode,
                'quantity' => $qty,
            ];
        }
        return $res;
    }

    public static function substitutionsProcess($substitutions, $SKUs, SubstitutionCondition $conditions, $edit_substitution, $user_id)
    {
        $substitution_id = $conditions->getSubstitutionId();
        $update = false;
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        if ($edit_substitution) {
            $upd_items = $del_items = [];
            foreach ($substitutions as $substitution) {
                $SKU_id = $substitution['id'];
                if (!empty($SKUs[$SKU_id])) {
                    if ($SKUs[$SKU_id] != $substitution['quantity'])
                        $upd_items[$substitution['id']] = $SKUs[$SKU_id];
                } else
                    $del_items[$substitution['id']] = $substitution['quantity'];
                unset($SKUs[$SKU_id]);
            }
            Substitutions::update($substitution_id, $upd_items, $user_id);
            Substitutions::delete($substitution_id, $del_items, $user_id);
            $update = true;
        }
        if (!empty($SKUs))
            Substitutions::add($substitution_id, $SKUs, $user_id);
        $conditions_old = SubstitutionConditions::getById($substitution_id);
        if (empty($conditions_old)) {
            $conditions->save();
            SubstitutionsHistories::addConditions($conditions, $user_id);
        } else {
            $upd = ['substitution_id' => $substitution_id];
            foreach ($conditions_old->toArray() as $field => $value)
                if ($field != 'substitution_id')
                    if ($value != $_POST['conditions'][$field])
                        $upd[$field] = $_POST['conditions'][$field];
            if (count($upd) > 1) {
                $conditions->save(array_keys($upd));
                SubstitutionsHistories::updateConditions($upd, $user_id);
                $update = true;
            }
        }
        $mysql->commit();
        return $update;
    }
}
