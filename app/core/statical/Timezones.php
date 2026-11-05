<?php

namespace app\core\statical;

use DateTime;
use DateTimeZone;
use Exception;

class Timezones
{
    public static function getByCountryCode($code): array
    {
        if (empty($code) || $code === 'UK')
            $code = 'GB';
        $data = @DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $code);
        return is_array($data) ? array_combine($data, $data) : [];
    }

    public static function getDateInZone($zone): ?string
    {
        try {
            $date = new DateTime("now", new DateTimeZone($zone) );
            return $date->format(WithDate::DATE_SQL_FORMAT);
        } catch (Exception $e) {
            return null;
        }
    }

    public static function getDiff($zone, $time = null, $opposite = false, $format = WithDate::DATE_SQL_FORMAT)
    {
        try {
            $timezone = @timezone_open($zone);
            if ($timezone === false)
                return 0;
            if (is_null($time)) {
                return timezone_offset_get($timezone, new DateTime()) / 60;
            } else {
                $date = new DateTime($time);
                if (!WithDate::validateDate($time))
                    return $time;
                $diff = timezone_offset_get($timezone, $date) / 60;
                $operator = $opposite ? '-' : '+';
                $date->modify("{$operator}{$diff} minute");
                return $date->format($format);
            }
        } catch (Exception $e) {
            return empty($time) ? 0 : $time;
        }
    }
}