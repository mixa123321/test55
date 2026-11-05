<?php

namespace app\core\models;

use app\core\entities\CollectionTime;
use app\core\entities\CollectionTimeHistory;

class CollectionTimeHistories
{
    const EVENT_CREATE = 1;
    const EVENT_UPDATE = 2;

    public static function addRecord(CollectionTime $collection_time, int $event, int $user_id)
    {
        $history = new CollectionTimeHistory([
            'collection_time_id' => $collection_time->getId(),
            'event' => $event,
            'user_id' => $user_id,
            'additional' => $collection_time->toArray(),
        ]);
        $history->save(['id', 'collection_time_id', 'created_at', 'event', 'user_id', 'additional']);
    }
}