<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\entities\Movement;
use app\core\entities\Order;
use app\core\entities\SectionBinding;
use app\core\entities\User;
use app\core\models\SectionBindings;
use Exception;
use InvalidArgumentException;
use app\core\entities\Warehouse;
use app\core\models\OrderLocations;
use app\core\models\OrderLocationTemporaries;

class Cart
{
    const CART_WEIGHT = 50;

    public static $modes = [
        'picking', 'dropping', 'movement', 'arrival', 'putaway', 'unexpected', 'unexpected_noASN'
    ];

    const NUM_NONE      = 0;
    const NUM_MOVE      = 1;
    const NUM_SINGLE    = 21;
    const NUM_MULTI     = 22;
    const NUM_LARGE     = 23;
    const NUM_PACKING   = 3;
    const NUM_PACK      = 31;
    const NUM_EXPIRED   = 4;

    const MODE_NONE     = 'none';
    const MODE_MOVE     = 'move';
    const MODE_SINGLE   = 'single';
    const MODE_MULTI    = 'multi';
    const MODE_LARGE    = 'large';
    const MODE_PACK     = 'pack';
    const MODE_EXPIRED  = 'expired';

    private static $places = [
        self::NUM_MOVE => '',
        self::NUM_SINGLE => ' (picking ' . self::MODE_SINGLE . ')',
        self::NUM_MULTI => ' (picking ' . self::MODE_MULTI . ')',
        self::NUM_LARGE => ' (picking ' . self::MODE_LARGE . ')',
        self::NUM_PACKING => ' (partial ' . self::MODE_PACK . 'ing)',
        self::NUM_PACK => ' (' . self::MODE_PACK . 'ing)',
        self::NUM_EXPIRED => ' (' . self::MODE_EXPIRED . ')'
    ];

    public static function getCarts($warehouse_id)
    {
        $name_select = Locations::getLocationNameSelect('l', 'w');
        $alias_select = Locations::getAliasSelect('l');
        $consist_select = self::getConsistSelect($warehouse_id, 'l');
        $sql = "SELECT l.id,
                       l.type_id,
                       l.state,
                       $name_select name,
                       $alias_select alias,
                       $consist_select consist,
                       IFNULL((SELECT CONCAT(u.login, ' (', u.id, ')')
                               FROM users u
                               WHERE u.cart_id = l.id AND u.warehouse_id = :warehouse_id), 
                              IFNULL((SELECT CONCAT('Table ', num)
                                      FROM section_binding
                                      WHERE object_id = l.id AND type_id = :bind_table AND warehouse_id = :warehouse_id),
                                     IFNULL((SELECT CONCAT('Large ', num)
                                             FROM section_binding
                                             WHERE object_id = l.id AND type_id = :bind_large AND warehouse_id = :warehouse_id), 0))) user_id
                FROM locations l
                    JOIN warehouses w ON l.warehouse = w.id
                WHERE l.warehouse = :warehouse_id
                HAVING type_id = :type OR user_id != 0
                ORDER BY l.state desc, LPAD(l.row, 5, 0)";
        return (new SafeMysql())->fetchAssocWithKey($sql, [
            'warehouse_id' => $warehouse_id,
            'type' => Location::CA,
            'bind_table' => SectionBinding::TYPE_BIND_SINGLE_TABLE,
            'bind_large' => SectionBinding::TYPE_BIND_LARGE_TABLE,
        ], 'id');
    }

