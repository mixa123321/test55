<?php

namespace app\core\entities;

use app\core\dbal\SafeMysql;

class OrderQueue extends Entity
{
    private $order_id;
    private $priority = 0;

    const PRIORITY = [
        0 => 'None',
        1, 2, 3, 4, 5
    ];
    const STATUSES = [
        Order::WAITING_FOR_LARGE_NUM => Order::WAITING_FOR_LARGE,
        Order::AWAITING_LARGE_PICK_NUM => Order::AWAITING_LARGE_PICK,
        Order::LARGE_PICKING_NUM => Order::LARGE_PICKING,
        Order::WAITING_FOR_SINGLE_NUM => Order::WAITING_FOR_SINGLE,
        Order::AWAITING_SINGLE_PICK_NUM => Order::AWAITING_SINGLE_PICK,
        Order::SINGLE_PICKING_NUM => Order::SINGLE_PICKING,
        Order::WAITING_FOR_ORDER_BIN_NUM => Order::WAITING_FOR_ORDER_BIN,
        Order::AWAITING_PICK_NUM => Order::AWAITING_PICK,
        Order::PICKING_NUM => Order::PICKING,
        Order::AWAITING_PACK_NUM => Order::AWAITING_PACK
    ];

    public function getOrderId(): int
    {
        return (int)$this->order_id;
    }

    public function setOrderId($id)
    {
        $this->order_id = (int)$id;
    }

    public function getPriority()
    {
        return $this->priority;
    }

    public function setPriority($priority): void
    {
        $this->priority = $priority;
    }

    public function save()
    {
        (new SafeMysql())->execute("
            INSERT INTO order_queue (order_id, priority) VALUES (:order_id, :priority)
            ON DUPLICATE KEY UPDATE order_id = VALUES(order_id), priority = VALUES(priority)", $this->toArray());
    }

    public function toArray()
    {
        return [
            'order_id' => $this->getOrderId(),
            'priority' => $this->getPriority()
        ];
    }
}
