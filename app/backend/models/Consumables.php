<?php

namespace app\backend\models;

use app\api\models\ErrorCodes;
use app\core\dbal\SafeMysql;
use app\core\db\QueryBuilder;
use app\core\entities\Consumable;
use app\core\helpers\Request;
use app\core\helpers\Validator;
use app\core\models\ConsumablesStock;

class Consumables extends \app\core\models\Consumables
{
    const FILTER_PARAM = 'consumable_all_filters';
    const FILTER_BILLING_PARAM = 'consumable_billing_filters';
    const LIMIT_PER_PAGE = 10;

    public static function validate(Consumable $consumable): array
    {
        $rules = [
            'name' => [
                'val' => $consumable->getName(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [3, 255]]
            ],
            'type' => [
                'val' => $consumable->getType(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT_SET => array_keys(Consumable::TYPES)]
            ],
            'supplier_id' => [
                'val' => $consumable->getSupplierId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [-PHP_INT_MAX, PHP_INT_MAX]]
            ],
            'width' => [
                'val' => $consumable->getWidth(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_DOUBLE => [0.0001, 1000000000]]
            ],
            'length' => [
                'val' => $consumable->getLength(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_DOUBLE => [0.0001, 1000000000]]
            ],
            'height' => [
                'val' => $consumable->getHeight(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_DOUBLE => [0.0001, 1000000000]]
            ],
            'customer_ref' => [
                'val' => $consumable->getCustomerRef(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'max_weight' => [
                'val' => $consumable->getMaxWeight(),
                'rules' => [Validator::TYPE_DOUBLE => [0, 1000000000]]
            ],
        ];
        if ($consumable->getType() == Consumable::TYPE_PACKAGING)
            $rules['max_weight']['rules'] = [
                Validator::REQUIRED => 1,
                Validator::TYPE_DOUBLE => [0.0001, 1000000000]
            ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (empty($consumable->getId()) && (new SafeMysql())->checkUniqueFields('consumables', [
            'name' => $consumable->getName(),
            'supplier_id' => $consumable->getSupplierId(),
        ]))
            $errors[] = array(
                'field' => 'name + supplier_id',
                'code' => ErrorCodes::FIELDS_NOT_UNIQUE,
                'message' => 'combination Name + Company is not unique',
            );
        if (!empty($errors))
            return $errors;
        return [];
    }

    public static function getAvailableByFilter(int $lc_id, int $warehouse_id, $filters): array
    {
        $filters['warehouse_id'] = $warehouse_id;
        $current_page = Request::getPage($filters);
        $limit = self::LIMIT_PER_PAGE;
        $offset = $limit * ($current_page - 1);
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT SQL_CALC_FOUND_ROWS DISTINCT con.*,
                   IFNULL(sup.name, 'All') sup_name,
                   IFNULL(cc.quantity, 0) quantity
            FROM consumables con
                LEFT JOIN suppliers sup ON con.supplier_id = sup.id
                LEFT JOIN consumable_count cc ON con.id = cc.consumable_id AND cc.warehouse_id = :warehouse_id");
        if (!empty($filters['like_name'])) {
            $filters['like_name'] = addcslashes($filters['like_name'], '_%');
            $query->addWhere("con.name LIKE CONCAT('%', :like_name, '%')");
        }
        if (!empty($filters['like_customer_ref'])) {
            $filters['like_customer_ref'] = addcslashes($filters['like_customer_ref'], '_%');
            $query->addWhere("con.customer_ref LIKE CONCAT('%', :like_customer_ref, '%')");
        }
        if (!empty($filters['type']))
            $query->addWhere("con.type = :type");
        $suppliers_filter = [];
        if (!empty($filters['supplier_id']))
            $suppliers_filter['S'] = 'con.supplier_id IN (:supplier_id)';
        if ($filters['side'] == Roles::SIDE_CLIENT)
            $suppliers_filter['S'] = implode(' AND ', array_merge($suppliers_filter, ['con.supplier_id > 0']));
        else if (!empty($filters['global_packaging']))
            $suppliers_filter['LC'] = "con.supplier_id = -$lc_id";
        if (!empty($suppliers_filter))
            $query->addWhere('(' . implode(' OR ', $suppliers_filter) . ')');
        if (!empty($filters['volume']))
            $query->addWhere("con.width*con.length*con.height >= :volume");
        if (isset($filters['status']))
            if ($filters['status'] != -1)
                $query->addWhere("con.status = :status");
        $query->setLimit("{$offset}, " . $limit);
        $query->setOrderBy('con.id');
        $records_raw = (new SafeMysql())->fetchAll($query->build(), $filters);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        return ['records' => $records_raw, 'total' => $total, 'current_page' => $current_page, 'pages' => ceil($total / $limit)];
    }

    public static function changeMinStockLevel(&$min_stock_level_data, $role_id, $warehouse_id)
    {
        $role = Roles::getById($role_id);
        if (!$role->getMultipleWarehouses())
            foreach ($min_stock_level_data as $supplier_id => $warehouses_data) {
                foreach ($warehouses_data as $key => $warehouse_data)
                    if ($warehouse_data['warehouse_id'] != $warehouse_id)
                        unset($min_stock_level_data[$supplier_id][$key]);
                if (empty($min_stock_level_data[$supplier_id]))
                    unset($min_stock_level_data[$supplier_id]);
            }
    }

    public static function getConsumables($lc_id, $supplier_id, $client = false)
    {
        $where_client = !$client ? ", -$lc_id" : '';
        $supplier_id = (int)$supplier_id;
        $consumables = (new SafeMysql())->fetchAll("
            SELECT id, name, type, CONCAT(length, ' x ', width, ' x ', height) sizes, weight, max_weight, customer_ref
            FROM consumables
            WHERE supplier_id IN ($supplier_id $where_client) AND status = " . Consumable::STATUS_ACTIVE);
        if (empty($consumables))
            return [[], []];
        $res_packagings = [];
        $res_consumables = [];
        foreach ($consumables as $consumable) {
            $name = $consumable['name'] . (!empty($consumable['customer_ref']) ? " ({$consumable['customer_ref']})" : '') . " - {$consumable['sizes']} cm^3";
            if ($consumable['type'] == Consumable::TYPE_CONSUMABLE)
                $res_consumables[$consumable['id']] = $name;
            else
                $res_packagings[$consumable['id']] = "$name, " . (float)$consumable['weight'] . " kg, Max {$consumable['max_weight']} kg";
        }
        return [$res_packagings, $res_consumables];
    }

    public static function getByPackageId($package_id, bool $reuse = null): ?Consumable
    {
        $package_id = (int)$package_id;
        $params = ['package_id' => $package_id];
        $where_reuse = '';
        if (!is_null($reuse)) {
            $where_reuse = 'AND con.reuse = :reuse';
            $params['reuse'] = (int)$reuse;
        }
        $data = (new SafeMysql())->fetchOne("
            SELECT con.*
            FROM consumables con
                JOIN packages p ON con.id = p.packaging_id AND p.id = :package_id AND p.state = 1
            WHERE con.status = 1 $where_reuse", $params);
        if (empty($data))
            return null;
        return new Consumable($data);
    }

    public static function getStock($consumable_id, $warehouse_id, $from_date)
    {
        $stocks = ConsumablesStock::getStock($consumable_id, $warehouse_id, $from_date);
        if (!empty($stocks)) {
            $start_date = $stocks[0]['create_date'];
            $res[$start_date] = 0;
            $start = strtotime($start_date);
            $last = count($stocks) - 1;
            while ($start < strtotime($stocks[$last]['create_date'])) {
                $start = strtotime('+1 days', $start);
                $res[date('Y-m-d', $start)] = 0;
            }
            foreach ($stocks as $stock)
                $res[$stock['create_date']] = $stock['quantity'];
            krsort($res);
            $stocks = $res;
        }
        return $stocks;
    }

    public static function getPackagings(int $lc_id, int $warehouse_id, array $filters = []): array
    {
        $current_page = Request::getPage($filters);
        $limit = self::LIMIT_PER_PAGE;
        $offset = $limit * ($current_page - 1);
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT SQL_CALC_FOUND_ROWS DISTINCT con.id,
                   con.name, concat(con.length, ' x ', con.width, ' x ', con.height) LWH, con.weight, con.max_weight, con.status,
                   cg.id charge_group_id, cg.name group_name, cg.code, cgp.is_default charge_default
            FROM consumables con
                LEFT JOIN suppliers sup ON con.supplier_id = sup.id AND JSON_CONTAINS(sup.warehouses, :warehouse_id) 
                LEFT JOIN charge_groups_packagings cgp ON con.id = cgp.packaging_id
                LEFT JOIN charge_groups cg ON cgp.charge_group_id = cg.id AND cg.warehouse_id = :warehouse_id AND cg.status
            WHERE con.type = :type AND (con.supplier_id = -$lc_id OR sup.id IS NOT NULL)");
        $query->setLimit("{$offset}, " . $limit);
        $query->setOrderBy('con.id');
        $records_raw = (new SafeMysql())->fetchAll($query->build(), [
            'warehouse_id' => $warehouse_id,
            'type' => Consumable::TYPE_PACKAGING
        ]);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        return ['records' => $records_raw, 'total' => $total, 'current_page' => $current_page, 'pages' => ceil($total / $limit)];
    }
}