    public static function getCartsNearTable($warehouseId, $table_id, string $binding_type, &$orders = []): array
    {
        if (!in_array($binding_type, SectionBinding::TYPES_BIND_TABLES)) {
            // C - cart near single pack, U - cart near large pack,
            throw new InvalidArgumentException(sprintf(
                'Binding type must be some of "%s", got "%s"',
                implode('", "', SectionBinding::TYPES_BIND_TABLES),
                $binding_type,
            ));
        }
        $warehouseId = (int)$warehouseId;
        $table_id = (int)$table_id;
        $location_name = Locations::getLocationNameSelect('l', 'w');

        $sql = "SELECT l.id, $location_name as name, l.type_id
                FROM locations l
                    JOIN warehouses w ON l.warehouse = w.id
                    LEFT JOIN SKU_locations sl on l.id = sl.location_id
                    INNER JOIN section_binding sb on l.id = sb.object_id
            WHERE sb.num IN (SELECT `row` FROM locations WHERE id = :table_id)
                AND l.warehouse = :warehouse_id
                AND sb.type_id = :binding_type
                AND l.type_id in ('CA', 'ST')
                ORDER BY LPAD(l.row, 5, 0)";
        $carts = (new SafeMysql())->fetchAssocWithKey($sql, [
            'table_id' => $table_id,
            'warehouse_id' => $warehouseId,
            'binding_type' => $binding_type,
        ], 'id');
        array_walk($carts, function(&$cart, $cart_id) use (&$orders) {
            $cart_orders = self::getOrderIdsInTrolley($cart_id);
            $cart['orders_count'] = count($cart_orders);
            array_walk($orders, function(&$order, $order_id) use ($cart, $cart_orders) {
                if ($cart['type_id'] == Location::CA && empty($order['cart']))
                    $order['cart'] = in_array($order_id, $cart_orders) ? $cart['name'] : null;
                if ($cart['type_id'] == Location::ST && empty($order['pallet']))
                    $order['pallet'] = in_array($order_id, $cart_orders) ? $cart['name'] : null;
            });
        });
        return $carts;
    }

    public static function getVirtuals($warehouse_id, User $user)
    {
        if ($user->hasManagerAccess()) {
            $users = [];
            $where = '';
        } elseif ($user->isRoleWorker()) {
            $users = [$user->getId()];
            $where = " AND l.row = :user_id";
        } else
            return [];
        $name_select = Locations::getLocationNameSelect('l');
        $consist_select = self::getConsistSelect($warehouse_id, 'l');
        $sql = "SELECT l.id,
                       l.row,
                       $name_select name,
                       $consist_select consist,
                       (SELECT CONCAT(u.login, ' (', u.id, ')')
                        FROM users u
                        WHERE u.id = l.row AND u.warehouse_id = :warehouse_id) user_id
                FROM locations l
                WHERE l.warehouse = :warehouse_id AND l.type_id = :type_id $where
                ORDER BY user_id";
        $virtuals = (new SafeMysql())->fetchAssocWithKey($sql, [
            'warehouse_id' => $warehouse_id,
            'type_id' => Location::US,
            'user_id' => $user->getId(),
        ],'id');
        foreach ($virtuals as $key => $virt)
            if ((!empty($users) && !in_array($virt['row'], $users)) || empty($virt['consist']))
                unset($virtuals[$key]);
        return $virtuals;
    }

    public static function getPallets($warehouse_id)
    {
        $name_select = Locations::getLocationNameSelect('l', 'w');
        $alias_select = Locations::getAliasSelect('l');
        $consist_select = self::getConsistSelect($warehouse_id, 'l');
        // @TODO: add empty pallets from users
        $sql = "SELECT l.id,
                    $name_select name,
                    $alias_select alias,
                    $consist_select consist,
                    sb.num as bind_table,
                    sb.type_id as bind_type,
                    (SELECT CONCAT(u.login, ' (', u.id, ')')
                        FROM users u
                        WHERE u.cart_id = l.id AND u.warehouse_id = :warehouse_id) user_id
                    FROM locations l
                    JOIN warehouses w ON l.warehouse = w.id
                    LEFT JOIN SKU_locations sl ON l.id = sl.location_id
                    LEFT JOIN section_binding sb ON l.id = sb.object_id AND sb.type_id IN (:bind_tables)
                    WHERE l.warehouse = :warehouse_id
                        AND l.state = :pallet_state
                    ORDER BY LPAD(l.row, 5, 0)";
        $res = (new SafeMysql())->fetchAssocWithKey($sql, [
            'warehouse_id' => $warehouse_id,
            'pallet_state' => Location::DETACHED,
            'bind_tables' => [SectionBinding::TYPE_BIND_LARGE_TABLE, SectionBinding::TYPE_BIND_SINGLE_TABLE],
        ], 'id');
        foreach ($res as &$val) {
            if (empty($val['user_id']) && !empty($val['bind_table']))
                $val['user_id'] = $val['bind_type'] == SectionBinding::TYPE_BIND_SINGLE_TABLE
                    ? 'Table ' . $val['bind_table']
                    : 'Large ' . $val['bind_table'];
            $val['orders_count'] = count(self::getOrderIdsInTrolley($val['id']));
        }
        return $res;
    }

