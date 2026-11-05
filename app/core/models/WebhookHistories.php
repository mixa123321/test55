<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\WebhookHistory;

class WebhookHistories
{
    public static function createInternal($name): WebhookHistory
    {
        $webhook_history = new WebhookHistory();
        $webhook_history->setName($name);
        $webhook_history->setExternal(0);
        $webhook_history->setStatus(WebhookHistory::STATUS_SUCCESS);
        $webhook_history->setCreateDate(date(DATE_ATOM, time()));
        $webhook_history->save(['name', 'external', 'status', 'create_date']);
        return $webhook_history;
    }

    private static function add($name, $number, $json_data, $user_id, $external): WebhookHistory
    {
        $webhook_history = new WebhookHistory();
        $webhook_history->setName($name);
        $webhook_history->setExternal($external);
        $webhook_history->setNumber($number);
        $webhook_history->setJsonData($json_data);
        $webhook_history->setStatus(WebhookHistory::STATUS_SUCCESS);
        $webhook_history->setUserId($user_id);
        $webhook_history->setCreateDate(date(DATE_ATOM, time()));
        $webhook_history->save(['name', 'external', 'number', 'json_data', 'status', 'create_date', 'user_id']);
        return $webhook_history;
    }

    public static function addExternal($name, $number, $json_data, $user_id): WebhookHistory
    {
        return self::add($name, $number, $json_data, $user_id, 1);
    }

    public static function addInternal($name, $number, $json_data, $user_id): WebhookHistory
    {
        return self::add($name, $number, $json_data, $user_id, 2);
    }

    public static function getById($id): ?WebhookHistory
    {
        $webhook_data = (new SafeMysql())->fetchOne("SELECT * FROM webhook_history WHERE id = :id", ['id' => (int)$id]);
        if (empty($webhook_data))
            return null;
        return new WebhookHistory($webhook_data);
    }

    public static function getExternalByOrderId($order_id): array
    {
        return (new SafeMysql())->fetchAll("
            SELECT wh.*, u.login
            FROM webhook_history wh
                LEFT JOIN users u ON wh.user_id = u.id 
            WHERE wh.name IN ('OrderStatusChange', 'ShipConfirm') AND wh.external = 1 AND wh.number = :order_id",
            [
                'order_id' => $order_id
            ]
        );
    }

    public static function getExternalByASNId($ASN_id): array
    {
        return (new SafeMysql())->fetchAll("
            SELECT wh.*, u.login
            FROM webhook_history wh
                LEFT JOIN users u ON wh.user_id = u.id 
            WHERE wh.name IN ('InventoryReceived') AND wh.external = 1 AND wh.number = :ASN_id",
            [
                'ASN_id' => $ASN_id
            ]
        );
    }

    public static function changeNumber($id, $number)
    {
        $webhook_history = new WebhookHistory();
        $webhook_history->setId($id);
        $webhook_history->setNumber($number);
        $webhook_history->save(['id', 'number']);
    }
}