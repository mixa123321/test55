<?php

namespace app\core\models\perms;

use app\core\entities\RouteAccessHistory;
use app\core\interfaces\AccessObjectEntity;
use http\Exception\InvalidArgumentException;

class RouteAccessHistories
{

    public static function set($route_id, $object_type, $object_id, $access, $create_user_id = -1, $addition = [])
    {
        if (!in_array($object_type, AccessObjectEntity::ROUTE_ACCESS_OBJECTS))
            throw new InvalidArgumentException('Undefined object type');
        $data = [
            'route_id' => $route_id,
            'object_type' => $object_type,
            'object_id' => $object_id,
            'access' => $access,
            'addition' => !empty($addition) ? json_encode($addition) : '',
            'create_user_id' => $create_user_id
        ];
        (new RouteAccessHistory($data))->save(array_keys($data));
    }

}