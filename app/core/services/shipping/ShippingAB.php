<?php

namespace app\core\services\shipping;

use app\backend\models\Orders;
use app\core\entities\Order;
use app\core\entities\Shipping;
use app\core\Environment;
use app\core\helpers\CURL;
use app\core\helpers\File;
use app\core\models\Carriers;
use app\core\models\OrderCategories;
use app\core\models\ShippingServices;
use app\core\models\config\OrdersConfig;
use app\core\models\config\SKUsConfig;
use app\core\helpers\FileWrapper;
use app\core\statical\Countries;

class ShippingAB extends \app\core\services\shipping\Shipping
{
    const MAX_WEIGHT            = 30;
    const MIN_WEIGHT            = 0.1;

    public static function createShipment($order_info, $shipper, $consignee, $package, $user_id): Shipping
    {
        list($data, $reference, $filename) = self::sendCreateShipmentRequest($order_info, $shipper, $consignee, $package);
        $carrier = Carriers::getById($order_info['carrier_id']);
        $shipment = new Shipping([
            'status' => Shipping::STATUS_ACCEPT,
            'reference' => $reference,
            'create_date' => time(),
            'user_id' => $user_id,
            'carrier_id' => $carrier->getId()
        ]);
        $shipment->save(['id', 'status', 'reference', 'create_date', 'user_id', 'carrier_id']);
        if (!empty($data['errorlevel'])) {
            $shipment->setStatus(Shipping::STATUS_ERROR);
            $shipment->setTrackingNumber($filename);
            $shipment->setUrl(json_encode($data['error']));
            $shipment->save(['id', 'tracking_number', 'url', 'status']);
            return $shipment;
        }
        $tracking_number = $data['shipper_tracking_number'] ?: $data['system_tracking_number'] ?: (date('Ymd') . '_' . uniqid());
        $label_name = "$tracking_number.zpl";
        $label = !empty($data['last_mile_carrier_label']) ? base64_decode($data['last_mile_carrier_label']) : (
            !empty($data[$data['label_alt']]) ? base64_decode($data[$data['label_alt']]) : '');
        (new FileWrapper(FileWrapper::getContainerByPath(SHIPPING_LABELS_STORAGE)))->writeFileString(SHIPPING_LABELS_STORAGE . $label_name, $label);
        $shipment->setTrackingNumber($tracking_number);
        $shipment->setUrl($carrier->getUrl() . $tracking_number);
        $shipment->setCarrierTrackingNumber($data['last_mile_carrier_tracking_number'] ?? '');
        $shipment->setStatus(Shipping::STATUS_SUCCESS);
        $shipment->save(['id', 'tracking_number', 'url', 'carrier_tracking_number', 'status']);
        return $shipment;
    }

    private static function fakeCreateShipmentRequest($order_info, $shipper, $consignee, $package)
    {
        $shipper_reference = !empty($package['order_reference']) ? $package['order_reference'] :  $order_info['ref'];
        $tracking_number = 'test_' . uniqid();
        $label = "^XA\n"
            . "^FO50,50^A0N,40,40^FDFake Shipping Label^FS\n"
            . "^FO50,110^A0N,30,30^FDTracking: {$tracking_number}^FS\n"
            . "^FO50,160^A0N,30,30^FDReference: {$shipper_reference}^FS\n"
            . "^XZ";

        $data_res = [
            'errors' => '',
            'errorlevel' => null,
            'label_alt' => 'system_label', 
            'shipper_tracking_number' => $tracking_number,
            'system_label' => base64_encode($label),
            
            'last_mile_carrier_label' => null,
            'last_mile_carrier_tracking_number' => null,
        ];
        
        return [$data_res, $shipper_reference, "$tracking_number.txt"];
    }

