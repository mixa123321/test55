<?php

namespace app\core\services\billing;

abstract class Billing
{
    abstract public static function report($warehouse_id, $create_date = null);
    abstract public static function reportReturns($warehouse_id, $create_date = null);
    abstract public static function reportVirtual($warehouse_id, $create_date = null);
}