<?php

namespace app\core\services\upload;

use app\backend\models\Deliveries as DeliveriesModel;
use app\core\entities\Order;
use app\core\entities\Warehouse;
use app\core\entities\User;
use app\backend\models\Suppliers;
use app\core\models\config\OrdersConfig;
use app\core\models\WarehouseCarriers;
use app\backend\models\SKUs;
use app\core\models\Shippings;
use app\core\entities\Shipping;
use app\core\interfaces\SFTPUpload;
use app\core\entities\Supplier;
use app\core\models\Users;
use app\core\services\DeliveriesUploadValidationService;
use app\core\entities\Delivery;
use app\core\statical\WithDate;
use app\core\statical\Sources;
use app\core\statical\Currencies;
use app\backend\models\Orders;
use app\backend\models\Bundles;
use app\backend\models\Upload as UploadModel;
use app\core\models\OrdersOriginalRequest;
use app\core\entities\File;
use app\core\entities\Queue;
use app\core\models\OrderCategories;

class OrdersUploadService implements SFTPUpload
{
    private const ORDER_KEY_CARRIER_SERVICE = 0;
    private const ORDER_KEY_REFERENCE = 1;
    private const ORDER_KEY_DESPATCH_DATE = 2;
    private const ORDER_KEY_TERMS = 3;
    private const ORDER_KEY_COUNTRY_CODE = 4;
    private const ORDER_KEY_POSTCODE = 5;
    private const ORDER_KEY_COMPANY_NAME = 6;
    private const ORDER_KEY_CONTACT_NAME = 7;
    private const ORDER_KEY_CONTACT_TEL = 8;
    private const ORDER_KEY_CONTACT_EMAIL = 9;
    private const ORDER_KEY_ADDRESS1 = 10;
    private const ORDER_KEY_ADDRESS2 = 11;
    private const ORDER_KEY_ADDRESS3 = 12;
    private const ORDER_KEY_TOWN = 13;
    private const ORDER_KEY_COUNTY = 14;
    private const ORDER_KEY_BARCODE = 15;
    private const ORDER_KEY_QUANTITY = 16;
    private const ORDER_KEY_PRICE = 17;
    private const ORDER_KEY_REFERENCE2 = 18;
    private const ORDER_KEY_SPECIAL_INSTRUCTION = 19;
    private const ORDER_KEY_SPECIAL_PICKING_INSTRUCTION = 20;
    private const ORDER_KEY_SPECIAL_PACKING_INSTRUCTION = 21;
    private const ORDER_CONFIG_KEY_PRINTED_MATTER = 22;
    private const ORDER_KEY_CATEGORY = 23;
    private const ORDER_CONFIG_KEY_EORI_NUMBER = 24;
    private const ORDER_CONFIG_KEY_VAT_NUMBER = 25;
    private const ORDER_KEY_PURCHASE_ORDER_NUMBER = 26;
    private const ORDER_CONFIG_KEY_TAX_ID = 27;

    private $fieldsOrder = [
        self::ORDER_KEY_CARRIER_SERVICE => 'CarrierService',
        self::ORDER_KEY_REFERENCE => 'Reference',
        self::ORDER_KEY_REFERENCE2 => 'Reference2',
        self::ORDER_KEY_PURCHASE_ORDER_NUMBER => 'PurchaseOrderNumber',
        self::ORDER_KEY_SPECIAL_INSTRUCTION => 'SpecialInstruction',
        self::ORDER_KEY_SPECIAL_PICKING_INSTRUCTION => 'SpecialPickingInstruction',
        self::ORDER_KEY_SPECIAL_PACKING_INSTRUCTION => 'SpecialPackingInstruction',
        self::ORDER_KEY_DESPATCH_DATE => 'DespatchDate',
        self::ORDER_CONFIG_KEY_PRINTED_MATTER => 'PrintedMatter',
        self::ORDER_CONFIG_KEY_EORI_NUMBER => 'EoriNumber',
        self::ORDER_CONFIG_KEY_VAT_NUMBER => 'VatNumber',
        self::ORDER_CONFIG_KEY_TAX_ID => 'TaxId',
        self::ORDER_KEY_CATEGORY => 'OrderCategory',
        self::ORDER_KEY_TERMS => 'Terms',
        self::ORDER_KEY_COUNTRY_CODE => 'CountryCode',
        self::ORDER_KEY_COMPANY_NAME => 'CompanyName',
        self::ORDER_KEY_CONTACT_NAME => 'ContactName',
        self::ORDER_KEY_CONTACT_TEL => 'ContactTel',
        self::ORDER_KEY_CONTACT_EMAIL => 'ContactEmail',
        self::ORDER_KEY_ADDRESS1 => 'Address1',
        self::ORDER_KEY_ADDRESS2 => 'Address2',
        self::ORDER_KEY_ADDRESS3 => 'Address3',
        self::ORDER_KEY_TOWN => 'Town',
        self::ORDER_KEY_COUNTY => 'County',
        self::ORDER_KEY_POSTCODE => 'Postcode',
        self::ORDER_KEY_BARCODE => 'Barcode',
        self::ORDER_KEY_QUANTITY => 'Quantity',
        self::ORDER_KEY_PRICE => 'Price',
    ];

