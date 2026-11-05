<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Charge;
use app\core\entities\ChargeGroup;
use app\core\entities\ChargeHistory;
use app\core\helpers\Debug;
use app\core\models\ChargeGroups;
use app\core\models\config\OrdersConfig;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\helpers\FileWrapper;
use app\core\models\ChargeOrderPackagings;
use app\core\models\ChargeOrderPicks;
use app\core\models\ChargesHistory;
use app\core\models\Invoice;
use app\core\models\InvoiceBillingCharges;
use app\core\statical\WithDate;

class Billing
{
    const CHARGE_TAB = 'charges-tab';
    const RECURRING_TAB = 'recurring-tab';

    private static function separateData(array $data): array
    {
        $insert_data = $update_data = $delete_data = [];
        foreach ($data as $range)
            switch ($range['action']) {
                case 'insert':
                    $insert_data[] = $range;
                    break;
                case 'update':
                    $update_data[] = $range;
                    break;
                case 'delete':
                    $delete_data[] = $range;
                    break;
            }
        return [$insert_data, $update_data, $delete_data];
    }
    public static function setTariffs(int $sw_id, array $header, array $tariffs, User $user)
    {
        $code_pos = array_search('Service Code', $header);
        $pack_pos = array_search('Packaging', $header);
        $val_pos = array_search('Charge', $header);
        $from_pos = array_search('Valid From', $header);
        $to_pos = array_search('Valid To', $header);
        $charges = Charges::getAll();
        $codes = array_unique(array_column($charges, 'code'));
        $data = $errors = [];
        $is_create = false;
 //       $mysql = new SafeMysql();
 //       $mysql->beginTransaction();;
        foreach ($tariffs as $key => $billing_row) {
            $key++;
            $code = $billing_row[$code_pos];
            if (!in_array($code, $codes)) {
                $errors[] = "Row #$key: Service Code '$code' is not valid";
                continue;
            }
            if ($pack_pos !== false)
                $packaging = $billing_row[$pack_pos];
            $id = ChargesComplex::getByCode($sw_id, $code, $packaging ?? $pack_pos);
            if (!$id) {
                $errors[] = "Row #$key ($code): wrong params";
                continue;
            }
            $res = self::editCharge([
                'sw_id' => $sw_id,
                'cb_id' => 0,
                'id' => $id,
                'type_charge' => self::CHARGE_TAB,
                'value' => $billing_row[$val_pos],
                'valid_from' => $billing_row[$from_pos],
                'valid_to' => $billing_row[$to_pos]
            ], $user->getId());
            if (isset($res['error'])) {
                $errors[] = "Row #$key ($code): {$res['error']}";
                continue;
            }
            if (isset($res['confirm'])) {
                $id = array_values($res['confirm'])[0]['charge_complex_id'];
                [$insert_data, $update_data, $delete_data] = self::separateData($res['confirm']);
                self::updateRange($sw_id, self::CHARGE_TAB, $id, $user->getId(), $delete_data, $insert_data, $update_data);
            }
            $is_create = true;
            $data[] = $billing_row;
        }
        if ($is_create) {
            $charge_history_id = ChargesHistory::add($sw_id, $user->getId(), ChargeHistory::EVENT_IMPORT);
            ChargesBilling::saveCSV($charge_history_id, $header, $data);
        }
 //       $mysql->commit();
        return [count($data), $errors];
    }

