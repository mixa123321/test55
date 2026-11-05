<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;

class ChargesRecurring extends \app\core\models\ChargesRecurring
{
    const HEADER = [
        'recurring_charge',
        'quantity',
        'price',
        'total'
    ];

    public static function getByCode(int $sw_id, string $code)
    {
        $charge = Charges::getByCode($code, false);
        if (!$charge)
            return null;
        $mysql = new SafeMysql();
        $mysql->execute("
            INSERT IGNORE INTO charges_recurring (charge_id, supplier_warehouse_id) VALUES (:charge_id, :sw_id)", [
            'charge_id' => $charge['id'],
            'sw_id' => $sw_id,
        ]);
        return $mysql->fetchScalar("
            SELECT id FROM charges_recurring
            WHERE charge_id = :charge_id AND supplier_warehouse_id = :sw_id", [
            'charge_id' => $charge['id'],
            'sw_id' => $sw_id,
        ]);
    }

    public static function getFilename(int $warehouse_id, int $id)
    {
        ['sage_id' => $alias, ] = InvoiceBillingSuppliers::getSupplierBilling($id);
        return sprintf("$alias-%03d-recurring-charge.csv", $warehouse_id);
    }

    public static function createDataExport(int $warehouse_id, int $id)
    {
        $data = (new SafeMysql())->fetchAll("
            SELECT c.code, ibc.quantity, ibc.value, ROUND(ibc.quantity*ibc.value, 4) total
            FROM invoice_billing_charges ibc
                JOIN charges c ON ibc.type_id = c.id
            WHERE ibc.type = 0 AND ibc.invoice_billing_supplier_id = :id", [
            'id' => $id,
        ]);
        return [self::getFilename($warehouse_id, $id), self::HEADER, $data];
    }

    public static function getCodeById(int $id): string
    {
        if (!$id)
            return '';
        return (string)(new SafeMysql())->fetchScalar("
            SELECT c.code
            FROM charges_recurring cr
                JOIN charges c ON cr.charge_id = c.id
            WHERE cr.id = :id", [
            'id' => $id,
        ]);
    }
}