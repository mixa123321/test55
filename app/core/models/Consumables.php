<?php

namespace app\core\models;

use app\core\entities\Consumable;
use app\core\dbal\SafeMysql;

class Consumables
{
    public static function getById($id): ?Consumable
    {
        static $consumables = [];
        $id = (int)$id;
        if (empty($consumables[$id])) {
            $data = (new SafeMysql())->fetchOne("SELECT * FROM consumables WHERE id = :id", ['id' => $id]);
            $consumables[$id] = !empty($data) ? new Consumable($data) : null;
        }
        return $consumables[$id];
    }

    public static function getByNameAndSupplierId($name, $supplier_id)
    {
        $supplier_id = (int)$supplier_id;
        $data = (new SafeMysql())->fetchOne("SELECT * FROM consumables WHERE name = :name AND supplier_id = :supplier_id", ['name' => $name, 'supplier_id' => $supplier_id]);
        return !empty($data) ? new Consumable($data) : null;
    }
}