    public static function getOrderIdsInTrolley($card_id): array
    {
        $data = (new SafeMysql())->fetchArrayOneField("SELECT order_id FROM SKU_locations sl 
                                            JOIN locations l ON sl.location_id = l.id AND l.type_id in ('CA', 'ST')
                                        WHERE sl.location_id = :card_id AND sl.type = 31",
            [
               'card_id' => $card_id,
            ]
        );
        return array_map('intval', $data);
    }

    public static function getCardInTrolleyByOrderId(int $order_id): ?array
    {
        $sql = "
        SELECT DISTINCT sl.location_id as id, l.type_id
        FROM SKU_locations sl
            JOIN locations l ON sl.location_id = l.id AND l.type_id in ('CA', 'ST')
        WHERE sl.order_id = :order_id AND sl.type = 31;";
        $items = (new SafeMysql())->fetchAll($sql, ['order_id' => $order_id]);
        if (count($items) > 1)
            throw new Exception('More than one cart for order');
        if ($items)
            return $items[0];
        return null;
    }

//    public static function getPackingCartsByType($warehouse_id, $type_id)
//    {
//        if (!in_array($type_id, [self::MODE_SINGLE, self::MODE_LARGE]))
//            throw new InvalidArgumentException(sprintf('$typeId should equal "single" or "large", $type_id = "%s"', $type_id));
//        $warehouse_id = (int)$warehouse_id;
//        $quantity_where = 'AND sl.quantity ' . ($type_id == self::MODE_SINGLE ? '=' : '>') . ' 1';
//        $cart_type = 'UNLOAD';
//        $sql = "SELECT l.id,
//                    CONCAT(IF(w.label = '' OR w.label IS NULL, l.warehouse, w.label), '-', l.type_id, '-', l.zone, '-', l.`column`, '-', l.row) name,
//                    '$cart_type' AS cart_type
//                FROM locations l
//                    JOIN warehouses w ON l.warehouse = w.id
//                    LEFT JOIN SKU_locations sl on l.id = sl.location_id
//                WHERE l.id NOT IN (SELECT object_id FROM section_binding WHERE warehouse_id = $warehouse_id)
//                  AND l.warehouse = $warehouse_id
//                  AND l.type_id = 'CA'
//                  AND sl.type = " . self::NUM_PACK . "
//                  $quantity_where";
//        return Mysql::fetchAssocWithKey($sql, 'id');
//    }

    public static function addWeight(&$trolleys, $weight)
    {
        foreach ($weight as $W)
            if (!empty($trolleys[$W['id']]))
                $trolleys[$W['id']]['weight'][$W['type']] = $W['weight'] ?? 'unexpected';
        foreach ($trolleys as &$trolley) {
            if (empty($trolley['weight']))
                $trolley['mode'][] = self::MODE_NONE;
            if (!empty($trolley['weight'][self::NUM_MOVE]))
                $trolley['mode'][] = self::MODE_MOVE;
            if (!empty($trolley['weight'][self::NUM_SINGLE]))
                $trolley['mode'][] = self::MODE_SINGLE;
            if (!empty($trolley['weight'][self::NUM_MULTI]))
                $trolley['mode'][] = self::MODE_MULTI;
            if (!empty($trolley['weight'][self::NUM_LARGE]))
                $trolley['mode'][] = self::MODE_LARGE;
            if (!empty($trolley['weight'][self::NUM_PACK]))
                $trolley['mode'][] = self::MODE_PACK;
            if (!empty($trolley['weight'][self::NUM_EXPIRED]))
                $trolley['mode'][] = self::MODE_EXPIRED;
        }
        foreach ($trolleys as &$trolley)
            if (!empty($trolley['weight'])) {
                $res = [];
                foreach ($trolley['weight'] as $key => $val)
                    $res[] = ($val === 'unexpected' ? $val : sprintf("%.2fkg", $val)) . (self::$places[$key] ?? '');
                $trolley['weight'] = implode(';<br/>', $res);
            }
    }

