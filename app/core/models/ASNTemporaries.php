<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ASNTemporary;

class ASNTemporaries
{
    public static function getQuantity($ASN_id, $SKU_id)
    {
        $sql = "SELECT SUM(quantity) as summa FROM ASN_temporary WHERE SKU_id = :SKU_id AND ASN_id = :ASN_id";
        return (int)(new SafeMysql())->fetchOne($sql, ['SKU_id' => (int)$SKU_id, 'ASN_id' => (int)$ASN_id])['summa'];
    }

    public static function getQuantityByASN($ASN_id)
    {
        $sql = 'SELECT SUM(quantity) as summa FROM ASN_temporary WHERE ASN_id = :ASN_id';
        return (int)(new SafeMysql())->fetchOne($sql, ['ASN_id' => (int)$ASN_id])['summa'];
    }

    public static function getPutawayQuantity($ASN_id)
    {
        $sql = "SELECT SKU_id, SUM(quantity) as summa
                FROM ASN_temporary
                WHERE ASN_id = :ASN_id
                GROUP BY SKU_id";
        return (new SafeMysql())->fetchAssocWithKey($sql, ['ASN_id' => (int)$ASN_id], 'SKU_id');
    }
}
