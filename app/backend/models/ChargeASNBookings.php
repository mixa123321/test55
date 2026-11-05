<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Charge;

class ChargeASNBookings extends \app\core\models\ChargeASNBookings
{
    const HEADER = [
        'asn_id',
        'reference',
        'count_of_unique_SKUs',
        'cost_for_each_unique_sku',
        'count_of_all_SKUs',
        'cost_for_1_booking_in_sku',
        'cost_for_all_unique_SKUs',
        'cost_for_all_SKUs',
        'total'
    ];

    const TYPE_NAME = [
        Charge::TYPE_BOOKING_IN => 'booking-in',
    ];

    public static function createDataExport(int $warehouse_id, int $id)
    {
        $data = (new SafeMysql())->fetchAll("
            SELECT cab.ASN_id, a.ref, cab.unique_SKUs, cab.additional_SKU, cab.total_SKUs, cab.booking_1_SKU, 
                   0 total_unique, 0 total_all, 0 total
            FROM charge_ASN_booking cab 
                JOIN invoice_billing_charges ibc ON cab.ib_charge_id = ibc.id AND
                                                    ibc.invoice_billing_supplier_id = :id AND 
                                                    ibc.type = :type AND 
                                                    ibc.type_id = 0
                JOIN ASNs a ON cab.ASN_id = a.id",
            [
                     'id' => $id,
                     'type' => Charge::TYPE_BOOKING_IN
            ]);
        if ($data) {
            foreach ($data as &$item) {
                $item['total_unique'] = round($item['unique_SKUs'] * $item['additional_SKU'], 4);
                $item['total_all'] = round($item['total_SKUs'] * $item['booking_1_SKU'], 4);
                $item['total'] = $item['total_unique'] + $item['total_all'];
            }
            unset($item);
        }
        return [self::getFilename($warehouse_id, $id), self::HEADER, $data];
    }

    public static function getFilename(int $warehouse_id, int $id)
    {
        ['sage_id' => $alias, ] = InvoiceBillingSuppliers::getSupplierBilling($id);
        $type_name = self::TYPE_NAME[Charge::TYPE_BOOKING_IN] ?? 'unknown';
        return sprintf("$alias-%03d-charge-for-$type_name.csv", $warehouse_id);
    }

    public static function getDetails(int $id, string $symbol)
    {
        $data = (new SafeMysql())->fetchOne("
            SELECT SUM(unique_SKUs) unique_SKUs, ROUND(additional_SKU, 4) additional_SKU,
                   SUM(total_SKUs) total_SKUs, ROUND(booking_1_SKU, 4) booking_1_SKU
            FROM charge_ASN_booking
            WHERE ib_charge_id = :id",
            [
                'id' => $id
            ]
        );
        if (!$data)
            return [];
        $res = [
            ['Booking-in SKU', $data['unique_SKUs'], $data['additional_SKU']],
            ['Stock Count per item', $data['total_SKUs'], $data['booking_1_SKU']]
        ];
        foreach ($res as &$item) {
            $item[3] = sprintf("%s %.4f", $symbol, $item[1]*$item[2]);
            $item[2] = sprintf("%s %.4f", $symbol, $item[2]);
        }
        unset($item);
        return $res;
    }
}