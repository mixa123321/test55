<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class InvoiceBillingSuppliers
{
    public static function add(int $invoice_id, int $supplier_warehouse_id, int $user_id): int
    {
        return (new SafeMysql())->insert("
            INSERT INTO invoice_billing_suppliers (invoice_billing_id, supplier_warehouse_id, user_id)
            VALUES (:invoice_id, :supplier_warehouse_id, :user_id)", [
            'invoice_id' => $invoice_id,
            'supplier_warehouse_id' => $supplier_warehouse_id,
            'user_id' => $user_id,
        ]);
    }
}