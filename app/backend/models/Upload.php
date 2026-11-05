<?php

namespace app\backend\models;

use app\backend\models\Deliveries as DeliveriesModel;
use app\core\entities\Delivery;
use app\core\entities\FileJob;
use app\core\entities\Movement;
use app\core\entities\Order;
use app\core\entities\Shipping;
use app\core\entities\SKU;
use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\entities\UserJob;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\Encoding;
use app\core\helpers\File;
use app\core\helpers\Filter;
use app\core\models\FileJobs;
use app\core\models\OrdersOriginalRequest;
use app\core\models\Shippings;
use app\core\models\WarehouseCarriers;
use app\core\models\Warehouses;
use app\core\services\DeliveriesUploadValidationService;
use app\core\services\upload\BulkMovementsUploadService;
use app\core\services\upload\OrdersUploadService;
use app\core\services\upload\SKUsUploadService;
use app\core\statical\Currencies;
use app\core\statical\Sources;
use app\core\models\UserJobNotifications;
use app\core\Queue;
use app\core\statical\WithDate;

class Upload
{
    const MAX_LINES_ORDERS = 500;

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
    private const ORDER_KEY_BARCODE_1 = 15;
    private const ORDER_KEY_QUANTITY_1 = 16;
    private const ORDER_KEY_PRICE_1 = 17;
    private const ORDER_KEY_REFERENCE2 = 18;
    private const ORDER_KEY_SPECIAL_INSTRUCTION = 19;

    public static $fieldsOrder = [
        self::ORDER_KEY_CARRIER_SERVICE => 'CarrierService',
        self::ORDER_KEY_REFERENCE => 'Reference',
        self::ORDER_KEY_REFERENCE2 => 'Reference2',
        self::ORDER_KEY_SPECIAL_INSTRUCTION => 'SpecialInstruction',
        self::ORDER_KEY_DESPATCH_DATE => 'DespatchDate',
        self::ORDER_KEY_TERMS => 'Terms',
        self::ORDER_KEY_COUNTRY_CODE => 'CountryCode',
        self::ORDER_KEY_POSTCODE => 'Postcode',
        self::ORDER_KEY_COMPANY_NAME => 'CompanyName',
        self::ORDER_KEY_CONTACT_NAME => 'ContactName',
        self::ORDER_KEY_CONTACT_TEL => 'ContactTel',
        self::ORDER_KEY_CONTACT_EMAIL => 'ContactEmail',
        self::ORDER_KEY_ADDRESS1 => 'Address1',
        self::ORDER_KEY_ADDRESS2 => 'Address2',
        self::ORDER_KEY_ADDRESS3 => 'Address3',
        self::ORDER_KEY_TOWN => 'Town',
        self::ORDER_KEY_COUNTY => 'County',
        self::ORDER_KEY_BARCODE_1 => 'Barcode_1',
        self::ORDER_KEY_QUANTITY_1 => 'Quantity_1',
        self::ORDER_KEY_PRICE_1 => 'Price_1',
    ];

    public static $requiredOrderFieldKeys = [
        self::ORDER_KEY_CARRIER_SERVICE,
        self::ORDER_KEY_REFERENCE,
        self::ORDER_KEY_COUNTRY_CODE,
        self::ORDER_KEY_POSTCODE,
        self::ORDER_KEY_CONTACT_NAME,
        self::ORDER_KEY_ADDRESS1,
        self::ORDER_KEY_TOWN,
        self::ORDER_KEY_BARCODE_1,
        self::ORDER_KEY_QUANTITY_1,
    ];

    private static $validation_error_fields = [];

