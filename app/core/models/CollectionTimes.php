<?php

namespace app\core\models;

use app\core\entities\CollectionTime;
use app\core\dbal\SafeMysql;

class CollectionTimes
{
    public static function getCollectionTime(int $warehouse_id, int $service_id)
    {
        $collections = (new SafeMysql())->fetchAll("
            SELECT *
            FROM collection_time
            WHERE shipping_service_id = :service_id AND warehouse_id = :warehouse_id", ['service_id' => $service_id, 'warehouse_id' => $warehouse_id]);
        if (empty($collections))
            return null;
        return new CollectionTime($collections[0]);
    }
}