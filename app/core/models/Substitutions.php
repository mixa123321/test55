<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Order;
use app\core\entities\Substitution;
use app\backend\models\SubstitutionsHistories;
use app\backend\models\Orders;
use app\core\entities\SubstitutionCondition;

class Substitutions
{
    public static function getById($substitution_id, bool $onlyActive = false): array
    {
        $join_is_active = '';
        if ($onlyActive)
            $join_is_active = "
            JOIN substitution_conditions sub_c
            ON  sub_c.substitution_id = sub.substitution_id
            AND sub_c.status = " . SubstitutionCondition::ACTIVE;

        $sql = "
        SELECT
            s.id, s.name, sub.quantity
        FROM
            substitutions sub

            JOIN SKUs s
            ON  sub.SKU_id = s.id

            $join_is_active
        WHERE
            sub.substitution_id = :substitution_id";
        $result = (new SafeMysql())->fetchAll($sql, ['substitution_id' => (int)$substitution_id]);
        SKUBarcodes::addBarcodesToArray($result);
        return $result;
    }

    public static function getBySKUId($substitution_id, $SKU_ids): array
    {
        if (empty($SKU_ids) || !is_array($SKU_ids))
            return [];
        return (new SafeMysql())->fetchAll("
            SELECT *
            FROM substitutions 
            WHERE substitution_id = :substitution_id AND SKU_id IN (:SKU_ids)",
            ['substitution_id' => (int)$substitution_id, 'SKU_ids' => array_map('intval', $SKU_ids)]);
    }

    public static function getSubstitutionOrders($substitution_id, $warehouse_id)
    {
        $substitution_id = (int)$substitution_id;
        $warehouse_id = (int)$warehouse_id;
        $records = (new SafeMysql())->fetchAll("
            SELECT o.id, o.ref, o.ref2, o.status, o.create_date, o.type_create, os.quantity
            FROM orders o
                JOIN orders_substitutions os ON o.id = os.order_id AND os.substitution_id = :substitution_id
            WHERE o.warehouse_id = :warehouse_id",
            ['substitution_id' => $substitution_id, 'warehouse_id' => $warehouse_id]);
        $stat = [
            'in_progress' => 0,
            'despatched' => 0,
            'cancelled' => 0,
        ];
        $orders = [];
        if (!empty($records)) {
            foreach ($records as $order) {
                if (Order::isDespatched($order['status']))
                    $stat['despatched'] += 1;
                elseif (Order::isCancelled($order['status']))
                    $stat['cancelled'] += 1;
                else {
                    $stat['in_progress'] += 1;
                    $order['status'] = Order::getStateName($order['status']);
                    $order['status_color'] = Order::colorStatus($order['status']);
                    $orders[] = $order;
                }
            }
        }
        return [$orders, $stat];
    }

    public static function add($substitution_id, $SKUs, $user_id)
    {
        $substitution = new Substitution(['substitution_id' => (int)$substitution_id]);
        foreach ($SKUs as $SKU_id => $qty) {
            $substitution->setSKUId($SKU_id);
            $substitution->setQuantity($qty);
            $substitution->save(['substitution_id', 'SKU_id', 'quantity']);
        }
        SubstitutionsHistories::add($substitution_id, $SKUs, $user_id);
    }

    public static function update($substitution_id, $SKUs, $user_id)
    {
        $substitutions = self::getBySKUId($substitution_id, array_keys($SKUs));
        if (empty($substitutions))
            return;
        foreach ($substitutions as $substitution_data) {
            $substitution = new Substitution($substitution_data);
            $substitution->setQuantity($SKUs[$substitution->getSKUId()]);
            $substitution->save(['id', 'substitution_id', 'SKU_id', 'quantity']);
        }
        SubstitutionsHistories::update($substitution_id, $SKUs, $user_id);
    }

    public static function delete($substitution_id, $SKUs, $user_id)
    {
        $substitutions = self::getBySKUId($substitution_id, array_keys($SKUs));
        if (empty($substitutions))
            return;
        $mysql = new SafeMysql();
        foreach ($substitutions as $substitution_data)
            $mysql->execute("DELETE FROM substitutions WHERE id = :id", ['id' => $substitution_data['id']]);
        SubstitutionsHistories::delete($substitution_id, $SKUs, $user_id);
    }
}