    public static function getCart(User $user)
    {
        if (!empty($user->getCartId())) {
            $cart = Locations::getById($user->getCartId());
            if ($cart->getWarehouse() == $user->getWarehouseId())
                return $cart;
        }
        while (empty($id = Locations::getVirtualTrolley($user))) {
            $size = Sizes::getByName($user->getWarehouseId(), 'UNLIMITEDSIZE');
            Locations::createVirtualTrolley($user->getWarehouseId(), $user->getId(), empty($size) ? -1 : $size->getId());
        }
        return Locations::getById($id);
    }

    public static function isNone($modes)
    {
        return count($modes) == 1 && $modes[self::NUM_NONE] == self::MODE_NONE;
    }

    public static function freeCart($user_id)
    {
        (new SafeMysql())->execute("UPDATE users SET cart_id = NULL WHERE id = :user_id", [
            'user_id' => (int)$user_id,
        ]);
    }

    public static function unhookTrolleyFromPackingTable(User $user, $cart_id, $warehouse_id): array
    {
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        try {
            $user_id = $user->getId();
            $take_res = self::takeCart($user_id, $cart_id, $warehouse_id);
            if (!$take_res)
                throw new Exception('Trolley busy');
            $type = null;
            $order_status = null;
            switch (SectionBindings::getBindingType($cart_id)) {
                case SectionBinding::TYPE_BIND_SINGLE_TABLE:
                    $type = self::NUM_SINGLE;
                    $order_status = Order::SINGLE_PICKING;
                    break;
                case SectionBinding::TYPE_BIND_LARGE_TABLE:
                    $type = self::NUM_LARGE;
                    $order_status = Order::LARGE_PICKING;
                    break;
            }
            if (empty($type))
                throw new Exception('Trolley not bind to table');

            $where_sql = " WHERE location_id = :cart_id AND type = :type";
            if (!$user->hasWorkersAccess())
                $where_sql .= " AND (reason = :user_id OR reason = :user_system)";

            $order_ids = $mysql->fetchArrayOneField("SELECT order_id FROM SKU_locations " . $where_sql,
                [
                    'cart_id' => $cart_id,
                    'type' => self::NUM_PACK,
                    'user_id' => $user_id,
                    'user_system' => User::ID__SYSTEM
                ]
            );

            foreach ($order_ids as $order_id)
                Orders::changeStatus($order_id, $order_status, $user_id, 'Trolley unhooked from table. <br>Status: ' . $order_status);
            $mysql->execute('UPDATE SKU_locations SET type = :new_type' . $where_sql, [
                'new_type' => $type,
                'cart_id' => $cart_id,
                'type' => self::NUM_PACK,
                'user_id' => $user_id,
                'user_system' => User::ID__SYSTEM
            ]);
            SectionBindings::removeByCartId($cart_id);
        } catch (Exception $exception) {
            $mysql->rollback();
            return [false, $exception->getMessage()];
        }
        $mysql->commit();
        return [true];
    }

