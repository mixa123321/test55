<?php

namespace app\api\models;

use app\core\entities\Webhook;

class Webhooks extends \app\core\models\Webhooks
{
    public static function subscribe($name, $value, $user_id)
    {
        $name = addslashes($name);
        $value = addslashes($value);
        $user_id = (int)$user_id;
        if (empty($name) || empty($value) || empty($user_id))
            return [false, ['code' => ErrorCodes::FIELD_REQUIRED, 'message' => 'Data is not found']];
        $webhook = self::getByName($name, $user_id);
        if (!empty($webhook)) {
            if ($webhook->getSubscribed())
                return [false, ['code' => ErrorCodes::WEBHOOK_SUBSCRIBED, 'message' => 'Webhook is already subscribed']];
            $webhook->setCallback($value);
            $webhook->setSubscribed(1);
            $webhook->setUpdateDate(date(DATE_ATOM, time()));
            $webhook->save(['id', 'callback', 'update_date', 'subscribed']);
            return [true];
        }
        $webhook = new Webhook([
            'name' => $name,
            'callback' => $value,
            'user_id' => $user_id,
            'update_date' => date(DATE_ATOM, time()),
            'subscribed' => 1,
        ]);
        $webhook->save(['name', 'callback', 'user_id', 'update_date', 'subscribed']);
        return [true];
    }

    public static function update($name, $subscribed, $callback, $user_id)
    {
        $name = addslashes($name);
        $user_id = (int)$user_id;
        if (empty($name) || empty($user_id))
            return [false, ['code' => ErrorCodes::FIELD_REQUIRED, 'message' => 'Data is not found']];
        $webhook = self::getByName($name, $user_id);
        if (empty($webhook))
            return [false, ['code' => ErrorCodes::WEBHOOK_NOT_FOUND, 'message' => 'Webhook is not found']];
        $fields_to_save = ['id'];
        if (!is_null($subscribed))
            if ($webhook->getSubscribed() != (int)$subscribed) {
                $webhook->setSubscribed($subscribed);
                $fields_to_save[] = 'subscribed';
            }
        if (!is_null($callback))
            if ($webhook->getCallback() != $callback) {
                $webhook->setCallback($callback);
                $fields_to_save[] = 'callback';
            }
        if (count($fields_to_save) == 1)
            return [false, ['code' => ErrorCodes::WEBHOOK_NOTHING, 'message' => 'Nothing to change']];
        $webhook->setUpdateDate(date(DATE_ATOM, time()));
        $fields_to_save[] = 'update_date';
        $webhook->save($fields_to_save);
        return [true];
    }

    public static function list($user_id)
    {
        return self::getByUserId($user_id);
    }
}
