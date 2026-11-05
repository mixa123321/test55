<?php

namespace app\core\helpers;

class Encoding
{
    const UTF8 = 'UTF-8';
    const UTF16BE = 'UTF-16BE';
    const UTF16LE = 'UTF-16LE';
    const UTF32BE = 'UTF-32BE';
    const UTF32LE = 'UTF-32LE';

    private const BOM = [
        self::UTF32BE => "\x00\x00\xFE\xFF",
        self::UTF32LE => "\xFF\xFE\x00\x00",
        self::UTF16BE => "\xFE\xFF",
        self::UTF16LE => "\xFF\xFE",
        self::UTF8 => "\xEF\xBB\xBF",
    ];

    private const DEFAULT_ORDER = [
        self::UTF8,
        'Windows-1251',
        'Windows-1252',
        'ISO-8859-1',
        'ISO-8859-5',
        self::UTF16BE,
        self::UTF16LE,
    ];

    public static function detect(string $str, array $order = self::DEFAULT_ORDER): ?string
    {
        $encoding = mb_detect_encoding($str, $order, true);

        return $encoding !== false ? $encoding : null;
    }

    public static function isUtf8(string $str): bool
    {
        return $str === '' || preg_match('//u', $str) === 1;
    }

    public static function convertToUtf8($data, array $order = self::DEFAULT_ORDER)
    {
        $str = is_array($data) ? implode($data) : $data;

        if (self::isUtf8($str))
            return $data;

        $encoding = self::detect($str, $order) ?? 'Windows-1251';

        return mb_convert_encoding($data, self::UTF8, $encoding);
    }
}