    public static function handleSKUInRow($head, $data, $has_price, $key, $SKUs, $supplier_id): array
    {
        $i = 1;
        $res = [];
        $messages = [];
        $bundles_to_insert = [];
        $num_barcode = array_search('Barcode_' . $i, $head);
        $next_bar = $has_price ? 3 : 2;
        while (!empty($data[$num_barcode])) {
            $barcode = trim($data[$num_barcode]);
            if (empty($add_count = $data[$num_barcode + 1])) {
                $messages[] = "Invalid quantity #$i ($barcode)";
                continue;
            }
            $add_count = (int)$add_count;
            $calc_price = $has_price ? ($data[$num_barcode + 2] ?? null) : null;
            if (!empty($bundle = Bundles::getBundleInfo($barcode, $supplier_id))) {
                $bundle_id = array_values($bundle)[0]['id'];
                $bundle_price = array_sum(array_map(function ($el) {
                    return $el['quantity'] * $el['market_value'];
                }, $bundle));

                $calk_bundle_price = !is_null($calc_price) && trim($calc_price) !== '' ? round((float)$calc_price, 2) : $bundle_price;
                $koeff = $has_price ? ($calk_bundle_price / $bundle_price) : 1;
                foreach ($bundle as $bundle_SKU)
                    Bundles::accumulationBundleSKU((int)$bundle_SKU['SKU_id'], $bundle_id, $res, $bundle_SKU['quantity'] * $add_count, $bundle_SKU['market_value'] * $koeff);
                if (empty($bundles_to_insert[$bundle_id])) {
                    $bundles_to_insert[$bundle_id] = [
                        'bundle_id' => $bundle_id,
                        'quantity' => $add_count,
                        'price' => $has_price ? $calk_bundle_price : $bundle_price
                    ];
                } else
                    $bundles_to_insert[$bundle_id]['quantity'] += $add_count;
            } else {
                $found_key = null;
                foreach ($SKUs as $SKU_key => $SKU) {
                    if (in_array($barcode, $SKU['barcodes'])) {
                        $found_key = $SKU_key;
                        break;
                    }
                }
                if ($found_key !== null) {
                    $SKU_id = $SKUs[$found_key]['id'];
                    if (empty($res[$SKU_id]['qty']))
                        $res[$SKU_id]['qty'] = $add_count;
                    else
                        $res[$SKU_id]['qty'] += $add_count;
                    $res[$SKU_id]['price'] = self::handleUploadPrice($SKU_id, $calc_price, $messages, $key, $i, $barcode);
                } else
                    $messages[] = "Invalid barcode #$i ($barcode)";
            }
            $num_barcode += $next_bar;
            $i++;
        }
        if (!empty($bundles_to_insert))
            Bundles::walkSKUsWithBundles($res);
        return [$res, $messages, $bundles_to_insert];
    }

    private static function handleUploadPrice($SKU_id, $price, &$messages, $key, $i, $barcode)
    {
        $price_res = self::getUploadPrice($SKU_id, $price);
        if ($price_res[0])
            return $price_res[1];
        $messages[] = "{$price_res[1]} for #$i ($barcode)";
        return null;
    }

    private static function getUploadPrice($SKU_id, $price): array
    {
        if (is_null($price) || trim($price) === '') {
            $price = SKUs::getPriceById($SKU_id);
            if (is_null($price))
                return [false, 'price not found'];
            return [true, $price];
        }
        $price = (float)$price;
        if ($price < 0)
            return [false, 'price has negative value'];
        return [true, $price];
    }

    public static function fileOrder($data, $nums)
    {
        foreach (self::$requiredOrderFieldKeys as $field)
            if ($nums[$field] === false || empty($data[$nums[$field]]))
                self::$validation_error_fields[] = self::$fieldsOrder[$field];
        return !count(self::$validation_error_fields);
    }

    public static function handleOrderSKUsHeader($head, $num_barcode, $has_price): array
    {
        $i = 1;
        $next_bar = $has_price ? 3 : 2;
        $num_qty = $num_barcode + 1;
        $num_price = $has_price ? $num_barcode + 2 : null;
        while (!empty($head[$num_barcode])) {
            if ($head[$num_barcode] !== 'Barcode_' . $i) {
                $field = $head[$num_barcode];
                if ($field === ('Price_' . ($i - 1)))
                    return [false, "Invalid structure: must be all file without price, $field present"];
                return [false, "Invalid structure: must be Barcode_$i, $field given"];
            }
            if (empty($head[$num_qty]) || $head[$num_qty] !== 'Quantity_' . $i) {
                $field = $head[$num_qty];
                return [false, "Invalid structure: must be Quantity_$i" . (empty($field) ? '' : ", $field given")];
            }
            if ($has_price && (empty($head[$num_price]) || $head[$num_price] !== 'Price_' . $i)) {
                $field = $head[$num_price];
                return [false, "Invalid structure: must be Price_$i" . (empty($field) ? '' : ", $field given")];
            }
            $num_barcode += $next_bar;
            $num_qty += $next_bar;
            if ($has_price)
                $num_price += $next_bar;
            $i++;
        }
        return [true];
    }

