<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class TrackingsHistory
{
    const TIME_LIVE = 15552000;

    public static function addRecord($hash, $ip, $package_id, $action)
    {
        (new SafeMysql())->insert(
            '
            INSERT INTO tracking_history (hash, IP, package_id, action)
            VALUES (:hash, :ip, :package_id, :action)
            ',
            [
                'hash' => (string) $hash,
                'ip' => (string) $ip,
                'package_id' => (int) $package_id,
                'action' => (string) $action,
            ]
        );
    }

    public static function getByHash()
    {
        $ids = (new SafeMysql())->fetchArrayOneField(
            '
            SELECT hash
            FROM tracking_links
            WHERE TIMESTAMPDIFF(SECOND, created_at, CURRENT_TIMESTAMP) > :time_live
            ',
            [
                'time_live' => self::TIME_LIVE,
            ]
        );

        if (empty($ids)) {
            return false;
        }

        return $ids;
    }

    public static function deleteRecord($ids)
    {
        (new SafeMysql())->execute(
            'DELETE FROM tracking_history WHERE hash = :hash',
            ['hash' => $ids]
        );
    }
}