    private $requiredOrderFieldKeys = [
        self::ORDER_KEY_CARRIER_SERVICE,
        self::ORDER_KEY_REFERENCE,
        self::ORDER_KEY_COUNTRY_CODE,
        self::ORDER_KEY_POSTCODE,
        self::ORDER_KEY_CONTACT_NAME,
        self::ORDER_KEY_ADDRESS1,
        self::ORDER_KEY_TOWN,
        self::ORDER_KEY_BARCODE,
        self::ORDER_KEY_QUANTITY,
        self::ORDER_KEY_DESPATCH_DATE
    ];

    private $validation_error_fields = [];
    private $messages = [];
    private $success = [];
    private $invalid_references = [];
    private $nums = [];
    private $supplier_id = 0;
    private $actual_user_login = null;
    private $form = [];
    private $data = [];

    public function execute($head, $data, $form, Warehouse $warehouse, User $user): array
    {
        if (count($data) > UploadModel::MAX_LINES_ORDERS) {
            return [[true,
                [
                    'danger' => ['The file you are uploading is too large, it exceeds the 500-row limit, try splitting it into several parts and uploading again'],
                    'success' => []
                ]
            ], null];
        }
        $this->form = $form;
        $this->data = $data;

        $this->validateSupplier($form, $warehouse, $user)
            ->validateHeader($data, $head);

        $this->createOrders($this->groupProducts($data), $warehouse, $user);

        ksort($this->messages, SORT_NATURAL);

        return [[!empty($this->messages), ['danger' => $this->messages, 'success' => $this->success]], null];
    }

    private function validateHeader($data, $head): self
    {
        foreach ($this->fieldsOrder as $key => $field)
            $this->nums[$key] = array_search($field, $head);
        foreach ($data as $k => $item) {
            $line_name = 'Line #'. ($k+1);
            $reference = $item[self::ORDER_KEY_REFERENCE];

            if (count($item) !== count($head)) {
                $this->messages[$line_name] = 'Number of fields does not match header for reference#' . $reference;
                $this->invalid_references[$reference] = $reference;
                continue;
            }

            if (!$this->fileOrder($item, $this->nums, $line_name)) {
                $referenceMessage = $reference ? ' for reference#' . $reference : '';

                $this->invalid_references[$reference] = $reference;
                $this->messages[$line_name] = 'Missing required fields: ' . implode(', ', $this->validation_error_fields[$line_name]). $referenceMessage;
            }
            if (mb_strlen($this->getField(self::ORDER_KEY_PURCHASE_ORDER_NUMBER, $item)) > 20) {
                $this->invalid_references[$reference] = $reference;
                $this->messages[$line_name] = 'Purchase Order Number must not exceed 20 characters for reference#' . $reference;
            }
        }

        return $this;
    }

    private function validateSupplier($form, $warehouse, &$user): self
    {
        if (empty($form['supplier'])) {
            $suppliers = Suppliers::getSuppliersByUser($warehouse->getId(), $user->getId(), Supplier::ACTIVE);
            $this->supplier_id = array_keys($suppliers)[0];
        } else {
            $this->supplier_id = $form['supplier'];
            if (empty($form['user']))
                $this->messages[] = 'User is not selected';
            $this->actual_user_login = $user->getLogin();
            $user = Users::getById($form['user']);
            if (empty($user))
                $this->messages[] = 'User not found';
        }

        return $this;
    }

