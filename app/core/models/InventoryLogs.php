<?php

namespace app\core\models;

use app\core\entities\InventoryLog;

class InventoryLogs
{
    public static function addRecord($warehouse_id, $SKU_id, $user_id, $request, $start, $response)
    {
        $data = [
            'warehouse_id' => $warehouse_id,
            'SKU_id' => $SKU_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'request' => $request,
            'start' => $start,
            'response' => $response,
            'status' => (int)($response == 'success'),
        ];
        $inventory_log = new InventoryLog($data);
        $inventory_log->save(array_keys($data));
        return $inventory_log->getId();
    }

    public static function changeStatus($inventory_log_id, $response)
    {
        $inventory_log = new InventoryLog(['id' => $inventory_log_id]);
        $inventory_log->setResponse($response);
        $inventory_log->setStatus((int)($response == 'success'));
        $inventory_log->save(['id', 'response', 'status']);
        return $response;
    }

    public static function changeRecord($inventory_log_id, $data)
    {
        $inventory_log = new InventoryLog(['id' => $inventory_log_id] + $data);
        $inventory_log->save([-1 => 'id'] + array_keys($data));
    }
}
