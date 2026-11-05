<?php

namespace app\core\models;

use app\core\entities\MovementRequestHistory;

class  MovementRequestHistories
{
    private static function save($data)
    {
        $request_history = new MovementRequestHistory($data);
        $request_history->save(array_keys($data));
    }

    public static function create($request_id, $user_id)
    {
        self::save([
            'request_id' => $request_id,
            'event' => MovementRequestHistory::EVENT_CREATE,
            'user_id' => $user_id,
        ]);
    }

    public static function cancel($request_id, $user_id)
    {
        self::save([
            'request_id' => $request_id,
            'event' => MovementRequestHistory::EVENT_CANCEL,
            'user_id' => $user_id,
        ]);
    }

    public static function movement(int $request_id, int $movement_id, int $qty, int $user_id)
    {
        self::save([
            'request_id' => $request_id,
            'event' => MovementRequestHistory::EVENT_MOVEMENT,
            'user_id' => $user_id,
            'additional_info' => ['movement_id' => $movement_id, 'quantity' => $qty],
        ]);
    }

    public static function close(int $request_id, int $user_id)
    {
        self::save([
            'request_id' => $request_id,
            'event' => MovementRequestHistory::EVENT_CLOSE,
            'user_id' => $user_id,
        ]);
    }
}