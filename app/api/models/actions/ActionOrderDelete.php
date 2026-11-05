<?php

namespace app\api\models\actions;

use app\api\API;
use app\core\entities\Order;
use app\core\models\Orders;
use app\backend\models\Orders as OrdersModel;
use app\core\models\Warehouses;

class ActionOrderDelete extends BaseAction
{
    private $user;
    private $orders;

    public function __construct($data)
    {
        $this->user = API::getUser();
        $this->orders = [];
        if (count($data) < 1)
            return;
        foreach ($data as $id)
            if (isset($id['order']))
                $this->orders[] = ['id' => (int)$id['order']];
            elseif (isset($id['orderRef']))
                $this->orders[] = ['ref' => addslashes($id['orderRef'])];
            else
                $this->orders[] = ['id' => 0];
    }

    public function validateFields() { }

    public function process()
    {
        if (count($this->orders) < 1)
            return ['critical_error' => [
                'error_id' => 1,
                'error_msg' => 'Missing orders']
            ];
        $errors_ord = [];
        $ord = [];
        foreach ($this->orders as $key => $order_info) {
            /**
             * @var $order Order
             */
            if (!empty($order_info['id']))
                $order = Orders::getByIdAndUser($order_info['id'], $this->user);
            elseif (!empty($order_info['ref']))
                $order = Orders::getByRefAndUser($order_info['ref'], $this->user);
            else
                $order = null;
            $order_number = isset($order_info['id']) ? "#{$order_info['id']}" : $order_info['ref'];
            if (is_null($order)) {
                $errors_ord[] = ["Rec #$key" => $order_number, 'error' => 'Order not found'];
                continue;
            }
            if ($order->cancelled())
                $errors_ord[] = ["Rec #$key" => $order_number, 'error' => 'Order is already cancelled'];
            elseif(Order::canCancelOrder($order->getStatus(), Warehouses::isVirtual($order->getWarehouseId()), true)) {
                OrdersModel::cancelOrder($order, $this->user->getId());
                $ord[] = ["Rec #$key" => $order_number];
            } else
                $errors_ord[] = ["Rec #$key" => $order_number, 'error' => 'Order cannot be cancelled'];
        }
        return ['cancelled_orders' => $ord, 'error_orders' => $errors_ord];
    }

    public function getRequestFieldsConfig()
    {
    }

    public function getResponseFieldsConfig()
    {
    }
}
