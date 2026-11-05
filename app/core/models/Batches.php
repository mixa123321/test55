<?php

namespace app\core\models;

use app\backend\models\Locations;
use app\core\dbal\SafeMysql;
use app\core\entities\Batch;
use app\core\exceptions\BatchException;

class Batches
{
    public static function getById($id)
    {
        $res = (new SafeMysql())->fetchOne("SELECT * FROM batches WHERE id = :id LIMIT 1", ['id' => $id]);
        if (empty($res))
            return null;
        return new Batch($res);
    }

    /**
     * @param array $ids
     * @return Batch[]
     * @throws \app\core\db\DBException
     */
    public static function getByIds(array $ids): array
    {
        if (!$ids)
            return [];
        $items = (new SafeMysql())->fetchAssocWithKey("SELECT * FROM batches WHERE id IN (:ids);", ['ids' => array_map('intval', $ids)], 'id');
        $result = [];
        foreach ($items as $item)
            $result[(int)$item['id']] = new Batch($item);
        return $result;
    }

    public static function getBatchesBySKU($SKU_id)
    {
        $now_date = date('Y-m-d', time());
        $sql = "
            SELECT b.id, b.number, b.expired, DATE_ADD(b.expired, INTERVAL (IF(sup.has_weeks_before_expiry = 1, -sup.weeks_before_expiry, 0)) WEEK) <= :now_date danger, sl.location_id, IFNULL(SUM(sl.quantity), 0) quantity,
                   CONCAT(IF(w.label = '' OR w.label IS NULL, l.warehouse, w.label), '-', l.type_id, '-', l.`zone`, '-', l.`column`, '-', l.row) loc_name,
                   w.name
            FROM batches b 
                LEFT JOIN SKU_locations sl ON b.id = sl.batch_id AND b.SKU_id = sl.SKU_id
                LEFT JOIN locations l ON sl.location_id = l.id
                LEFT JOIN warehouses w ON l.warehouse = w.id
                LEFT JOIN SKUs s ON b.SKU_id = s.id
                LEFT JOIN suppliers sup ON s.supplier_id = sup.id
            WHERE b.SKU_id = :SKU_id
            GROUP BY b.id, sl.location_id
            ORDER BY b.expired, l.warehouse, l.type_id, LPAD(l.zone, 5, ' '), LPAD(l.`column`, 5, '0'), LPAD(l.row, 5, ' ')";
        $res = [];
        $batches = (new SafeMysql())->fetchAll($sql, ['SKU_id' => (int)$SKU_id, 'now_date' => $now_date,]);
        foreach ($batches as $batch) {
            if (empty($res[$batch['id']]))
                $res[$batch['id']] = array_slice($batch, 1, 3);
            if (!empty($batch['location_id']))
                $res[$batch['id']]['locs'][$batch['location_id']] = ['loc_name' => $batch['loc_name'], 'name' => $batch['name'], 'quantity' => $batch['quantity']];
        }
        foreach ($res as $batch_id => $batch)
            $res[$batch_id]['total'] = !empty($batch['locs']) ? array_sum(array_map(function($item) { return $item['quantity']; }, $batch['locs'])) : 0;
        return $res;
    }

    public static function getBatchesByIds($ids)
    {
        if (empty($ids) || !is_array($ids))
            return [];
        $sql = "SELECT b.id, s.id SKU_id, b.number, b.expired
                FROM batches b
                    JOIN SKUs s ON b.SKU_id = s.id 
                WHERE b.id IN (:ids)
                GROUP BY b.id";
        $items = (new SafeMysql())->fetchAssocWithKey($sql, [
            'ids' => $ids
        ],'id');
        SKUBarcodes::addBarcodesToArray($items, 'SKU_id');
        return $items;
    }

    public static function isBatches($ids)
    {
        if (empty($ids) || !is_array($ids))
            return [];
        $batches = (new SafeMysql())->fetchAll('
            SELECT SKU_id, COUNT(*) nums 
            FROM batches
            WHERE SKU_id IN (:ids)
            GROUP BY SKU_id', ['ids' => array_map('intval', $ids)]);
        $res = [];
        foreach ($batches as $batch)
            $res[$batch['SKU_id']] = (bool)$batch['nums'];
        return $res;
    }

    public static function getBatchBySKUAndNumber($SKU_id, $number)
    {
        return (new SafeMysql())->fetchOne("SELECT * FROM batches WHERE SKU_id = :SKU_id AND number = :number", [
            'SKU_id' => (int) $SKU_id,
            'number' => $number
        ]);
    }

    public static function create($number, $SKU_id, $expired, $insertIgnore = false)
    {
        return (new SafeMysql())->multiInsert('batches', [['number' => $number, 'SKU_id' => $SKU_id, 'expired' => $expired]], $insertIgnore);
    }

    public static function deleteBatch($batch_id)
    {
        (new SafeMysql())->execute("DELETE FROM batches WHERE id = :id", ['id' => (int)$batch_id]);
    }

    /** @throws BatchException */
    public static function addBatch($number, $SKU_id, $expired)
    {
        $number = trim($number);
        if (empty($number))
            return null;
        if (empty($SKU_id) || empty($expired) || (new \DateTime($expired))->getTimestamp() <= 0)
            throw new BatchException('Batch data bad value');
        $SKU = SKUs::getById($SKU_id);
        if (empty($SKU))
            throw new BatchException('Batch data bad value');

        $exist = Batches::getBatchBySKUAndNumber($SKU_id, $number);
        $expired_new = (new \DateTime($expired))->format('Y-m-d');
        if ($exist) {
            $batch_expired = (new \DateTime($exist['expired']))->format('Y-m-d');
            if (!empty($exist) && $expired_new != $batch_expired) {
                throw new BatchException("Batch $number already exists with another expiry date ($batch_expired)");
            }
            if (!empty($exist)) {
                return $exist['id'];
            }
        }
        $sup_data = Suppliers::getWeeksBeforeExpiryBySupplierId($SKU->getSupplierId());
        if (!empty($sup_data)) {
            $date_from = date('Y-m-d', !empty($sup_data['weeks_before_expiry']) ? strtotime("{$sup_data['weeks_before_expiry']} weeks") : time());
            if ($expired_new <= $date_from)
                throw new BatchException("Batch $number ($expired_new) expired (see Weeks Before Expiry for company)");
        }
        $data = [
            'number' => $number,
            'SKU_id' => $SKU_id,
            'expired' => $expired,
        ];
        $batch = new Batch($data);
        $batch->save(array_keys($data));
        return $batch->getId();
    }
}
