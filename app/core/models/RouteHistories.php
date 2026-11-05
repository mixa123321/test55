<?php

namespace app\core\models;

use app\core\entities\Route;
use app\core\entities\RouteHistory;

class RouteHistories
{

    private static function store($data)
    {
        (new RouteHistory($data))->save(array_keys($data));
    }

    public static function create(Route $route)
    {
        self::store([
            'route_id' => $route->getId(),
            'event_type' => RouteHistory::EVENT_CREATE,
            'addition' => json_encode($route->toArray()),
        ]);
    }

    public static function update(Route $route, Route $old_route, $user_id)
    {
        $diff = [];
        $old_fields = $old_route->toArray();
        foreach ($route->toArray() as $field => $value) {
            if ($field === 'id')
                continue;
            if ($value != $old_fields[$field])
                $diff[$field] = [
                    'old' => $old_fields[$field],
                    'new' => $value
                ];
        }
        if (empty($diff))
            return;
        self::store([
            'route_id' => $route->getId(),
            'event_type' => RouteHistory::EVENT_UPDATE,
            'addition' => json_encode($diff),
            'user_id' => $user_id
        ]);
    }

    public static function addChild(Route $route, Route $child_route, $user_id)
    {
        $child_fake = clone $child_route;
        $child_fake->setParentId(null);
        self::update($child_route, $child_fake, $user_id);
        self::store([
            'route_id' => $route->getId(),
            'event_type' => RouteHistory::EVENT_ADD_CHILD,
            'addition' => json_encode([
                'route_id' => $child_route->getId()
            ]),
            'user_id' => $user_id
        ]);
    }

    public static function removeChild(Route $route, Route $child_route, $user_id)
    {
        $child_fake = clone $child_route;
        $child_fake->setParentId($route->getId());
        self::update($child_route, $child_fake, $user_id);
        self::store([
            'route_id' => $route->getId(),
            'event_type' => RouteHistory::EVENT_REMOVE_CHILD,
            'addition' => json_encode([
                'route_id' => $child_route->getId()
            ]),
            'user_id' => $user_id
        ]);
    }

    public static function lock($route_id)
    {
        self::store([
            'route_id' => $route_id,
            'event_type' => RouteHistory::EVENT_LOCK,
            'user_id' => -1
        ]);
    }

    public static function unlock($route_id)
    {
        self::store([
            'route_id' => $route_id,
            'event_type' => RouteHistory::EVENT_UNLOCK,
            'user_id' => -1
        ]);
    }

    public static function delete(Route $route, $user_id)
    {
        self::store([
            'route_id' => $route->getId(),
            'event_type' => RouteHistory::EVENT_DELETE,
            'addition' => $route->toArray(),
            'user_id' => $user_id
        ]);
    }

}