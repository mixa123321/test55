<?php

namespace app\core\services\billing\AB;

use app\backend\models\SKUBarcodes;
use app\core\dbal\SafeMysql;
use app\core\entities\ASN;

class ASNs extends \app\backend\models\ASNs
{
    public static function getBooking(int $warehouse_id, $start_date, $last_date)
    {
        return (new SafeMysql())->fetchAll("
            SELECT sup.id, sw.sage_id, sup.name sup_name, a.id ASN_id, a.ref,
                   ask.SKU_id, s.name, (" . SKUBarcodes::getPrimaryBySKUIdSQL('ask.SKU_id') . ") barcode, SUM(ask.accepted_quantity) qty
            FROM ASNs a
                JOIN ASN_SKUs ask ON a.id = ask.ASN_id AND ask.accepted_quantity
                JOIN SKUs s ON ask.SKU_id = s.id
                JOIN suppliers sup ON s.supplier_id = sup.id
                JOIN suppliers_warehouses sw ON sup.id = sw.supplier_id AND a.warehouse_id = sw.warehouse_id 
            WHERE a.completed_date >= :start_date AND a.completed_date <= :last_date AND
                  a.warehouse_id = :warehouse_id AND
                  a.status = :status AND
                  (a.order_id IS NULL OR NOT a.order_id)
            GROUP BY a.id, ask.SKU_id", [
                'warehouse_id' => $warehouse_id,
                'status' => ASN::COMPLETED,
                'start_date' => $start_date,
                'last_date' => $last_date
        ]);
    }
}
