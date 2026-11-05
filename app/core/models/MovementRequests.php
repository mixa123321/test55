<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\MovementRequest;

class MovementRequests
{
    public static function getById(int $id): ?MovementRequest
    {
        $movements = (new SafeMysql())->fetchOne("SELECT * FROM movement_requests WHERE id = :id LIMIT 1", ['id' => $id]);
        return $movements ? new MovementRequest($movements) : null;
    }

    public static function add(int $location_id, string $type_location, int $SKU_id, int $qty): int
    {
        $data = [
            'SKU_id' => $SKU_id,
            'location_id' => $location_id,
            'type_location' => $type_location,
            'quantity' => $qty,
            'status' => MovementRequest::NEW,
        ];
        $request = new MovementRequest($data);
        $request->save(array_keys($data));
        return $request->getId();
    }

    /** @throws */
    public static function changeStatus(int $id, int $status): void
    {
        if (!MovementRequest::isStatus($status))
            throw new \Exception('Invalid movement request status');
        (new SafeMysql())->execute("UPDATE movement_requests SET status = :status WHERE id = :id", [
            'status' => $status,
            'id' => $id,
        ]);
    }

    public static function getRequest(int $id): ?array
    {
        $location_select_name = Locations::getLocationNameSelect('l', 'w');
        $sql = "
            SELECT mr.id, mr.SKU_id, s.name, s.photo, (" . SKUBarcodes::getBarcodesSQL('mr.SKU_id') . ") barcode, s.max_capacity,
                   (mr.quantity - mr.accepted_quantity) quantity, (mr.quantity - mr.accepted_quantity) max_quantity,
                   mr.type_location, mr.location_id, l.type_id,
                   CASE 
                       WHEN l.type_id = 'US' 
                           THEN CONCAT(u.login, ' #', u.id)
                           ELSE $location_select_name
                   END AS loc_name                
            FROM movement_requests mr
                JOIN SKUs s ON s.id = mr.SKU_id
                JOIN locations l ON mr.location_id = l.id
                JOIN warehouses w ON l.warehouse = w.id
                LEFT JOIN users u ON u.id = l.`row`
            WHERE mr.id = :id AND mr.type_location IN (:statuses)
        ";
        $params = [
            'id' => $id,
            'statuses' => [MovementRequest::NEW, MovementRequest::IN_PROGRESS],
        ];
        return (new SafeMysql())->fetchOne($sql, $params);
    }
}
