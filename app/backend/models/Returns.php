<?php

namespace app\backend\models;

use app\core\models\ReturnSAPI;
use app\core\entities\Returns as ReturnsEntity;
use app\core\Storage;

class Returns extends \app\core\models\Returns
{
    const EVENT_NO_STATUS   = 0;
    const EVENT_NOT_MAPPED  = 1;
    const EVENT_DELAYED     = 2;
    const EVENT_NEW         = 100;
    const EVENT_RECEIVED    = 200;
    const EVENT_TRANSIT     = 500;
    const EVENT_DELIVERED   = 1000;
    const EVENT_CANCELLED   = 1100;
    const EVENT_LOST        = 1200;
    const EVENT_RETURN      = 2000;

    public static function getReturnLabelFullPath($return_carrier_id, $relative_path)
    {
        return str_replace("\\", "/", Storage::getFile(RETURNS_LABELS_STORAGE, $return_carrier_id . DIRECTORY_SEPARATOR . ltrim($relative_path, "/")));
    }

    public static function checkActivate()
    {
        $returns = self::getNew();
        if (empty($returns))
            return;
        foreach ($returns as $return_data) {
            $events = ReturnSAPI::sendReturnTrackingEvents($return_data['tracking_number'], (int)$return_data['is_test']);
            if (!empty($events['errors'])) {
                echo "{$return_data['id']} - error\n";
//                self::returnsStatus($return_data['id'], ReturnsEntity::ERROR);
                continue;
            }
            $path = [];
            foreach ($events as $event)
                if ($event['event_code'] != self::EVENT_NEW)
                    $path[] = $event['event_code'];
            if (empty($path))
                continue;
            if (in_array(self::EVENT_CANCELLED, $path)) {
                echo "{$return_data['id']} - cancelled\n";
//                self::returnsStatus($return_data['id'], ReturnsEntity::CANCEL);
                continue;
            }
            $active = array_intersect([self::EVENT_RECEIVED, self::EVENT_TRANSIT, self::EVENT_DELIVERED, self::EVENT_LOST, self::EVENT_RETURN], $path);
            if (!empty($active))
                echo "{$return_data['id']} - active (" . implode(',', $path) . ")\n";
//                self::returnsStatus($return_data['id'], ReturnsEntity::ACTIVE);
        }
    }

    private static function returnsStatus($return_id, $status)
    {
        $return_obj = new ReturnsEntity(['id' => $return_id]);
        $return_obj->setStatus($status);
        $return_obj->setUpdateDate(time());
        $return_obj->save(['id', 'status', 'update_date']);
    }
}
