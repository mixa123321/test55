<?php

namespace app\backend\models;

use app\core\entities\Warehouse;

class FTPUsers extends \app\core\models\FTPUsers
{
    public static function getWarehouseLogin(Warehouse $warehouse): string
    {
        return strtolower(str_replace(' ', '', $warehouse->getName())) . '_' . $warehouse->getId();
    }
}
