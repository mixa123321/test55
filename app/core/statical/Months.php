<?php

namespace app\core\statical;

class Months
{
    private static $months = [
        1 => 'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December'
    ];
    private static $daysMonth = [ 1 => 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];

    public static function getMonths($month = '')
    {
        if ($month === '')
            return array_keys(self::$months);
        return array_search($month, self::$months);
    }

    public static function isMonths($month)
    {
        return isset(self::$months[$month]);
    }

    public static function getNameMonth($code)
    {
        return empty(self::$months[$code]) ? null : self::$months[$code];
    }

    public static function getDaysInMonth($code, $year)
    {
        return empty(self::$months[$code]) ? null : (self::$daysMonth[$code] + ($year % 4 == 0 && $code == 2));
    }

    public static function getAll()
    {
        return self::$months;
    }
}