    public static function fileOrders($head, $data, $form, Warehouse $warehouse, User $user)
    {
        return self::prepareErrorsForUX(
            (new OrdersUploadService())->execute($head, $data, $form, $warehouse, $user)
        );

        //TODO: below code don't use, this is an old version

        self::$validation_error_fields = [];
        // 1
        $nums = [];
        foreach (self::$fieldsOrder as $key => $field)
            $nums[$key] = array_search($field, $head);
        foreach ($data as $item)
            if (!self::fileOrder($item, $nums))
                return [[false, ['danger' => ['Missing required fields: ' . implode(', ', self::$validation_error_fields)]]], null];
        $has_price = $nums[self::ORDER_KEY_PRICE_1] !== false;
        $res = self::handleOrderSKUsHeader($head, $nums[self::ORDER_KEY_BARCODE_1], $has_price);
        if ($res[0] === false)
            return [[false, ['danger' => [$res[1]]]], null];
        // 2
        $actual_user_login = null;
        if (empty($form['supplier'])) {
            $suppliers = Suppliers::getSuppliersByUser($warehouse->getId(), $user->getId(), Supplier::ACTIVE);
            $supp_id = array_keys($suppliers)[0];
        } else {
            $supp_id = $form['supplier'];
            if (empty($form['user']))
                return [[false, ['danger' => ['User is not selected']]], null];
            $actual_user_login = $user->getLogin();
            $user = Users::getById($form['user']);
            if (empty($user))
                return [[false, ['danger' => ['User not found']]], null];
        }
        $SKUs = SKUs::getByUserIdBarcodeKeys($user->getId());
        // 3
        $messages_dng = [];
        $messages_scs = [];
        $orderSKUs = [];
//        $ids = Arr::maps($SKUs, 'id', ['barcode', 'state']);
        $bundles_to_insert = [];
        foreach ($data as $key => $item) {
            list($SKUs_res, $messages, $bundles_to_insert_in_row) = self::handleSKUInRow($head, $item, $has_price, $key + 1, $SKUs, $supp_id);
            if (!empty($SKUs_res))
                $orderSKUs[$key] = $SKUs_res;
            if (!empty($messages))
                $messages_dng = array_merge($messages_dng, $messages);
            if (!empty($bundles_to_insert_in_row))
                $bundles_to_insert[$key] = $bundles_to_insert_in_row;
            foreach ($SKUs_res as $SKUs_item)
                if (!isset($SKUs_item['qty']) || !isset($SKUs_item['price'])) {
                    $messages_dng = array_merge($messages_dng, ['Missing data for SKUs']);
                    return [[false, ['danger' => $messages_dng]], null];
                }
        }

        // 5
        $currency = Currencies::getCurrency($warehouse->getCurrencyId());
        foreach ($data as $key => $order) {
            if (!isset($orderSKUs[$key]) || !is_array($orderSKUs) || !count($orderSKUs[$key]))
                continue;
            $get_field = function($key) use($order, $nums) {
                return $nums[$key] === false ? '' : $order[$nums[$key]];
            };
            $data_delivery = [
                'name' => $get_field(self::ORDER_KEY_COMPANY_NAME),
                'contact' => $get_field(self::ORDER_KEY_CONTACT_NAME),
                'phone' => $get_field(self::ORDER_KEY_CONTACT_TEL),
                'email' => $get_field(self::ORDER_KEY_CONTACT_EMAIL),
                'address1' => $get_field(self::ORDER_KEY_ADDRESS1),
                'address2' => $get_field(self::ORDER_KEY_ADDRESS2),
                'address3' => $get_field(self::ORDER_KEY_ADDRESS3),
                'town' => $get_field(self::ORDER_KEY_TOWN),
                'county' => $get_field(self::ORDER_KEY_COUNTY),
                'postcode' => $get_field(self::ORDER_KEY_POSTCODE),
                'country_id' => $get_field(self::ORDER_KEY_COUNTRY_CODE),
            ];
            $delivery = new Delivery($data_delivery);
            $errors = DeliveriesUploadValidationService::validate($delivery);
            if (!empty($errors)) {
                foreach ($errors as $error)
                    $messages_dng[] = $error['message'];
                return [[false, ['danger' => $messages_dng]], null];
            }
            $shipping_service_val = $get_field(self::ORDER_KEY_CARRIER_SERVICE);

            $shipping_service = Shippings::getServiceByCode($shipping_service_val, $warehouse->getId());
            if (empty($shipping_service)) {
                if (!$warehouse->getCollectionByCustomer())
                    return [[false, ['danger' => "'shipping_service' shouldn't be empty"]], null];
                if ($shipping_service_val !== Shipping::COLLECTION_BY_CUSTOMER) {
                    $messages_dng[] = 'Order #' . ($key + 1) . ": Incorrect Service";
                    return [[false, ['danger' => $messages_dng]], null];
                }
                $carrier_id = WarehouseCarriers::getAvailableCarrierList($warehouse->getId());
                if (empty($carrier_id))
                    return [[false, ['danger' => 'Carrier is not found']], null];
                $carrier_id = $carrier_id[0]['id'];
                $shipping_services_id = 0;
            } else {
                $carrier_id = $shipping_service['carrier_id'];
                $shipping_services_id = $shipping_service['id'];
            }
            // order add
            $despatch_date = $get_field(self::ORDER_KEY_DESPATCH_DATE);
            $data_order = [
                'delivery_id' => DeliveriesModel::handleDeliveryReturningId($delivery, $user->getId()),
                'carrier_id' => $carrier_id,
                'shipping_services_id' => $shipping_services_id,
                'despatch_date' => empty($despatch_date) ? time() : WithDate::dateToInt($despatch_date),
                'ref' => $get_field(self::ORDER_KEY_REFERENCE),
                'ref2' => $get_field(self::ORDER_KEY_REFERENCE2),
                'notes' => $get_field(self::ORDER_KEY_SPECIAL_INSTRUCTION),
                'create_date' => time(),
                'user_id' => $user->getId(),
                'shop_id' => 0,
                'supplier_id' => $supp_id,
                'status' => Order::NEW_NUM,
                'type_create' => Sources::SOURCE_UPLOAD,
                'warehouse_id' => $warehouse->getId(),
                'currency' => $currency,
                'DDP' => Shippings::getTermsKey($get_field(self::ORDER_KEY_TERMS))
            ];
            if (!empty($actual_user_login))
                $data_order['actual_user_login'] = $actual_user_login;

            $SKUs_res = Orders::createOrder($data_order, [], $orderSKUs[$key]);
            $order = $SKUs_res[2] ?? null;
            foreach ($SKUs_res[1]['order'] as $message)
                if (!$SKUs_res[0])
                    $messages_dng[] = $message;
                else
                    $messages_scs[] = $message;
            if ($order instanceof Order) {
                if (!empty($bundles_to_insert[$key]))
                    foreach ($bundles_to_insert[$key] as $bundle)
                        Bundles::addToOrder($order->getId(), $bundle['bundle_id'], $bundle['quantity'], $order->getUserId());
                OrdersOriginalRequest::saveOriginalRequest($order->getId(), json_encode(['file' => $form['file_name'], 'data' => $data]), '');
            }
            if (!$SKUs_res[0])
                return [[false, ['danger' => $messages_dng, 'success' => $messages_scs]], null];
            Orders::creationOrder($order, $SKUs_res[3], $user->getId());
        }
        return [[true, ['danger' => $messages_dng, 'success' => $messages_scs]], null];
    }

