<?php

namespace app\core\services;

use app\core\dbal\SafeMysql;
use app\core\entities\Order;
use app\core\entities\SubstitutionHistory;
use app\core\helpers\Debug;
use app\core\helpers\Str;
use app\core\models\OrderDocs;
use Exception;

class ClearOrderDataService
{
    /** @var Order|null  */
    private $order;

    private $points = [];

    /** @throws Exception */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function setPoints(array $points)
    {
        $this->points = $points;
    }

    public function bundles()
    {
        $mysql = new SafeMysql();
        $history_ids = $mysql->fetchArrayOneField('SELECT bundle_history_id FROM orders_bundles WHERE bundle_history_id IS NOT NULL AND order_id = :order_id', ['order_id' => $this->order->getId()]);
        $mysql->execute('DELETE FROM orders_bundles WHERE order_id = :order_id', ['order_id' => $this->order->getId()]);
        if (!empty($history_ids)) {
            $history_ids_str = implode(', ', $history_ids);
            $mysql->execute("DELETE FROM bundles_history WHERE id IN ($history_ids_str)");
        }
    }

    public function docs()
    {
        OrderDocs::clear($this->order);
    }

    public function packages()
    {
        $mysql = new SafeMysql();
        $order_id = (int)$this->order->getId();
        $sql_ids = "IN (SELECT op.id FROM orders_packages op WHERE order_id = {$order_id})";
        $mysql->execute('DELETE r, opr FROM returns r 
                                INNER JOIN order_packages_returns opr ON opr.return_id = r.id
                                    WHERE opr.order_package_id ' . $sql_ids);
        $mysql->execute('DELETE sh, ops FROM shipping sh 
                                INNER JOIN order_packages_shipping ops ON ops.shipping_id = sh.id
                                    WHERE ops.order_package_id ' . $sql_ids);
        $mysql->execute('DELETE FROM orders_packages_consumables WHERE order_package_id ' . $sql_ids);
        $mysql->execute('DELETE FROM orders_packages_SKUs WHERE orders_packages_id ' . $sql_ids);

        $mysql->execute('DELETE FROM orders_packages WHERE order_id = :order_id', [
            'order_id' => $order_id
        ]);
    }
    public function substitutions()
    {
        $mysql = new SafeMysql();
        $histories_remove_ids = [];
        $histories = $mysql->fetchAll("SELECT id, addition FROM substitution_history WHERE event = :event AND addition LIKE :pattern", [
            'event' => SubstitutionHistory::EVENT_ORDER,
            'pattern' => '%' . $this->order->getId() . '%'
        ]);
        foreach ($histories as $history_item) {
            if (empty($addition = json_decode($history_item['addition'], TRUE)))
                continue;
            if ((int)$addition['order_id'] == $this->order->getId())
                $histories_remove_ids[] = (int)$history_item['id'];
        }
        $mysql->execute('DELETE FROM orders_substitutions WHERE order_id = :order_id', ['order_id' => $this->order->getId()]);
        if (!empty($histories_remove_ids))
            $mysql->execute('DELETE FROM substitution_history WHERE id IN (' . implode(',', $histories_remove_ids) . ')');
    }

    public function handle($re = false)
    {
        try {
            foreach ($this->points as $point) {
                $method = Str::toCamelCase($point);
                if (method_exists($this, $method))
                    $this->{$method}();
                else
                    (new SafeMysql())->execute("DELETE FROM {$point} WHERE order_id = :order_id", ['order_id' => $this->order->getId()]);
            }
        } catch (Exception $e) {
            Debug::sendDebugInfo('ClearOrderDataService', var_export([$e->getCode(), $e->getMessage()], 1));
            if (!$re) {
                sleep(5);
                $this->handle(true);
            }
        }
    }
}