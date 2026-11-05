<?php

namespace app\core\statical;

class Frequency
{
    private static $frequency = [
        1 => 'Weekly',
        2 => 'Monthly',
        3 => 'Quarterly'
    ];

    public static function getFrequency($account = '')
    {
        if ($account === '')
            return array_keys(self::$frequency);
        return array_search($account, self::$frequency);
    }

    public static function isFrequency($account)
    {
        return isset(self::$frequency[$account]);
    }

    public static function getNameFrequency($code)
    {
        return empty(self::$frequency[$code]) ? null : self::$frequency[$code];
    }

    public static function getAll()
    {
        return self::$frequency;
    }
}