    public static function fileOrderSKUs($head, $data, $form, Warehouse $warehouse, User $user)
    {
        $num_id = array_search('SkuId', $head);
        $num_barcode = array_search('SkuBarcode', $head);
        $num_quantity = array_search('quantity', $head);
        if ($num_quantity === false || ($num_id === false && $num_barcode === false))
            return [[false, ['danger' => ['Absent quantity or SKU ID or barcode in CSV']]], null];
        $ids = [];
        $barcodes = [];
        foreach ($data as $SKU)
            if (!empty($SKU[$num_id]))
                $ids[] = $SKU[$num_id];
            elseif (!empty($SKU[$num_barcode]))
                $barcodes[] = $SKU[$num_barcode];
        $tmp = array_values(array_merge(SKUs::getByIds($ids, $form['supplier']), SKUs::getByBarcodes($barcodes, $form['supplier'])));
        $ids = Arr::maps($tmp, 'id', ['barcode', 'state']);
        $barcodes = Arr::maps($tmp, 'barcode', ['id', 'barcodes', 'state']);
        $bundles = Arr::map(Bundles::getNamesBySupplier($form['supplier']), 'name', 'id');
        $qty = [];
        $messages = [];
        foreach ($data as $SKU)
            if (!empty($SKU[$num_id])) {
                if (!isset($ids[$SKU[$num_id]]))
                    $messages[] = "Absent SKU (#{$SKU[$num_id]})";
                elseif ($ids[$SKU[$num_id]]['state'] == 1)
                    $qty[$SKU[$num_id]] = $SKU[$num_quantity];
                elseif ($ids[$SKU[$num_id]]['state'] == 0)
                    $messages[] = "SKU \"{$ids[$SKU[$num_id]]['barcode']}\" (#{$SKU[$num_id]}) is locked";
                else
                    $messages[] = "SKU \"{$ids[$SKU[$num_id]]['barcode']}\" (#{$SKU[$num_id]}) is skipped";
            } elseif (!empty($SKU[$num_barcode])) {
                if (isset($bundles[$SKU[$num_barcode]]))
                    $qty['bundle_' . $bundles[$SKU[$num_barcode]]] = $SKU[$num_quantity];
                else {
                    $barcode_key = null;
                    foreach ($barcodes as $barcode_item => $barcode_data)
                        if (in_array($SKU[$num_barcode], $barcode_data['barcodes'])) {
                            $barcode_key = $barcode_item;
                            break;
                        }
                    if ($barcode_key === null)
                        $messages[] = "Absent SKU \"{$SKU[$num_barcode]}\"";
                    elseif ($barcodes[$barcode_key]['state'] == 1)
                        $qty[$barcodes[$barcode_key]['id']] = $SKU[$num_quantity];
                    elseif ($barcodes[$barcode_key]['state'] == 0)
                        $messages[] = "SKU \"{$SKU[$num_barcode]}\" (#{$barcodes[$SKU[$num_barcode]]['id']}) is locked";
                    else
                        $messages[] = "SKU \"{$SKU[$num_barcode]}\" (#{$barcodes[$SKU[$num_barcode]]['id']}) is skipped";
                }
            }
        $form['quantity'] = $qty;
        return [[true, ['danger' => $messages]], base64_encode(json_encode($form))];
    }

