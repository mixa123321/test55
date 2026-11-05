<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class UsersLogged
{
    public static function addUser($user_id, $ip, $country_id)
    {
        return (new SafeMysql())->insert(
            'INSERT INTO user_logged (user_id, IPv4, country_id)
            VALUES (:user_id, :ip, :country_id)',
            [
                'user_id' => (int)$user_id,
                'ip' => (string)$ip,
                'country_id' => $country_id !== null ? (string)$country_id : null,
            ]
        );
    }

    public static function removeUser($id)
    {
        return (new SafeMysql())->execute(
            'DELETE FROM user_logged WHERE id = :id',
            ['id' => (int)$id]
        );
    }

    public static function removeInactiveUser()
    {
        return (new SafeMysql())->execute(
            'DELETE FROM user_logged
            WHERE last_access < DATE_SUB(NOW(), INTERVAL 90 SECOND)'
        );
    }

    public static function updateList($id, $user_id, $ip, $country_id)
    {
        return (new SafeMysql())->execute(
            'INSERT INTO user_logged (id, user_id, last_access, country_id, IPv4)
            VALUES (:id, :user_id, NOW(), :country_id, :ip)
            ON DUPLICATE KEY UPDATE
                IPv4 = :ip,
                last_access = NOW(),
                country_id = :country_id',
            [
                'id' => (int)$id,
                'user_id' => (int)$user_id,
                'country_id' => (string)$country_id,
                'ip' => (string)$ip,
            ]
        );
    }
}
