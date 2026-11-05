<?php

namespace app\core\helpers;

class Barcode128Rule
{
//    public const REGEXP_BARCODE_128 = "^[\\000-\\039\\041-\\177]*$";   // Symbol SPACE(\040) is skipped by design
    public const REGEXP_BARCODE_128 = "^[\\000-\\177]*$";   // not for AB :(

    public static function validate($value): bool
    {
        if (empty($value) || !is_string($value))
            return false;
        $result = preg_match("/" . static::REGEXP_BARCODE_128 . "/", $value);
        if ($result == 0)
            return false;
        return true;
    }
}
