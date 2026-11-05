<?php

namespace app\core\models;

use app\core\entities\ReportSKUArrivalHistory;
use app\core\dbal\SafeMysql;

class ReportSKUArrivalHistories
{
    /**
     * @param ReportSKUArrivalHistory[] $objects
     * @return void
     */
    public static function insert(array $objects): void
    {
        if (!$objects)
            return;
        $values = $bind = [];
        foreach ($objects as $key => $object) {
            $values[] = "(:SKU_id_$key, :supplier_id_$key, :name_$key, :pin_$key, :product_code_$key, :in_out_$key, :reason_code_$key, :reason_desc_$key, :qty_$key, :run_date_$key, :event_date_$key, :warehouse_id_$key, :SKU_history_id_$key)";
            $bind["SKU_id_$key"] = $object->getSKUId();
            $bind["supplier_id_$key"] = $object->getSupplierId();
            $bind["name_$key"] = $object->getName();
            $bind["pin_$key"] = $object->getPin();
            $bind["product_code_$key"] = $object->getProductCode();
            $bind["in_out_$key"] = $object->getInOut();
            $bind["reason_code_$key"] = $object->getReasonCode();
            $bind["reason_desc_$key"] = $object->getReasonDesc();
            $bind["qty_$key"] = $object->getQty();
            $bind["run_date_$key"] = $object->getRunDate();
            $bind["event_date_$key"] = $object->getEventDate();
            $bind["warehouse_id_$key"] = $object->getWarehouseId();
            $bind["SKU_history_id_$key"] = $object->getSKUHistoryId();
        }
        $sql = "
        INSERT IGNORE INTO report_SKU_arrival_history (SKU_id, supplier_id, `name`, pin, product_code, in_out, reason_code, reason_desc, qty, run_date, event_date, warehouse_id, SKU_history_id)
        VALUES " . implode(', ', $values);
        (new SafeMysql())->execute($sql, $bind);
    }
}