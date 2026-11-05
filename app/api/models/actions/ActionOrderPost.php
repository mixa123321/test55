<?php

namespace app\api\models\actions;

use app\api\API;
use app\api\APIException;
use app\api\models\ErrorCodes;
use app\backend\models\Deliveries as DeliveriesModel;
use app\backend\models\Orders;
use app\backend\models\SKUs;
use app\core\entities\Delivery;
use app\core\entities\Order;
use app\core\entities\Supplier;
use app\core\helpers\Debug;
use app\core\models\Bundles;
use app\core\models\config\OrdersConfig;
use app\core\models\OrderDocs;
use app\core\models\OrdersOriginalRequest;
use app\core\models\Shippings;
use app\core\models\Suppliers;
use app\core\models\WarehouseCarriers;
use app\core\models\Warehouses;
use app\core\statical\Currencies;
use app\core\statical\Sources;
use app\core\statical\WithDate;
use app\core\interfaces\RequestValidationInterface;
use app\core\traits\RequestValidation;
use app\core\helpers\Defends;

class ActionOrderPost extends BaseOrderPost implements RequestValidationInterface
{
    use RequestValidation;

    private $orders = [];
    private $data_delivery;
    private $data_docs;
    private $orderSKUs;
    private $user_id;
    private $warehouse_id;
    private $supplier_id;
    private $currency;
    private $warehouse_virtual;
    protected $data;

