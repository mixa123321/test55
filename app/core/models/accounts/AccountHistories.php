<?php

namespace app\core\models\accounts;

use app\core\entities\AccountHistory;
use app\core\interfaces\AccessObjectEntity;
use app\core\services\crypto\Redactor;
use http\Exception\InvalidArgumentException;

class AccountHistories
{

    protected static function store($data)
    {
        (new AccountHistory($data))->save(array_keys($data));
    }

    public static function handleDifference(AccessObjectEntity $object, AccessObjectEntity $old_object, array $except = [AccessObjectEntity::IDENT]): array
    {
        $diff = [];
        $old_fields = $old_object->toArray();
        foreach ($object->toArray() as $field => $value) {
            if (in_array($field, $except))
                continue;
            if ($value != $old_fields[$field])
                $diff[$field] = [
                    'old' => $old_fields[$field],
                    'new' => $value
                ];
        }
        return $diff;
    }

    protected static function initData($object_id, $object_type, $event, $additional, $user_id): array
    {
        if (!in_array($object_type, AccessObjectEntity::OBJECTS))
            throw new InvalidArgumentException('Undefined object type');
        return [
            'object_id' => $object_id,
            'object_type' => $object_type,
            'event' => $event,
            // A user's API key (and password hash) must not stay readable in history; a role's api_key is only a flag
            'additional' => json_encode($object_type === AccessObjectEntity::OBJECT_USER && is_array($additional) ? Redactor::redact($additional) : $additional),
            'user_id' => $user_id
        ];
    }

    protected static function storeCreate($object_id, $object_type, $additional, $user_id)
    {
        self::store(self::initData($object_id, $object_type, AccountHistory::EVENT_CREATE, $additional, $user_id));
    }

    protected static function storeUpdate($object_id, $object_type, $diff, $user_id)
    {
        self::store(self::initData($object_id, $object_type, AccountHistory::EVENT_UPDATE, $diff, $user_id));
    }

    public static function delete($object_id, $object_type, AccessObjectEntity $object, $user_id)
    {
        self::store(self::initData($object_id, $object_type, AccountHistory::EVENT_DELETE, $object->toArray(), $user_id));
    }

}