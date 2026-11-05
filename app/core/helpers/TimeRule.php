<?php

namespace app\core\helpers;

class TimeRule
{
    public const REGEXP_TIME = "/^\d{1,2}:\d{2}(:\d{2})*$/";

    public static function validate($value): bool
    {
        if (empty($value) || !is_string($value))
            return false;
        preg_match(static::REGEXP_TIME, $value, $match);
        if (empty($match))
            return false;
        return true;
    }
}
