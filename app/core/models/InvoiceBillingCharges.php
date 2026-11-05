<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class InvoiceBillingCharges
{
    public static function add(bool &$is_invoice, int $invoice_id, int $qty, float $price, int $type, int $type_id = 0): int
    {
        if (!$is_invoice)
            $is_invoice = true;
        return (new SafeMysql())->insert("
            INSERT INTO invoice_billing_charges (invoice_billing_supplier_id, type, type_id, quantity, value)
            VALUES (:invoice_id, :type, :type_id, :qty, :price)", [
            'invoice_id' => $invoice_id,
            'type' => $type,
            'type_id' => $type_id,
            'qty' => $qty,
            'price' => $price,
        ]);
    }
}