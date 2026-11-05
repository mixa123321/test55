<?php

namespace app\backend\models;

use app\core\entities\Order;
use app\core\entities\WebhookHistory;
use app\core\helpers\CURL;

class Webhooks extends \app\core\models\Webhooks
{
    private static function callback($supplier_id, $warehouse_id, $name, $number, $data)
    {
        if (!in_array($name, self::WEBHOOK_LIST))
            return ['error' => 'Not allowed webhook ' . $name];
        $webhooks_lst = self::getBySupplier($name, $supplier_id, $warehouse_id);
        if (empty($webhooks_lst))
            return ['success' => 'Not found webhook ' . $name . ' for supplier #' . $supplier_id . ' warehouse #' . $warehouse_id];
        foreach ($webhooks_lst as $webhook_data) {
            $fields_to_save = ['id', 'response'];
            $webhook_history = WebhookHistories::addInternal($name, $number, $data, $webhook_data['user_id']);
            $curl = new CURL($webhook_data['callback'], "webhook_callback_$name");
            $curl->setBaseAuth('Fulfilment', $webhook_data['api_key']);
            $curl->setHeader('Content-type', 'application/json');
            $curl->setMethod('POST');
            $curl->setBody(json_encode($data));
            $result = $curl->exec();
            if ($curl->getResultCode() != 200) {
                $webhook_history->setStatus(WebhookHistory::STATUS_ERROR);
                $fields_to_save[] = 'status';
                $webhook_history->setCreateDate(date(DATE_ATOM, time()));
                $fields_to_save[] = 'create_date';
                $webhook_history->setResponse(['errors' => addslashes($curl->getResponse())]);
            } else
                $webhook_history->setResponse(['success' => $result]);
            $webhook_history->save($fields_to_save);
        }
        return ['success' => 'Webhooks ' . $name .' done for supplier #' . $supplier_id . ' warehouse #' . $warehouse_id];
    }

    public static function orderStatusChange($order_id, $statuses)
    {
        if (empty($order_id))
            return ['error' => 'Empty order_id webhook orderStatusChange'];
        $order = Orders::getById($order_id);
        if (empty($order))
            return ['error' => 'Wrong order #' . $order_id . ' webhook orderStatusChange'];
        $order_data = [
            'Order_ID' => $order->getId(),
            'Customer_Reference' => $order->getRef(),
            'Customer_Reference_2' => $order->getRef2(),
            'Event_DateTime' => date_format(date_create(), "Y-m-d H:i:s.v"),
            'Order_Status' => $statuses[1],
            'Previous_Order_Status' => $statuses[0],
        ];
        return self::callback($order->getSupplierId(), $order->getWarehouseId(), Webhooks::ORDER_STATUS_CHANGE, $order->getId(), $order_data);
    }

    public static function getPackages($order_id, $virtual)
    {
        $method_name = "getPackagesDespatched$virtual";
        if (!in_array($method_name, get_class_methods(Orders::class)))
            return false;
        return Orders::$method_name($order_id);
    }

    public static function orderDespatch($order_id, $virtual = null)
    {
        if (empty($order_id))
            return ['error' => 'Empty order_id webhook orderDespatch'];
        $order = Orders::getById($order_id);
        if (empty($order))
            return ['error' => 'Wrong order #' . $order_id . ' webhook orderDespatch'];
        $packages = self::getPackages($order->getId(), $virtual);
        if ($packages === false)
            return ['error' => 'No packages for order #' . $order_id . ' webhook orderDespatch'];
        $shipper = Orders::getShipper($order->getId());
        $order_data = [
            'Order_ID' => $order->getId(),
            'Customer_Reference' => $order->getRef(),
            'Customer_Reference_2' => $order->getRef2(),
            'Despatch_DateTime' => date_format(date_create(), "Y-m-d H:i:s.v"),
            'Shipment_Carrier' => $shipper['code'] ?? '',
            'Shipment_Service' => $shipper['scode'] ?? '',
            'Packages' => $packages,
        ];
        return self::callback($order->getSupplierId(), $order->getWarehouseId(), Webhooks::ORDER_DESPATCH, $order->getId(), $order_data);
    }
}