    /**
     * @throws APIException
     * @return BaseAction
     */
    public function init(): BaseAction {
        $data = $this->getValidRequestData();
        $this->data = $data;
        $this->orders = [];
        if (count($data) < 1)
            throw new APIException(422, [['code' => ErrorCodes::DATA_ERROR, 'message' => 'Orders are not found']]);
        $this->user_id = API::getUser()->getId();
        $this->warehouse_id = API::getUser()->getWarehouseId();
        $suppliers = Suppliers::getSuppliersByUser($this->warehouse_id, $this->user_id, Supplier::ACTIVE);
        if (empty($suppliers))
            throw new APIException(422, [['code' => ErrorCodes::DATA_ERROR, 'message' => 'Supplier is not found']]);
        $this->supplier_id = array_keys($suppliers)[0];
        $warehouse = Warehouses::getById($this->warehouse_id);
        $this->currency = Currencies::getCurrency($warehouse->getCurrencyId());
        $this->warehouse_virtual = $warehouse->getIsVirtual();
        $this->data_delivery = [];
        $this->data_docs = [];
        foreach ($data as $key => $order) {
            $delivery = empty($order['Delivery']) ? [] : $order['Delivery'];
            $this->data_delivery[$key] = [
                'name' => empty($delivery['CompanyName']) ? '' : $delivery['CompanyName'],
                'contact' => empty($delivery['ContactName']) ? '' : $delivery['ContactName'],
                'phone' => empty($delivery['ContactTel']) ? '' : $delivery['ContactTel'],
                'email' => empty($delivery['ContactEmail']) ? '' : $delivery['ContactEmail'],
                'address1' => empty($delivery['Address1']) ? '' : $delivery['Address1'],
                'address2' => empty($delivery['Address2']) ? '' : $delivery['Address2'],
                'address3' => empty($delivery['Address3']) ? '' : $delivery['Address3'],
                'town' => empty($delivery['Town']) ? '' : $delivery['Town'],
                'county' => empty($delivery['County']) ? '' : $delivery['County'],
                'postcode' => empty($delivery['Postcode']) ? '' : $delivery['Postcode'],
                'country_id' => empty($delivery['CountryCode']) ? '' : $delivery['CountryCode'],
            ];
            foreach ($this->data_delivery[$key] as $k => $val)
                if (empty($val))
                    unset($this->data_delivery[$key][$k]);
            $currency = empty($order['Currency']) ? $this->currency : $order['Currency'];
            if (empty($currency) || !Currencies::isCurrency($currency))
                throw new APIException(422, [['code' => ErrorCodes::FIELD_INVALID, 'message' => 'Currency is not found']]);
            $order_shipping_service = $order['shipping_service'] ?? ($order['ShippingService'] ?? null);
            if (!empty($order_shipping_service))
                $shipping_service = Shippings::getServiceByCode($order_shipping_service, $this->warehouse_id);
            if (empty($shipping_service)) {
                if (!$warehouse->getCollectionByCustomer())
                    throw new APIException(422, [['code' => ErrorCodes::FIELD_INVALID, 'message' => "ShippingService shouldn't be empty"]]);
                $carrier_id = WarehouseCarriers::getAvailableCarrierList($this->warehouse_id);
                if (empty($carrier_id))
                    throw new APIException(422,  [['code' => ErrorCodes::DATA_ERROR, 'message' => 'Carrier is not found']]);
                $carrier_id = $carrier_id[0]['id'];
                $shipping_services_id = 0;
            } else {
                $carrier_id = $shipping_service['carrier_id'];
                $shipping_services_id = $shipping_service['id'];
            }

            $documentUrl = $order['document_url'] ?? ($order['DocumentUrl'] ?? '');
            if (!empty($documentUrl))
                $this->data_docs[$key] = $documentUrl;
            $DDP = Shippings::TYPE_TERM_DEFAULT;
            if (isset($order['shipping_terms']))
                switch ($order['shipping_terms']) {
                    case Shippings::TYPE_TERM_DDP_NAME:
                        $DDP = Shippings::TYPE_TERM_DDP;
                        break;
                    case Shippings::TYPE_TERM_DDU_NAME:
                        $DDP = Shippings::TYPE_TERM_DDU;
                        break;
                    default:
//                        $DDP = Shippings::TYPE_TERM_DEFAULT;
                        // https://trello.com/c/Kw04xgag/417-change-terms-to-ddu-for-api-created-orders
                        $DDP = Shippings::TYPE_TERM_DDU;
                        // ----------------------------------------------------------------------------
                }
            // https://trello.com/c/Kw04xgag/417-change-terms-to-ddu-for-api-created-orders // Part 2
            else $DDP = Shippings::TYPE_TERM_DDU;
            // ----------------------------------------------------------------------------
            $this->orders[$key] = [
                'carrier_id' => $carrier_id,
                'shipping_services_id' => $shipping_services_id,
                'despatch_date' => empty($order['DespatchDate']) ? '' : WithDate::dateToInt($order['DespatchDate']),
                'ref' => empty($order['Reference']) ? '' : $order['Reference'],
                'currency' => $currency,
                'user_id' => $this->user_id,
                'supplier_id' => $this->supplier_id,
                'shop_id' => 0,
                'status' => Order::NEW_NUM,
                'warehouse_id' => $this->warehouse_id,
                'DDP' => $DDP,
                'config' => [],
            ];
            if (!empty(trim($delivery['TaxId'] ?? '')))
                $this->orders[$key]['config'][OrdersConfig::TYPE_ORDER_TAX_ID] = trim($delivery['TaxId']);
            $this->orderSKUs[$key] = empty($order['SKUs']) ? [] : $order['SKUs'];
        }
        return $this;
    }

    public function validateFields() {
        if (count($this->orders) < 1) {
            return [['code' => ErrorCodes::DATA_ERROR, 'message' => 'Orders are not found']];
        }
        return [];
    }

    /** @throws APIException */
    private function defendingOrderRequest($ref, \Closure $creationOrder)
    {
        $defends = (new Defends())->setType(Defends::TYPE_ORDER_REQUEST)->setHash($ref . Defends::SALT_FOR_DIFFICULT_HASH . $this->supplier_id);
        if ($defends->lockDate() !== Defends::LOCK_NOT_UPDATE) {
            $creationOrder();
            $defends->unLock();
        } else {
            Debug::sendDebugInfo('defendingAPIOrderRequest', 'Trying to get defending API order request ref#' . $ref
                                        . ' by ' . (Suppliers::getById($this->supplier_id))->getName() . ' hash ' . $defends->getHash(),  Debug::TEST);
            throw new APIException(401, [['code' => ErrorCodes::ACCESS_DENIED, 'message' => 'Defending request']]);
        }
    }

