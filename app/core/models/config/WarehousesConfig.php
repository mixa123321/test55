<?php

namespace app\core\models\config;

class WarehousesConfig extends \app\core\entities\config\WarehousesConfig
{
    const TYPE_AUTOMATICAL_RELEASE = 'automaticalRelease';
    const TYPE_TIMER_STATUS = 'timer_status';
    const TYPE_TIMER_PDF = 'timer_print_pdf';

    const TYPES = [
        self::TYPE_AUTOMATICAL_RELEASE => self::FIELD_VALUE_INT,
        self::TYPE_TIMER_STATUS => self::FIELD_VALUE_INT,
        self::TYPE_TIMER_PDF => self::FIELD_VALUE_INT,
    ];

    public static function automaticalRelease(int $warehouse_id, int $value): WarehousesConfig
    {
        return new WarehousesConfig(self::getData($warehouse_id, self::TYPE_AUTOMATICAL_RELEASE, $value));
    }

    public static function getAutomaticalRelease(int $warehouse_id): int
    {
        return self::readConfigById($warehouse_id, self::TYPE_AUTOMATICAL_RELEASE);
    }

    public static function timerStatus(int $warehouse_id, bool $value): WarehousesConfig
    {
        return new WarehousesConfig(self::getData($warehouse_id, self::TYPE_TIMER_STATUS, (int)$value));
    }

    public static function getTimerStatus(int $warehouse_id): bool
    {
        return (bool)self::readConfigById($warehouse_id, self::TYPE_TIMER_STATUS);
    }

    public static function timerPrintPdf(int $warehouse_id, int $value): WarehousesConfig
    {
        return new WarehousesConfig(self::getData($warehouse_id, self::TYPE_TIMER_PDF, (int)$value));
    }

    public static function getTimerPrintPdf(int $warehouse_id): int
    {
        return (int)self::readConfigById($warehouse_id, self::TYPE_TIMER_PDF);
    }
}
