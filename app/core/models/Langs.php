<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class Langs
{
    const MAIN_LANG             = 'en';

    public static function getAll()
    {
        $sql = "
            SELECT id, CONCAT(name, ' (', code, ')') name
            FROM langs";
        $res = [];
        foreach ((new SafeMysql())->fetchAll($sql) as $lang)
            $res[$lang['id']] = $lang['name'];
        return $res;
    }

    public static function getAllForTranslate()
    {
        $sql = "
            SELECT id, CONCAT(name, ' (', code, ')') name
            FROM langs
            WHERE code <> :main_lang";
        $res = [];
        foreach ((new SafeMysql())->fetchAll($sql, ['main_lang' => self::MAIN_LANG]) as $lang)
            $res[$lang['id']] = $lang['name'];
        return $res;
    }

    public static function getAllShort()
    {
        $res = [];
        foreach ((new SafeMysql())->fetchArrayOneField("SELECT code FROM langs") as $lang)
            $res[$lang] = strtoupper($lang);
        return $res;
    }

    public static function getCode(int $id)
    {
        return (new SafeMysql())->fetchScalar("SELECT code FROM langs WHERE id = :id", ['id' => $id]);
    }

    public static function getIdByCode(string $code): int
    {
        return (int)(new SafeMysql())->fetchScalar("SELECT id FROM langs WHERE code = :code", ['code' => $code]);
    }
}