    public static function setRecurringTariffs(int $sw_id, array $header, array $tariffs, User $user)
    {
        $code_pos = array_search('Service Code', $header);
        $qty_pos = array_search('Quantity', $header);
        $val_pos = array_search('Charge', $header);
        $from_pos = array_search('Valid From', $header);
        $to_pos = array_search('Valid To', $header);
        $charges = Charges::getAll(false);
        $codes = array_unique(array_column($charges, 'code'));
        $data = $errors = [];
        $is_create = false;
//        $mysql = new SafeMysql();
//        $mysql->beginTransaction();
        foreach ($tariffs as $key => $billing_row) {
            $key++;
            $code = $billing_row[$code_pos];
            if (!in_array($code, $codes)) {
                $errors[] = "Row #$key: Service Code '$code' is not valid";
                continue;
            }
            $id = ChargesRecurring::getByCode($sw_id, $code);
            if (!$id) {
                $errors[] = "Row #$key ($code): wrong params";
                continue;
            }
            $res = self::editCharge([
                'sw_id' => $sw_id,
                'cb_id' => 0,
                'id' => $id,
                'type_charge' => self::RECURRING_TAB,
                'quantity' => $billing_row[$qty_pos],
                'value' => $billing_row[$val_pos],
                'valid_from' => $billing_row[$from_pos],
                'valid_to' => $billing_row[$to_pos]
            ], $user->getId());
            if (isset($res['error'])) {
                $errors[] = "Row #$key ($code): {$res['error']}";
                continue;
            }
            if (isset($res['confirm'])) {
                $id = array_values($res['confirm'])[0]['charge_recurring_id'];
                [$insert_data, $update_data, $delete_data] = self::separateData($res['confirm']);
                self::updateRange($sw_id, self::RECURRING_TAB, $id, $user->getId(), $delete_data, $insert_data, $update_data);
            }
            $is_create = true;
            $data[] = $billing_row;
        }
        if ($is_create) {
            $charge_history_id = ChargesHistory::add($sw_id, $user->getId(), ChargeHistory::EVENT_IMPORT_RECURRING);
            ChargesBilling::saveCSV($charge_history_id, $header, $data);
        }
 //       $mysql->commit();
        return [count($data), $errors];
    }

    public static function getFiles(int $warehouse_id, int $id)
    {
        $sql = "
            SELECT GROUP_CONCAT(b.filename) files, b.created_at, ib.year, ib.month
            FROM billing b
                JOIN invoices_billing ib ON b.invoice_billing_id = ib.id
            WHERE b.warehouse_id = :warehouse_id AND 
                  b.invoice_billing_id = :id AND
                  b.created_at = (SELECT MAX(created_at) FROM billing WHERE warehouse_id = :warehouse_id AND invoice_billing_id = :id)";
        return (new SafeMysql())->fetchOne($sql, [
            'warehouse_id' => $warehouse_id,
            'id' => $id,
        ]);
    }

    public static function convert(array $suppliers, array $shipper_ids, array $data, array &$start_charges)
    {
        $data = array_filter($data, function ($item) use($shipper_ids, $suppliers) {
            return in_array($item['ShipperId'], $shipper_ids) &&
                in_array($item['ServiceCode'], $suppliers[$item['ShipperId']]['types']);
        });
        foreach ($data as $item) {
            $sw_id = $suppliers[$item['ShipperId']]['id'];
            if (empty($start_charges[$sw_id]))
                $start_charges[$sw_id] = [];
            if (empty($start_charges[$sw_id][$item['ServiceCode']]))
                $start_charges[$sw_id][$item['ServiceCode']] = [];
            $start_charges[$sw_id][$item['ServiceCode']][] = array_slice($item, 4);
        }
    }