    public static function fileASNSKUs($head, $data, $form, Warehouse $warehouse, User $user)
    {
        $num_barcode = array_search('SkuBarcode', $head);
        $num_quantity = array_search('AsnManifestQuantity', $head);
        if ($num_quantity === false || $num_barcode === false)
            return [[false, ['danger' => ['Absent quantity or SKU ID or barcode in CSV']]], null];
        $barcodes = [];
        foreach ($data as $SKU)
            $barcodes[] = $SKU[$num_barcode];
        $ids = SKUs::getByBarcodes($barcodes, $form['ASN']['supplier_id']);
        if (!count($ids))
            return [[false, ['danger' => ['Absent SKU barcodes in CSV']]], null];
        if (!empty($form['quantity']) && is_array($form['quantity'])) {
            $qty = [];
            foreach ($form['quantity'] as $key => $q)
                $qty[$key] = (int)$q;
        } else
            $qty = [];
        $danger_messages = [];
        $warning_messages = [];
        foreach ($data as $SKU) {
            $id_key = null;
            foreach ($ids as $key => $id_data)
                if (in_array($SKU[$num_barcode], $id_data['barcodes'])) {
                    $id_key = $key;
                    break;
                }
            if ($id_key === null)
                $danger_messages[] = "Absent SKU barcode \"{$SKU[$num_barcode]}\"";
            else {
                $SKU_id = $ids[$id_key]['id'];
                if (isset($qty[$SKU_id]))
                    $warning_messages[] = "Duplicate SKU \"{$SKU[$num_barcode]}\"";
                $qty[$SKU_id] = (int)$SKU[$num_quantity];
            }
        }
        $form['quantities'] = $qty;
        return [[true, ['danger' => $danger_messages, 'warning' => $warning_messages]], base64_encode(json_encode($form))];
    }

    public static function loadCSV($filename, string $escape = "\\")
    {
        if (($handle = fopen($filename, "r")) === false) {
            $err = error_get_last();
            return [false, $err['message']];
        }
        return self::loadCSVHandler($handle, $escape);
    }

