<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Delivery;
use app\backend\models\DeliveryHistories;
use app\core\entities\Order;
use app\core\statical\WithDate;

class Deliveries
{
    private static $main_fields = [
        'contact',
        'address1',
        'town',
        'postcode',
        'country_id',
    ];

    /**
     * @param $id
     * @param null $timestamp
     * @return Delivery|null
     */
    public static function getById($id, $timestamp = null)
    {
        if (is_null($timestamp)) {
            $data = (new SafeMysql())->fetchOne('SELECT * FROM deliveries WHERE id = :id', ['id' => (int)$id]);
            if (empty($data))
                $data = DeliveryHistories::restoreDeliveryById($id, date(WithDate::DATE_SQL_FORMAT));
            return !empty($data) ? new Delivery($data) : null;
        } else
            return new Delivery(DeliveryHistories::restoreDeliveryById($id, $timestamp));
    }

    public static function getByContact($name)
    {
        $delivery_info = (new SafeMysql())->fetchAll("SELECT * FROM deliveries WHERE contact = :name", ['name' => $name]);
        if (count($delivery_info) > 0) {
            $res = [];
            foreach ($delivery_info as $delivery)
                $res[] = new Delivery($delivery);
            return $res;
        }
        return [];
    }

    /**
     * @param Delivery $delivery
     */
    public static function getAlreadyExists(Delivery $delivery)
    {
        $exist_data = $params = [];
        foreach ($delivery->toArray() as $field => $value)
            if (in_array($field, self::$main_fields)) {
                $param = $field;
                $exist_data[] = "$field = :$param";
                // fields missing from the channel data stay null; rows are stored with '' (legacy compared with '')
                $params[$param] = (string)$value;
            }
        if (empty($exist_data))
            return [];
        return (new SafeMysql())->fetchAll('SELECT * FROM deliveries WHERE ' . implode(' AND ', $exist_data), $params);
    }

    private static function createNew($delivery_data, $user_id): Delivery
    {
        $fields = array_filter(array_keys($delivery_data), function ($f) {
            return $f !== 'id';
        });
        unset($delivery_data['id']);
        $delivery = new Delivery($delivery_data);
        $delivery->save($fields);
        DeliveryHistories::createDelivery($delivery->getId(), $user_id, $delivery_data);
        return $delivery;
    }

    private static function handleUpdate(Delivery $delivery, $user_id): Delivery
    {
        $diff = self::getDifferance($delivery, self::getById($delivery->getId()));
        if (!empty($diff)) {
            DeliveryHistories::updateDelivery($delivery->getId(), $diff, $user_id);
            $delivery->save(array_keys($delivery->toArray()));
        }
        return $delivery;
    }

    private static function getDifferance(Delivery $new_delivery, Delivery $old_delivery): array
    {
        $new_delivery_arr = $new_delivery->toArray();
        $old_delivery_arr = $old_delivery->toArray();
        $data = [];
        foreach ($new_delivery_arr as $field => $value) {
            if ($value !== ($old_delivery_arr[$field] ?? null))
                $data[$field] = $value;
        }
        return $data;
    }

    public static function handleDeliveryReturningId(Delivery $delivery, $user_id, Order $order = null)
    {
        $delivery_data = $delivery->toArray();
        $fields = array_filter(array_keys($delivery_data), function ($f) {
            return $f !== 'id';
        });

        // поля для обновления
        $up_fields = array_diff($fields, self::$main_fields);

        //Проверка на существование такого же получателя по основным полям
        $delivery_exists = self::getAlreadyExists($delivery);
        if (empty($delivery_exists)) {
            //если такового нет - сохраняем значения и возвращаем новый ID для заказа
            if (!is_null($order) && count(self::getExistActiveOrderIds($order->getDeliveryId())) === 1) {
                //обработатка старого значения
                $delivery->setId($order->getDeliveryId());
                return self::handleUpdate($delivery, $user_id)->getId();
            } else
                return self::createNew($delivery_data, $user_id)->getId();
        }

        //Собераем массив подобных для объединения.
        //Условиние - нет активных заказов
        $merge_deliveries = [];
        foreach ($delivery_exists as $delivery_exist_item)
            if (!self::existActiveOrders($delivery_exist_item['id']))
                $merge_deliveries[] = $delivery_exist_item;

        if (!empty($merge_deliveries)) {
            //если таких получателей больше одного - оставляем первый и удаляем остальные.
            if (count($merge_deliveries) > 1) {
                $main_del = array_shift($merge_deliveries);
                $destroy_deliveries = [];
                foreach ($merge_deliveries as $merge_delivery)
                    $destroy_deliveries[] = $merge_delivery['id'];
                DeliveryHistories::mergeDelivery($main_del['id'], $destroy_deliveries, $user_id);
                (new SafeMysql())->execute('DELETE FROM deliveries WHERE id IN (:ids)', ['ids' => $destroy_deliveries]);
            } else {
                $main_del = $merge_deliveries[0];
            }

            //У первого подобного обновляем поля
            $delivery_update = [];
            foreach ($main_del as $m_field => $main_del_value) {
                if ((in_array($m_field, $up_fields) && $main_del_value != $delivery_data[$m_field]) && $m_field !== 'id')
                    $delivery_update[$m_field] = $delivery_data[$m_field];
            }
            if (!empty($delivery_update)) {
                DeliveryHistories::updateDelivery($main_del['id'], $delivery_update, $user_id);
                $delivery_update['id'] = $main_del['id'];
                $delivery = new Delivery($delivery_update);
                $delivery->save(array_keys($delivery_update));
            }
            return $main_del['id'];
        }

        //Остались получатели с активными заказами.
        //Ищем полностью подобный и возвращаем его ID
        $equal_delivery = self::getEqual($delivery, array_map(function($del) {
            return new Delivery($del);
        }, $delivery_exists));
        if (!empty($equal_delivery))
            return $equal_delivery->getId();

        //обработка заказа с данным получателем
        if (!is_null($order)) {
            $delivery_id = $order->getDeliveryId();
            $order_ids = self::getExistActiveOrderIds($delivery_id);
            if (in_array($order->getId(), $order_ids) && count($order_ids) === 1) {
                $delivery->setId($delivery_id);
                return self::handleUpdate($delivery, $user_id)->getId();
            }
        }

        //создание нового
        return self::createNew($delivery_data, $user_id)->getId();
    }

    private static function getEqual(Delivery $delivery, array $delivery_exists): ?Delivery
    {
        $exist = false;
        foreach ($delivery_exists as $delivery_by_main) {
            $exist |= $delivery->equals($delivery_by_main);
            if ($exist) {
                return $delivery_by_main;
            }
        }
        return null;
    }

    private static function existActiveOrders($delivery_id): bool
    {
        $delivery_id = (int)$delivery_id;
        if ($delivery_id > 0) {
            $res = (new SafeMysql())->fetchAll("SELECT 1 FROM orders
                    WHERE status != :status AND delivery_id = :delivery_id", ['status' => Order::DESPATCHED_NUM, 'delivery_id' => $delivery_id]);
            return !empty($res);
        }
        return false;
    }

    public static function getExistActiveOrderIds($delivery_id): array
    {
        $delivery_id = (int)$delivery_id;
        if ($delivery_id > 0) {
            $order_ids = (new SafeMysql())->fetchArrayOneField('SELECT id FROM orders WHERE delivery_id = :delivery_id AND status != :status', ['delivery_id' => $delivery_id, 'status' => Order::DESPATCHED_NUM]);
            return array_map(function($order_id) {
                return (int)$order_id;
            }, $order_ids);
        }
        return [];
    }

}
