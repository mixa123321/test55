<?php

namespace app\core\models;

use app\core\entities\Queue;
use app\core\entities\OrderDoc;
use app\core\entities\User;
use app\core\dbal\SafeMysql;
use app\backend\models\Pagination;
use app\core\helpers\TimeZonesConvertor;
use app\core\statical\WithDate;
use app\core\db\QueryBuilder;

class Queues
{
    public static function validateDataBeforeShow(array $queues, int $warehouse_id, User $user): string
    {
        if (!$queues)
            return 'Empty data';
        foreach ($queues as $id => $queue) {
            if ($warehouse_id && $queue['warehouse_id'] !== $warehouse_id)
                return 'Access denied';
            if (!$user->isRoleSA() && $queue['user_id'] !== $user->getId())
                return 'Access denied';
            if (!$queue['json_data']['order_ids'])
                return 'Wrong data';
        }
        return '';
    }

    public static function getOrderIdsBulkPrintInProcess(int $warehouse_id)
    {
        $sql = "SELECT JSON_EXTRACT(json_data, '$.order_ids') data FROM queues WHERE type = :type AND warehouse_id = :warehouse_id AND status IN (:status);";
        $items = (new SafeMysql())->fetchAll($sql, ['type' => Queue::TYPE_BULK_PRINT, 'warehouse_id' => $warehouse_id, 'status' => [Queue::STATUS_NEW, Queue::STATUS_PROCESS]]);
        $order_ids = [];
        foreach ($items as $item)
            foreach (json_decode($item['data']) as $order_id)
                $order_ids[(int)$order_id] = (int)$order_id;
        return $order_ids;
    }

    public static function createBulkPrintJsonData(array $order_ids, array $filters, float $length, float $width, float $height, float $weight, int $package_type_id): string
    {
        return json_encode(['order_ids' => $order_ids, 'filters' => $filters, 'pack' => ['length' => $length, 'width' => $width, 'height' => $height, 'weight' => $weight, 'package_type_id' => $package_type_id]]);
    }

    public static function insertOne(Queue $queue)
    {
        return (new SafeMysql())->insert("INSERT INTO queues (type, status, warehouse_id, user_id, json_data) VALUES (:type, :status, :warehouse_id, :user_id, :json_data)", [
            'type' => $queue->getType(),
            'status' => $queue->getStatus(),
            'user_id' => $queue->getUserId(),
            'warehouse_id' => $queue->getWarehouseId(),
            'json_data' => $queue->getJsonData(),
        ]);
    }

    public static function getStatus(int $id)
    {
        $sql = "SELECT status FROM queues WHERE id = :id;";
        $status = (new SafeMysql())->fetchScalar($sql, ['id' => $id]);
        return (int)($status ?: 0);
    }

    public static function setStatus(int $id, int $old_status, int $new_status): bool
    {
        return (bool)(new SafeMysql())->execute("UPDATE queues SET status = :new_status WHERE id = :id AND status = :old_status", [
            'new_status' => $new_status,
            'id' => $id,
            'old_status' => $old_status,
        ]);
    }

    public static function getBulkData(array $queue_ids, string $time_zone): array
    {
        $queues = self::formatBulkQueueData(self::getByIdsType($queue_ids, Queue::TYPE_BULK_PRINT), $time_zone);
        self::addPercentToData($queues);
        BulkLogic::addPrintOrders($queues);
        self::addOrderDocuments($queues);
        BulkLogic::addHash($queues);
        return $queues;
    }

    public static function getByIdsType(array $ids, string $type): array
    {
        if (!$ids)
            return [];
        $sql = "SELECT id, json_data, status, create_date, user_id, warehouse_id FROM queues WHERE id IN (:ids) AND type = :type ORDER BY id DESC;";
        return (new SafeMysql())->fetchAll($sql, ['ids' => array_map('intval', $ids), 'type' => $type]);
    }