    public static function loadCSVHandler($handle, string $escape = "\\")
    {
        $head = fgetcsv($handle, 0, ',', '"', $escape);
        if ($head === false) {
            fclose($handle);
            return [false, 'Absent header of CSV'];
        }
        $head = array_map(function ($el) {
            return trim($el, "\x00..\x1F\x80..\xFF");
        }, $head);
        $data = [];
        while (($res = fgetcsv($handle, 0, ',', '"', $escape)) !== false)
            if (!empty($res))
                $data[] = $res;
        fclose($handle);

        $data = array_map(function ($row) {
            return Encoding::convertToUtf8($row);
        }, $data);
        
        return [true, [$head, $data]];
    }

    public static function fileSKUs($head, $data, $form, Warehouse $warehouse, User $user): array
    {
        return self::prepareErrorsForUX(
            (new SKUsUploadService())->execute($head, $data, $form, $warehouse, $user)
        );
    }

    private static function prepareErrorsForUX($result): array
    {
        $dangersList = $result[0][1]['danger'];

        if (isset($dangersList) && is_array($dangersList)) {
            foreach ($dangersList as $k => $errors) {
                if (is_array($errors)) {
                    foreach ($errors as $line => $errorsList) {
                        $stringError = is_array($errorsList) ? $line.': '. implode(';', $errorsList) : $errorsList;
                        $result[0][1]['danger'][$k] = $stringError;
                    }
                } else {
                    $result[0][1]['danger'][$k] = $k.': ' . $errors;
                }
            }
        }

        return $result;
    }

    public static function fileBulkMovements($head, $data, $form, Warehouse $warehouse, User $user)
    {
        return (new BulkMovementsUploadService())->execute($head, $data, $form, $warehouse, $user);
    }

