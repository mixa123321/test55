<?php

namespace app\core\models;

use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\entities\Address;
use app\core\entities\Order;
use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\models\perms\Roles;

class Suppliers
{
    const LIMIT_PER_PAGE = 10;
    const REGEXP_SERIAL_NUMBER_FOR_SUPPLIERS = [
        22 => '^CO\d{12}$',
    ];
    const FILTER_PARAM = 'suppliers_filters';

    public static function getByUserInstance(Warehouse $warehouse, User $user): array
    {
        $suppliers = [];
        switch ($user->getRoleId()) {
            case Roles::SA:
                $suppliers = self::getSuppliers($warehouse->getLCId(), $warehouse->getId());
                break;
            case Roles::SUPPLIER_ADMIN:
                $suppliers = self::getSuppliersByAdmin($warehouse->getId(), $user->getId());
                break;
            case Roles::MANAGER:
                $suppliers = self::getByFilters($warehouse->getLCId(), ['warehouse_id' => $warehouse->getId()]);
                break;
        }
        return $suppliers;
    }

    public static function getSuppliers(int $lc_id, int $warehouse_id, int $user_id = 0, ?int $status = null)
    {
        $warehouses_lc = Warehouses::getAllId((int)$lc_id);
        if ($warehouse_id && !in_array($warehouse_id, $warehouses_lc))
            return [];
        $where_params = [];
        if ($user_id)
            $where_params[] = "s.user_id = $user_id";
        if (!is_null($status))
            $where_params[] = "s.del = $status";
        $where_params = !empty($where_params) ? 'WHERE ' . implode(' AND ', $where_params) : '';
        $suppliers = (new SafeMysql())->fetchAll("
            SELECT s.*, 
                   sw.VAT, sw.EORI, sw.sage_id, sw.shipping_api_key, sw.packing_slip_type,
                   sw.retainme_credentials, sw.shipping_account_number, sw.address
            FROM suppliers s 
                JOIN persons p ON s.user_id = p.user_id AND p.lc_id = :lc_id
                LEFT JOIN suppliers_warehouses sw ON s.id = sw.supplier_id AND sw.warehouse_id = :warehouse_id
            $where_params
            ORDER BY s.name", ['lc_id' => $lc_id, 'warehouse_id' => $warehouse_id]);
        $res = [];
        foreach ($suppliers as $supplier) {
            $warehouses = json_decode($supplier['warehouses']) ?? [];
            $address = (new Address(json_decode($supplier['address'] ?? '') ?? []))->toSupplierAddressArray();
            unset($supplier['address']);
            $supplier += $address;
            if (empty($warehouse_id) || in_array($warehouse_id, $warehouses))
                $res[$supplier['id']] = SuppliersWarehouses::decryptSecrets($supplier);
        }
        return $res;
    }

    public static function getSuppliersAll(int $lc_id, int $warehouse_id, int $user_id, array $filters = [])
    {
        $warehouses_lc = Warehouses::getAllId((int)$lc_id);
        if ($warehouse_id && !in_array($warehouse_id, $warehouses_lc))
            return [];
        if (!empty($filters['search_name']))
            $filters['search_name'] = addslashes($filters['search_name']);
        if (isset($filters['state']))
            $filters['state'] = (int)$filters['state'];
        if (isset($filters['acc_admin']))
            $filters['acc_admin'] = (int)$filters['acc_admin'];
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT SQL_CALC_FOUND_ROWS s.*,
                   sw.VAT, sw.EORI, sw.sage_id, sw.shipping_api_key, sw.packing_slip_type,
                   sw.retainme_credentials, sw.shipping_account_number, sw.address
            FROM suppliers s");
        $query->addJoin("JOIN persons p ON s.user_id = p.user_id AND p.lc_id = :lc_id");
        $query->addJoin("LEFT JOIN suppliers_warehouses sw ON s.id = sw.supplier_id AND sw.warehouse_id = :warehouse_id");
        $params = ['lc_id' => $lc_id, 'warehouse_id' => $warehouse_id];
        if ($user_id) {
            $query->addWhere("s.user_id = :user_id");
            $params['user_id'] = $user_id;
        }
        if (!empty($filters['search_name'])) {
            $query->addWhere("s.name LIKE :search_name");
            $params['search_name'] = '%' . $filters['search_name'] . '%';
        }
        if (isset($filters['state']) && $filters['state'] != -1) {
            $query->addWhere("s.del = :state");
            $params['state'] = (int)$filters['state'];
        }
        if (isset($filters['acc_admin']) && $filters['acc_admin'] != -1) {
            $query->addWhere("s.user_id = :acc_admin");
            $params['acc_admin'] = (int)$filters['acc_admin'];
        }
        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);
        $query->setOrderBy('s.name');
        $query->setLimit("$offset, " . self::LIMIT_PER_PAGE);
        $records_raw = (new SafeMysql())->fetchAll($query->build(), $params);
        $res = [];
        foreach ($records_raw as $record) {
            $warehouses = json_decode($record['warehouses']) ?? [];
            $address = (new Address(json_decode($record['address'] ?? '') ?? []))->toSupplierAddressArray();
            unset($record['address']);
            $record += $address;
            if (empty($warehouse_id) || in_array($warehouse_id, $warehouses))
                $res[$record['id']] = SuppliersWarehouses::decryptSecrets($record);
        }
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS()');
        return ['total' => $total, 'current_page' => $current_page, 'records' => $res];
    }

