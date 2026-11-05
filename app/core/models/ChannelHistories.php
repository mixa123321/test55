<?php

namespace app\core\models;

use app\core\entities\ChannelHistory;

class ChannelHistories
{

    public static function setEvent($data)
    {
        $channelHistory = new ChannelHistory($data);
        $channelHistory->save(array_keys($data));
    }

}