    public static function fileMovements($head, $data, $form, Warehouse $warehouse, User $user)
    {
        if (empty($form['supplier']))
            return [[false, ['danger' => ['Supplier is not selected']]], null];
        $header = [
            'SkuBarcode',
            'Quantity',
            'LocationFrom',
            'LocationTo',
        ];
        $nums = [];
        foreach ($header as $field)
            $nums[$field] = array_search($field, $head);
        $absent = array_filter($nums, static function ($num) {
            return $num === false;
        });
        if (!empty($absent))
            return [[false, ['danger' => ['Absent header of CSV. The following columns need to be added: ' . implode(',', array_keys($absent))]]], null];
        $barcodes = [];
        $messages = [];
        $csv_data = array_filter($data, function ($row) {
            return !empty($row) && (count($row) > 1 || !empty($row[0]));
        });
        foreach ($csv_data as $line => $row) {
            $assocRow = array_combine($head, $row);
            $assocRow = array_filter($assocRow, static function ($column) use ($header) {
                return in_array($column, $header);
            }, ARRAY_FILTER_USE_KEY);
            if (empty($assocRow['SkuBarcode'])) {
                $messages[$line] = 'Barcode is required';
                continue;
            }
            if (empty($assocRow['Quantity'])) {
                $messages[$line] = 'Quantity is required';
                continue;
            }
            if (empty($assocRow['LocationFrom'])) {
                $messages[$line] = 'Location from is required';
                continue;
            }
            if (empty($assocRow['LocationTo'])) {
                $messages[$line] = 'Location to is required';
                continue;
            }
            $barcodes[] = $assocRow['SkuBarcode'];
        }
        $messages_add = [];
        $success = [];
        // additional fields
        $header[] = 'Comment';
        if (!empty($barcodes)) {
            $SKUs = SKUs::getByBarcodes($barcodes, $form['supplier'], Filter::TYPE__EQUALS, true);
            foreach ($csv_data as $line => $row) {
                $assocRow = array_combine($head, $row);
                $assocRow = array_filter($assocRow, static function ($column) use ($header) {
                    return in_array($column, $header);
                }, ARRAY_FILTER_USE_KEY);
                if (empty($assocRow['SkuBarcode']))
                    continue;
                $barcode = $assocRow['SkuBarcode'];
                if (empty($SKUs[$barcode])) {
                    $messages_add[$line] = "SKU \"{$barcode}\" not found";
                    continue;
                }
                $SKU = $SKUs[$barcode];
                $quantity = (int)$assocRow['Quantity'];
                if ($quantity <= 0) {
                    $messages_add[$line] = "Quantity must be greater than 0 for SKU \"{$barcode}\"";
                    continue;
                }
                $location_from = Locations::getByScan($warehouse->getId(), $assocRow['LocationFrom']);
                if (empty($location_from)) {
                    $messages_add[$line] = "Location from not found for SKU \"{$barcode}\"";
                    continue;
                }
                // restrict CNT locations
                if (Locations::isDHMLocation($location_from) || Locations::isDHPLocation($location_from)) {
                    $messages_add[$line] = "Location from is forbidden for SKU \"{$barcode}\"";
                    continue;
                }
                $location_to = Locations::getByScan($warehouse->getId(), $assocRow['LocationTo']);
                if (empty($location_to)) {
                    $messages_add[$line] = "Location to not found for SKU \"{$barcode}\"";
                    continue;
                }
                if ($location_from->getId() === $location_to->getId()) {
                    $messages_add[$line] = "Location from and location to must be different for SKU \"{$barcode}\"";
                    continue;
                }
                // restrict carts, noverify, ORDCANCEL and CNT locations
                if (Locations::isDHMLocation($location_to) || Locations::isDHPLocation($location_to)
                    || Locations::isCancelLocation($location_to) || Locations::isNoVerifyLocation($location_to)
                ) {
                    $messages_add[$line] = "Location to is forbidden for SKU \"{$barcode}\"";
                    continue;
                }
                $error = Movements::validationMovements(new SKU($SKU), $location_from, $location_to, $quantity);
                if (!empty($error)) {
                    $messages_add[$line] = "$error for SKU \"{$barcode}\"";
                    continue;
                }
                if ($location_from->PR() && !($location_to->notCounted() || $location_to->cart())) {
                    $messages_add[$line] = "Movement is impossible. SKU can not be taken or put";
                    continue;
                }
                $type = Movement::TYPE_UPLOAD;
                $comment = $assocRow['Comment'] ?? '';
                if ($location_from->PR() || $location_to->PR()) {
                    $type = Movement::TYPE_UNRESTRICTED;
                    $upload_msg = 'Upload from csv';
                    if ($comment)
                        $upload_msg = ' (' . strtolower($upload_msg) . ')';
                    $comment .= $upload_msg;
                }
                [$res, ] = Movements::insertMovement(
                    $SKU['id'],
                    $quantity,
                    $location_from->getId(),
                    $location_to->getId(),
                    $user->getId(),
                    $comment,
                    0,
                    Movement::NEW_MOVEMENT,
                    $type,
                );
                $success[] = "movement $res for SKU \"{$barcode}\"";
            }
        }
        $format_message = static function ($message, $line) {
            $line++;
            return "Line $line: $message";
        };
        if (!empty($messages_add))
            return [[false, ['danger' => Arr::mapWithKey($format_message, $messages + $messages_add), 'success' => $success]], null];
        return [[true, ['danger' => Arr::mapWithKey($format_message, $messages), 'success' => $success]], -1];
    }

    public static function handleOrderFileFromQueue($file, $type, $warehouse_id, $user_job_id)
    {
        /** @var UserJob $user_job */
        $user_job = UserJobs::getById($user_job_id);
        $user_id = UserJobs::getUserId($user_job_id);
        if (empty($user_job))
            return ['error' => 'User queue not found'];
        if (in_array($user_job->getStatus(), [UserJob::STATUS_DONE, UserJob::STATUS_ERROR, UserJob::STATUS_ABORT]))
            return ['success' => 'User queue stopped'];
        $warehouse = Warehouses::getById($warehouse_id);
        $user_job->setStatus(UserJob::STATUS_PROGRESS);
        $user_job->setUpdateDate(time());
        $user_job->save(['id', 'status', 'update_date']);

        if (is_file($file)) {
            try {
                /** @var FileJob $file_job */
                $file_job = FileJobs::getByUserJobIdAndFile($user_job_id, File::clearPath($file));
                if (empty($file_job))
                    throw new \Exception('File Job not found');
                if (($handle = fopen($file, "r")) === false) {
                    $err = error_get_last();
                    throw new \Exception($err['message']);
                }
                $head = fgetcsv($handle);
                if ($head === false) {
                    fclose($handle);
                    throw new \Exception('Absent header of CSV');
                }

                list($open, $res) = self::loadCSV($file);
                $result = self::fileOrders($res[0], $res[1], File::getFileNameFromPath($file, 3), $warehouse, Users::getById($user_id));

                if (!empty($result[0][1]['danger']))
                    foreach ($result[0][1]['danger'] as $danger_msg)
                        UserJobNotifications::addNewDanger($user_job_id, $danger_msg);
                if (!empty($result[0][1]['success']))
                    foreach ($result[0][1]['success'] as $success_msg)
                        UserJobNotifications::addNewSuccess($user_job_id, $success_msg);

                $file_job->setIsDone(true);
                $file_job->setUpdateDate(time());
                $file_job->save(['id', 'is_done', 'update_date']);

                $user_job->setStatus(UserJob::STATUS_DONE);
                $user_job->setUpdateDate(time());
                $user_job->save(['id', 'status', 'update_date']);
            } catch (\Exception $e) {
                $user_job->setStatus(UserJob::STATUS_ERROR);
                $user_job->setUpdateDate(time());
                $user_job->save(['id', 'status', 'update_date']);
                return ['error' => $e->getMessage()];
            }
        }
        return ['success' => 'Success'];
    }

