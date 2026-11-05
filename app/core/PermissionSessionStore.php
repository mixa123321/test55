<?php

namespace app\core;

use app\core\entities\Perm;

class PermissionSessionStore
{

    private const DIFF_TIMESTAMP = 60;

    public static function set($route_id, $user_id, Perm $perm)
    {
        $_SESSION['permissions'][$user_id . '_' . $route_id] = [
            'perm' => $perm->toArray(),
            'timestamp' => time()
        ];
    }

    public static function get($route_id, $user_id): ?Perm
    {
        if (empty($_SESSION['permissions'][$user_id . '_' . $route_id]))
            return null;
        if (time() - ($_SESSION['permissions'][$user_id . '_' . $route_id]['timestamp'] ?? 0) > self::DIFF_TIMESTAMP) {
            unset($_SESSION['permissions'][$user_id . '_' . $route_id]);
            return null;
        }
        return Perm::init($_SESSION['permissions'][$user_id . '_' . $route_id]['perm'] ?? []);
    }

}