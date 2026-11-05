<?php

namespace app\core\models\perms;

use app\backend\models\Users;

class AccessScope
{
    public const ACCESS_VIRTUAL = "virtual";
    public const ACCESS_PICKING = "picking";
    public const ACCESS_STOCK = "stock";
    public const ACCESS_PACKING = "packing";
    public const ACCESS_RETURNS = "returns";
    public const ACCESS_INVENTORY = "inventory";
    public const ACCESS_ORDERS = "orders";
    public const ACCESS_SUPERADMIN = "superadmin";
    public const ACCESS_RECEPTION = "reception";
    public const ACCESS_MOVEMENT = "movement";
    public const ACCESS_CONTROL_PANEL = "control_panel";
    public const ACCESS_API = "api";
    public const ACCESS_USERS = "users";
    public const ACCESS_WORKERS = "workers";
    public const ACCESS_ASNS = "asns";

    public static function getAccessScope($scope): array
    {
        return array_filter(array_map(function($scope) {
            return Users::$allScopes[$scope] ?? '';
        }, $scope));
    }

    public static function getAccessScopeForSupplierAdmin(): array
    {
        return self::getAccessScope([
            self::ACCESS_USERS,
            self::ACCESS_CONTROL_PANEL,
            self::ACCESS_ORDERS,
            self::ACCESS_STOCK,
            self::ACCESS_API
        ]);
    }

    public static function getAccessScopeForSupplierUser(): array
    {
        return self::getAccessScope([
            self::ACCESS_ORDERS,
            self::ACCESS_STOCK,
            self::ACCESS_API
        ]);
    }

    public static function getAccessScopeForManager(): array
    {
        return self::getAccessScope(array_merge([
            self::ACCESS_WORKERS,
            self::ACCESS_CONTROL_PANEL
        ], self::$worker_gr));
    }

    public static $worker_gr = [
        -10 => self::ACCESS_RECEPTION,
        -11 => self::ACCESS_MOVEMENT,
        -12 => self::ACCESS_PICKING,
        -13 => self::ACCESS_PACKING,
        -14 => self::ACCESS_INVENTORY,
        -15 => self::ACCESS_RETURNS
    ];

    public static function getAccessScopeForWorker(): array
    {
        return self::getAccessScope(self::$worker_gr);
    }



}