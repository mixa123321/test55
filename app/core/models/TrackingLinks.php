<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Order;

class TrackingLinks
{
    public static function createLink(Order $order): ?string
    {
        $db = new SafeMysql();

        for ($i = 0; $i < 10; $i++) {
            $hash = md5(random_bytes(1000));

            $insertId = $db->execute(
                '
                INSERT IGNORE INTO tracking_links
                SET hash = :hash,
                    order_id = :order_id
                ',
                [
                    'hash' => $hash,
                    'order_id' => (int) $order->getId(),
                ]
            );

            if ($insertId > 0) {
                return $hash;
            }
        }

        return null;
    }

    public static function deleteOldLink($id)
    {
        return (new SafeMysql())->execute(
            'DELETE FROM tracking_links WHERE hash = :hash',
            ['hash' => $id]
        );
    }

    public static function getOrder(string $hash): ?Order
    {
        $order_id = (new SafeMysql())->fetchScalar(
            'SELECT order_id FROM tracking_links WHERE hash = :hash',
            ['hash' => $hash]
        );

        if ($order_id === null) {
            return null;
        }
        return Orders::getById((int) $order_id);
    }

    public static function getByNumber($number)
    {
        return (new SafeMysql())->fetchScalar(
            'SELECT 1 FROM tracking_links WHERE hash = :hash',
            ['hash' => $number]
        );
    }
}