    public static function getSuppliers4SKUs(Warehouse $warehouse, User $user)
    {
        return self::getSuppliers(
            $warehouse->getLCId(),
            $user->isRoleSA() ? 0 : $warehouse->getId(),
            $user->isWarehouseSide() ? 0 : $user->getId()
        );
    }

    public static function getByFilters(int $lc_id, array $filters = [])
    {
        $warehouse_id = (int)($filters['warehouse_id'] ?? 0);
        $warehouses_lc = Warehouses::getAllId($lc_id);
        if ($warehouse_id && !in_array($warehouse_id, $warehouses_lc))
            return [];
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT s.*, sw.VAT, sw.EORI, sw.sage_id, sw.shipping_api_key, sw.packing_slip_type,
                   sw.retainme_credentials, sw.shipping_account_number, sw.address , sw.warehouse_id
            FROM suppliers s");
        $query->addJoin("JOIN persons p ON s.user_id = p.user_id AND p.lc_id = :lc_id");
        $query->addJoin('LEFT JOIN suppliers_warehouses sw ON s.id = sw.supplier_id AND sw.warehouse_id = :warehouse_id');
        $params = [
            'warehouse_id' => $warehouse_id,
            'lc_id' => $lc_id,
        ];
        if (!empty($filters['user_ids']) || !empty($filters['only_active_users'])) {
            $query->addJoin("JOIN supplier_users su ON s.id = su.supplier_id");
            if (!empty($filters['user_ids'])) {
                $query->addWhere("su.user_id IN (:user_ids)");
                $params['user_ids'] = array_map('intval', $filters['user_ids']);
            }
            if (!empty($filters['only_active_users'])) {
                $query->addJoin("JOIN users u ON su.user_id = u.id AND u.status = :status");
                $params['status'] = User::USER_STATUS_ACTIVE;
            }
        }
        if (!empty($filters['admin_ids'])) {
            $query->addWhere("s.user_id in (:admin_ids)");
            $params['admin_ids'] = array_map('intval', $filters['admin_ids']);
        }
        if (!empty($filters['active']))
            $query->addWhere("del = 0");
        $query->setOrderBy('s.name');

        $suppliers = (new SafeMysql())->fetchAssocWithKey($query->build(), $params, 'id');

        foreach ($suppliers as $supplier_id => $supplier) {
            $warehouses = json_decode($supplier['warehouses']) ?? [];
            if (empty($warehouses) ||
                ($warehouse_id && !in_array($warehouse_id, $warehouses)) ||
                (!$warehouse_id && !empty(array_diff($warehouses, $warehouses_lc))))
                unset($suppliers[$supplier_id]);
        }
        array_walk($suppliers, function (&$s) {
            $s = SuppliersWarehouses::decryptSecrets($s);
            $address = (new Address(json_decode($s['address'] ?? '') ?? []))->toSupplierAddressArray();
            unset($s['address']);
            $s += $address;
        });
        return $suppliers;
    }

