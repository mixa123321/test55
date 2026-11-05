<?php

namespace app\api\models\actions;

use app\api\API;
use app\core\entities\Order;
use app\core\models\OrderDocs;
use app\core\models\Orders;

class ActionOrderGet extends BaseAction
{
    private $user;
    private $orders_id;

    public function __construct($data)
    {
        $this->user = API::getUser();
        $data = json_decode($data['q'] ?? '', 1);
        $this->orders_id = [];
        if (empty($data) || count($data) < 1)
            return;
        foreach ($data as $id)
            $this->orders_id[] = (int)$id['order'];
    }

    public function validateFields() { }

    public function process()
    {
        if (count($this->orders_id) < 1)
            return ['critical_error' => [
                'error_id' => 1,
                'error_msg' => 'Missing orders']
            ];
        $errors_ord = [];
        $ord = [];
        foreach ($this->orders_id as $key => $order_id) {
            /** @var $order Order */
            $order = Orders::getByIdAndUser($order_id, $this->user);
            if (is_null($order)) {
                $errors_ord[] = ["Rec #$key" => $order_id, 'error' => 'Order not found'];
                continue;
            }
            $order_data = $order->toArrayView();
            $order_data['docs'] = '';
            $doc = OrderDocs::getFirst($order->getId());
            if (!empty($doc) && $doc->existFile())
                $order_data['docs'] = $doc->getName();
            $ord[] = ["Rec #$key" => $order_data];
        }
        return ['info_orders' => $ord, 'error_orders' => $errors_ord];
    }

    public function getRequestFieldsConfig()
    {
        return [];
    }

    public function getResponseFieldsConfig()
    {
        return [];
    }
}
