<?php

namespace app\core\models;

use app\core\entities\Dimensioner;
use app\core\dbal\SafeMysql;

class Dimensioners
{
    public static function getDimensioners(int $warehouse_id)
    {
        $res = (new SafeMysql())->fetchAll("SELECT * FROM dimensioners WHERE warehouse_id = :warehouse_id", ['warehouse_id' => $warehouse_id]);
        $dimensioners = [];
        foreach ($res as $dimensioner_data)
            $dimensioners[$dimensioner_data['id']] = new Dimensioner($dimensioner_data);
        return $dimensioners;
    }

    public static function getByNameOrUnitId(int $warehouse_id, string $name, int $unit_id)
    {
        return (new SafeMysql())->fetchAll("SELECT * FROM dimensioners WHERE warehouse_id = :warehouse_id AND (name = :name OR unit_id = :unit_id)", ['warehouse_id' => $warehouse_id, 'name' => $name, 'unit_id' => $unit_id]);
    }

    public static function getById(int $id)
    {
        $data = (new SafeMysql())->fetchOne("SELECT * FROM dimensioners WHERE id = :id LIMIT 1", ['id' => $id]);
        if (empty($data))
            return null;
        return new Dimensioner($data);
    }

    public static function getByUnitId(int $warehouse_id, int $unit_id)
    {
        $data = (new SafeMysql())->fetchOne("SELECT * FROM dimensioners WHERE warehouse_id = :warehouse_id AND unit_id = :unit_id AND state != 0 LIMIT 1", ['warehouse_id' => $warehouse_id, 'unit_id' => $unit_id]);
        if (empty($data))
            return null;
        return new Dimensioner($data);
    }

    public static function setState(Dimensioner $dimensioner, $state)
    {
        $dimensioner->setState($state);
        $dimensioner->save(['id', 'state']);
    }
}
