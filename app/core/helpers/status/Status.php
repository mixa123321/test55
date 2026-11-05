<?php

namespace app\core\helpers\status;

use app\core\helpers\Html;
use app\core\helpers\View;

abstract class Status
{
    private static function getClasses($style): string
    {
        return "mb-0 badge badge-$style";
    }
    public static function get($status)
    {
        return Html::span(View::toHtml(static::getStatusName($status)), ['class' => self::getClasses(static::getStatusStyle($status))]);
    }

    public static function getStatus($status, $style)
    {
        return Html::span(View::toHtml($status), ['class' => self::getClasses($style)]);
    }

    public static function getCustom($status, $style)
    {
        return Html::span(View::toHtml(static::getStatusName($status)), ['class' => self::getClasses($style)]);
    }

    abstract static function getStatusName($status): string;
    abstract static function getStatusStyle($status): string;
}
