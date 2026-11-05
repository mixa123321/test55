<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;

class InvoiceBillingSuppliers extends \app\core\models\InvoiceBillingSuppliers
{
    public static function getInvoices(int $warehouse_id, int $invoice_id)
    {
        $data = (new SafeMysql())->fetchAll("
            SELECT ibs.id, sup.name, sw.sage_id, u.login, ibs.create_date 
            FROM invoice_billing_suppliers ibs
                JOIN users u ON ibs.user_id = u.id
                JOIN suppliers_warehouses sw ON ibs.supplier_warehouse_id = sw.id AND sw.warehouse_id = :warehouse_id
                JOIN suppliers sup ON sw.supplier_id = sup.id
            WHERE ibs.invoice_billing_id = :invoice_id
            ORDER BY ibs.create_date DESC", [
            'warehouse_id' => $warehouse_id,
            'invoice_id' => $invoice_id,
        ]);
        $res = [];
        foreach ($data as $item) {
            if (empty($res[$item['create_date']]))
                $res[$item['create_date']] = [];
            if (empty($res[$item['create_date']][$item['login']]))
                $res[$item['create_date']][$item['login']] = [];
            $res[$item['create_date']][$item['login']][$item['id']] = "{$item['name']} ({$item['sage_id']})";
        }
        return $res;
    }

    public static function getSupplierBilling(int $id)
    {
        return (new SafeMysql())->fetchOne("
            SELECT sw.sage_id, UNIX_TIMESTAMP(ibs.create_date) create_date, sw.billing_address, sw.VAT, w.currency_id, sw.warehouse_id 
            FROM invoice_billing_suppliers ibs
                JOIN suppliers_warehouses sw ON ibs.supplier_warehouse_id = sw.id
                JOIN warehouses w ON sw.warehouse_id = w.id
            WHERE ibs.id = :id", [
            'id' => $id,
        ]);
    }
}