<?php

namespace app\core\statical;

use app\core\entities\Location;

class TypeLocation
{
    private static $typeLocation = [
        Location::PF => 'Pick Face',
        Location::ST => 'Storage',                          // No limits?
        Location::OB => 'Order Bin',                        // полочки для посылки
        Location::CA => 'Trolley',                          // корзинки (тачки))
        Location::TP => 'Table Pack',                       // стол для упаковки
        Location::LP => 'Large Pack',                       // стол для упаковки
        Location::DH => 'Discrepancy Hold',
        Location::PA => 'PutAway',                          // зона приема товара
        Location::PR => 'Processing',                       // обработка товаров - не участвует в процессах
        // Location::US => 'User trolley',                     // virtual
    ];

    public static function getTypeLocation()
    {
        return array_keys(self::$typeLocation);
    }

    public static function isTypeLocation($location)
    {
        return isset(self::$typeLocation[$location]);
    }

    public static function getNameLocation($code)
    {
        return empty(self::$typeLocation[$code]) ? null : self::$typeLocation[$code];
    }

    public static function getAll()
    {
        return self::$typeLocation;
    }
}