    public static function leaveTrolleyNearPackingTable(Location $cart, User $user, Warehouse $warehouse, $mode)
    {
        $modes = self::getCartInfo($cart);
        if (empty($modes[$mode]) || (!empty($modes[$mode]) && count($modes) != 1) && !$cart->isDetached()) {
            throw new \Exception('Trolley is not leave near packing station. In trolley is mixed types of SKU', 1);
        }
        if (empty(SKULocations::getQuantityInLocation($cart->getId()))) {
            throw new \Exception('Trolley is empty', 2);
        }
        $location_id = (int)$_POST['id'];
        $ords = [];
        list(, $orders) = SKULocations::getDroppingByWorker($mode, $cart->getId());
        $status = '2' . Pick::$mode_val[$mode][0];
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        foreach ($orders as $id => $order) {
            list(, $SKU_id, $batch_id) = explode('_', $id);
            if ($batch_id == '.')
                $batch_id = '';
            foreach ($order as $order_id => $quantity) {
                OrderLocationTemporaries::addSKU($SKU_id, $quantity, $order_id, $location_id);
                OrderLocations::reserveLocation($order_id, $location_id);
                $move_id = Movements_::setDropping($location_id, $order_id, $SKU_id, $batch_id, $quantity, $user->getId());
                SKUHistories::addDroppingTable($cart->getId(), $order_id, $SKU_id, $batch_id, $quantity, $move_id, $user->getId(), $warehouse->getId());
                Pick::addNearTable($SKU_id, $batch_id, $quantity, $status, $order_id, $cart->getId(), $user->getId());
                if ($mode === 'single')
                    Orders::changeStatus($order_id, Order::AWAITING_PACK, $user->getId());
                if ($mode !== 'single')
                    $ords[$order_id] = 1;
            }
        }
        foreach ($ords as $order_id => $tmp)
            if (OrderSKUs::isCompleted($order_id))
                Orders::changeStatus($order_id, Order::AWAITING_PACK, $user->getId());
        Pick::leaveTrolleyNearTable($cart->getId(), $mode, $location_id);
        Cart::freeCart($user->getId());
        $mysql->commit();
    }

    public static function takeCart($user_id, $cart_id, $warehouse_id)
    {
        $user_id = (int) $user_id;
        $cart_id = (int) $cart_id;
        if (self::isBusy($cart_id, $warehouse_id))
            return false;
        $mysql = new SafeMysql();
        $mysql->execute("UPDATE users SET cart_id = NULL WHERE cart_id = :cart_id", [
            'cart_id' => $cart_id,
        ]);
        $mysql->execute("UPDATE users SET cart_id = :cart_id WHERE id = :user_id", [
            'cart_id' => $cart_id,
            'user_id' => $user_id,
        ]);
        return true;
    }

    public static function takeForceCart($user_id, $cart_id)
    {
        $mysql = new SafeMysql();
        $mysql->execute("UPDATE users SET cart_id = NULL WHERE cart_id = :cart_id", [
            'cart_id' => (int)$cart_id,
        ]);
        $mysql->execute("UPDATE users SET cart_id = :cart_id WHERE id = :user_id", [
            'cart_id' => (int)$cart_id,
            'user_id' => (int)$user_id,
        ]);
        return true;
    }

    public static function isBusy($cart_id, $warehouse_id)
    {
        return (new SafeMysql())->fetchScalar('SELECT COUNT(*) FROM users WHERE cart_id = :cart_id AND warehouse_id = :warehouse_id', [
            'cart_id' => (int)$cart_id,
            'warehouse_id' => (int)$warehouse_id
        ]) > 0;
    }

    public static function getCartUser($cart_id, $wradhouse_id)
    {
        $res = (new SafeMysql())->fetchAll("SELECT u.*
                                   FROM users u
                                   WHERE u.cart_id = :cart_id AND u.warehouse_id = :wradhouse_id",
            [
                'cart_id' => $cart_id,
                'wradhouse_id' => $wradhouse_id
            ]
        );
        if (empty($res))
            return null;

        return new User($res[0]);
    }

    public static function getIndexCarts($warehouse_id, $name, $carts)
    {
        $location = Locations::getByScan($warehouse_id, htmlentities(trim($name)));
        if ($location && is_array($carts)) {
            $cart_ids = array_keys($carts);
            $location_id = $location->getId();
            if (in_array($location_id, $cart_ids, true))
                return $location_id;
        }
        return 0;
    }