    /** @throws APIException */
    public function process()
    {
        if (count($this->orders) < 1)
            return [
                'critical_error' => [
                    'error_id' => 1,
                    'error_msg' => 'Missing orders'
                ]
            ];
        $errors_ord = [];
        $ord = [];
        foreach ($this->orders as $key => $order_data) {
            $delivery = new Delivery($this->data_delivery[$key]);
            $errors = DeliveriesModel::validate($delivery, $this->warehouse_virtual);
            if (!empty($errors)) {
                $errors_ord["Rec #$key"]['delivery'] = [];
                foreach ($errors as $error)
                    $errors_ord["Rec #$key"]['delivery'][] = $error['message'];
                continue;
            }
            $this->orders[$key]['delivery_id'] = DeliveriesModel::handleDeliveryReturningId($delivery, $this->user_id);
            $this->orders[$key]['create_date'] = time();
            $this->orders[$key]['type_create'] = Sources::SOURCE_API;
            $res = [];
            $order = null;
            $this->defendingOrderRequest($this->orders[$key]['ref'], function() use (&$res, &$order, $key) {
                $res = Orders::createOrder($this->orders[$key], [], $this->orderSKUs[$key], self::class . '::prepareSKUs');
                /** @var Order $order */
                $order = $res[2];
                if (!empty($order)) {
                    OrdersOriginalRequest::saveOriginalRequest($order->getId(), $this->getOriginalRequest(), '');
                    if (!empty($this->data_docs[$key]))
                        $order = OrderDocs::setDocumentUrl($order, $this->data_docs[$key], $this->user_id);
                }
            });
            if (!$res[0]) {
                if (!empty($res[1]['order']))
                    $errors_ord["Rec #$key"]['order'] = $res[1]['order'];
                if (!empty($res[1]['SKUs']))
                    $errors_ord["Rec #$key"]['SKUs'] = $res[1]['SKUs'];
                continue;
            }
            $ord["Rec #$key"] = $order->getId();
            if (!empty($res[1]['order'])) {
                $messages = [];
                for ($i = 1; $i < count($res[1]['order']); $i++)
                    $messages[] = $res[1]['order'][$i];
                if (!empty($messages))
                    $errors_ord["Rec #$key"]['messages'] = $messages;
            }
            Orders::creationOrder($order, $res[3], $this->user_id);
        }
        return ['created_orders' => $ord, 'error_orders' => array_merge($errors_ord, $this->getValidationRequestErrors())];
    }

