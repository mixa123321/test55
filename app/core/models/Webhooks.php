<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Webhook;
use app\core\services\crypto\StoredSecret;

class Webhooks
{
    const ORDER_STATUS_CHANGE = 'OrderStatusChange';
    const ORDER_DESPATCH = 'OrderDespatch';

    const WEBHOOK_LIST = [
        self::ORDER_STATUS_CHANGE,
        self::ORDER_DESPATCH
    ];

    public static function getById($id)
    {
        $webhook_data = (new SafeMysql())->fetchOne("SELECT * FROM webhooks WHERE id = :id", ['id' => (int)$id]);
        if (empty($webhook_data))
            return null;
        return new Webhook($webhook_data);
    }

    public static function getByName($name, $user_id)
    {
        $webhook_data = (new SafeMysql())->fetchOne(
            "SELECT * FROM webhooks WHERE name = :name AND user_id = :user_id",
            ['name' => $name, 'user_id' => (int)$user_id]
        );
        if (empty($webhook_data))
            return null;
        return new Webhook($webhook_data);
    }

    public static function getByUserId($user_id)
    {
        return (new SafeMysql())->fetchAll("
            SELECT name WebHookName,
                   IF(subscribed = 1, 'true', 'false') Subscribed,
                   callback CallbackURL,
                   update_date ModifiedDateTime
            FROM webhooks
            WHERE user_id = :user_id", ['user_id' => (int)$user_id]);
    }

    public static function getBySupplier($name, $supplier_id, $warehouse_id)
    {
        $sql = "SELECT w.callback, w.user_id, u.api_key 
                FROM webhooks w
                    JOIN supplier_users su ON w.user_id = su.user_id AND su.supplier_id = :supplier_id
                    JOIN users u ON w.user_id = u.id AND u.warehouse_id = :warehouse_id
                WHERE w.name = :name AND w.callback > '' AND w.subscribed = 1";
        $webhooks = (new SafeMysql())->fetchAll($sql, [
            'supplier_id' => (int)$supplier_id,
            'warehouse_id' => (int)$warehouse_id,
            'name' => $name,
        ]);
        // The user's API key (encrypted at rest) is sent back as the webhook's Basic-auth password
        return array_map(function (array $webhook) {
            return StoredSecret::decryptFields($webhook, ['api_key']);
        }, $webhooks);
    }
}
