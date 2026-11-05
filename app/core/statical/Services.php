<?php

namespace app\core\statical;

class Services
{
    private static $services = [
        0 => 'Other',
        'Counting',
        'Labelling',
        'Poly bagging',
        'Quality checking',
    ];

    public static function getService($service)
    {
        return empty(self::$services[$service]) ? null : self::$services[$service];
    }

    public static function getAll()
    {
        return self::$services;
    }
}