    public static function getCartInfo(Location $cart)
    {
        $weight = SKULocations::getWeight($cart->getWarehouse(), $cart->getId());
        $res = [];
        if (empty($weight))
            $res[self::MODE_NONE] = self::NUM_NONE;
        if (!empty($weight[self::NUM_MOVE]))
            $res[self::MODE_MOVE] = $weight[self::NUM_MOVE]['weight'];
        if (!empty($weight[self::NUM_SINGLE]))
            $res[self::MODE_SINGLE] = $weight[self::NUM_SINGLE]['weight'];
        if (!empty($weight[self::NUM_MULTI]))
            $res[self::MODE_MULTI] = $weight[self::NUM_MULTI]['weight'];
        if (!empty($weight[self::NUM_LARGE]))
            $res[self::MODE_LARGE] = $weight[self::NUM_LARGE]['weight'];
        if (!empty($weight[self::NUM_PACKING]))
            $res[self::MODE_PACK] = $weight[self::NUM_PACKING]['weight'];
        if (!empty($weight[self::NUM_PACK]))
            $res[self::MODE_PACK] = ($res[self::MODE_PACK]) ?? 0 + $weight[self::NUM_PACK]['weight'];
        if (!empty($weight[self::NUM_EXPIRED]))
            $res[self::MODE_EXPIRED] = $weight[self::NUM_EXPIRED]['weight'];
        return $res;
    }

    public static function changeTrolleys($loc_from_id, $loc_to_id, $modes, User $user, $warehouse_id): bool
    {
        if (self::isNone($modes))
            return false;
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        SKULocations::changeWorkerLocation($loc_from_id, $loc_to_id, $modes);
        if (in_array(self::MODE_MOVE, $modes) || in_array(self::MODE_EXPIRED, $modes))
            if ($mysql->checkUniqueFields('movements', ['loc_from_id' => $loc_from_id])) {
                $movements = $mysql->fetchAll("SELECT * FROM movements WHERE loc_from_id = :loc_from_id", [
                    'loc_from_id' => $loc_from_id
                ]);
                foreach ($movements as $movement) {
                    $uniq_movement = Movements::getByUniqueFields((int)$movement['SKU_id'], $loc_to_id, (int)$movement['loc_to_id']);
                    if (!empty($uniq_movement)) {
                        /** @var  $uniq_movement Movement */
                        $fields = ['id', 'quantity'];
                        $uniq_movement->setQuantity((int)$uniq_movement->getQuantity() + (int)$movement['quantity']);
                        $m_comments = json_decode($movement['comment'], TRUE);
                        if (!empty($m_comments)) {
                            $fields[] = 'comment';
                            $uniq_movement->addComments($m_comments);
                        }
                        $uniq_movement->save($fields);
                        MovementHistories::update($uniq_movement->getId(), $user->getId(), 'merged with  ' .  (int)$movement['id']);
                        $mysql->execute('DELETE FROM movements WHERE id = :id', ['id' => (int)$movement['id']]);
                        MovementHistories::delete(new Movement($movement), $user, 'delete by merge to movement '  . $uniq_movement->getId());
                    } else {
                        $mysql->execute('UPDATE movements SET loc_from_id = :loc_to_id WHERE id = :id', [
                            'loc_to_id' => $loc_to_id,
                            'id' => (int)$movement['id'],
                        ]);
                    }
                }
                MovementHistories::update(null, $user->getId(), 'change trolley', ['loc_from_id' => $loc_from_id]);
            }
        SKUHistories::addHandover($loc_from_id, $loc_to_id, $modes, $user->getId(), $warehouse_id);
        $mysql->commit();
        return true;
    }

    public static function isAtWorker(string $cart_user_id, User $worker)
    {
        return $cart_user_id == sprintf('%s (%s)', $worker->getLogin(), $worker->getId());
    }

    public static function freeCartById(int $cart_id): void
    {
        (new SafeMysql())->execute("UPDATE users SET cart_id = NULL WHERE cart_id = :cart_id", [
            'cart_id' => $cart_id,
        ]);
    }

    private static function getConsistSelect($warehouse_id, $alias)
    {
        return "(SELECT CONCAT(COUNT(SKU_id), '/', SUM(quantity)) FROM (SELECT id, SKU_id, SUM(quantity) quantity
                                                                               FROM SKU_locations, locations
                                                                               WHERE location_id = id AND warehouse = $warehouse_id
                                                                               GROUP BY SKU_id, id) SL
                                                                         WHERE id=$alias.id)";
    }
}
