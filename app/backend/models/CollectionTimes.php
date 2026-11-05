<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\CollectionTime;
use app\core\helpers\Validator;

class CollectionTimes extends \app\core\models\CollectionTimes
{
    public static function validate(CollectionTime $collection_time): array
    {
        $rules = [
            'shipping_service_id' => [
                'val' => $collection_time->getShippingServiceId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'warehouse_id' => [
                'val' => $collection_time->getWarehouseId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'collection' => [
                'val' => $collection_time->getCollection(),
                'rules' => [Validator::TYPE_TIME => []]
            ],
            'is_priority' => [
                'val' => $collection_time->getIsPriority(),
                'rules' => [Validator::TYPE_BOOL => []]
            ],
            'priority_level' => [
                'val' => $collection_time->getPriorityLevel(),
                'rules' => [Validator::TYPE_INT => [0, PHP_INT_MAX]]
            ],
        ];
        if (!empty($collection_time->getCollection()) && $collection_time->getIsPriority()) {
            $rules['priority_level']['rules'][Validator::REQUIRED] = 1;
            $rules['priority_level']['rules'][Validator::TYPE_INT][0] = 1;
        }
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        return [];
    }

    public static function getCollectionTimes(int $warehouse_id)
    {
        return (new SafeMysql())->fetchAll("
            SELECT ss.id shipping_service_id, ss.name, ss.code, ct.id, DATE_FORMAT(ct.collection, '%H:%i') collection, IFNULL(ct.is_priority, 0) is_priority, ct.priority_level
            FROM shipping_services ss
                JOIN shipping_service_warehouses ssw ON ss.id = ssw.shipping_service_id AND ssw.warehouse_id = :warehouse_id
                LEFT JOIN collection_time ct ON ss.id = ct.shipping_service_id AND ssw.warehouse_id = ct.warehouse_id
            ORDER BY ss.id", [
            'warehouse_id' => $warehouse_id,
        ]);
    }
}