    private static function getDataFromFiles(Warehouse $warehouse, array $invoice_data, array $suppliers, array &$errors)
    {
        $start_charges = [];
        $shipper_ids = array_keys($suppliers);
        $files = explode(',', $invoice_data['files'] ?? '[]');
        foreach ($files as $file) {
            $filename = BILLING_STORAGE . DIRECTORY_SEPARATOR . $warehouse->getBilling() . DIRECTORY_SEPARATOR . $file;
            $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($filename)));
            if (!$file_wrapper->isFile($filename)) {
                $errors[] = "File '$file' is not found";
                continue;
            }
            $data = stream_get_contents($file_wrapper->getStream($filename));
            if (empty($data)) {
                $errors[] = "File '$file' is empty";
                continue;
            }
            $data = json_decode($data, true)['Data'];
            self::convert($suppliers, $shipper_ids, $data, $start_charges);
        }
        return $start_charges;
    }

    public static function createInvoice(Warehouse $warehouse, int $id, int $user_id)
    {
        $suppliers = SupplierWarehouses::getSuppliersBilling($warehouse->getId());
        if (!$suppliers)
            return 'Data is not found (suppliers)';
        $errors = $start_charges = [];
        $invoice_data = self::getFiles($warehouse->getId(), $id);
        if ($invoice_data)
            $start_charges = self::getDataFromFiles($warehouse, $invoice_data, $suppliers, $errors);
        $packagings = SupplierWarehouses::getPackagings(array_column($suppliers, 'id'));
        $is_invoice = false;
        foreach ($suppliers as $supplier) {
            $is_invoice_supplier = false;
            $mysql = new SafeMysql();
            $mysql->beginTransaction();
            $invoice_id = InvoiceBillingSuppliers::add($id, $supplier['id'], $user_id);
            $packaging = $packagings[$supplier['id']] ?? [];
            foreach ($start_charges[$supplier['id']] ?? [] as $type => $values) {
                $type_num = array_search($type, Charge::TYPE_NAMES);
                $charges_complex = ChargesBilling::getChargeByType($supplier['id'], $invoice_data['created_at'],
                    !in_array($type_num, array_keys(Charge::TYPE_EQUAL)) ? $type_num : Charge::TYPE_EQUAL[$type_num]);
                switch ($type_num) {
                    case Charge::TYPE_STORAGE_PF:
                        [$qty, $price] = self::solveStorage($values, $charges_complex);
                        InvoiceBillingCharges::add($is_invoice_supplier, $invoice_id, $qty, $price, $type_num);
                        break;
                    case Charge::TYPE_STORAGE_ST:
                        [$qty, $price] = self::solveStorage($values, $charges_complex, 'storage_st');
                        InvoiceBillingCharges::add($is_invoice_supplier, $invoice_id, $qty, $price, $type_num);
                        break;
                    case Charge::TYPE_PICK:
                    case Charge::TYPE_CULLING_PICK:
                    case Charge::TYPE_BUNDLING_PICK:
                        [$qty, $price, $charge_picks] = self::solvePicks($values, $charges_complex);
                        $ib_charge_id = InvoiceBillingCharges::add($is_invoice_supplier, $invoice_id, $qty, $price, $type_num);
                        ChargeOrderPicks::add($ib_charge_id, $charge_picks);
                        break;
                    case Charge::TYPE_CULLING_PACKAGING:
                        $code = 'culling_packaging';
                    case Charge::TYPE_PACKAGING:
                    case Charge::TYPE_BUNDLING_PACKAGING:
                        if (empty($packaging))
                            break;
                        if ($type_num !== Charge::TYPE_CULLING_PACKAGING)
                            $code = 'packaging';
                        [$packaging_prices, $charge_packagings] = self::solvePackagings($values, $charges_complex, $packaging, $code);
                        foreach ($packaging_prices as $packaging_id => $price_data) {
                            $ib_charge_id = InvoiceBillingCharges::add($is_invoice_supplier, $invoice_id, $price_data['qty'], $price_data['price'], $type_num, $packaging_id);
                            if (empty($charge_packagings[$packaging_id]))
                                continue;
                            ChargeOrderPackagings::add($ib_charge_id, $charge_packagings[$packaging_id]);
                        }
                        break;
                    case Charge::TYPE_BOOKING_IN:
                        [$qty, $price, $charge_bookings] = self::solveBooking($values, $charges_complex);
                        $ib_charge_id = InvoiceBillingCharges::add($is_invoice_supplier, $invoice_id, $qty, $price, $type_num);
                        ChargeASNBookings::add($ib_charge_id, $charge_bookings);
                        break;
                    case Charge::TYPE_LOCATION_CULLING:
                        // Coming soon
                        break;
                }
            }
            $charges_recurring = ChargesRecurringBilling::getChargesRecurring($supplier['recurring'], $invoice_data['created_at']);
            foreach ($charges_recurring as $charge_recurring)
                InvoiceBillingCharges::add($is_invoice_supplier, $invoice_id, $charge_recurring['quantity'], $charge_recurring['value'], 0, $charge_recurring['charge_id']);
            if (!$is_invoice_supplier) {
                $mysql->rollback();
                continue;
            }
            $mysql->commit();
            $is_invoice = true;
        }
        if (!$is_invoice)
            return 'There is no data to create invoices';
        return $errors;
    }

    public static function solveStorage(array $values, array $charges_complex, string $code = 'storage_pf')
    {
        $qty = array_sum(array_column($values, 'Items'));
        $price = 0;
        foreach ($charges_complex as $charge_complex)
            if ($charge_complex['code'] == $code) {
                $price = $charge_complex['value'];
                break;
            }
        return [$qty, $price];
    }

    public static function solvePicks(array $values, array $charges_complex)
    {
        $orders_exclude = OrdersConfig::readConfigsByIdsAndParams(array_column($values, 'OrderId'), [OrdersConfig::TYPE_EXCLUDE_ORDER_STATUS]);
        $orders = [];
        foreach ($values as $item) {
            if ($orders_exclude[$item['OrderId']][OrdersConfig::TYPE_EXCLUDE_ORDER_STATUS])
                continue;
            if (empty($orders[$item['OrderId']]))
                $orders[$item['OrderId']] = [];
            if (empty($orders[$item['OrderId']][$item['Barcode']]))
                $orders[$item['OrderId']][$item['Barcode']] = 0;
            $orders[$item['OrderId']][$item['Barcode']] += $item['Items'];
        }
        $processing_1_order = $pick_each_unique_sku = $pick_1_sku = 0;
        foreach ($charges_complex as $charge_complex) {
            if ($charge_complex['code'] == 'processing_1_order')
                $processing_1_order = $charge_complex['value'];
            if ($charge_complex['code'] == 'pick_each_unique_sku')
                $pick_each_unique_sku = $charge_complex['value'];
            if ($charge_complex['code'] == 'pick_1_sku')
                $pick_1_sku = $charge_complex['value'];
        }
        $price = 0;
        $charge_picks = [];
        foreach ($orders as $order_id => $items) {
            $unique_SKUs = count($items);
            $total_SKUs = array_sum($items);
            $price += $processing_1_order + ($unique_SKUs - 1)*$pick_each_unique_sku + $total_SKUs*$pick_1_sku;
            $charge_picks[] = [
                'ib_charge_id' => 0,
                'order_id' => $order_id,
                'packing_1_order' => $processing_1_order,
                'unique_SKUs' => $unique_SKUs,
                'additional_SKU' => $pick_each_unique_sku,
                'total_SKUs' => $total_SKUs,
                'pick_1_SKU' => $pick_1_sku,
            ];
        }
        return [1, $price, $charge_picks];
    }

    public static function solvePackagings(array $values, array $charges_complex, array $packaging_list, string $code = 'packaging')
    {
        $orders_exclude = OrdersConfig::readConfigsByIdsAndParams(array_column($values, 'OrderId'), [OrdersConfig::TYPE_EXCLUDE_ORDER_STATUS]);
        $packagings = [];
        foreach ($values as $item) {
            if ($orders_exclude[$item['OrderId']][OrdersConfig::TYPE_EXCLUDE_ORDER_STATUS])
                continue;
            if (empty($packagings[$item['Packaging']]))
                $packagings[$item['Packaging']] = 0;
            $packagings[$item['Packaging']]++;
        }
        $prices = [];
        foreach ($charges_complex as $charge_complex)
            if ($charge_complex['code'] == $code && $charge_complex['cc_type'] == 1)
                $prices[$charge_complex['type_id']] = $charge_complex['value'];
        $res = [];
        foreach ($packagings as $name => $qty) {
            if (empty($packaging_list[$name]))
                continue;
            $id = $packaging_list[$name];
            if (empty($prices[$id]))
                continue;
            $res[$id] = ['qty' => $qty, 'price' => $prices[$id]];
        }
        $charge_packagings = [];
        foreach ($values as $item) {
            if ($orders_exclude[$item['OrderId']][OrdersConfig::TYPE_EXCLUDE_ORDER_STATUS])
                continue;
            if (empty($packaging_list[$item['Packaging']]))
                continue;
            $id = $packaging_list[$item['Packaging']];
            if (empty($charge_packagings[$id]))
                $charge_packagings[$id] = [];
            $charge_packagings[$id][] = [
                'ib_charge_id' => 0,
                'order_id' => $item['OrderId'],
                'packaging_id' => $id,
                'packaging' => $res[$id]['price'] ?? 0,
            ];
        }
        return [$res, $charge_packagings];
    }

    public static function solveBooking(array $values, array $charges_complex)
    {
        $ASNs = [];
        foreach ($values as $item) {
            if (empty($ASNs[$item['ASNId']]))
                $ASNs[$item['ASNId']] = [];
            if (empty($ASNs[$item['ASNId']][$item['Barcode']]))
                $ASNs[$item['ASNId']][$item['Barcode']] = 0;
            $ASNs[$item['ASNId']][$item['Barcode']] += $item['Items'];
        }
        $booking_each_unique_sku = $booking_1_sku = 0;
        foreach ($charges_complex as $charge_complex) {
            if ($charge_complex['code'] == 'booking_each_unique_sku')
                $booking_each_unique_sku = $charge_complex['value'];
            if ($charge_complex['code'] == 'booking_1_sku')
                $booking_1_sku = $charge_complex['value'];
        }
        $price = 0;
        $charge_bookings = [];
        foreach ($ASNs as $ASN_id => $items) {
            $unique_SKUs = count($items);
            $total_SKUs = array_sum($items);
            $price += $unique_SKUs*$booking_each_unique_sku + $total_SKUs*$booking_1_sku;
            $charge_bookings[] = [
                'ib_charge_id' => 0,
                'ASN_id' => $ASN_id,
                'unique_SKUs' => $unique_SKUs,
                'additional_SKU' => $booking_each_unique_sku,
                'total_SKUs' => $total_SKUs,
                'booking_1_SKU' => $booking_1_sku,
            ];
        }
        return [1, $price, $charge_bookings];
    }

    public static function createInvoicePDF(int $id)
    {
        (new Invoice($id))
            ->setLogoName('air-business-logo.jpg')
            ->setAddressFrom([
                'Air Business Limited',
                'The Beacon, Mosquito Way, Hatfield, AL10 9WN',
                'T 01727 890600 F 01727 890601',
                'www.airbusiness.com',
                'V.A.T. No GB 860 2290 43'
            ])
            ->make();
    }

    public static function createCharge(array $data)
    {
        $code = addslashes(trim($data['code']));
        if (empty($code))
            return ['error' => 'Charge code is empty'];
        if (strpos($code, ' ') !== false)
            return ['error' => 'Charge code is wrong (spaces)'];
        if (Charges::getIdByCode($code, false))
            return ['error' => 'Charge code is not unique'];
        $data = [
            'name' => addslashes(trim($data['name'])),
            'code' => $code,
            'type' => 0
        ];
        $charge = new Charge($data);
        $charge->save(array_keys($data));
        return ['update' => true];
    }

    public static function updateCharge(array $data, int $id)
    {
        $charge = Charges::getById($id);
        if (!$charge)
            return ['error' => 'Charge is not found'];
        $is_changed = false;
        $fields = ['id'];
        $name = addslashes(trim($data['name']));
        if ($name !== $charge->getName()) {
            $is_changed = true;
            $charge->setName($name);
            $fields[] = 'name';
        }
        if (!$charge->standard()) {
            $code = addslashes(trim($data['code']));
            if (empty($code))
                return ['error' => 'Charge code is empty'];
            if (strpos($code, ' ') !== false)
                return ['error' => 'Charge code is wrong (spaces)'];
            $charge_id = Charges::getIdByCode($code, false);
            if ($charge_id && $charge_id != $charge->getId())
                return ['error' => 'Charge code is not unique'];
            if ($code !== $charge->getCode()) {
                $is_changed = true;
                $charge->setCode($code);
                $fields[] = 'code';
            }
        }
        if (!$is_changed || count($fields) === 1)
            return ['update' => false];
        $charge->save($fields);
        return ['update' => true];
    }

    private static function changeData(array $start, array $end)
    {
        $change_data = $start['value'] != ($end['value'] ?? 0);
        if ($start['type_charge'] === self::RECURRING_TAB)
            $change_data = $change_data || ($start['quantity'] != ($end['quantity'] ?? 0));
        return [
            $change_data,
            strtotime($start['valid_from']) != strtotime($end['valid_from'] ?? ''),
            strtotime($start['valid_to']) != strtotime($end['valid_to'] ?? '')
        ];
    }

    private static function changeValues(string $type_charge, int $quantity, float $value, array $data, int $id, int $cb_id, int $sw_id, int $user_id)
    {
        $changes = [];
        if ($type_charge === self::RECURRING_TAB && $quantity != $data['quantity'])
            $changes[] = [
                'field' => 'quantity',
                'old' => (int)$data['quantity'],
                'new' => $quantity,
            ];
        if ($value != $data['value'])
            $changes[] = [
                'field' => 'charge',
                'old' => (float)$data['value'],
                'new' => $value,
            ];
        if ($changes) {
            if ($type_charge === self::CHARGE_TAB) {
                ChargesBilling::change($cb_id, $value);
                ChargesHistory::add($sw_id, $user_id, ChargeHistory::EVENT_CHANGE, $changes);
                ChargesBilling::reduction($id);
            } else {
                ChargesRecurringBilling::change($cb_id, $quantity, $value);
                ChargesHistory::add($sw_id, $user_id, ChargeHistory::EVENT_CHANGE_RECURRING, $changes);
                ChargesRecurringBilling::reduction($id);
            }
        }
        return !empty($changes);
    }

    private static function getRanges(array $data)
    {
        $ranges = [];
        foreach ($data as $item)
            $ranges[] = [WithDate::dateToInt($item['valid_from']), WithDate::dateToInt($item['valid_to'])];
        return $ranges;
    }

    private static function getIndex(int $start_date, array $ranges)
    {
        $index = -1;
        foreach ($ranges as $key => $item) {
            if ($start_date <= $item[0])
                break;
            $index = $key;
            if ($index === count($ranges) - 1 && (!$item[1] || $start_date > $item[1]))
                $index++;
        }
        return $index;
    }

    private static function analysisData(int $id, string $type_charge, array $user_data): array
    {
        $data = [];
        if ($type_charge === self::CHARGE_TAB)
            $data = ChargesBilling::getRanges($id);
        elseif ($type_charge === self::RECURRING_TAB)
            $data = ChargesRecurringBilling::getRanges($id);
        $ranges = self::getRanges($data);
        $start_date = WithDate::dateToInt($user_data['valid_from']);
        $end_date = WithDate::dateToInt($user_data['valid_to']);
        $start_index = self::getIndex($start_date, $ranges);
        $nums = count($data);
        $create_tariff = function(int $cb_id, int $quantity, float $value, string $valid_from, string $valid_to) use($id, $type_charge) {
            return ['id' => $cb_id] + ($type_charge === self::RECURRING_TAB ? ['charge_recurring_id' => $id, 'quantity' => $quantity] : ['charge_complex_id' => $id]) + [
                    'value' => $value,
                    'valid_from' => $valid_from,
                    'valid_to' => $valid_to
                ];
        };
        $is_save = false;
        $res_conf = [];
        $res_conf['insert'][] = $create_tariff(0, $user_data['quantity'] ?? 0, $user_data['value'], date(WithDate::DATE_SQL_FORMAT, $start_date), $end_date ? date(WithDate::DATE_SQL_FORMAT, $end_date) : '');
        $next_date = date(WithDate::DATE_SQL_FORMAT, $end_date + 1);
        $prev_date = date(WithDate::DATE_SQL_FORMAT, $start_date - 1);
        if ($start_index === -1) {
            if (!$end_date) {
                if ($data)
                    $res_conf['delete'] = array_column($data, 'id');
                else
                    $is_save = true;
            } else {
                $end_index = self::getIndex($end_date, $ranges);
                if ($end_index === $nums) {
                    $res_conf['delete'] = array_column($data, 'id');
                    $res_conf['insert'][] = $create_tariff(0, 0, 0, $next_date, '');
                } elseif ($end_index !== -1) {
                    $curr_date = $data[$end_index];
                    if ($del_data = array_slice($data, 0, $end_index))
                        $res_conf['delete'] = array_column($del_data, 'id');
                    $res_conf['update'][$curr_date['id']] = $create_tariff($curr_date['id'], $curr_date['quantity'] ?? 0, $curr_date['value'], $next_date, $curr_date['valid_to']);
                } else {
                    $prev_start_date = WithDate::dateToInt($data[0]['valid_from']) - 1;
                    if ($end_date < $prev_start_date) {
                        $prev_start_date = date(WithDate::DATE_SQL_FORMAT, $prev_start_date);
                        $res_conf['insert'][] = $create_tariff(0, 0, 0, $next_date, $prev_start_date);
                    } else
                        $is_save = true;
                }
            }
        } elseif ($start_index === $nums) {
            $last_range = $ranges[$nums - 1];
            if (!$last_range[1]) {
                $last_date = $data[$nums - 1];
                $res_conf['update'][$last_date['id']] = $create_tariff($last_date['id'], $last_date['quantity'] ?? 0, $last_date['value'], $last_date['valid_from'], $prev_date);
                if ($end_date)
                    $res_conf['insert'][] = $create_tariff(0, 0, 0, $next_date, '');
            } else
                $res_conf['insert'][] = $create_tariff(0, 0, 0, date(WithDate::DATE_SQL_FORMAT, $last_range[1] + 1), $prev_date);
        } else {
            if (!$end_date) {
                if ($del_data = array_slice($data, $start_index + 1))
                    $res_conf['delete'] = array_column($del_data, 'id');
                $curr_date = $data[$start_index];
                $res_conf['update'][$curr_date['id']] = $create_tariff($curr_date['id'], $curr_date['quantity'] ?? 0, $curr_date['value'], $curr_date['valid_from'], $prev_date);
            } else {
                $end_index = self::getIndex($end_date, $ranges);
                if ($del_data = array_slice($data, $start_index + 1, $end_index - $start_index))
                    $res_conf['delete'] = array_column($del_data, 'id');
                $curr_date = $data[$start_index];
                $res_conf['update'][$curr_date['id']] = $create_tariff($curr_date['id'], $curr_date['quantity'] ?? 0, $curr_date['value'], $curr_date['valid_from'], $prev_date);
                if ($end_index !== $nums) {
                    $curr_date = $data[$end_index];
                    $res_conf['insert'][] = $create_tariff(0, $curr_date['quantity'] ?? 0, $curr_date['value'], $next_date, $curr_date['valid_to']);
                } else
                    $res_conf['insert'][] = $create_tariff(0, 0, 0, $next_date, '');
            }
        }
        return [$is_save, $data, $res_conf];
    }

    private static function reduceData(string $type_charge, array $data, array $res_conf): array
    {
        $res = [];
        foreach ($data as $datum) {
            $index = WithDate::dateToInt($datum['valid_from']);
            $res[$index] = $datum + ['action' => 'none'];
            if (!empty($res_conf['delete']) && in_array($datum['id'], $res_conf['delete']))
                $res[$index]['action'] = 'delete';
            if (!empty($res_conf['update']) && in_array($datum['id'], array_keys($res_conf['update']))) {
                $new_index = WithDate::dateToInt($res_conf['update'][$datum['id']]['valid_from']);
                if ($new_index !== $index)
                    unset($res[$index]);
                if (!empty($res[$new_index]) && $res[$new_index]['action'] !== 'none')
                    Debug::sendDebugInfo('editCharge', json_encode([$res[$new_index], $res_conf['update'][$datum['id']] + ['action' => 'update']]), Debug::TEST);
                $res[$new_index] = $res_conf['update'][$datum['id']] + ['action' => 'update'];
            }
        }
        if (!empty($res_conf['insert']))
            foreach ($res_conf['insert'] as $datum) {
                $index = WithDate::dateToInt($datum['valid_from']);
                if (!empty($res[$index])) {
                    if ($res[$index]['action'] === 'delete') {
                        if ($type_charge === self::RECURRING_TAB)
                            $res[$index]['quantity'] = $datum['quantity'];
                        $res[$index]['value'] = $datum['value'];
                        $res[$index]['valid_from'] = $datum['valid_from'];
                        $res[$index]['valid_to'] = $datum['valid_to'];
                        $res[$index]['action'] = 'update';
                    } else
                        Debug::sendDebugInfo('editCharge', json_encode([$res[$index], $datum + ['action' => 'insert']]), Debug::TEST);
                } else
                    $res[$index] = $datum + ['action' => 'insert'];
            }
        ksort($res);
        return $res;
    }

    public static function editCharge(array $user_data, int $user_id)
    {
        $sw_id = (int)$user_data['sw_id'];
        $type_charge = $user_data['type_charge'];
        $quantity = (int)($user_data['quantity'] ?? 0);
        if ($type_charge === self::RECURRING_TAB && $quantity <= 0)
            return ['error' => 'The quantity must be greater than 0'];
        $value = (float)($user_data['value'] ?? 0);
        if ($value < 0)
            return ['error' => 'The charge should not be less than 0'];
        if (empty($user_data['valid_from']))
            return ['error' => 'The date "Valid from" cannot be empty'];
        $user_data['valid_from'] = str_replace('T', ' ', $user_data['valid_from']);
        if (!empty($user_data['valid_to']))
            $user_data['valid_to'] = str_replace('T', ' ', $user_data['valid_to']);
        if ($user_data['valid_to'] && $user_data['valid_to'] <= $user_data['valid_from'])
            return ['error' => "Valid To '{$user_data['valid_to']}' is not valid"];
        $id = (int)$user_data['id'];
        $cb_id = (int)$user_data['cb_id'];
        switch ($type_charge) {
            case self::CHARGE_TAB:
                $data = ChargesBilling::getChargeById($cb_id, $id);
                break;
            case self::RECURRING_TAB:
                $data = ChargesRecurringBilling::getChargeById($cb_id, $id);
                break;
            default:
                return ['error' => 'Error data'];
        }
        [$change_data, $change_date_from, $change_date_to] = self::changeData($user_data, $data);
        if (!$change_data && !$change_date_from && !$change_date_to)
            return ['update' => false];
        if ($cb_id && !$change_date_from && !$change_date_to)
            return ['update' => self::changeValues($type_charge, $quantity, $value, $data, $id, $cb_id, $sw_id, $user_id)];
        [$is_save, $data, $res_conf] = self::analysisData($id, $type_charge, $user_data);
        if ($is_save) {
            $values = [
                ['field' => 'charge', 'new' => $value],
                ['field' => 'valid_from', 'new' => $user_data['valid_from']],
                ['field' => 'valid_to', 'new' => $user_data['valid_to']],
            ];
            if ($type_charge === self::CHARGE_TAB) {
                ChargesBilling::add($res_conf['insert'][0]);
                ChargesHistory::add($sw_id, $user_id, ChargeHistory::EVENT_ADD, $values);
            } elseif ($type_charge === self::RECURRING_TAB) {
                ChargesRecurringBilling::add($res_conf['insert'][0]);
                ChargesHistory::add($sw_id, $user_id, ChargeHistory::EVENT_ADD_RECURRING, array_merge([['field' => 'quantity', 'new' => $quantity]], $values));
            }
            return ['update' => true];
        }
        $res = self::reduceData($type_charge, $data, $res_conf);
        if (!$res)
            return ['update' => false];
        return ['confirm' => $res];
    }

    public static function updateRange(int $sw_id, string $type_charge, int $id, int $user_id, array $delete, array $insert, array $update)
    {
        switch ($type_charge) {
            case self::CHARGE_TAB:
                return ChargesBilling::updateRange($sw_id, $id, $user_id, $delete, $insert, $update);
            case self::RECURRING_TAB:
                return ChargesRecurringBilling::updateRange($sw_id, $id, $user_id, $delete, $insert, $update);
            default:
                return ['error' => 'Error data'];
        }
    }

    public static function updateGroup(array $data, int $id, int $warehouse_id)
    {
        $group = ChargeGroups::getById($id);
        if (!$group)
            return ['error' => 'Group is not found'];
        $is_changed = false;
        $fields = ['id'];
        $name = addslashes(trim($data['name']));
        if ($name !== $group->getName()) {
            $is_changed = true;
            $group->setName($name);
            $fields[] = 'name';
        }
        $code = addslashes(trim($data['code']));
        if (empty($code))
            return ['error' => 'Group code is empty'];
        if (strpos($code, ' ') !== false)
            return ['error' => 'Group code is wrong (spaces)'];
        $group_id = ChargeGroups::getIdByCode($code, $warehouse_id);
        if ($group_id && $group_id != $group->getId())
            return ['error' => 'Group code is not unique'];
        if ($code !== $group->getCode()) {
            $is_changed = true;
            $group->setCode($code);
            $fields[] = 'code';
        }
        if (!$is_changed || count($fields) === 1)
            return ['update' => false];
        $group->save($fields);
        return ['update' => true];
    }

    public static function createGroup(array $data, int $warehouse_id)
    {
        $code = addslashes(trim($data['code']));
        if (empty($code))
            return ['error' => 'Group code is empty'];
        if (strpos($code, ' ') !== false)
            return ['error' => 'Group code is wrong (spaces)'];
        if (ChargeGroups::getIdByCode($code, $warehouse_id))
            return ['error' => 'Group code is not unique'];
        $data = [
            'warehouse_id' => $warehouse_id,
            'name' => addslashes(trim($data['name'])),
            'code' => $code
        ];
        $group = new ChargeGroup($data);
        $group->save(array_keys($data));
        return ['update' => true];
    }
}
