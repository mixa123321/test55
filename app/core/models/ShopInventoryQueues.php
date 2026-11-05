<?php

namespace app\core\models;

use app\core\db\DBException;
use app\core\dbal\SafeMysql;
use app\core\entities\ShopInventoryQueue;
use app\core\helpers\Debug;

class ShopInventoryQueues
{
    public static function add(ShopInventoryQueue $record)
    {
        (new SafeMysql())->execute("
            INSERT INTO shop_inventory_queue
            SET shop_warehouse_id = :shop_warehouse_id,
                item_id = :item_id,
                available = :available,
                source = :source,
                object_id = :object_id
            ON DUPLICATE KEY UPDATE available = :available, updated_at = NOW()", [
            'shop_warehouse_id' => $record->getShopWarehouseId(),
            'item_id' => $record->getItemId(),
            'available' => $record->getAvailable(),
            'source' => $record->getSource(),
            'object_id' => $record->getObjectId(),
        ]);
    }

    public static function setProcessing($num, $nums)
    {
        $inventory_data = (new SafeMysql())->fetchAll(
            "SELECT * FROM shop_inventory_queue WHERE status = :status ORDER BY created_at LIMIT " . (int)$nums,
            ['status' => ShopInventoryQueue::NEW]);
        if (empty($inventory_data) || count($inventory_data) < $num)
            return null;
        try {
            $inventory = new ShopInventoryQueue($inventory_data[$num - 1]);
            $inventory->setStatus(ShopInventoryQueue::PROCESSING);
            $inventory->save(['id', 'status']);
        } catch (\Exception $e) {
            if ($e instanceof DBException) {
                if ($e->isDuplicateEntry()) {
                    $inventory_dupl = self::getRecordByUnique(
                        $inventory->getShopWarehouseId(),
                        $inventory->getItemId(),
                        ShopInventoryQueue::PROCESSING);
                    if (!empty($inventory_dupl) && (time() - strtotime($inventory_dupl->getCreatedAt())) > 3600)
                        self::delete($inventory_dupl->getId());
                    return false;
                }
                if ($e->isDeadlock(0))
                    return false;
            }
            Debug::sendDebugInfo('ShopInventoryQueues::setProcessing', $e->getMessage(), Debug::TEST);
            throw $e;
        }
        return $inventory;
    }

    public static function getRecordByUnique($shop_warehouse_id, $item_id, $status)
    {
        $data = (new SafeMysql())->fetchOne("
            SELECT *
            FROM shop_inventory_queue
            WHERE shop_warehouse_id = :shop_warehouse_id AND item_id = :item_id AND status = :status",
            ['shop_warehouse_id' => (int)$shop_warehouse_id, 'item_id' => $item_id, 'status' => (int)$status]);
        if (empty($data))
            return null;
        return new ShopInventoryQueue($data);
    }

    public static function setError(ShopInventoryQueue $inventory)
    {
        $inventory->setStatus(ShopInventoryQueue::ERROR);
        $inventory->save(['id', 'status']);
    }

    public static function delete($id)
    {
        (new SafeMysql())->execute("DELETE FROM shop_inventory_queue WHERE id = :id", ['id' => (int)$id]);
    }
}
