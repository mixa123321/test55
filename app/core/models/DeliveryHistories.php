<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class DeliveryHistories
{
    public static function restoreDeliveryById($id, $timestamp)
    {
        $id = (int)$id;
        $sql = "SELECT comment, `fields`, create_date FROM delivery_history 
            WHERE delivery_id = :id AND `comment` IN ('create', 'update')";
        $params = ['id' => $id];
        if (!empty($timestamp)) {
            $sql .= " AND create_date <= :timestamp";
            $params['timestamp'] = $timestamp;
        }    
        $sql .= " ORDER BY create_date";
        $res = (new SafeMysql())->fetchAll($sql, $params);
        $data = [];
        foreach ($res as $item)
            if ($item['comment'] == 'create') {
                $data = json_decode($item['fields'], true);
                break;
            }
        foreach ($res as $item)
            if ($item['comment'] == 'update') {
                $fields = json_decode($item['fields'], true);
                if (!empty($fields))
                    $data = array_merge($data, $fields);
            }
        return $data;
    }

    public static function restoreDeliveryByIds($ids, $timestamp)
    {
        if (empty($ids) || !is_array($ids))
            return [];
        $ids = array_map('intval', $ids);
        $sql = "SELECT * FROM deliveries WHERE id IN (:ids)";
        $params = ['ids' => $ids];
        $deliveries = (new SafeMysql())->fetchAssocWithKey($sql, $params, 'id');
        $timestamp_sql = "
            SELECT delivery_id, `comment`, `fields`, create_date
            FROM delivery_history
            WHERE delivery_id IN (:ids)
            AND `comment` IN ('create', 'update')
        ";
        if (!empty($timestamp)) {
            $timestamp_sql .= " AND create_date <= :timestamp";
            $params['timestamp'] = $timestamp;
        }
        $timestamp_sql .= " ORDER BY delivery_id, create_date";
        $res = (new SafeMysql())->fetchAll($timestamp_sql, $params);
        foreach ($res as $item)
            if ($item['comment'] == 'create')
                $deliveries[$item['delivery_id']] = json_decode($item['fields'], true);
        foreach ($res as $item)
            if ($item['comment'] == 'update')
                $deliveries[$item['delivery_id']] = array_merge($deliveries[$item['delivery_id']], json_decode($item['fields'], true));
        return $deliveries;
    }

    public static function isHash($delivery_id)
    {
        $delivery_id = (int)$delivery_id;
        return (new SafeMysql())->fetchScalar("
            SELECT 1 
            FROM delivery_history
            WHERE delivery_id = :delivery_id AND `comment` = 'hash'", ['delivery_id' => $delivery_id]);
    }
}