    public static function getSuppliersIdByUsers($warehouse_id, $users_id)
    {
        if (!is_array($users_id))
            $users_id = [$users_id];
        $users_id = array_map('intval', array_unique($users_id));
        $suppliers = (new SafeMysql())->fetchAll("
            SELECT id, name, warehouses
            FROM suppliers
            WHERE user_id IN (:users_id)
            
            UNION
            
            SELECT id, name, warehouses
            FROM suppliers
                JOIN supplier_users su ON id = supplier_id AND su.user_id IN (:users_id)
            ORDER BY name", ['users_id' => $users_id]);
        $res = [];
        foreach ($suppliers as $supplier) {
            if (!empty($warehouse_id)) {
                $warehouses = json_decode($supplier['warehouses']);
                if (!empty($warehouses) && in_array($warehouse_id, $warehouses))
                    $res[$supplier['id']] = ['id' => $supplier['id'], 'name' => $supplier['name']];
            } else
                $res[$supplier['id']] = ['id' => $supplier['id'], 'name' => $supplier['name']];
        }
        return $res;
    }

    public static function getSuppliersByUsers($users_id)
    {
        if (!is_array($users_id))
            $users_id = [$users_id];
        $users_id = array_map('intval', $users_id);
        $suppliers = (new SafeMysql())->fetchAll("
            SELECT id, name 
            FROM suppliers 
            WHERE id IN (SELECT id FROM suppliers s where s.user_id IN (:users_id)
                         UNION
                         SELECT supplier_id as id FROM supplier_users su where su.user_id IN (:users_id))
            ORDER BY name", ['users_id' => $users_id]);
        if (empty($suppliers))
            return [];
        $res = [];
        foreach ($suppliers as $supplier)
            $res[$supplier['id']] = $supplier['name'];
        return $res;
    }

    public static function getWarehouseIds(int $id)
    {
        $warehouses = (new SafeMysql())->fetchScalar("SELECT warehouses FROM suppliers WHERE id = :id", ['id' => $id]);
        return json_decode($warehouses, true) ?? [];
    }

    public static function getById($id): ?Supplier
    {
        static $suppliers = [];
        if (!array_key_exists($id, $suppliers)) {
            $data = (new SafeMysql())->fetchOne('SELECT * FROM suppliers WHERE id = :id', ['id' => (int) $id]);
            $suppliers[$id] = $data ? new Supplier($data) : null;
        }
        return $suppliers[$id];
    }

    public static function getByName($name)
    {
        $supplier_info = (new SafeMysql())->fetchOne("SELECT * FROM suppliers WHERE name = :name LIMIT 1", ['name' => $name]);
        if (!empty($supplier_info))
            return new Supplier($supplier_info);
        return null;
    }

    public static function getSetUp(int $warehouse_id, int $id)
    {
        $res = [];
        $res[] = (new SafeMysql())->fetchScalar("
            SELECT COUNT(*)
            FROM supplier_users
                JOIN users ON user_id = id AND warehouse_id = :warehouse_id
            WHERE supplier_id = :id", ['warehouse_id' => $warehouse_id, 'id' => $id]);
        $res[] = (new SafeMysql())->fetchScalar("SELECT COUNT(*) FROM SKUs WHERE supplier_id = :id", ['id' => $id]);
        $res[] = (new SafeMysql())->fetchScalar("
            SELECT COUNT(*)
            FROM packages p
                JOIN package_warehouses pw ON p.id = pw.package_id AND pw.warehouse_id = :warehouse_id
            WHERE p.supplier_id = :id", ['warehouse_id' => $warehouse_id, 'id' => $id]);
        return $res;
    }

    public static function getSuppliersByAdmin(int $warehouse_id, int $admin_id, ?int $status = null)
    {
        $where_status = '';
        $params = ['warehouse_id' => $warehouse_id, 'admin_id' => $admin_id];
        if (!is_null($status)) {
            $where_status = "AND del = :status";
            $params['status'] = $status;
        }
        $suppliers = (new SafeMysql())->fetchAll("
            SELECT s.*, sw.VAT, sw.EORI, sw.sage_id, sw.shipping_api_key, sw.packing_slip_type,
                   sw.retainme_credentials, sw.shipping_account_number, sw.address 
            FROM suppliers s 
                LEFT JOIN suppliers_warehouses sw ON s.id = sw.supplier_id AND sw.warehouse_id = :warehouse_id
            WHERE user_id = :admin_id $where_status
            ORDER BY name", $params);
        $res = [];
        foreach ($suppliers as $supplier) {
            $warehouses = json_decode($supplier['warehouses']);
            if (!empty($warehouses) && in_array($warehouse_id, $warehouses)) {
                $address = (new Address(json_decode($supplier['address'] ?? '') ?? []))->toSupplierAddressArray();
                unset($supplier['address']);
                $supplier += $address;
                $res[$supplier['id']] = SuppliersWarehouses::decryptSecrets($supplier);
            }
        }
        return $res;
    }

    public static function getAllSuppliersByAdmin(int $admin_id): array
    {
        $data = (new SafeMysql())->fetchAll("SELECT * FROM suppliers WHERE user_id = :admin_id", ['admin_id' => $admin_id]);
        return array_map(function ($item) {
            return new Supplier($item);
        }, $data);
    }

    public static function getSuppliersByUser(int $warehouse_id, int $user_id, ?int $status = null)
    {
        $where_status = '';
        $params = ['warehouse_id' => $warehouse_id, 'user_id' => $user_id];
        if (!is_null($status)) {
            $where_status = "AND del = :status";
            $params['status'] = $status;
        }
        $suppliers = (new SafeMysql())->fetchAll("
            SELECT s.*, sw.VAT, sw.EORI, sw.sage_id, sw.shipping_api_key, sw.packing_slip_type,
                   sw.retainme_credentials, sw.shipping_account_number, sw.address
            FROM suppliers s
                LEFT JOIN suppliers_warehouses sw ON s.id = sw.supplier_id AND sw.warehouse_id = :warehouse_id
            WHERE s.id IN (SELECT supplier_id
                           FROM supplier_users
                           WHERE user_id = :user_id) $where_status
            ORDER BY name", $params);
        $res = [];
        foreach ($suppliers as $supplier) {
            $warehouses = json_decode($supplier['warehouses']);
            if (!empty($warehouses) && in_array($warehouse_id, $warehouses)) {
                $address = (new Address(json_decode($supplier['address'] ?? '') ?? []))->toSupplierAddressArray();
                unset($supplier['address']);
                $supplier += $address;
                $res[$supplier['id']] = SuppliersWarehouses::decryptSecrets($supplier);
            }
        }
        return $res;
    }

    public static function getSupplierById(int $warehouse_id, int $supplier_id)
    {
        $res = (new SafeMysql())->fetchOne(
            "SELECT s.*, sw.VAT, sw.EORI, sw.sage_id, sw.shipping_api_key, sw.address, sw.return_label, sw.reg_number
                FROM suppliers s
                    LEFT JOIN suppliers_warehouses sw ON s.id = sw.supplier_id AND sw.warehouse_id = :warehouse_id
                WHERE s.id = :supplier_id
                ORDER BY s.name
                LIMIT 1",
            ['warehouse_id' => $warehouse_id, 'supplier_id' => $supplier_id]);
        if (empty($res))
            return null;
        $res = SuppliersWarehouses::decryptSecrets($res);
        $warehouses = json_decode($res['warehouses']);
        if (!empty($warehouses) && in_array($warehouse_id, $warehouses)) {
            $address = (new Address(json_decode($res['address'] ?? '') ?? []))->toSupplierAddressArray();
            unset($res['address']);
            return array_merge($res, $address);
        }
        return null;
    }

    public static function getSupplierByUser(int $warehouse_id, int $user_id, ?int $status = 0)
    {
        $where_status = !is_null($status) ? 'WHERE del = :status' : '';
        $res = (new SafeMysql())->fetchOne(
            '
                SELECT s.*, sw.VAT, sw.EORI, sw.sage_id, sw.shipping_api_key, sw.address, sw.return_label, sw.reg_number
                FROM suppliers s
                    JOIN supplier_users su ON s.id = su.supplier_id AND su.user_id = :user_id
                    LEFT JOIN suppliers_warehouses sw ON s.id = sw.supplier_id AND sw.warehouse_id = :warehouse_id
                '. $where_status. '
                ORDER BY s.name
                LIMIT 1
            ',
            [
                'user_id' => $user_id,
                'warehouse_id' => $warehouse_id,
                'status' => $status
            ]
        );
        if (empty($res))
            return null;
        $res = SuppliersWarehouses::decryptSecrets($res);
        $warehouses = json_decode($res['warehouses']);
        if (!empty($warehouses) && in_array($warehouse_id, $warehouses)) {
            $address = (new Address(json_decode($res['address'] ?? '') ?? []))->toSupplierAddressArray();
            unset($res['address']);
            return array_merge($res, $address);
        }
        return null;
    }

    public static function getUsers($warehouse_id, $supplier_id)
    {
        return (new SafeMysql())->fetchAll("
            SELECT user_id, login
            FROM supplier_users
                JOIN users ON user_id = id AND warehouse_id = :warehouse_id
            WHERE supplier_id = :supplier_id", [
                'warehouse_id' => (int) $warehouse_id,
                'supplier_id' => (int) $supplier_id
            ]
        );
    }

    public static function getWeeksBeforeExpiries(int $lc_id)
    {
        $suppliers = (new SafeMysql())->fetchAll("
            SELECT id, warehouses, weeks_before_expiry
            FROM suppliers
            WHERE has_weeks_before_expiry = 1");
        if (empty($suppliers))
            return [];
        $warehouses_lc = Warehouses::getAllId($lc_id);
        $res = [];
        foreach ($suppliers as $supplier) {
            $warehouses = json_decode($supplier['warehouses']) ?? [];
            if (empty(array_diff($warehouses, $warehouses_lc)))
                $res[] = $supplier;
        }
        return $res;
    }

    public static function getWeeksBeforeExpiryBySupplierId(int $supplier_id)
    {
        return (new SafeMysql())->fetchOne("SELECT warehouses, weeks_before_expiry FROM suppliers 
                                       WHERE id = :supplier_id AND has_weeks_before_expiry = 1",
            [
                'supplier_id' => $supplier_id
            ]
        );
    }

    public static function getSuppliersForPicking($warehouse_id, $mode, $type)
    {
        if ($mode === 'single')
            return self::getSuppliersWithOrders($warehouse_id, [Order::AWAITING_SINGLE_PICK_NUM, Order::SINGLE_PICKING_NUM], $type);
        elseif ($mode === 'multi')
            return self::getSuppliersWithOrders($warehouse_id, [Order::AWAITING_PICK_NUM, Order::PICKING_NUM], $type);
        elseif ($mode === 'large')
            return self::getSuppliersWithOrders($warehouse_id, [Order::AWAITING_LARGE_PICK_NUM, Order::LARGE_PICKING_NUM], $type);
        return [];
    }

    public static function getSuppliersWithOrders($warehouse_id, $stasuses, $type)
    {
        $params = ['statuses' => $stasuses, 'warehouse_id' => (int)$warehouse_id, 'type' => $type];
        $suppliers = (new SafeMysql())->fetchAll("SELECT DISTINCT sup.id, sup.name
                                                FROM suppliers sup
                                                JOIN orders o ON o.supplier_id = sup.id
                                                JOIN SKU_locations sl ON sl.order_id = o.id AND sl.type = 1 AND sl.reason = '1'
                                                JOIN locations l on sl.location_id = l.id 
                                           WHERE o.status IN (:statuses) AND o.warehouse_id = :warehouse_id AND l.type_id = :type", $params);
        $suppliers = Arr::map($suppliers,'id', 'name');
        if (empty($suppliers))
            return [];
        $measures = (new SafeMysql())->fetchAssocWithKey("SELECT sum(sl.quantity) quantity, count(DISTINCT sl.SKU_id) total, s.id
                                            FROM SKU_locations sl
                                               JOIN orders o ON o.id = sl.order_id
                                               JOIN suppliers s ON o.supplier_id = s.id
                                               JOIN locations l on sl.location_id = l.id 
                                            WHERE o.status IN (:statuses) AND o.warehouse_id = :warehouse_id AND sl.type = 1 AND sl.reason = '1' AND l.type_id = :type
                                            GROUP BY s.id", $params, 'id');
        if (!empty($measures)) {
            foreach ($suppliers as $id => $supplier_name) {
                $suppliers[$id] = ['m' => 0, 'u' => 0, 'name' => $supplier_name];
                $suppliers[$id]['m'] = $measures[$id]['total'];
                $suppliers[$id]['u'] = $measures[$id]['quantity'];
            }
        }
        return $suppliers;
    }
}
