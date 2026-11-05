<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Billing as BillingEntity;

class InvoicesBilling extends \app\core\models\InvoicesBilling
{
    public static function getAll(int $warehouse_id)
    {
        $data = (new SafeMysql())->fetchAll("
            SELECT ib.id, ib.year, ib.month, GROUP_CONCAT(DISTINCT CONCAT(b.created_at, '|', b.filename) ORDER BY b.created_at, b.filename) files, COUNT(ibs.id) status
            FROM invoices_billing ib
                JOIN billing b ON ib.id = b.invoice_billing_id AND b.warehouse_id = :warehouse_id AND b.status = :status
                LEFT JOIN invoice_billing_suppliers ibs ON ib.id = ibs.invoice_billing_id
            GROUP BY ib.id
            ORDER BY ib.year DESC, ib.month DESC", [
            'warehouse_id' => $warehouse_id,
            'status' => BillingEntity::DONE,
        ]);
        if (!$data)
            return [];
        foreach ($data as &$item) {
            $files = explode(',', $item['files']);
            $item_files = [];
            foreach ($files as $file) {
                [$date, $filename] = explode('|', $file);
                if (empty($item_files[$date]))
                    $item_files[$date] = [];
                $item_files[$date][] = $filename;
            }
            $item['files'] = $item_files;
        } unset($item);
        return $data;
    }
}