<?php

namespace app\core\statical;

use app\core\dbal\SafeMysql;

class Currencies
{
    const EXTEND_INFO = [
        50 => ['code' => 'GBP', 'name' => 'British Pounds', 'symbol' => '£', 'coins' => 'p'],
    ];

    public static function getCodes()
    {
        $sql = "SELECT currency FROM currency";
        $res = (new SafeMysql())->fetchAll($sql);
        $codes = array_column($res, 'currency');
        return array_combine($codes, $codes);
    }

    public static function getAll()
    {
        $sql = "SELECT * FROM currency";
        return (new SafeMysql())->fetchAll($sql);
    }

    public static function getCurrency($currency_id)
    {
        return (new SafeMysql())->fetchScalar("SELECT currency FROM currency WHERE id = :id LIMIT 1", [
            'id' => $currency_id
        ]);
    }

    public static function isCurrency($currency)
    {
        return (new SafeMysql())->fetchScalar("SELECT 1 FROM currency WHERE currency = :currency LIMIT 1", [
            'currency' => $currency
        ]);
    }

    public static function getId(string $currency): int
    {
        return (int)(new SafeMysql())->fetchScalar("SELECT id FROM currency WHERE currency = :currency LIMIT 1", [
            'currency' => $currency
        ]);
    }

    public static function getExtend(int $id): array
    {
        return self::EXTEND_INFO[$id] ?? ['code' => '???', 'name' => '???', 'symbol' => '?', 'coins' => '?'];
    }
}