    private function groupProducts($data): array
    {
        $orders = [];
        $res = [];
        $lineArr = [];
        foreach ($data as $k => $row) {
            $line_name = 'Line #' . ($k+1);
            if (!$row[0] || isset($this->messages[$line_name]))
                continue;

            $barcodeValue = $this->getField(self::ORDER_KEY_BARCODE, $row);
            $has_price = $this->getField(self::ORDER_KEY_PRICE, $row) !== false;
            $calc_price = $has_price ? $this->getField(self::ORDER_KEY_PRICE, $row) : null;
            $order_key_reference = $row[self::ORDER_KEY_REFERENCE];
            $orders[$order_key_reference]['order'] = $row;

            $lineArr[$order_key_reference][] = ($k+1);
            $orders[$order_key_reference]['order']['line'] = 'Line #' . implode(',', $lineArr[$order_key_reference]);

            $sku = SKUs::getByBarcodeAndSupplier($barcodeValue, $this->supplier_id);
            if ($sku) {
                $quantity = $this->getField(self::ORDER_KEY_QUANTITY, $row);
                $price = $this->getField(self::ORDER_KEY_PRICE, $row);

                if (!(int) $quantity) {
                    $this->messages[$line_name] = "Invalid quantity barcode#{$barcodeValue} for reference#{$order_key_reference}";
                    $this->invalid_references[$order_key_reference] = $order_key_reference;
                    continue;
                }

                if (!(float) $price) {
                    $this->messages[$line_name] = "Invalid price barcode#{$barcodeValue} for reference#{$order_key_reference}";
                    $this->invalid_references[$order_key_reference] = $order_key_reference;
                    continue;
                }

                $orders[$order_key_reference]['products'][$sku->getId()]['qty'] = $quantity;

                if (isset($orders[$row[self::ORDER_KEY_REFERENCE]]['products'][$sku->getId()]['bundle_qty'])) {
                   $bundle_qty = reset($orders[$row[self::ORDER_KEY_REFERENCE]]['products'][$sku->getId()]['bundle_qty']);
                   $orders[$order_key_reference]['products'][$sku->getId()]['qty'] += $bundle_qty;
                }

                $orders[$order_key_reference]['products'][$sku->getId()]['price'] = $price;
                continue;
            }

            $bundle = Bundles::getBundleInfo($barcodeValue, $this->supplier_id);
            if ($bundle && isset($bundle[0]['id'])) {
                $bundleId = $bundle[0]['id'];

                $bundle_price = array_sum(array_map(function ($el) {
                    return $el['quantity'] * $el['market_value'];
                }, $bundle));

                $calk_bundle_price = !is_null($calc_price) && trim($calc_price) !== '' ? round((float)$calc_price, 2) : $bundle_price;
                $koeff = $has_price ? ($calk_bundle_price / $bundle_price) : 1;
                $order_key_quantity = $this->getField(self::ORDER_KEY_QUANTITY, $row);
                $order_key_price = $this->getField(self::ORDER_KEY_PRICE, $row);

                if (!(int) $order_key_quantity) {
                    $this->messages[$line_name] = "Invalid quantity bundle#{$barcodeValue}";
                    $this->invalid_references[$order_key_reference] = $order_key_reference;
                    continue;
                }

                if (!(float) $order_key_price) {
                    $this->messages[$line_name] = "Invalid price bundle#{$barcodeValue}";
                    $this->invalid_references[$order_key_reference] = $order_key_reference;
                    continue;
                }

                $orders[$order_key_reference]['bundles'][$bundleId] = [
                    'bundle_id' => $bundleId,
                    'qty' => $order_key_quantity,
                    'price' => $order_key_price,
                ];

                foreach ($bundle as $bundleItem) {
                    Bundles::accumulationBundleSKU(
                        (int)$bundleItem['SKU_id'],
                        $bundleId,
                        $res,
                        $bundleItem['quantity'] * $order_key_quantity,
                        $bundleItem['market_value'] * $koeff
                    );

                    $orders[$row[self::ORDER_KEY_REFERENCE]]['products'][$bundleItem['SKU_id']]['bundle_qty'] =
                         $res[$bundleItem['SKU_id']]['bundle_qty'] ?? 0;
                    $orders[$row[self::ORDER_KEY_REFERENCE]]['products'][$bundleItem['SKU_id']]['bundle_price'] =
                        $res[$bundleItem['SKU_id']]['bundle_price'] ?? 0;
                    $orders[$row[self::ORDER_KEY_REFERENCE]]['products'][$bundleItem['SKU_id']]['qty'] =
                        ($orders[$row[self::ORDER_KEY_REFERENCE]]['products'][$bundleItem['SKU_id']]['qty'] ?? 0)  + $bundleItem['quantity'];
                }
                continue;
            }

            $this->messages[$line_name] = "Invalid barcode#{$barcodeValue} for reference#{$order_key_reference}";
            $this->invalid_references[$order_key_reference] = $order_key_reference;
        }

        foreach ($orders as $keyReference => $itemOrder) {
            if (isset($itemOrder['bundles'])) {
                $products = $itemOrder['products'];
                Bundles::walkSKUsWithBundles($products);
                $orders[$keyReference]['products'] = $products;
            }
        }


        return $orders;
    }