    public static function prepareSKUs(Order $order, $quantities, $params)
    {
        $errors_ord = [];
        $ordSKUs = [];
        $bundles_to_insert = [];
        $SKUs_has_price = [
            false => 0,
            true => 0,
        ];
        foreach ($quantities as $SKU)
            $SKUs_has_price[isset($SKU['SalesPrice'])]++;
        if ($SKUs_has_price[true] > 0 && $SKUs_has_price[false] > 0)
            $errors_ord['SKUs'][] = "Price error";
        $has_price = $SKUs_has_price[true] > 0;
        if (empty($errors_ord))
            foreach ($quantities as $index => $SKU) {
                if (empty($SKU['Quantity']) || $SKU['Quantity'] < 1) {
                    $errors_ord['SKUs'][] = "Empty quantity ({$SKU['Barcode']})";
                    continue;
                }
                $sales_price = 0;
                if (isset($SKU['SalesPrice'])) {
                    if (floatval($SKU['SalesPrice']) < 0 || (string)floatval($SKU['SalesPrice']) != $SKU['SalesPrice']) {
                        $errors_ord['SKUs'][] = "Error sales price for {$SKU['Barcode']}";
                        continue;
                    }
                    $sales_price = round((float)$SKU['SalesPrice'], 2);
                }
                //1. $SKU['Barcode'] can be a Bundle name from "bundles" table
                $bundle_by_barcode = Bundles::getBundleInfo($SKU['Barcode'], $order->getSupplierId());
                if (!empty($bundle_by_barcode)) {
                    $bundle_id = $bundle_by_barcode[0]['id'];
                    $koeff = $sales_price / array_sum(array_map(function($el) { return $el['quantity']*$el['market_value']; }, $bundle_by_barcode));
                    foreach ($bundle_by_barcode as $SKU_data)
                        Bundles::accumulationBundleSKU($SKU_data['SKU_id'], $bundle_id, $ordSKUs, $SKU_data['quantity'] * $SKU['Quantity'], $SKU_data['market_value'] * $koeff);
                    if (empty($bundles_to_insert[$bundle_id]))
                        $bundles_to_insert[$bundle_id] = [
                            'bundle_id' => $bundle_id,
                            'quantity' => $SKU['Quantity'],
                            'price' => $sales_price
                        ];
                    else {
                        if ($bundles_to_insert[$bundle_id]['price'] !== $sales_price)
                            $errors_ord['SKUs'][] = 'Duplicate ' . ($index + 1) . ') ' . $SKU['Barcode'] . ' with different sales price';
                        $bundles_to_insert[$bundle_id]['quantity'] += $SKU['Quantity'];
                    }
                    continue;
                }
                //2. $SKU['Barcode'] can be a unique_customer_ref
                $SKU_obj = SKUs::getByUniqueCustomerRefAndSupplier($SKU['Barcode'], $order->getSupplierId());
                if (empty($SKU_obj)) {
                    //3. $SKU['Barcode'] can be a barcode
                    $SKU_obj = SKUs::getByBarcodeAndSupplier($SKU['Barcode'], $order->getSupplierId());
                    if (!$SKU_obj) {
                        //4. $SKU['Barcode'] can be a SKU internal ID
                        $SKU_obj = SKUs::getById($SKU['Barcode']);
                        if (!$SKU_obj) {
                            $errors_ord['SKUs'][] = "Invalid barcode {$SKU['Barcode']}";
                            continue;
                        }
                    }
                }
                if (!self::accumulationSKU($SKU_obj->getId(), $ordSKUs, (int)$SKU['Quantity'], $sales_price))
                    $errors_ord['SKUs'][] = 'Duplicate ' . ($index + 1) . ') ' . $SKU['Barcode'] . ' with different sales price';
            }
        if (!empty($errors_ord) || empty($ordSKUs))
            return [false, ['SKUs' => $errors_ord['SKUs']]];
        if (!empty($bundles_to_insert)) {
            foreach ($bundles_to_insert as $bundle)
                Bundles::addToOrder($order->getId(), $bundle['bundle_id'], $bundle['quantity'], $order->getUserId());
            Bundles::walkSKUsWithBundles($ordSKUs);
        }
        return [
            true,
            [$has_price ? $ordSKUs : SKUs::getPriceSKUs(array_map(function($s) { return $s['qty']; }, $ordSKUs))],
            !empty($bundles_to_insert)
        ];
    }

    private static function accumulationSKU($SKU_id, &$ordSKUs, $quantity, $sales_price)
    {
        if (empty($ordSKUs[$SKU_id]))
            $ordSKUs[$SKU_id]['qty'] = 0;
        $ordSKUs[$SKU_id]['qty'] += $quantity;
        if (!empty($ordSKUs[$SKU_id]['price']) && $ordSKUs[$SKU_id]['price'] !== $sales_price)
            return false;
        $ordSKUs[$SKU_id]['price'] = $sales_price;
        return true;
    }

    public function getResponseFieldsConfig()
    {
        return [];
    }

