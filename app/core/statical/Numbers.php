<?php

namespace app\core\statical;

class Numbers
{
    private static $numbs = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
    ];
    private static $size = 24;

    public static function getNumb($symb)
    {
        $main = 0;
        $base = 1;
        for ($i = strlen($symb) - 1; $i >= 0; $i--) {
            $pos = array_search($symb[$i], self::$numbs);
            if ($pos === false)
                return false;
            $main = $main + $base * ($pos + 1);
            $base = $base * self::$size;
        }
        return $main;
    }

    public static function getSymb($i)
    {
        if ((int)$i <= 0)
            return false;
        $res = '';
        while ($i) {
            $res = self::$numbs[($i - 1) % self::$size] . $res;
            $i = (int)(($i - 1) / self::$size);
        }
        return $res;
    }
}
