<?php

namespace app\api\models\actions;

use app\api\API;
use app\backend\models\Orders;
use app\backend\models\SKUs;
use app\backend\models\ASNs;
use app\core\dbal\SafeMysql;
use app\core\entities\ASN;
use app\core\entities\Supplier;
use app\core\models\Suppliers;
use app\core\models\Warehouses;
use app\core\statical\Services;
use app\core\statical\WithDate;

class ActionASNPost extends BaseASNPost
{
    private $ASNs;
    private $ASNSKUs;
    private $user_id;
    private $warehouse_id;
    private $warehouse_virtual;

    public function __construct($data)
    {
        $this->ASNs = [];
        if (count($data) < 1)
            return;
        $this->user_id = API::getUser()->getId();
        $this->warehouse_id = API::getUser()->getWarehouseId();
        $this->warehouse_virtual = Warehouses::isVirtual($this->warehouse_id);
        $supplier_id = array_keys(Suppliers::getSuppliersByUser($this->warehouse_id, $this->user_id, Supplier::ACTIVE))[0];
        foreach ($data as $key => $ASN) {
            $services = !empty($ASN['Service']) ? array_map('strtolower', $ASN['Service']) : [];
            $tmp = [];
            foreach (Services::getAll() as $k => $service) {
                $pos = array_search(strtolower($service), $services);
                if ($pos !== false) {
                    $tmp[$k] = 1;
                    unset($services[$pos]);
                }
            }
            if (count($services))
                $tmp[0] = implode('; ', $services);
            ksort($tmp);
            $order = null;
            if (!empty($ASN['OrderRef']))
                $order = Orders::getDespatchedByIdOrReference($supplier_id, $ASN['OrderRef'], $this->warehouse_id);
            $this->ASNs[$key] = [
                'supplier_id' => $supplier_id,
                'unscheduled' => 0,
                'arrival_date' => empty($ASN['ArrivalDate']) ? null : WithDate::dateToInt($ASN['ArrivalDate']),
                'user_id' => $this->user_id,
                'status' => ASN::NEW,
                'ref' => empty($ASN['Reference']) ? '' : $ASN['Reference'],
                'carrier' => empty($ASN['Carrier']) ? '' : $ASN['Carrier'],
                'tracking_number' => empty($ASN['TrackingNumber']) ? '' : $ASN['TrackingNumber'],
                'container' => !empty($ASN['Container']) ? ($ASN['Container'] == 20 || $ASN['Container'] == 40) ? ($ASN['Container'] . 'ft_container') : '' : '',
                'order_id' => !empty($order) ? $order->getId() : 0,
                'comment' => empty($ASN['Comment']) ? '' : $ASN['Comment'],
                'services' => $tmp,
                'warehouse_id' => $this->warehouse_id,
                'ship_to_location' => empty($ASN['ShipToLocation']) ? '' : $ASN['ShipToLocation'],
            ];
            foreach ($this->ASNs[$key] as $k => $val)
                if (is_null($val) || empty($val))
                    unset($this->ASNs[$key][$k]);
            $this->ASNSKUs[$key] = !empty($ASN['SKUs']) ? $ASN['SKUs'] : [];
        }
    }

    public function validateFields() { }

