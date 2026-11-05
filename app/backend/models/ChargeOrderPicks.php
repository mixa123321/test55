<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Charge;

class ChargeOrderPicks extends \app\core\models\ChargeOrderPicks
{
    const HEADER = [
        'order_id',
        'reference',
        'reference2',
        'cost_for_packing_1_order',
        'count_of_unique_SKUs',
        'cost_for_each_additional_unique_sku',
        'count_of_all_SKUs_in_order',
        'cost_for_1_pick_sku',
        'total_cost_for_picks',
        'total_additional_cost_for_unique_sku',
        'total'
    ];

    const TYPE_NAME = [
        Charge::TYPE_PICK => 'picks',
        Charge::TYPE_CULLING_PICK => 'culling-picks',
        Charge::TYPE_BUNDLING_PICK => 'bundling-picks',
    ];

    const TYPE_NAME_INVOICE = [
        Charge::TYPE_PICK => [
            'Pick and Pack 1st SKU',
            'Pick and Pack Additional SKUs',
            'Pick and Pack Per Item'
        ],
        Charge::TYPE_CULLING_PICK => [
            'Pick and Cull 1st SKU',
            'Pick and Cull Additional SKUs',
            'Pick & Cull Per Item'
        ],
        Charge::TYPE_BUNDLING_PICK => [
            'Pick and Bundle 1st SKU',
            'Pick and Bundle Additional SKUs',
            'Pick & Bundle Per Item'
        ],
    ];

    public static function createDataExport(int $warehouse_id, int $id, int $type)
    {
        $data = (new SafeMysql())->fetchAll("
            SELECT cop.order_id, o.ref, o.ref2, cop.packing_1_order, cop.unique_SKUs, cop.additional_SKU, cop.total_SKUs, cop.pick_1_SKU, 
                   0 total_picks, 0 total_unique, 0 total
            FROM charge_order_pick cop 
                JOIN invoice_billing_charges ibc ON cop.ib_charge_id = ibc.id AND
                                                    ibc.invoice_billing_supplier_id = :id AND 
                                                    ibc.type = :type AND 
                                                    ibc.type_id = 0
                JOIN orders o ON cop.order_id = o.id", [
            'id' => $id,
            'type' => $type,
        ]);
        if ($data) {
            foreach ($data as &$item) {
                $item['total_picks'] = round($item['total_SKUs']*$item['pick_1_SKU'], 4);
                $item['total_unique'] = round(($item['unique_SKUs'] - 1)*$item['additional_SKU'], 4);
                $item['total'] = $item['packing_1_order'] + $item['total_picks'] + $item['total_unique'];
            } unset($item);
        }
        return [self::getFilename($warehouse_id, $id, $type), self::HEADER, $data];
    }

    public static function getFilename(int $warehouse_id, int $id, int $type)
    {
        ['sage_id' => $alias, ] = InvoiceBillingSuppliers::getSupplierBilling($id);
        $type_name = self::TYPE_NAME[$type] ?? 'unknown';
        return sprintf("$alias-%03d-charge-for-$type_name.csv", $warehouse_id);
    }

    public static function getDetails(int $id, int $type, string $symbol)
    {
        $data = (new SafeMysql())->fetchOne("
            SELECT ROUND(packing_1_order, 4) packing_1_order,
                   SUM(unique_SKUs) unique_SKUs, COUNT(*) nums, ROUND(additional_SKU, 4) additional_SKU,
                   SUM(total_SKUs) total_SKUs, ROUND(pick_1_SKU, 4) pick_1_SKU
            FROM charge_order_pick
            WHERE ib_charge_id = :id", [
            'id' => $id,
        ]);
        if (!$data)
            return [];
        $res = [
            [self::TYPE_NAME_INVOICE[$type][0], $data['nums'], $data['packing_1_order']],
            [self::TYPE_NAME_INVOICE[$type][1], $data['unique_SKUs'] - $data['nums'], $data['additional_SKU']],
            [self::TYPE_NAME_INVOICE[$type][2], $data['total_SKUs'], $data['pick_1_SKU']]
        ];
        foreach ($res as &$item) {
            $item[3] = sprintf("%s %.4f", $symbol, $item[1]*$item[2]);
            $item[2] = sprintf("%s %.4f", $symbol, $item[2]);
        } unset($item);
        return $res;
    }
}