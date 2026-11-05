<?php

namespace app\core\models\config;

class SKUsConfig extends \app\core\entities\config\SKUsConfig
{
    const TYPE_NS_PID = 'ns_pid';
    const TYPE_S_PID = 's_pid';

    const TYPES = [
        self::TYPE_NS_PID => self::FIELD_VALUE_STRING,
        self::TYPE_S_PID => self::FIELD_VALUE_STRING,
    ];

    public static function nsPid(int $SKU_id, string $value): SKUsConfig
    {
        return new SKUsConfig(self::getData($SKU_id, self::TYPE_NS_PID, self::sanitizeValue($value)));
    }

    public static function getNsPid(int $SKU_id): string
    {
        return self::readConfigById($SKU_id, self::TYPE_NS_PID);
    }

    public static function sPid(int $SKU_id, string $value): SKUsConfig
    {
        return new SKUsConfig(self::getData($SKU_id, self::TYPE_S_PID, self::sanitizeValue($value)));
    }

    public static function getSPid(int $SKU_id): string
    {
        return self::readConfigById($SKU_id, self::TYPE_S_PID);
    }

    // ABF-475: no format/charset rule is defined yet (pending answer from the lead) - this only
    // protects against the value_string(255) DB column, not a business validation
    private static function sanitizeValue(string $value): string
    {
        return substr(trim($value), 0, 255);
    }
}
