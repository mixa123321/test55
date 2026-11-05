<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;

class UsersLogged extends \app\core\models\UsersLogged
{
    public static function getUsers()
    {
        $sql = "SELECT ul.id, r.name role_name, u.login, w.id warehouse_id, w.name warehouse_name, ul.last_access, ul.IPv4, ul.country_id
                FROM user_logged ul
                JOIN users u on ul.user_id = u.id 
                JOIN roles r on u.role_id = r.id
                JOIN warehouses w on u.warehouse_id = w.id";
        return (new SafeMysql())->fetchAll($sql);
    }
}
