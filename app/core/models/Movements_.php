<?php

namespace app\core\models;

use app\core\entities\Movement_;
use app\core\dbal\SafeMysql;

class Movements_
{
    public static function getById($id)
    {
        static $movements = [];
        if (!array_key_exists($id, $movements)) {
            $data = (new SafeMysql())->fetchOne('SELECT * FROM movement WHERE id = :id', ['id' => (int)$id]);
            if ($data) {
                $movements[$id] = new Movement_($data);
            } else {
                $movements[$id] = null;
            }
        }
        return $movements[$id];
    }
}