    private function createOrders($ordersList, Warehouse $warehouse, User $user)
    {
        $currency = Currencies::getCurrency($warehouse->getCurrencyId());
        $orders = array_diff_key($ordersList, $this->invalid_references);

        foreach ($orders as $item) {

           $deliveryId = $this->getDelivery($item['order'], $user);
           $carrierAndShippingService = $this->getCarrierAndShippingService($item['order'], $warehouse);

           if (!$deliveryId || !$carrierAndShippingService)
               continue;

           $this->createOneOrder($item, $deliveryId, $carrierAndShippingService, $currency, $warehouse, $user);
        }
    }

    private function fileOrder($data, $nums, $lineName): bool
    {
        foreach ($this->requiredOrderFieldKeys as $field) {
            if ($nums[$field] === false || (!isset($data[$nums[$field]]) || $data[$nums[$field]] === '')) {
                $this->validation_error_fields[$lineName][] = $this->fieldsOrder[$field];
            }
        }

        return !isset($this->validation_error_fields[$lineName]);
    }

    private function getDelivery($item, User $user)
    {
        $data_delivery = [
            'name' => $this->getField(self::ORDER_KEY_COMPANY_NAME, $item),
            'contact' => $this->getField(self::ORDER_KEY_CONTACT_NAME, $item),
            'phone' => $this->getField(self::ORDER_KEY_CONTACT_TEL, $item),
            'email' => $this->getField(self::ORDER_KEY_CONTACT_EMAIL, $item),
            'address1' => $this->getField(self::ORDER_KEY_ADDRESS1, $item),
            'address2' => $this->getField(self::ORDER_KEY_ADDRESS2, $item),
            'address3' => $this->getField(self::ORDER_KEY_ADDRESS3, $item),
            'town' => $this->getField(self::ORDER_KEY_TOWN, $item),
            'county' => $this->getField(self::ORDER_KEY_COUNTY, $item),
            'postcode' => $this->getField(self::ORDER_KEY_POSTCODE, $item),
            'country_id' => $this->getField(self::ORDER_KEY_COUNTRY_CODE, $item),
        ];
        $delivery = new Delivery($data_delivery);
        $errors = DeliveriesUploadValidationService::validate($delivery);

        if (!empty($errors)) {
            foreach ($errors as $error)
                $this->messages[$item['line']] = $error['message'].' for reference#' . $this->getField(self::ORDER_KEY_REFERENCE, $item);
            return false;
        }

        return DeliveriesModel::handleDeliveryReturningId($delivery, $user->getId());
    }

    private function getCarrierAndShippingService($item, Warehouse $warehouse)
    {
        $shipping_service_val = $this->getField(self::ORDER_KEY_CARRIER_SERVICE, $item);
        $shipping_service = Shippings::getServiceByCode($shipping_service_val, $warehouse->getId());

        if (empty($shipping_service)) {
            if (!$warehouse->getCollectionByCustomer()) {
                $this->messages[$item['line']] = "'shipping_service' shouldn't be empty for reference#" . $this->getField(self::ORDER_KEY_REFERENCE, $item);
                return false;
            }

            if ($shipping_service_val !== Shipping::COLLECTION_BY_CUSTOMER) {
                $this->messages[$item['line']] = "Incorrect Service for reference#" . $this->getField(self::ORDER_KEY_REFERENCE, $item);
                return false;
            }
            $carrier_id = WarehouseCarriers::getAvailableCarrierList($warehouse->getId());
            if (empty($carrier_id)) {
                $this->messages[$item['line']] = "Carrier is not found for reference#" . $this->getField(self::ORDER_KEY_REFERENCE, $item);
                return false;
            }

            return [
                'carrier_id' => $carrier_id,
                'shipping_services_id' => 0
            ];
        }

        return [
            'carrier_id' => $shipping_service['carrier_id'],
            'shipping_services_id' => $shipping_service['id']
        ];
    }

