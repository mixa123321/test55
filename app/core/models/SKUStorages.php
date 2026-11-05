<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class SKUStorages
{
    public static function insert(array $data)
    {
        if (empty($data))
            return 0;
        return (new SafeMysql())->multiInsert('SKU_storage', $data);
    }
}