    public function validationRequest(): RequestValidationInterface
    {
        if (empty($this->request_data)) {
            $this->errors[self::ERROR_KEY_PREFIX . "0"]['order'][] = 'Order is required';
            return $this;
        }
        foreach ($this->request_data as $key => $values) {
            if ($error = $this->mandatoryMaxLen($values, self::MAX_LEN_255, 'Reference'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['order'][] = $error;
            //TODO временно выключили валидацию. Используют не документированные возможности
//            if ($error = $this->mandatoryMinMaxLen($values, self::MIN_LEN_3, self::MAX_LEN_3, 'Currency'))
//                $this->errors[self::ERROR_KEY_PREFIX . $key]['order'][] = $error;
            if ($error = $this->mandatoryDate($values, self::DATE_WITHOUT_TIME, 'DespatchDate'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['order'][] = $error;
            //TODO временно выключили валидацию. Используют не документированные возможности
//            if ($error = $this->mandatoryMaxLen($values, self::MAX_LEN_4, 'ShippingService'))
//                $this->errors[self::ERROR_KEY_PREFIX . $key]['order'][] = $error;
            if ($error = $this->noMandatoryMaxLen($values, self::MAX_LEN_3, 'shipping_terms'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['order'][] = $error;
            if ($error = $this->mandatoryArray($values, 'Delivery'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
            else {
                if ($error = $this->mandatoryMaxLen($values['Delivery'], self::MAX_LEN_100, 'ContactName'))
                    $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
                if ($error = $this->noMandatoryMaxLen($values['Delivery'], self::MAX_LEN_100, 'CompanyName'))
                    $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
                if ($error = $this->mandatoryMaxLen($values['Delivery'], self::MAX_LEN_255, 'Address1'))
                    $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
                if ($error = $this->noMandatoryMaxLen($values['Delivery'], self::MAX_LEN_255, 'Address2'))
                    $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
                if ($error = $this->noMandatoryMaxLen($values['Delivery'], self::MAX_LEN_255, 'Address3'))
                    $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
                if ($error = $this->mandatoryMaxLen($values['Delivery'], self::MAX_LEN_50, 'Town'))
                    $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
                if ($error = $this->noMandatoryMaxLen($values['Delivery'], self::MAX_LEN_50, 'County'))
                    $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
                if ($error = $this->mandatoryMaxLen($values['Delivery'], self::MAX_LEN_15, 'Postcode'))
                    $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
                if ($error = $this->mandatoryMinMaxLen($values['Delivery'], self::MIN_LEN_2, self::MAX_LEN_2, 'CountryCode'))
                    $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
                if ($error = $this->mandatoryMaxLen($values['Delivery'], self::MAX_LEN_15, 'ContactTel'))
                    $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
                if ($error = $this->mandatoryMaxLen($values['Delivery'], self::MAX_LEN_50, 'ContactEmail'))
                    $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
                if ($error = $this->noMandatoryMaxLen($values['Delivery'], self::MAX_LEN_50, 'TaxId'))
                    $this->errors[self::ERROR_KEY_PREFIX . $key]['delivery'][] = $error;
            }
            if ($error = $this->mandatoryArray($values, 'SKUs')) {
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKUs'][] = $error;
            } else
                foreach ($values['SKUs'] as $SKU) {
                    if ($error = $this->mandatoryMaxLen($SKU, self::MAX_LEN_25, 'Barcode'))
                        $this->errors[self::ERROR_KEY_PREFIX . $key]['SKUs'][] = $error;
                    if ($error = $this->mandatoryRegexp($SKU, self::REGEXP_INT_1_TO_11, 'Quantity'))
                        $this->errors[self::ERROR_KEY_PREFIX . $key]['SKUs'][] = $error;
                    //TODO временно выключили валидацию. Используют не документированные возможности
//                    if ($error = $this->mandatoryRegexp($SKU, self::REGEXP_FLOAT_1_TO_4_POINT_1_TO_2, 'SalesPrice'))
//                        $this->errors[self::ERROR_KEY_PREFIX . $key]['SKUs'][] = $error;
                }
            if (empty($this->errors[self::ERROR_KEY_PREFIX . $key]))
                $this->valid_request_data[$key] = $values;
        }
        return $this;
    }
}