    private function prepareSKUs($key, &$errors_ASN, $supplier_id)
    {
        $ASN_SKUs = [];
        foreach ($this->ASNSKUs[$key] as $SKU) {
            if (empty($SKU['Barcode'])) {
                $errors_ASN["Rec #$key"]['SKUs'][] = "Invalid barcode ";
                continue;
            }
            if (empty($SKU['Quantity']) || $SKU['Quantity'] < 1) {
                $errors_ASN["Rec #$key"]['SKUs'][] = "Empty quantity ({$SKU['Barcode']})";
                continue;
            }
            // 1. $SKU['Barcode'] can be a unique_customer_ref
            $SKU_by_unique_customer_ref = SKUs::getByUniqueCustomerRefAndSupplier($SKU['Barcode'], $supplier_id);
            if ($SKU_by_unique_customer_ref) {
                if(empty($ASN_SKUs[$SKU_by_unique_customer_ref->getId()])) {
                    $ASN_SKUs[$SKU_by_unique_customer_ref->getId()] = [
                        'quantity' => $SKU['Quantity'],
                        'batch_number' => $SKU['batch_number'] ?? null,
                        'batch_expired' => $SKU['batch_expired'] ?? null,
                    ];
                } else
                    $ASN_SKUs[$SKU_by_unique_customer_ref->getId()]['quantity'] += $SKU['Quantity'];
                continue;
            }
            // 2. $SKU['Barcode'] can be a barcode
            $SKU_by_barcode = SKUs::getByBarcodeAndSupplier($SKU['Barcode'], $supplier_id);
            if ($SKU_by_barcode) {
                if(empty($ASN_SKUs[$SKU_by_barcode->getId()])) {
                    $ASN_SKUs[$SKU_by_barcode->getId()] = [
                        'quantity' => $SKU['Quantity'],
                        'batch_number' => $SKU['batch_number'] ?? null,
                        'batch_expired' => $SKU['batch_expired'] ?? null,
                    ];
                } else
                    $ASN_SKUs[$SKU_by_barcode->getId()]['quantity'] += $SKU['Quantity'];
                continue;
            }
            // 3. $SKU['Barcode'] can be a SKU internal ID
            $SKU_by_id = SKUs::getByIdAndSupplier($SKU['Barcode'], $supplier_id);
            if ($SKU_by_id) {
                if(empty($ASN_SKUs[$SKU_by_id->getId()])) {
                    $ASN_SKUs[$SKU_by_id->getId()] = [
                        'quantity' => $SKU['Quantity'],
                        'batch_number' => $SKU['batch_number'] ?? null,
                        'batch_expired' => $SKU['batch_expired']  ?? null,
                    ];
                } else
                    $ASN_SKUs[$SKU_by_id->getId()]['quantity'] += $SKU['Quantity'];
                continue;
            }
            $errors_ASN["Rec #$key"]['SKUs'][] = "Invalid barcode {$SKU['Barcode']}";
        }
        if (!count($ASN_SKUs)) {
            $errors_ASN["Rec #$key"]['SKUs'] = 'Missing SKUs';
            return false;
        }
        if (!empty($errors_ASN))
            return false;
        return $ASN_SKUs;
    }

    public function process()
    {
        if (count($this->ASNs) < 1) {
            return ['critical_error' => [
                'error_id' => 1,
                'error_msg' => 'Missing ASNs']
            ];
        }
        $errors_ASN = [];
        $crt_ASN = [];
        foreach ($this->ASNs as $key => $ASN_data) {
            $ASN_SKUs = self::prepareSKUs($key, $errors_ASN, $ASN_data['supplier_id']);
            if ($ASN_SKUs === false)
                continue;
            $mysql = new SafeMysql();
            $mysql->beginTransaction();
            list($done, $ASN_id) = ASNs::addASN($ASN_data, $ASN_SKUs, [], $this->warehouse_virtual, $this->user_id);
            if (!$done) {
                $mysql->rollback();
                $errors_ASN["Rec #$key"]['ASN'] = [];
                if (is_array($ASN_id))
                    foreach ($ASN_id as $error)
                        $errors_ASN["Rec #$key"]['ASN'][] = $error['message'];
                else
                    $errors_ASN["Rec #$key"]['ASN'][] = $ASN_id;
                continue;
            } else
                $crt_ASN["Rec #$key"] = $ASN_id;
            $mysql->commit();
        }
        return ['created_ASNs' => $crt_ASN, 'error_ASNs' => $errors_ASN];
    }

    public function getResponseFieldsConfig() { }
}
