<?php

namespace app\core\helpers;

use app\core\statical\WithDate;

class TimeZonesConvertor
{
    const UTC_TIME_ZONE = 'UTC';
    private $time_zone = self::UTC_TIME_ZONE;
    /** @var null|\DateTime */
    private $date = null;
    /** @var null|\DateTime */
    private $converted_date = null;

    public function setTimeZone(string $time_zone): TimeZonesConvertor
    {
        $this->checkTimeZone($time_zone);
        $this->time_zone = $time_zone;
        return $this;
    }

    public function setDateByObject(\DateTime $date): TimeZonesConvertor
    {
        $this->date = $date;
        return $this;
    }

    public function setDateCurrent(): TimeZonesConvertor
    {
        $this->date = new \DateTime('now', new \DateTimeZone($this->time_zone));
        return $this;
    }

    public function setDateByString(string $date, ?string $format = null): TimeZonesConvertor
    {
        if ($format) {
            $date = \DateTime::createFromFormat($format, $date, new \DateTimeZone($this->time_zone));
            if ($date === false)
                throw new \Exception('wrong date format');
        } else
            $date = new \DateTime($date, new \DateTimeZone($this->time_zone));
        $this->date = $date;
        return $this;
    }

    public function setTime(int $h = 0, int $m = 0, int $s = 0): TimeZonesConvertor
    {
        if ($this->date === null)
            throw new \Exception('empty date');
        $this->date->setTime($h, $m, $s);
        return $this;
    }

    public function convert(string $time_zone = self::UTC_TIME_ZONE): TimeZonesConvertor
    {
        if ($this->date === null)
            throw new \Exception('empty date');
        $this->checkTimeZone($time_zone);
        $this->converted_date = (clone $this->date)->setTimeZone(new \DateTimeZone($time_zone));
        return $this;
    }

    public function getDiffTimeZone(string $time_zone_diff = self::UTC_TIME_ZONE): int
    {
        $this->checkTimeZone($time_zone_diff);
        $this->checkTimeZone($this->time_zone);

        $offset_diff = (new \DateTime('now', new \DateTimeZone($time_zone_diff)))->format('Z');
        $offset = (new \DateTime('now', new \DateTimeZone($this->time_zone)))->format('Z');
        //seconds
        return $offset - $offset_diff;
    }

    public function getDate(): \DateTime
    {
        if ($this->date === null)
            throw new \Exception('empty date');
        return $this->date;
    }

    public function getDateTimestamp(): int
    {
        if ($this->date === null)
            throw new \Exception('empty date');
        return $this->date->getTimestamp();
    }

    public function getDateString(string $format = WithDate::DATE_SQL_FORMAT)
    {
        if ($this->date === null)
            throw new \Exception('empty date');
        return $this->date->format($format);
    }

    public function getConvertedDate(): \DateTime
    {
        if ($this->converted_date === null)
            throw new \Exception('empty date');
        return clone $this->converted_date;
    }

    public function getConvertedDateString(string $format = WithDate::DATE_SQL_FORMAT): string
    {
        if ($this->converted_date === null)
            throw new \Exception('empty date');
        return $this->converted_date->format($format);
    }

    protected function checkTimeZone(string $time_zone): void
    {
        if (!in_array($time_zone, \DateTimeZone::listIdentifiers()))
            throw new \Exception('wrong time zone ' . $time_zone);
    }
}