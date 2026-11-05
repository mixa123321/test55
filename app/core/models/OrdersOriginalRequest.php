<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\statical\Sources;

class OrdersOriginalRequest
{

    public static function saveOriginalRequest($order_id, $request_data, $response_data)
    {
        (new SafeMysql())->execute("INSERT INTO orders_original_requests 
                    SET order_id = :order_id,
                    request = :request,
                    response = :response", [
            'order_id' => (int)$order_id,
            'request' => $request_data,
            'response' => $response_data,
        ]);
    }

    public static function getOriginalRequest($order_id): ?array
    {
        $order_id = (int)$order_id;
        $res = (new SafeMysql())->fetchOne("SELECT request, response FROM orders_original_requests where order_id = :order_id", ['order_id' => $order_id]);
        if (!empty($res))
            return $res;
        else return null;
    }

    public static function changeResponse($order_id, $response_data)
    {
        (new SafeMysql())->execute("UPDATE orders_original_requests 
                SET response = :response
                WHERE order_id = :order_id", [
            'response' => $response_data,
            'order_id' => (int)$order_id,
        ]);
    }

    public static function hashRequest($order_id)
    {
        $order_id = (int)$order_id;
        $record = (new SafeMysql())->fetchOne("SELECT oor.id, oor.request, oor.response, o.id as order_id, o.type_create FROM orders o
                                                JOIN orders_original_requests oor ON o.id = oor.order_id
                                                    WHERE o.id = :order_id LIMIT 1", ['order_id' => $order_id]);
        if (empty($record))
            return;
        self::handleHashRequest($record);
    }

    public static function handleHashRequest($record)
    {
        $column = null;
        switch ($record['type_create']) {
            case Sources::SOURCE_API:
                $column = 'request';
                $data = self::handleHashRequestAPI($record[$column]);
                break;
            case Sources::SOURCE_UPLOAD:
                $column = 'request';
                $data = self::handleHashRequestUpload($record[$column]);
                break;
            case Sources::SOURCE_FLAT:
                $column = 'request';
                $data = self::handleHashRequestFlat($record[$column]);
                break;
            case Sources::SOURCE_WOOCOMMERCE:
                $column = 'response';
                $data = self::handleHashRequestWC($record[$column]);
                break;
            case Sources::SOURCE_SHOPIFY:
                $column = 'response';
                $data = self::handleHashRequestShopify($record[$column]);
                break;
            case Sources::SOURCE_MAGENTO:
                $column = 'response';
                $data = self::handleHashRequestMagento($record[$column]);
                break;
        }

        if (!empty($column) && !empty($data) && is_array($data))
            (new SafeMysql())->execute("UPDATE orders_original_requests
                        SET $column = :data WHERE id = :id", [
                'data' => json_encode($data),
                'id' => (int)$record['id'],
            ]);
    }

    private static function handleHashRequestAPI($data): ?array
    {
        if (empty($request = json_decode($data, TRUE)))
            return null;
        $fields = [
            'CompanyName',
            'ContactName',
            'Address1',
            'Address2',
            'Address3',
            'ContactTel',
            'ContactEmail',
            'TaxId',
        ];
        $update = false;
        foreach ($request as $key => $order_form) {
            if (!empty($order_form['Delivery'])) {
                $update = true;
                $Delivery = $order_form['Delivery'];
                foreach ($fields as $field)
                    if (!empty($Delivery[$field]))
                        $Delivery[$field] = md5($Delivery[$field]);
                $request[$key]['Delivery'] = $Delivery;
            }
        }
        return $update ? $request : null;
    }

    private static function handleHashRequestUpload($data)
    {
        if (empty($request = json_decode($data, TRUE)))
            return null;
        if (empty($request['data']))
            return null;
        $data = $request['data'];
        foreach ($data as &$row) {
            if (empty($row) || !is_array($row))
                continue;
            array_walk($row, function (&$item, $key) {
                if (in_array($key, [6,7,8,9,10,11,12]) && !empty($item))
                    $item = md5($item);
            });
        }
        $request['data'] = $data;
        return $request;
    }

    private static function handleHashRequestFlat($data)
    {

        if (empty($request = json_decode($data, TRUE)))
            return null;
        if (empty($request['data']))
            return null;
        $data = $request['data'];
        foreach ($data as &$row) {
            if (empty($row) || !is_array($row))
                continue;
            array_walk($row, function (&$item, $key) {
                if (in_array($key, [1, 2, 3, 8]) && !empty($item))
                    $item = md5($item);
            });
        }
        $request['data'] = $data;
        return $request;
    }

    private static function handleHashRequestWC($data): ?array
    {
        if (empty($response = json_decode($data, TRUE)))
            return null;
        $hash = function (&$delivery) {
            if (empty($delivery) || !is_array($delivery))
                return;
            array_walk($delivery, function (&$d, $field){
                if (!empty($d) && in_array($field, ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'email', 'phone']))
                    $d = md5($d);
            });
        };
        if (!empty($response['billing'])) {
            $delivery = $response['billing'];
            $hash($delivery);
            $response['billing'] = $delivery;
        }
        if (!empty($response['shipping'])) {
            $delivery = $response['shipping'];
            $hash($delivery);
            $response['shipping'] = $delivery;
        }
        return $response;
    }

    private static function handleHashRequestShopify($data): ?array
    {
        if (empty($response = json_decode($data, TRUE)))
            return null;
        $hash = function (&$delivery) {
            if (empty($delivery) || !is_array($delivery))
                return;
            array_walk($delivery, function (&$d, $field) {
                if (!empty($d) && in_array($field, ['company', 'name', 'first_name', 'last_name', 'address1', 'address2', 'phone', 'email']))
                    $d = md5($d);
            });
        };
        $hash($response);
        $update = false;
        if (!empty($response['shipping_address'])) {
            $delivery = $response['shipping_address'];
            $hash($delivery);
            $response['shipping_address'] = $delivery;
            $update = true;
        }
        if (!empty($response['billing_address'])) {
            $delivery = $response['billing_address'];
            $hash($delivery);
            $response['billing_address'] = $delivery;
            $update = true;
        }
        if (!empty($response['customer'])) {
            $delivery = $response['customer'];
            $hash($delivery);
            $response['customer'] = $delivery;
            $update = true;
            if (!empty($response['customer']['default_address'])) {
                $delivery = $response['customer']['default_address'];
                $hash($delivery);
                $response['customer']['default_address'] = $delivery;
            }

            if (!empty($response['customer']['origin_location'])) {
                $delivery = $response['customer']['origin_location'];
                $hash($delivery);
                $response['customer']['origin_location'] = $delivery;
            }
        }
        return $update ? $response : [];
    }

    private static function handleHashRequestMagento($data)
    {
        if (empty($response = json_decode($data, TRUE)))
            return null;
        $hash = function (&$delivery) {
            array_walk($delivery, function (&$d, $field){
                if (!empty($d) && in_array($field, ['company', 'firstname', 'lastname', 'telephone', 'email']))
                    $d = md5($d);
            });

            if (!empty($delivery['street'][0]))
                $delivery['street'][0] = md5($delivery['street'][0]);
            if (!empty($delivery['street'][1]))
                $delivery['street'][1] = md5($delivery['street'][1]);
            if (!empty($delivery['street'][2]))
                $delivery['street'][2] = md5($delivery['street'][2]);
        };

        if (!empty($response['billing_address'])) {
            $delivery = $response['billing_address'];
            $hash($delivery);
            $response['billing_address'] = $delivery;
        }
        if (!empty($response['extension_attributes']['shipping_assignments'][0]['shipping']['address'])) {
            $delivery = $response['extension_attributes']['shipping_assignments'][0]['shipping']['address'];
            $hash($delivery);
            $response['extension_attributes']['shipping_assignments'][0]['shipping']['address'] = $delivery;
        }
        return $response;
    }

}