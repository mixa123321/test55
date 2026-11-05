<?php

namespace app\core\statical;

class Account
{
    private static $typeAccount = [
        1 => 'Regular',
        2 => 'Cross Dock',
        3 => 'Wholesale'
    ];

    public static function getTypeAccount($account = '')
    {
        if ($account === '')
            return array_keys(self::$typeAccount);
        return array_search($account, self::$typeAccount);
    }

    public static function isTypeAccount($account)
    {
        return isset(self::$typeAccount[$account]);
    }

    public static function getNameAccount($code)
    {
        return empty(self::$typeAccount[$code]) ? null : self::$typeAccount[$code];
    }

    public static function getAll()
    {
        return self::$typeAccount;
    }
}
