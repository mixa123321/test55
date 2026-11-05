<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Charge;

class ChargeOrderPackagings extends \app\core\models\ChargeOrderPackagings
{
    const HEADER = [
        'order_id',
        'package_type',
        'cost_for_package'
    ];

    const TYPE_NAME = [
        Charge::TYPE_PACKAGING => 'packagings',
        Charge::TYPE_CULLING_PACKAGING => 'culling-packagings',
        Charge::TYPE_BUNDLING_PACKAGING => 'bundling-packagings',
    ];

    public static function createDataExport(int $warehouse_id, int $id, int $type)
    {
        $data = (new SafeMysql())->fetchAll("
            SELECT cop.order_id, c.name, cop.packaging
            FROM charge_order_packaging cop 
                JOIN invoice_billing_charges ibc ON cop.ib_charge_id = ibc.id AND
                                                    ibc.invoice_billing_supplier_id = :id AND 
                                                    ibc.type = :type
                JOIN consumables c ON ibc.type_id = c.id", [
            'id' => $id,
            'type' => $type,
        ]);
        return [self::getFilename($warehouse_id, $id, $type), self::HEADER, $data];
    }

    public static function getFilename(int $warehouse_id, int $id, int $type)
    {
        ['sage_id' => $alias, ] = InvoiceBillingSuppliers::getSupplierBilling($id);
        $type_name = self::TYPE_NAME[$type] ?? 'unknown';
        return sprintf("$alias-%03d-charge-for-$type_name.csv", $warehouse_id);
    }
}