    private static function sendCreateShipmentRequest($order_info, $shipper, $consignee, $package)
    {
        $order_info = self::prepare($order_info);
        $shipper = self::prepare($shipper);
        $consignee = self::prepare($consignee);
        $package = self::prepare($package);
        $shipper_reference = !empty($package['order_reference']) ? $package['order_reference'] :  $order_info['ref'];
        $data = self::getCreateRequest($shipper_reference, $order_info, $shipper, $consignee, $package);
        $curl = new CURL(CONSERO_HOST . DIRECTORY_SEPARATOR_FTP . 'parcels', 'ab_add_parcel');
        $curl->setMethod('POST');
        $curl->setHeader("Content-Type", "application/json");
        $curl->setHeader("Accept", "application/json");                 // ???
        $curl->setBody(json_encode($data));
        $result = $curl->exec();
        $path = SHIPPING_LABELS_STORAGE . 'logs/';
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($path)))->handleDir($path);
        $file2save = time() . '_' . uniqid();
        if ($curl->getResultCode() !== 201 || empty($result)) {
            if (!$curl->getResultCode())
                return self::setError($path, $file2save, $data, $curl->getResultError(), -2, 'Connection is not established');
            $errors = json_decode($curl->getResultError(), true) ?? [];
            return self::setError($path, $file2save, $data, $curl->getResultError(), $curl->getResultCode(), $errors['errors'] ?? []);
        }
        $data_res = json_decode($result, true);
        $data_res['label_alt'] = 'system_label';
        if (!empty($data_res['errors'])) {
            $data_res['label_alt'] = 'error_label';
            return self::setError($path, "error_$file2save", $data, $result, -3, $data_res['errors']);
        }
        if (empty($data_res['shipper_tracking_number']))
            return self::setError($path, $file2save, $data, $result, -1, 'Response has a wrong structure');
        unset($data_res['errors']);
        $file2save = $data_res['shipper_tracking_number'];

        $file_wrapper->writeFileString($path . "$file2save.txt", json_encode($data));
        $file_wrapper->writeFileString($path . "$file2save.res", $result);
        return [$data_res, $shipper_reference, "$file2save.txt"];
    }

    public static function cancelShipment($shipping_api_key, $tracking_number, $reference): ?string
    {
        if (empty($tracking_number))
            $tracking_number = $reference;
        $is_test = empty($shipping_api_key) || Environment::isDev();
        $data = [
            'api_key' => $is_test ? CONSERO_API_KEY : $shipping_api_key,
            'parcel_unique_identifier' => $tracking_number,
            'is_test' => $is_test
        ];
        $curl = new CURL(CONSERO_HOST . DIRECTORY_SEPARATOR_FTP . 'parcels', 'ab_cancel_parcel');
        $curl->setMethod('DELETE');
        $curl->setHeader("Content-Type", "application/json");
        $curl->setHeader("Accept", "application/json");
        $curl->setBody(json_encode($data));
        $result = $curl->exec();
        $code = 0;
        $msg = '';
        if ($curl->getResultCode() !== 201 || empty($result)) {
            $code = $curl->getResultCode();
            $msg = json_decode($curl->getResultError(), true);
        } else {
            $data_res = json_decode($result, true);
            if (!$data_res['success']) {
                $code = -1;
                $msg = $data_res['error'];
            }
        }
        if (!empty($code)) {
            if (is_array($msg))
                $msg = json_encode($msg['message'] ?? []);
            return "<a class='btn btn-primary btn-link' target='_blank' href='" . CONSERO_CANCEL_HOST . "$reference&is_test=" . (int)$is_test . "'>$reference: $msg</a>";
        }
        return null;
    }

    public static function trackShipment($shipping_api_key, $tracking_number, $reference): array
    {
        return [];
    }

    public static function getMaxWeight(): ?float
    {
        return self::MAX_WEIGHT;
    }

    public static function getMinWeight()
    {
        return self::MIN_WEIGHT;
    }

    private static function setError($path, $filename, $data, $result, $code, $errors)
    {
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($path)));
        $file_wrapper->writeFileString("{$path}$filename.txt", json_encode($data));
        $file_wrapper->writeFileString("{$path}$filename.res", $result);
        return [[
            'errorlevel' => $code,
            'error' => $errors
        ], 0, "$filename.txt"];
    }

    private static function getPrice(string $type_create, string $currency, array $SKU, array $product_value_map): float
    {
        if (Order::isShopify($type_create)) {
            if (empty($currency)) {
                if (isset($product_value_map[$SKU['id']]))
                    $SKU_value = $product_value_map[$SKU['id']] ?: MIN_PRICE;
                else
                    $SKU_value = $SKU['value'];
            } else
                $SKU_value = $SKU['price'] ?: MIN_PRICE;
        } else {
            $SKU_value = empty($currency) ? $SKU['value'] : $SKU['price'];
            if (Order::isWooCommerce($type_create) || Order::isAPI($type_create))
                $SKU_value = $SKU_value ?: MIN_PRICE;
        }
        return round($SKU_value, 2);
    }

    private static function getItems(array $order_info, array $SKUs, bool $is_eu_destination): array
    {
        $product_value_map = [];
        if (empty($order_info['currency']))
            list($currency, $product_value_map) = Orders::getCurrencyPrice($order_info);
        else
            $currency = $order_info['currency'];
        $common_value = 0;
        $items = [];
        $SKU_ids = array_column($SKUs, 'id');
        // ABF-475/CON-382 EU PID: only pass NS-PID/S-PID when shipping to an EU destination, per the lead's clarification
        $PID_configs = $is_eu_destination ? SKUsConfig::readConfigsByIdsAndParams($SKU_ids, [SKUsConfig::TYPE_NS_PID, SKUsConfig::TYPE_S_PID]) : [];
        foreach ($SKUs as $SKU) {
            $SKU_value = self::getPrice($order_info['type_create'], $order_info['currency'], $SKU, $product_value_map);
            $common_value += $SKU_value * $SKU['quantity'];
            $ns_pid = $PID_configs[$SKU['id']][SKUsConfig::TYPE_NS_PID] ?? '';
            $s_pid = $PID_configs[$SKU['id']][SKUsConfig::TYPE_S_PID] ?? '';
            $items[] = [
                'quantity' => $SKU['quantity'],
                'description' => $SKU['name'],
                'total_value' => $SKU_value * $SKU['quantity'],
                'value' => $SKU_value,
                'weight' => $SKU['weight'],
                'length' => $SKU['depth'],
                'width' => $SKU['width'],
                'height' => $SKU['height'],
                'country_of_origin' => $SKU['country_id'] == 'UK' ? 'GB' : $SKU['country_id'],
                'sku' => $SKU['id'],
                'hs_code' => $SKU['hs_code'] ?? '',
                'eudr_dds_code' => $SKU['DDS'] ?? '',
                'eudr_y_code' => $SKU['exemption_TARIC'] ?? '',
                'ns_pid' => $ns_pid !== '' ? $ns_pid : null,
                's_pid' => $s_pid !== '' ? $s_pid : null,
            ];
        }
        return [$items, $common_value, $currency];
    }

    private static function getCreateRequest(string $shipper_reference, $order_info, $shipper, $consignee, $package): array
    {
        $service = ShippingServices::getById($order_info['shipping_services_id']);
        $printed_matter = OrdersConfig::getOrderPrintedMatter($order_info['id']);
        $eori_number = OrdersConfig::getOrderEoriNumber($order_info['id']);
        $vat_number = OrdersConfig::getOrderVatNumber($order_info['id']);
        $ioss_number = OrdersConfig::getOrderIossNumber($order_info['id']);
        $tax_id = OrdersConfig::getOrderTaxId($order_info['id']);
        $test_mode = (empty($shipper['shipping_api_key']) || Environment::isDev());
        // ABF-475/CON-382 EU PID: "передавать - это при создании лейбла" (pass NS-PID/S-PID only when creating the label, and only for EU)
        $is_eu_destination = isset(Countries::EU_COUNTRIES[$consignee['country_id'] ?? '']);
        [$items, $common_value, $currency] = self::getItems($order_info, $package['skus'], $is_eu_destination);
        $length = round($package['length'], 2);
        $width = round($package['width'], 2);
        $height = round($package['height'], 2);
        if (empty($package['weight']) || $package['weight'] < self::getMinWeight())
            $package['weight'] = self::getMinWeight();
        $weight = round($package['weight'], 2);
        
        // Get category from package data, order level category, or use the default
        $category = !empty($package['category']) ? $package['category'] : 
                    (!empty($order_info['category']) ? $order_info['category'] : OrderCategories::DEFAULT);
        
        return [
            'is_test' => $test_mode,
            'api_key' => $test_mode ? CONSERO_API_KEY : $shipper['shipping_api_key'],
            'currency' => $currency,
            'shipping' => [
                'shipping_solution_code' => $service->getCode(),
                'label_format' => 'zpl200',                 // pdf, zpl200, zpl300
                'label_type' => 'last_mile',                // system, last_mile
                'shipping_cost' => (string)round((float)($order_info['cost_shipping'] ?? 0), 2),
                'collection_date' => [],
                'delivery_date' => []
            ],
            'package' => [
                'shipper_reference' => $shipper_reference,
                'order_reference' => $order_info['ref2'],
                'incoterm' => $order_info['DDP'] ?? '',
                'description' => $package['skus'][0]['name'],
                'value' => round($common_value, 2),
                'category' => $category,
                'tags' => $printed_matter ? ['printed_matter'] : [],
                'weight' => [
                    'value' => $weight,
                    'unit' => 'kg',
                ],
                'dimensions' => [
                    'length' => $length,
                    'width' => $width,
                    'height' => $height,
                    'unit' => 'cm'
                ]
            ],
            'origin_address' => [
//                'name' => $shipper['contact'] ?? '',
                'company_name' => $shipper['name'] ?? '',
                'address_line_1' => $shipper['address1'] ?? '',
                'address_line_2' => $shipper['address2'] ?? '',
                'address_line_3' => $shipper['address3'] ?? '',
                'city' => $shipper['town'] ?? '',
                'state' => $shipper['county'] ?? '',
                'zip' => $shipper['postcode'] ?? '',
                'country' => !empty($shipper['country_id']) ? ($shipper['country_id'] == 'UK' ? 'GB' : $shipper['country_id']) : '',
                'phone' => $shipper['contact'] ?? '',
                'email' => $shipper['email'] ?? '',
                'vat' => $shipper['VAT'] ?? '',
                'eori' => $shipper['EORI'] ?? '',
                'ioss' => $shipper['reg_number'] ?? '',
//                'gst' => "123456789RT1234"
            ],
            'destination_address' => [
                'name' => $consignee['contact'] ?? '',
                'company_name' => $consignee['name'] ?? '',
                'address_line_1' => $consignee['address1'] ?? '',
                'address_line_2' => $consignee['address2'] ?? '',
                'address_line_3' => $consignee['address3'] ?? '',
                'city' => $consignee['town'] ?? '',
                'state' => $consignee['county'] ?? '',
                'zip' => $consignee['postcode'] ?? '',
                'country' => !empty($consignee['country_id']) ? ($consignee['country_id'] == 'UK' ? 'GB' : $consignee['country_id']) : '',
                'phone' => $consignee['phone'] ?? '',
                'email' => $consignee['email'] ?? '',
                'vat' => !empty($vat_number) ? $vat_number : '',
                'eori' => !empty($eori_number) ? $eori_number : '',
                'ioss' => !empty($ioss_number) ? $ioss_number : '',
                'tax_identity' => !empty($tax_id) ? $tax_id : '',
//                'gst' => "123456789RT1234"
            ],
            'return_address' => [],
            'parcel_items' => $items,
        ];
    }
}