    /** @throws */
    public static function splitUploadFile($file, $type, $warehouse_id, $user_job_id): array
    {
        $user_job = UserJobs::getById($user_job_id);
        if (!empty($user_job)) {
            if (in_array($user_job->getStatus(), [UserJob::STATUS_DONE, UserJob::STATUS_ERROR, UserJob::STATUS_ABORT]))
                throw new \Exception('Job stopped unexpectedly');
            $user_job->setStatus(UserJob::STATUS_PROGRESS);
            $user_job->setUpdateDate(time());
            $user_job->save(['id', 'status', 'update_date']);
        } else
            throw new \Exception('User job not found');
        $fileCount = 1;
        if (is_file($file)) {
            $fileArr = explode(DIRECTORY_SEPARATOR, $file);
            $fullFileName = array_pop($fileArr);
            $fileNameArr = explode('.', $fullFileName);
            $fileName = array_shift($fileNameArr);
            $ext = array_pop($fileNameArr);
            $filePath = implode(DIRECTORY_SEPARATOR, $fileArr) . DIRECTORY_SEPARATOR . $fileName . DIRECTORY_SEPARATOR;
            if (!is_dir($filePath)) {
                mkdir($filePath, 0777, true);
                chmod($filePath, 0777);
            }
            $outputFile = $filePath;
            if (strcasecmp($ext, 'csv') == 0) {
                $in = fopen($file, 'r');
                $head = fgetcsv($in);
                $rowCount = 0;
                while (!feof($in)) {
                    if ($rowCount % self::MAX_LINES_ORDERS == 0) {
                        if ($rowCount > 0 && !empty($out))
                            fclose($out);
                        $split_file_name = $outputFile . $fileCount++ . '.csv';
                        if ($fileCount === 2)
                            switch ($type) {
                                case FileJob::TYPE_ORDERS:
                                    Queue::addJobToHandleFileOrders([
                                        'file' => addslashes($split_file_name),
                                        'user_job_id' => $user_job_id,
                                        'warehouse_id' => $warehouse_id
                                    ]);
                                    break;
                            }
                        $out = fopen($split_file_name, 'w');
                        if ($out) {
                            FileJobs::addNew($split_file_name, $user_job_id);
                            fputcsv($out, $head);
                        }
                    }
                    $data = fgetcsv($in);
                    if ($data && !empty($out))
                        fputcsv($out, $data);
                    $rowCount++;
                }
                if (!empty($out))
                    fclose($out);
            } else {
                $user_job->setStatus(UserJob::STATUS_ERROR);
                $user_job->setUpdateDate(time());
                $user_job->save(['id', 'status', 'update_date']);
                UserJobNotifications::addNewDanger($user_job->getId(), 'File error');
                throw new \Exception('File type is not correct');
            }
        } else
            throw new \Exception('File not found');
        $count_parts = $fileCount - 1;
        if ($count_parts >= 1)
            return ['success' => 'File was split in ' . --$fileCount . ' parts'];
        else {
            $user_job->setStatus(UserJob::STATUS_ERROR);
            $user_job->setUpdateDate(time());
            $user_job->save(['id', 'status', 'update_date']);
            UserJobNotifications::addNewDanger($user_job->getId(), 'File error');
            throw new \Exception('The file was not split');
        }
    }
}
