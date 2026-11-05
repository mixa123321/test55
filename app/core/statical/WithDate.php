<?php

namespace app\core\statical;

class WithDate
{
    const DATE_SQL_FORMAT                   = 'Y-m-d H:i:s';
    const DATE_SQL_SHORT                    = 'Y-m-d H:i';
    const DATE_SIMPLE_FORMAT                = 'Y-m-d';
    const DATE_FRONT_DETAIL                 = 'jS M Y H:i:s';
    const DATE_FRONT_FORMAT                 = 'jS M Y H:i';
    const DATE_FRONT_FORMAT_WITHOUT_TIME    = 'jS M Y';
    const DATE_US_DETAIL                    = 'm/d/Y H:i:s';
    const DATE_US                           = 'm/d/Y';
    const DATE_UK_DETAIL                    = 'd/m/Y H:i:s';
    const DATE_UK_FORMAT                    = 'd/m/Y H:i';
    const DATE_UK                           = 'd/m/Y';

    const DATE_INT                          = 'int';
    const DATE_STRING                       = 'string';
    const DATE_ANY                          = 'any';
    const STRING_FORMATS                    = [
        self::DATE_UK_DETAIL                => 'dd/mm/yyyy hh:mm:ss',
        self::DATE_UK_FORMAT                => 'dd/mm/yyyy hh:mm',
        self::DATE_UK                       => 'dd/mm/yyyy',
        'd-m-Y H:i:s'                       => 'dd-mm-yyyy hh:mm:ss',
        'd-m-Y'                             => 'dd-mm-yyyy',
        self::DATE_US_DETAIL                => 'mm/dd/yyyy hh:mm:ss',
        self::DATE_US                       => 'mm/dd/yyyy',
        'Y/m/d H:i:s'                       => 'yyyy/mm/dd hh:mm:ss',
        'Y/m/d'                             => 'yyyy/mm/dd',
        self::DATE_SQL_FORMAT               => 'yyyy-mm-dd hh:mm:ss',
        self::DATE_SQL_SHORT                => 'yyyy-mm-dd hh:mm',
        self::DATE_SIMPLE_FORMAT            => 'yyyy-mm-dd',
    ];

    public static function isNextDay($date): bool
    {
        return (int)date('d', strtotime($date)) === ((int)date('d') + 1);
    }

    public static function startDay($time)
    {
        return strtotime(date(self::DATE_SIMPLE_FORMAT, $time));  // 0:00 left day
    }

    public static function toFrontFormat($date): string
    {
        return date(WithDate::DATE_FRONT_FORMAT, strtotime($date));
    }

    public static function dateToInt($date, $check_type = self::DATE_ANY)
    {
        if (in_array($check_type, [self::DATE_INT, self::DATE_ANY])) {
            if (is_int($date))
                return $date;
            if (is_numeric($date))
                return (int)$date;
        }
        if (in_array($check_type, [self::DATE_STRING, self::DATE_ANY]))
            if (is_string($date))
                foreach (array_keys(self::STRING_FORMATS) as $format) {
                    $check_date = \DateTime::createFromFormat($format, $date);
                    if ($check_date && $check_date->format($format) == $date) {
                        $unix_timestamp = $check_date->getTimestamp();
                        return 86400 >= $unix_timestamp ? 0 : $unix_timestamp;
                    }
                }
        return 0;
    }

    public static function showAllowedDateFormats()
    {
        return implode(', ', self::STRING_FORMATS);
    }

    public static function getClosestDate($array, $date)
    {
        foreach($array as $day)
            $interval[] = abs(strtotime($date) - strtotime($day));
        asort($interval);
        $closest = key($interval);
        return $array[$closest];
    }

    /**
     * @return array [start: \DateTime, end: \DateTime]
     * @throws
     */
    public static function getQuarter(\DateTime $date): ?array
    {
        return self::getDatesOfQuarter('current', $date);
    }

    /** @throws */
    public static function getDatesOfQuarter($needle = 'current', \DateTime $date = null, $format = null): array
    {
        if (is_null($date))
            $date = new \DateTime;
        $year = $date->format('Y');
        $quarter = ceil($date->format('n') / 3);
        switch (strtolower($needle)) {
            case 'this':
            case 'current':
                break;
            case 'next':
                if ($quarter == 4) {
                    $quarter = 1;
                    $year++;
                } else
                    $quarter++;
                break;
            case 'previous':
                if ($quarter == 1) {
                    $quarter = 4;
                    $year--;
                } else
                    $quarter--;
                break;
            case 'first':
                $quarter = 1;
                break;
            case 'last':
                $quarter = 4;
                break;
            default:
                $quarter = (!is_int($needle) || $needle < 1 || $needle > 4) ? $quarter : $needle;
                break;
        }
        $start = new \DateTime($year . '-' . (3 * $quarter - 2) . '-1 00:00:00');
        $end = new \DateTime($year . '-' . (3 * $quarter) . '-3' . (int)($quarter == 1 || $quarter == 4) . ' 23:59:59');
        return array(
            'start' => $format ? $start->format($format) : $start,
            'end' => $format ? $end->format($format) : $end,
        );
    }

    public static function validateDate($date, $format = self::DATE_SQL_FORMAT): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        if (!$d) {
            $date .= ':00';
            $d = \DateTime::createFromFormat($format, $date);
        }
        return $d && $d->format($format) === $date;
    }

    public static function getInterval($date_from, $date_to, bool $is_reverse = false): array
    {
        $end = new \DateTime($date_from);
        $start = new \DateTime($date_to);
        $diff = $end->diff($start);
        $isStartEqualsEnd = $diff->days === 0;
        $strInterval = $isStartEqualsEnd ? '0 day' : '-1 day';
        $interval = \DateInterval::createFromDateString($strInterval);
        $period = new \DatePeriod($start, $interval, $diff->days ?: 1, $isStartEqualsEnd ? \DatePeriod::EXCLUDE_START_DATE : 0);
        $dates = [];
        foreach ($period as $dt) {
            $date_formatted = $dt->format(WithDate::DATE_SIMPLE_FORMAT);
            $dates[] = $date_formatted;
        }
        return !$is_reverse ? array_reverse($dates) : $dates;
    }
}
