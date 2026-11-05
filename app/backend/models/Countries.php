<?php

namespace app\backend\models;

use app\core\statical\Countries as CountriesStatical;

class Countries extends CountriesStatical
{
    /**
     * Keeps only known country codes (keys of getAll()) from a JSON array string,
     * without duplicates; returns a JSON array string ('[]' on invalid input).
     */
    public static function sanitizeJsonCodes($countries): string
    {
        $decoded = is_string($countries) ? json_decode($countries, true) : null;
        if (!is_array($decoded))
            return '[]';
        $all_countries = self::getAll();
        $result = [];
        foreach ($decoded as $code)
            if (is_string($code) && isset($all_countries[$code]) && !in_array($code, $result, true))
                $result[] = $code;
        return json_encode($result);
    }
}
