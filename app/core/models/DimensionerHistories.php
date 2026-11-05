<?php

namespace app\core\models;

use app\core\entities\Dimensioner;
use app\core\entities\DimensionerHistory;

class DimensionerHistories
{
    const EVENT_CREATE  = 'create';
    const EVENT_UPDATE  = 'update';
    const EVENT_STATE   = 'state';
    const EVENT_MEASURE = 'measure';

    private static function add(Dimensioner $dimensioner, string $event, int $user_id)
    {
        $dimensioner_data = $dimensioner->toArray();
        unset($dimensioner_data['id']);
        $data = [
            'dimensioner_id' => $dimensioner->getId(),
            'event' => addslashes($event),
            'user_id' => $user_id,
            'dimensioner_params' => $dimensioner_data,
        ];
        $keys = array_keys($data);
        $temp = new DimensionerHistory($data);
        $temp->save($keys);
    }

    public static function create(Dimensioner $dimensioner, int $user_id)
    {
        self::add($dimensioner, self::EVENT_CREATE, $user_id);
    }

    public static function update(Dimensioner $dimensioner, int $user_id)
    {
        self::add($dimensioner, self::EVENT_UPDATE, $user_id);
    }

    public static function state(Dimensioner $dimensioner, int $user_id)
    {
        self::add($dimensioner, self::EVENT_STATE, $user_id);
    }

    public static function measure(Dimensioner $dimensioner, array $query, int $user_id)
    {
        if (!empty($query['ImageBase64']))
            $query['ImageBase64'] = '<skipped>';
        $data = [
            'dimensioner_id' => $dimensioner->getId(),
            'event' => self::EVENT_MEASURE,
            'user_id' => $user_id,
            'dimensioner_params' => $query,
        ];
        $keys = array_keys($data);
        $temp = new DimensionerHistory($data);
        $temp->save($keys);
    }
}