    public static function getCountByFilters(array $filters): int
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("SELECT count(*) FROM queues");
        $params = self::applyFilters($query, $filters);
        $safeParams = [];
        foreach ($params as $k => $v)
            $safeParams[ltrim($k, ':')] = $v;
        return (int)(new SafeMysql())->fetchScalar($query->build(), $safeParams);
    }

    public static function getByFilters(array $filters, Pagination $pagination): array
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("SELECT id FROM queues");
        $params = self::applyFilters($query, $filters);
        $query->setOrderBy("id DESC");
        $query->setLimit($pagination->getLimitStatement());
        $safeParams = [];
        foreach ($params as $k => $v)
            $safeParams[ltrim($k, ':')] = $v;
        return array_column((new SafeMysql())->fetchAll($query->build(), $safeParams), 'id');
    }

    public static function getOneNew(string $type): ?array
    {
        $sql = "SELECT id, json_data, user_id, warehouse_id, create_date FROM queues WHERE type = :type AND status = :status ORDER BY create_date LIMIT 1;";
        return (new SafeMysql())->fetchOne($sql, ['type' => $type, 'status' => Queue::STATUS_NEW]);
    }

    public static function addErrorIds(int $id, array $ids): void
    {
        $mysql = new SafeMysql();
        if (!$ids) {
            $mysql->execute("UPDATE queues SET json_data = JSON_REMOVE(json_data, '$.error_ids') WHERE id = :id", ['id' => $id]);
            return;
        }
        $mysql->execute("UPDATE queues SET json_data = JSON_INSERT(json_data, '$.error_ids', JSON_ARRAY(:ids)) WHERE id = :id", [
            'id' => $id,
            'ids' => array_map('intval', $ids),
        ]);
    }

    public static function checkExistQueue(string $type, int $user_id, int $warehouse_id)
    {
        $sql = "SELECT 1 FROM queues WHERE type = :type AND user_id = :user_id AND warehouse_id = :warehouse_id LIMIT 1;";
        return (bool)(new SafeMysql())->fetchScalar($sql, ['type' => $type, 'user_id' => $user_id, 'warehouse_id' => $warehouse_id]);
    }

    private static function applyFilters(QueryBuilder $query, array $filters)
    {
        $params = [];
        if (!empty($filters['type'])) {
            $query->addWhere("type = :type");
            $params[':type'] = (string)$filters['type'];
        }
        if (!empty($filters['user_id'])) {
            $query->addWhere("user_id = :user_id");
            $params[':user_id'] = (int)$filters['user_id'];
        }
        if (!empty($filters['warehouse_id'])) {
            $query->addWhere("warehouse_id = :warehouse_id");
            $params[':warehouse_id'] = (int)$filters['warehouse_id'];
        }
        return $params;
    }

    public static function formatBulkQueueData(array $items, string $time_zone = TimeZonesConvertor::UTC_TIME_ZONE): array
    {
        $result = [];
        foreach ($items as $item) {
            $json_data = self::getBulkPrintJsonData($item['json_data']);
            $result[(int)$item['id']] = [
                'status' => (int)$item['status'],
                'status_name' => Queue::ALL_STATUSES[(int)$item['status']],
                'user_id' => (int)$item['user_id'],
                'warehouse_id' => (int)$item['warehouse_id'],
                'create_date' => $item['create_date'],
                'create_date_front' => (new TimeZonesConvertor())->setDateByString($item['create_date'], WithDate::DATE_SQL_FORMAT)->convert($time_zone)->getConvertedDateString(WithDate::DATE_FRONT_FORMAT),
                'back' => '/pack/packing_b?' . http_build_query($json_data['filters']),
                'json_data' => $json_data,
            ];
        }
        return $result;
    }

    public static function getBulkPrintJsonData(string $json_data): array
    {
        $data = json_decode($json_data, true);
        return [
            'order_ids' => (array)($data['order_ids'] ?? []),
            'filters' => (array)($data['filters'] ?? []),
            'error_ids' => (array)($data['error_ids'] ?? []),
        ];
    }

    public static function addPercentToData(array &$queues): void
    {
        foreach ($queues as $id => $queue) {
            $percent = 0;
            if ($queue['status'] === Queue::STATUS_DONE)
                $percent = 100;
            elseif (in_array($queue['status'], [Queue::STATUS_NEW, Queue::STATUS_PROCESS])) {
                $count_done = self::getProgress((int)$id) ?: 0;
                $percent = (string)(int)(round($count_done / count($queue['json_data']['order_ids']), 2) * 100);
            }
            $queues[$id]['percent'] = $percent;
        }
    }

    public static function addOrderDocuments(array &$queues): void
    {
        $order_ids = [];
        foreach ($queues as $queue) {
            if (!in_array($queue['status'], [Queue::STATUS_DONE, Queue::STATUS_ERROR], true))
                continue;
            foreach ($queue['json_data']['order_ids'] as $order_id)
                $order_ids[(int)$order_id] = (int)$order_id;
        }

        $documents = self::getLoadedDocumentsByOrderIds($order_ids);
        foreach ($queues as $id => $queue) {
            $queues[$id]['document_orders'] = [];
            $queues[$id]['documents'] = [];
            if (!in_array($queue['status'], [Queue::STATUS_DONE, Queue::STATUS_ERROR], true))
                continue;
            foreach ($queue['json_data']['order_ids'] as $order_id) {
                $order_id = (int)$order_id;
                if (empty($documents[$order_id]))
                    continue;
                $queues[$id]['document_orders'][$order_id] = $order_id;
                $queues[$id]['documents'][$order_id] = $documents[$order_id];
            }
        }
    }

    private static function getLoadedDocumentsByOrderIds(array $order_ids): array
    {
        if (!$order_ids)
            return [];

        $sql = "
            SELECT odm.order_id, od.*
            FROM order_docs_map odm
                JOIN order_docs od ON od.id = odm.doc_id
            WHERE odm.order_id IN (:order_ids)
                AND od.status = :status
            ORDER BY odm.order_id, od.id";
        $items = (new SafeMysql())->fetchAll($sql, [
            'order_ids' => array_map('intval', $order_ids),
            'status' => OrderDoc::STATUS_LOADED,
        ]);

        $documents = [];
        foreach ($items as $item) {
            $order_id = (int)$item['order_id'];
            if (isset($documents[$order_id]))
                continue;
            $document = new OrderDoc($item);
            if (!$document->existFile())
                continue;
            $documents[$order_id] = [
                'id' => $document->getId(),
                'name' => $document->getName(),
            ];
        }
        return $documents;
    }

    public static function getProgress(int $queue_id): int
    {
        $sql = "SELECT JSON_EXTRACT(json_data, '$.count_done') count_done FROM queues WHERE id = :id;";
        return (int)(new SafeMysql())->fetchScalar($sql, ['id' => $queue_id]);
    }

    public static function setProgress(int $queue_id)
    {
        $count = self::getProgress($queue_id);
        return (new SafeMysql())->execute("UPDATE queues SET json_data = JSON_SET(json_data, '$.count_done', :count) WHERE id = :id", [
            'id' => $queue_id,
            'count' => ++$count,
        ]);
    }

    public static function clearProgress(int $queue_id)
    {
        (new SafeMysql())->execute("UPDATE queues SET json_data = JSON_REMOVE(json_data, '$.count_done') WHERE id = :id", ['id' => $queue_id]);
    }
}
