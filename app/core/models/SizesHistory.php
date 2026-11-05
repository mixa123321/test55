<?php

namespace app\core\models;

use app\core\entities\SizeHistory;

class SizesHistory
{
    public static function addRecord($size_id, $user_id, $event, $addition)
    {
        $size_history = new SizeHistory([
            'size_id' => $size_id,
            'user_id' => $user_id,
            'event' => $event,
            'addition' => json_encode($addition),
        ]);
        $size_history->save(['size_id', 'user_id', 'event', 'addition']);
        return $size_history->getId();
    }
}
