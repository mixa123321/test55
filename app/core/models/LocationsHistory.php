<?php

namespace app\core\models;

use app\core\entities\LocationHistory;

class LocationsHistory
{
    public static function addRecord($location_id, $user_id, $event, $addition)
    {
        $loc_history = new LocationHistory([
            'location_id' => $location_id,
            'user_id' => $user_id,
            'event' => $event,
            'addition' => json_encode($addition),
        ]);
        $loc_history->save(['location_id', 'user_id', 'event', 'addition']);
        return $loc_history->getId();
    }
}