    private function createOneOrder($item, $deliveryId, $carrierAndShippingService, $currency, Warehouse $warehouse, User $user)
    {
        if (!isset($item['products']) || !isset($item['order'])) {
            return false;
        }

        $despatch_date = $this->getField(self::ORDER_KEY_DESPATCH_DATE, $item['order']);
        $category = $this->getField(self::ORDER_KEY_CATEGORY, $item['order']);
        $data_order = [
            'delivery_id' => $deliveryId,
            'carrier_id' => $carrierAndShippingService['carrier_id'],
            'shipping_services_id' => $carrierAndShippingService['shipping_services_id'],
            'despatch_date' => empty($despatch_date) ? time() : WithDate::dateToInt($despatch_date),
            'ref' => $this->getField(self::ORDER_KEY_REFERENCE, $item['order']),
            'ref2' => $this->getField(self::ORDER_KEY_REFERENCE2, $item['order']),
            'purchase_order_number' => $this->getField(self::ORDER_KEY_PURCHASE_ORDER_NUMBER, $item['order']),
            'notes' => $this->getField(self::ORDER_KEY_SPECIAL_INSTRUCTION, $item['order']),
            'picking' => $this->getField(self::ORDER_KEY_SPECIAL_PICKING_INSTRUCTION, $item['order']),
            'packing' => $this->getField(self::ORDER_KEY_SPECIAL_PACKING_INSTRUCTION, $item['order']),
            'create_date' => time(),
            'user_id' => $user->getId(),
            'shop_id' => 0,
            'supplier_id' => $this->supplier_id,
            'status' => Order::NEW_NUM,
            'type_create' => Sources::SOURCE_UPLOAD,
            'warehouse_id' => $warehouse->getId(),
            'currency' => $currency,
            'DDP' => Shippings::getTermsKey($this->getField(self::ORDER_KEY_TERMS, $item['order'])),
            'category' => !empty($category) ? OrderCategories::getCategory($category) : OrderCategories::DEFAULT,
            'config' => [],
        ];
        if (!empty($this->actual_user_login))
            $data_order['actual_user_login'] = $this->actual_user_login;
        $data_order['config'][OrdersConfig::TYPE_ORDER_PRINTED_MATTER] = $this->getField(self::ORDER_CONFIG_KEY_PRINTED_MATTER, $item['order']) === 'Yes';
        
        $eori_number = $this->getField(self::ORDER_CONFIG_KEY_EORI_NUMBER, $item['order']);
        if (!empty(trim($eori_number)))
            $data_order['config'][OrdersConfig::TYPE_ORDER_EORI_NUMBER] = trim($eori_number);
            
        $vat_number = $this->getField(self::ORDER_CONFIG_KEY_VAT_NUMBER, $item['order']);
        if (!empty(trim($vat_number)))
            $data_order['config'][OrdersConfig::TYPE_ORDER_VAT_NUMBER] = trim($vat_number);

        $tax_id = $this->getField(self::ORDER_CONFIG_KEY_TAX_ID, $item['order']);
        if (!empty(trim($tax_id)))
            $data_order['config'][OrdersConfig::TYPE_ORDER_TAX_ID] = trim($tax_id);

//        $file_sftp = Orders::getFileDataOrderDocFromSFTP($data_order['ref'], $this->supplier_id);
        $data_order[Queue::TYPE_PDF_FROM_SFTP_TO_ORDER] = true;
        $SKUs_res = Orders::createOrder($data_order, [], $item['products']);

        //if has some errors
        if (!$SKUs_res[0]) {
            foreach ($SKUs_res[1]['order'] as $error) {
                $this->messages[$item['order']['line']] = $error. ' for reference#' . $this->getField(self::ORDER_KEY_REFERENCE, $item['order']);
            }

            return false;
        }

        $this->success[$this->getKeyErrorForOrder($item['order'])] = 'Created';

        $order = $SKUs_res[2] ?? null;
        if ($order instanceof Order) {
            if (isset($item['bundles'])) {
                foreach ($item['bundles'] as $bundle)
                    Bundles::addToOrder($order->getId(), $bundle['bundle_id'], $bundle['qty'], $order->getUserId());
            }
            OrdersOriginalRequest::saveOriginalRequest(
                $order->getId(),
                json_encode(['file' => $this->form['file_name'], 'data' => $this->data]),
                ''
            );
            Orders::creationOrder($order, $SKUs_res[3], $user->getId());
        }
    }

    private function getKeyErrorForOrder($item): string
    {
        return "Order Ref: {$this->getField(self::ORDER_KEY_REFERENCE, $item)}";
    }

    private function getField($key, $row): string
    {
        return $this->nums[$key] === false ? '' : $row[$this->nums[$key]];
    }

}