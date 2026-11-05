<?php

namespace app\backend\models;

use app\backend\models\Shippings as ShippingsBackend;
use app\core\channels\shopify\ShopifyConnector;
use app\core\db\DBException;
use app\core\dbal\SafeMysql;
use app\core\db\QueryBuilder;
use app\core\entities\Delivery;
use app\core\entities\File;
use app\core\entities\FileJob;
use app\core\entities\Location;
use app\core\entities\LogisticCompany;
use app\core\entities\Order;
use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\entities\UserJob;
use app\core\entities\Warehouse;
use app\core\entities\Queue as QueueEntity;
use app\core\exceptions\VirtualException;
use app\core\helpers\Arr;
use app\core\helpers\Debug;
use app\core\helpers\formatters\FormatterCSV;
use app\core\helpers\notifiers\Notifier;
use app\core\helpers\Request;
use app\core\helpers\Validator;
use app\core\models\Batches;
use app\core\models\Channels;
use app\core\models\Movements;
use app\core\models\OrderLocations;
use app\core\models\OrderLocationTemporaries;
use app\core\models\Orders as OrdersModel;
use app\core\models\OrderSKUSerialNumbers;
use app\core\models\OrdersOriginalRequest;
use app\core\models\OrdersPackagesSKUs;
use app\core\models\RetainmeOrders;
use app\core\models\Shippings;
use app\core\models\Shops;
use app\core\models\SKUChannelSuppliers;
use app\core\models\SKUPreffLocs;
use app\core\models\TokensTranslations;
use app\core\models\Warehouses;
use app\core\models\Queues;
use app\core\Queue;
use app\core\services\archiving\ArchivingOrderService;
use app\core\statical\Currencies;
use app\core\statical\Sources;
use app\core\interfaces\ReportCSV as InterfaceReportCSV;
use app\core\statical\Timezones;
use app\core\statical\WithDate;
use app\core\traits\ReportCSV as TraitReportCSV;
use app\core\virtual\VirtualNotification;
use Exception;
use app\core\helpers\Defends;
use app\core\models\config\OrdersConfig;

class Orders extends OrdersModel implements InterfaceReportCSV
{
    use TraitReportCSV;

    public const LIMIT_PER_PAGE = 10;
    public const FILTER_PARAM = 'orders_filters';

    private const REPORT_FOLDER_NAME = 'order_reports';
    private const REPORT_NAME = 'Fulfilment_order_list';

    public static function dateTypes(array $tokens = [])
    {
        return [
            'create_date' => TokensTranslations::getTranslate($tokens, 'Created'),
            'despatch_date' => TokensTranslations::getTranslate($tokens, 'Expected Despatch'),
            'despatched_date' => TokensTranslations::getTranslate($tokens, 'Despatched'),
        ];
    }

    /**
     * @param $order Order
     * @return array
     */
    public static function validate($order)
    {
        $rules = [
            'delivery_id' => [
                'val' => $order->getDeliveryId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'supplier_id' => [
                'val' => $order->getSupplierId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'carrier_id' => [
                'val' => $order->getCarrierId(),
                'rules' => [Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'despatch_date' => [
                'val' => $order->getDespatchDate(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_DATE => []]
            ],
            'ref' => [
                'val' => $order->getRef(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
            ],
            'ref2' => [
                'val' => $order->getRef2(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'picking' => [
                'val' => $order->getPicking(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'packing' => [
                'val' => $order->getPacking(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'notes' => [
                'val' => $order->getNotes(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'shop_id' => [
                'val' => $order->getShopId(),
                'rules' => [Validator::TYPE_INT => [0, PHP_INT_MAX]]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors)) {
            return $errors;
        }
        if (!Shippings::checkServiceIdExists($order->getShippingServicesId(), $order->getWarehouseId())) {
            $validator->addError("shipping_service_invalid", "Invalid shipping service provided");
        }
        if (!empty($order->getId())) {
            $order_by_id = Orders::getById($order->getId());
            if ($order_by_id === null) {
                $validator->addError("order_not_found", "Order not found");
            }
        }
        return $validator->getErrors();
    }

    public static function isShopify($type_create)
    {
        return $type_create === Sources::SOURCE_SHOPIFY;
    }

    public static function isWooCommerce($type_create)
    {
        return $type_create === Sources::SOURCE_WOOCOMMERCE;
    }

    public static function isAPI($type_create)
    {
        return $type_create === Sources::SOURCE_API;
    }

    private static function fromChannels($order)
    {
        return !empty($order['type_create']) && in_array($order['type_create'], [Sources::SOURCE_SHOPIFY, Sources::SOURCE_WOOCOMMERCE]);
    }

    private static function getCurrency($origin_request)
    {
        $response = json_decode($origin_request['response'], true);
        if (!empty($response) && !empty($response['currency']))
            return $response['currency'];
        return null;
    }

    private static function countShopifyDiscountedValue($shopify_request, $shop_id): array
    {
        $response = json_decode($shopify_request['response'], true);
        $product_value_map = [];
        if (!empty($response)) {
            $products = $response['line_items'];
            if (!empty($products))
                foreach ($products as $product) {
                    $SKU_id = SKUChannelSuppliers::getIdByPrefixExternalId($product['variant_id'], $product['product_id'], $shop_id);
                    if (!isset($product['price']) || !isset($product['discount_allocations']) || !isset($product['quantity'])) {
                        Debug::sendDebugInfo('CreateShipment', "Information or their part of product (#$SKU_id) is absent");
                        continue;
                    }
                    $sum = 0;
                    if (!empty($product['discount_allocations']))
                        foreach ($product['discount_allocations'] as $discount)
                            $sum += $discount['amount'];
                    $product_value_map[$SKU_id] = $product['price'] * $product['quantity'] - $sum;
                    $product_value_map[$SKU_id] = abs($product_value_map[$SKU_id]) < 1E-3 ? 0 : $product_value_map[$SKU_id];
                }
        }
        return $product_value_map;
    }

    public static function getCurrencyPrice($order)
    {
        $product_value_map = [];
        if (Orders::fromChannels($order)) {
            $original_request = OrdersOriginalRequest::getOriginalRequest($order['id']);
            $currency = Orders::getCurrency($original_request);
            if (Orders::isShopify($order['type_create']) && !$order['product_price'])
                $product_value_map = Orders::countShopifyDiscountedValue($original_request, $order['shop_id']);
        }
        if (empty($currency) || !Currencies::isCurrency($currency)) {
            $warehouse = Warehouses::getById($order['warehouse_id']);
            $currency = Currencies::getCurrency($warehouse->getCurrencyId());
        }
        return [$currency, $product_value_map];
    }

    private static function getRisk(Order $order)
    {
        /** @var Shops $shops */
        $shops = Channels::getUserChannel([
            'shop_id' => $order->getShopId(),
            'warehouse_id' => $order->getWarehouseId(),
        ]);
        if (empty($shops) || empty($shops->getShop()))
            return false;
        $shopify_connector = new ShopifyConnector($shops->getShop()->getCredentials());
        $risks = $shopify_connector->getOrderRisks($order->getExternalId());
        if (!empty($risks) && !in_array($risks['recommendation'], ['NONE', 'ACCEPT'])) {
            $risk_factors = [];
            foreach ($risks['assessments'] as $assessment) {
                if ($assessment['riskLevel'] === 'NONE')
                    continue;
                $risk_factors[] = "Recommendation: {$risks['recommendation']}. Message: {$assessment['facts'][0]['description']}";
            }
            return $risk_factors;
        }
        return [];
    }

    private static function isPendingApproval(Order $order)
    {
        if (Orders::isApprove($order->getId()))
            return true;
        if (Channels::isShopify(Orders::getChannel($order->getId())) && Shops::isRisk($order->getShopId()))
            return !empty(self::getRisk($order));
        return false;
    }

    private static function setPendingApproval(Order $order, $user_id)
    {
        if (!Orders::isApprove($order->getId()))
            return false;
        self::changeStatus($order->getId(), Order::PENDING_APPROVAL, $user_id);
        SupplierNotifications::addNotification($order->getSupplierId(), SupplierNotifications::PENDING_APPROVAL, [
            'id' => $order->getId(),
            'ref' => $order->getRef(),
            'ref2' => $order->getRef2(),
            'approval' => time(),
        ]);
        return true;
    }

    private static function setRiskShopify(Order $order, $user_id)
    {
        if (!Channels::isShopify(Orders::getChannel($order->getId())) || !Shops::isRisk($order->getShopId()))
            return false;
        $risk_factors = self::getRisk($order);
        if ($risk_factors === false) {
            Debug::sendDebugInfo('getRisk', "Shops is empty (order #{$order->getId()})");
            self::changeStatus($order->getId(), Order::PAUSED, $user_id, 'Error with shops');
            return true;
        }
        if (!empty($risk_factors)) {
            self::changeStatus($order->getId(), Order::PENDING_APPROVAL, $user_id, 'Pending approval by Shopify fraud risk assessment. ' . implode('.', $risk_factors));
            SupplierNotifications::addNotification($order->getSupplierId(), SupplierNotifications::PENDING_APPROVAL, [
                'id' => $order->getId(),
                'ref' => $order->getRef(),
                'ref2' => $order->getRef2(),
                'approval' => time(),
            ]);
            return true;
        }
        return false;
    }

    private static function prepareOrder(Order $order, $SKUs, $largeSKUs, $user_id, $warehouse_virtual)
    {
        if ($order->getStatus() < Order::NEW_NUM)
            return;
        if ($warehouse_virtual && ($order->backorder() || $order->allocated()))
            return;
        if ($order->new())
            self::changeStatus($order->getId(), Order::ALLOCATING_STOCK, $user_id);
        $toApproved = $order->pendingApproval();
        if (!$warehouse_virtual) {
            $SKU_objs = [];
            foreach ($SKUs as $SKU_id => $qty)
                $SKU_objs[$SKU_id] = SKUs::getById($SKU_id);
            $large = SKULocations::isLarge($order->getWarehouseId(), $SKUs, $largeSKUs);
            if ($order->allocatingStock() || $order->backorder()) {
//                $backorder = self::checkSKUsConfig($order, $SKUs, $user_id);
//                if (!empty($backorder)) {
//                    if (!$order->backorder())
//                        self::changeStatus($order->getId(), Order::BACKORDER, $user_id);
//                    return;
//                }
                $status = self::preReserved($order, $SKUs, $SKU_objs, $large || $toApproved);
                if ($status === null)
                    return;
                if ($status) {
                    self::changeStatus($order->getId(), $status, $user_id);
                    return;
                }
            }
        }
        if ($order->allocatingStock() || $order->backorder()) {
            if (self::setPendingApproval($order, $user_id))
                return;
            if (self::setRiskShopify($order, $user_id))
                return;
        }
        if ($warehouse_virtual) {
            if ($order->allocatingStock() || $order->approved()) {
                try {
                    $processor = Warehouses::getVirtualProcessor($order->getWarehouseId(), $order->getSupplierId());
                    if (empty($processor))
                        throw new VirtualException('Supplier is absent for warehouse');
                    $result = $processor->createOrder($order->toArrayVirtual(), OrderSKUs::getSKUsByOrder($order->getId()));
                    if ($result != true)
                        throw new VirtualException($result);
                } catch (Exception $e) {
                    VirtualNotification::sendErrorToTelegram('prepareOrder', "Order #{$order->getId()}: {$e->getMessage()}", Notifier::GROUP_VIRTUAL);
                    if (!$order->errorVirtual())
                        self::changeStatus($order->getId(), Order::ERROR_VIRTUAL, $user_id, $e->getMessage());
                    return;
                }
                self::changeStatus($order->getId(), Order::ALLOCATED, $user_id);
            }
            return;
        }

        $locs_type = $large ? [1 => Location::PF, 0 => Location::ST] : [0 => Location::PF];
        if (($large && $order->backorder()) || $order->approved() || $order->waitingForReplen()) {
            $skip_cancel = SKULocations::getExistOrderSKUsInCart($order->getId(), array_keys($SKUs));
            if ($skip_cancel === false && $order->waitingForReplen()) {
                $candidate_for_replane = SKULocations::getCandidateForReplane($order->getId(), array_keys($SKUs));
                $locs = SKULocations::getLocations4ReservedMass($order->getWarehouseId(), array_keys($candidate_for_replane));
                $free_in_PF = [];
                foreach ($locs as $loc) {
                    if ($loc['type_id'] === Location::PF)
                        $free_in_PF[$loc['SKU_id']] = $loc['quantity'] + ($free_in_PF[$loc['SKU_id']] ?? 0);
                }
                if (empty($free_in_PF) && !empty($candidate_for_replane))
                    return '';
                $skip_cancel = empty($candidate_for_replane);
            }
            if ($skip_cancel === false) {
                foreach ($SKU_objs as $SKU) {
                    if (!SKULocations::cancelOrderReserve($SKU, $order))
                        return;
                }
                $status = self::preReserved($order, $SKUs, $SKU_objs, $large || $toApproved);
                if ($status === null)
                    return;
                if ($status) {
                    self::changeStatus($order->getId(), $status, $user_id);
                    return;
                }
            }
        }
        foreach ($SKU_objs as $SKU)
            if (SKULocations::moveToReserveSKU($order->getWarehouseId(), $order->getId(), $SKU, $locs_type) === false)
                return;
        if ($order->allocatingStock())
            self::changeStatus($order->getId(), Order::ALLOCATED, $user_id);
        if ($order->allocated() || $order->waitingForReplen() || $order->backorder() || $order->approved()) {
            if ($large) {
                if (!$order->approved())
                    foreach ($SKUs as $SKU_id => $qty) {
                        $q = SKULocations::prepare4PickingSKU($order->getWarehouseId(), $order->getId(), $SKU_objs[$SKU_id], $qty);
                        if ($q === false || $q > $qty) {
                            Debug::sendDebugInfo('prepareOrder', "SKU #$SKU_id is not prerared for large picking (order #{$order->getId()})");
                            if (!$order->error())
                                self::changeStatus($order->getId(), Order::ERROR, $user_id);
                            return;
                        }
                        if ($q < $qty) {
                            if (!$order->backorder())
                                self::changeStatus($order->getId(), Order::BACKORDER, $user_id);
                            return;
                        }
                    }
                if (!$order->waitingForLarge())
                    self::changeStatus($order->getId(), Order::WAITING_FOR_LARGE, $user_id);
                return;
            }
            $locs = self::isReplenishV2($order->getWarehouseId(), $order, $SKUs);
            if ($locs === true) {
                if (!$order->waitingForReplen())
                    self::changeStatus($order->getId(), Order::WAITING_FOR_REPLEN, $user_id);
                return;
            }
            if ($locs === null) {
                Debug::sendDebugInfo('prepareOrder', "SKUs is not replenish in full for order #{$order->getId()}");
                if (!$order->error())
                    self::changeStatus($order->getId(), Order::ERROR, $user_id);
                return;
            }
        }
        if (count($SKUs) == 1) {
            $SKU_id = array_keys($SKUs)[0];
            if ($SKUs[$SKU_id] == 1) {
                $q = SKULocations::prepare4PickingSKU($order->getWarehouseId(), $order->getId(), $SKU_objs[$SKU_id], 1, false);
                if ($q === false || $q > $qty) {
                    Debug::sendDebugInfo('prepareOrder', "SKU #$SKU_id is not prerared for single picking (order #{$order->getId()})");
                    if (!$order->error())
                        self::changeStatus($order->getId(), Order::ERROR, $user_id);
                    return;
                }
                if ($q < 1) {
                    if (!$order->backorder())
                        self::changeStatus($order->getId(), Order::BACKORDER, $user_id);
                    return;
                }
                if (!$order->waitingForSingle())
                    self::changeStatus($order->getId(), Order::WAITING_FOR_SINGLE, $user_id);
                return;
            }
        }
        $all_SKUs = true;
        foreach ($SKUs as $SKU_id => $qty) {
            $q = SKULocations::prepare4PickingSKU($order->getWarehouseId(), $order->getId(), $SKU_objs[$SKU_id], $qty, false);
            if ($q === false || $q > $qty) {
                Debug::sendDebugInfo('prepareOrder', "SKU #$SKU_id is not prerared for multi picking (order #{$order->getId()})");
                if (!$order->error())
                    self::changeStatus($order->getId(), Order::ERROR, $user_id);
                return;
            }
            if ($q < $qty) {
                if (!$order->backorder())
                    self::changeStatus($order->getId(), Order::BACKORDER, $user_id);
                return;
            }
        }
        if ($all_SKUs)
            if (!$order->waitingForOrderBin())
                self::changeStatus($order->getId(), Order::WAITING_FOR_ORDER_BIN, $user_id);
    }

    public static function createOrder($order_data, $files, $SKUs, $add_func = null, $params = null)
    {
        $success = [];
        if (count($SKUs) == 0)
            return [false, ['order' => ['List of SKU is empty']], null, [[], []]];
//        $order_price = null;
//        if (isset($order_data['order_price'])) {
//            $order_price = $order_data['order_price'];
//            unset($order_data['order_price']);
//        }
        $order_data['ref'] = trim($order_data['ref']);
        if (mb_ereg('\s+', $order_data['ref']))
            return [false, ['order' => ['Spaces not allowed in customer ref']], null, [[], []]];
        $fields_to_save = array_keys($order_data);
        if (!is_null(Orders::getByRef($order_data['ref'], $order_data['supplier_id'])))
            return [false, ['order' => ['Reference not unique']], null, [[], []]];
        $order = new Order($order_data);
        $errors = Orders::validate($order);
        if (!empty($errors))
            return [false, ['order' => Arr::column($errors, 'message')], null, [[], []]];
        $order_config = [];
        $num = 0;
        $mysql = new SafeMysql();
        while (true) {
            $mysql->beginTransaction();
            try {
                $order->save($fields_to_save);
                foreach ($order_data['config'] ?? [] as $name => $value) {
                    if ($name === OrdersConfig::TYPE_ORDER_PRINTED_MATTER)
                        $order_config[] = OrdersConfig::orderPrintedMatter($order->getId(), (int)$value);
                    elseif ($name === OrdersConfig::TYPE_ORDER_EORI_NUMBER && !empty(trim($value)))
                        $order_config[] = OrdersConfig::orderEoriNumber($order->getId(), trim($value));
                    elseif ($name === OrdersConfig::TYPE_ORDER_VAT_NUMBER && !empty(trim($value)))
                        $order_config[] = OrdersConfig::orderVatNumber($order->getId(), trim($value));
                    elseif ($name === OrdersConfig::TYPE_ORDER_IOSS_NUMBER && !empty(trim($value)))
                        $order_config[] = OrdersConfig::orderIossNumber($order->getId(), trim($value));
                    elseif ($name === OrdersConfig::TYPE_ORDER_TAX_ID && !empty(trim($value)))
                        $order_config[] = OrdersConfig::orderTaxId($order->getId(), trim($value));
                }
                OrdersConfig::saveConfigs($order_config);

//                $delivery = Deliveries::getById($order->getDeliveryId());
//                // https://trello.com/c/L9PU8mXA/598-all-orders-created-in-wms-for-destination-country-russian-federation-should-go-in-to-error-status
//                if (strtolower($delivery->getCountryId()) === 'ru') {
//                    Channels::sendMessageToTelegram("Order #{$order->getId()}", 'Order from Russia');
//                    OrdersModel::changeStatus($order->getId(), Order::ERROR, $order->getUserId());
//                    $mysql->commit();
//                    return [false, ['order' => ['Order from russia']], $order, (empty($res) || count($res[1]) == 1) ? $SKUs : [$SKUs, $res[1][1]]];
//                }
//                // -------------------------------------------------------
                if (!empty($files)) {
                    $fileRes = OrderDocs::setFilePDF($order, new File($files), $order->getUserId());
                    if (isset($fileRes[0]) && !$fileRes[0]) {
                        $mysql->rollback();
                        return [false, ['order' => [$fileRes[1]]], $order, [[], []]];
                    }
                } elseif (!empty($order_data[QueueEntity::TYPE_PDF_FROM_SFTP_TO_ORDER])) {
                    Queues::insertOne((new QueueEntity())->setType(QueueEntity::TYPE_PDF_FROM_SFTP_TO_ORDER)->setUserId($order->getUserId())
                        ->setWarehouseId($order->getWarehouseId())->setStatus(QueueEntity::STATUS_NEW)->setJsonData(json_encode(['order_id' => $order->getId()])));
                }
                $res = [];
                if (!is_null($add_func)) {
                    /** @var string $add_func */
                    $res = $add_func($order, $SKUs, $params);
                    if (!$res[0]) {
                        $mysql->rollback();
                        return [$res[0], $res[1], $order, [[], []]];
                    }
                    $SKUs = $res[1][0];
                }
                if (!Substitutions::applySubstitutions($order, $SKUs)) {
                    $mysql->rollback();
                    return [false, ['order' => ['Substitutions are wrong'], $order, [[], []]]];
                }
                list($add, $ids, $prices) = OrderSKUs::add($SKUs, $order->getId());
                foreach ($SKUs as $SKU_id => $qty)
                    if (is_array($qty))
                        $SKUs[$SKU_id] = $qty['qty'];
                if (!empty($ids))
                    foreach ($ids as $SKU)
                        if ($SKU['state'] == 0) {
                            OrderHistories::lockedSKUs($order->getId(), $SKU['id'], $SKU['barcode'], $order->getUserId());
                            Channels::sendMessageToTelegram("Order #{$order->getId()}", "SKU {$SKU['barcode']} (#{$SKU['id']}) is locked");
                        }
                if (!$add) {
                    OrderHistories::lockedAllSKU($order->getId(), $order->getUserId());
                    Channels::sendMessageToTelegram("Order #{$order->getId()}", 'All SKUs is locked or absent');
                    OrdersModel::changeStatus($order->getId(), Order::ERROR, $order->getUserId());
                    $mysql->commit();
                    return [false, ['order' => ['All SKUs is locked or absent']], $order, (empty($res) || count($res[1]) == 1) ? $SKUs : [$SKUs, $res[1][1]]];
                }
                $success[] = 'created';
                if (!empty($ids))
                    foreach ($ids as $SKU) {
                        $success[] = "SKU \"{$SKU['barcode']}\" (#{$SKU['id']}) is " . ($SKU['state'] == 0 ? 'locked' : 'skipped');
                        unset($SKUs[$SKU['id']]);
                    }
                OrderHistories::createOrder($order->getId(), $order->getUserId());
                if (!empty($order_data['actual_user_login']))
                    OrderHistories::actualUser($order->getId(), $order_data['actual_user_login']);
            } catch (Exception $e) {
                $mysql->rollback();
                if ($e instanceof DBException && $e->isDeadlock($num++)) {
                    Debug::sendDebugInfo('Create order!', "Error is fixed! {$e->getMessage()}");
                    continue;
                }
                Debug::sendDebugInfo('Create order confirmation', var_export([$e->getCode(), $e->getMessage()], 1));
                return [false, ['order' => ['Operation is not complete']], $order, (empty($res) || count($res[1]) == 1) ? $SKUs : [$SKUs, $res[1][1]]];
            }
            $mysql->commit();
            break;
        }
        return [true, ['order' => $success], $order, (empty($res) || count($res[1]) == 1) ? $SKUs : [$SKUs, $res[1][1]]];
    }

    /** @throws Exception */
    public static function creationOrder(Order $order, $qtys, $user_id, bool $skip_prepare_order = false)
    {
//        Queue::addJobToSetInventoryLevel(['order_id' => $order->getId()]);
        ShopInventoryQueues::addOrder($order->getId());
        Queue::addJobToUpdateOrderSearch(['order_id' => $order->getId()]);
        RetainmeOrders::addOrder($order);
        if (self::isSetScheduled($order)) {
            Orders::changeStatus($order->getId(), Order::SCHEDULED, $user_id);
            return;
        }
//        $largeSKUs = SKUPreffLocs::getSKUs4LargePick($order->getWarehouseId());
//        $warehouse_virtual = Warehouses::isVirtual($order->getWarehouseId());
//        if ($skip_prepare_order)
//            return;
//        self::defendingOrder($order->getId(), function () use ($warehouse_virtual, $user_id, $largeSKUs, $qtys, $order) {
//            self::prepareOrder($order, $qtys, $largeSKUs, $user_id, $warehouse_virtual);
//        });
    }

    public static function updateOrders($warehouse_id)
    {
        $orders = OrdersModel::getWaitingReplen($warehouse_id);
        $largeSKUs = SKUPreffLocs::getSKUs4LargePick($warehouse_id);
        $warehouse_virtual = Warehouses::isVirtual($warehouse_id);
        foreach ($orders as $order_id => $SKUs) {
            $order = Orders::getById($order_id);
            if (in_array($order->getStatus(), Order::$allowed_update_statuses)) {
                self::defendingOrder($order->getId(), function ()
                use ($order, $SKUs, $largeSKUs, $warehouse_virtual) {
                    self::prepareOrder($order, $SKUs, $largeSKUs, -1, $warehouse_virtual);
                });
            }
        }
    }

    private static function defendingOrder($order_id, \Closure $handle, $reporting = true)
    {
        $defends = (new Defends())->setType(Defends::TYPE_ORDERS)->setKey((string)$order_id);
        if ($defends->lockDate() !== Defends::LOCK_NOT_UPDATE) {
            try {
                return $handle();
            } finally {
                $defends->unLock();
            }
        } else {
            if ($reporting)
                Debug::sendDebugInfo('defendingOrder', 'Trying to get defending order #' . $order_id, Debug::TEST);
            return false;
        }
    }

//    private static function checkSKUsConfig(Order $order, $SKUs, $user_id)
//    {
//        $backorder = [];
//        foreach ($SKUs as $SKU_id => $qty) {
//            if (!SKUPreffLocs::isMeasure($order->getWarehouseId(), $SKU_id)) {
//                OrderHistories::backOrderMeasure($order->getId(), $SKU_id, $user_id);
//                $backorder[] = $SKU_id;
//            } elseif (SKUPreffLocs::isUnassigned($order->getWarehouseId(), $SKU_id)) {
//                OrderHistories::backOrderUnassigned($order->getId(), $SKU_id, $user_id);
//                $backorder[] = $SKU_id;
//            }
//        }
//        return $backorder;
//    }

    private static function preReserved($order, $SKUs, $SKU_objs, $large): ?string
    {
        $pre_reserved = SKULocations::preReserveSKUByRules($order, $SKUs, $SKU_objs, $large);
        if ($pre_reserved === null)
            return Order::ERROR;
        if ($pre_reserved === false)
            return Order::BACKORDER;
        foreach ($SKUs as $SKU_id => $qty)
            if (SKULocations::moveToReserveSKU($order->getWarehouseId(), $order->getId(), $SKU_objs[$SKU_id]) === false)
                return null;
        $pre_reserved_candidate = SKULocations::getCandidateForReserve((int)$order->getId(), $SKUs);
        if ($pre_reserved_candidate === null)
            return Order::ERROR;
        if (!empty($pre_reserved_candidate))
            return Order::ERROR;
        return '';
    }

    private static function isReplenishV2(int $warehouse_id, Order $order, array $SKUs): ?bool
    {
        $order_id = (int)$order->getId();
        $SKU_ids = array_map('intval', array_keys($SKUs));
        $types = [Location::PF, Location::ST, Location::CA, Location::US];
        $sql = "
        SELECT sl.SKU_id, sl.location_id, l.type_id, sl.order_id, sl.type, sl.reason, sl.quantity, sl.batch_id
        FROM SKU_locations sl
            JOIN locations l ON sl.location_id = l.id AND l.type_id IN (:types) AND l.warehouse = :warehouse_id
        WHERE sl.type = :type AND sl.SKU_id IN (:SKU_ids) AND sl.order_id = :order_id";
        $reserved = (new SafeMysql())->fetchAll($sql, ['types' => $types, 'type' => 1, 'warehouse_id' => $warehouse_id, 'SKU_ids' => $SKU_ids, 'order_id' => $order_id]);
        if (empty($reserved))
            return true;
        $reserved_SKUs = [];
        foreach ($reserved as $item) {
            $reserved_SKUs[(int)$item['SKU_id']][] = $item;
            //Если есть полный резерв в ST локации значит это большой заказ и он не должен попадать в isReplenish
            if ($item['type_id'] === Location::ST)
                return null;
        }
        foreach ($SKU_ids as $SKU_id) {
            $reserved_qty = array_sum(array_column($reserved_SKUs[$SKU_id] ?? [], 'quantity'));
            //Если зарезервированное не достаточное количество товаров
            if ((int)$reserved_qty !== (int)$SKUs[$SKU_id])
                return true;
        }
        return false;
    }

    private static function isReplenish($warehouse_id, $order_id, $SKUs, $SKU_objs)
    {
        $order_id = (int)$order_id;
        $SKU_ids = array_keys($SKUs);
        $types = array_merge(Location::STORAGE_TYPES, Location::CART_TYPES);
        $sql = "SELECT sl.SKU_id, sl.location_id, l.type_id, sl.order_id, sl.type, sl.reason, sl.quantity, sl.batch_id
                FROM SKU_locations sl
                    JOIN locations l ON sl.location_id = l.id AND l.type_id IN (:types) AND l.warehouse = :warehouse_id
                WHERE sl.type = :type AND
                      sl.SKU_id IN (:SKU_ids) AND
                      (sl.order_id = :order_id OR
                       (sl.order_id IS NULL AND POSITION(:reason_prefix IN sl.reason) = 1 AND SUBSTRING_INDEX(sl.reason, '_', -1) = :order_id_str) OR
                       (sl.order_id IS NULL AND sl.reason = :reason_2 AND l.type_id = :type_pf))
                ORDER BY sl.SKU_id, l.type_id, sl.batch_id";
        $SKUs_check = (new SafeMysql())->fetchAll($sql, [
            'types' => $types,
            'warehouse_id' => $warehouse_id,
            'type' => 1,
            'SKU_ids' => $SKU_ids,
            'order_id' => $order_id,
            'reason_prefix' => '2_',
            'order_id_str' => (string)$order_id,
            'reason_2' => '2',
            'type_pf' => Location::PF
        ]);
        if (empty($SKUs_check))
            return true;
        $res = [];
        $SKUs_res = [];
        foreach ($SKUs_check as $SKU) {
            $SKUs_res[$SKU['SKU_id']][$SKU['type_id']][$SKU['location_id']][] = array_slice($SKU, 3);
            if (empty($res[$SKU['SKU_id']]))
                $res[$SKU['SKU_id']] = $SKU['quantity'];
            else
                $res[$SKU['SKU_id']] += $SKU['quantity'];
        }
        if (count($res) != count($SKUs))
            return true;
        foreach ($res as $SKU_id => $qty)
            if ($qty < $SKUs[$SKU_id])
                return true;
        $replenish = false;
        foreach ($SKUs_res as $SKU_id => $sl) {
            if (empty($sl[Location::PF]))
                return true;
            $q = 0;
            $PFs = [];
            foreach ($sl[Location::PF] as $location_id => $data)
                foreach ($data as $datum) {
                    if ($datum['order_id'] == $order_id)
                        $q += $datum['quantity'];
                    elseif (empty($datum['order_id']) && $datum['reason'] == 2)
                        $PFs[$location_id] = $datum;
                }
            if (!empty($sl[Location::CA]))
                foreach ($sl[Location::CA] as $data)
                    foreach ($data as $datum)
                        $q += $datum['quantity'];
            if (!empty($sl[Location::US]))
                foreach ($sl[Location::US] as $data)
                    foreach ($data as $datum)
                        $q += $datum['quantity'];
            if ($q == $SKUs[$SKU_id])
                continue;
            $q = $SKUs[$SKU_id] - $q;
            if (!empty($sl[Location::ST]))
                foreach ($sl[Location::ST] as $loc_from_id => $data) {
                    foreach ($data as $datum) {
                        if ($datum['order_id'] == $order_id)
                            return false;
                        if (empty($datum['order_id']) && $datum['reason'] != "2_$order_id")
                            continue;
                        $qty = $datum['quantity'];
                        foreach ($PFs as $location_id => $PF) {
                            $res = SKULocations::fixedSTinPF($warehouse_id, $SKU_objs[$SKU_id], $loc_from_id, $location_id, min($qty, $PF['quantity']), $order_id);
                            if (!$res[0])
                                return [$res[0], "Order #$order_id: " . $res[1]];
                            $qty -= $res[1];
                            $q -= $res[1];
                            if (!$qty)
                                break;
                            if (!$q)
                                break 3;
                        }
                        if (!$q)
                            break 2;
                    }
                }
            if ($q)
                $replenish = true;
        }
        if ($replenish)
            return true;
        return 1;
    }

    public static function handleDeliveries(&$records_raw)
    {
        array_walk($records_raw, function (&$order) {
            if (empty($order['exist_delivery']) && !Order::isDespatched($order['status'])) {
                $delivery = DeliveryHistories::restoreDeliveryById($order['delivery_id'], date(WithDate::DATE_SQL_FORMAT));
                if (!empty($delivery))
                    $order = $delivery + $order;
            }
        });
        $order_ids = Arr::column(array_filter($records_raw, function ($order) {
            return Order::isDespatched($order['status']);
        }), 'order_id');
        if (empty($order_ids))
            return;
        $delivery_despatched = (new SafeMysql())->fetchAssocWithKey("
            SELECT order_id, delivery
            FROM orders_original_despatched
            WHERE order_id IN (:order_ids) AND (delivery IS NOT NULL OR delivery <> '')
        ",  ['order_ids' => $order_ids], 'order_id');
        if (!empty($delivery_despatched))
            foreach ($records_raw as &$order)
                if (!empty($delivery_despatched[$order['order_id']])) {
                    $delivery_data = $delivery_despatched[$order['order_id']];
                    if (!empty($delivery_data['delivery'])) {
                        $delivery = json_decode($delivery_data['delivery'], true);
                        if (!empty($delivery)) {
                            $delivery['delivery_id'] = array_shift($delivery);
                            $order = $delivery + $order;
                        }
                    }
                }
    }

    public static function getAll(array $filters)
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("SELECT SQL_CALC_FOUND_ROWS o.*,
            o.id as order_id, d.*, IFNULL(d.id, 0) as exist_delivery,
            o.billing_charge_preparation + o.billing_charge_shipping + o.billing_charge_additional charge,
            o.billing_charge_currency, o.supplier_id, sup.name name_sup, o.shipping_services_id,
            (SELECT COUNT(*) FROM ASNs WHERE order_id = o.id) nums,
            IF(o.priority > 0, true, false) as priority, IFNULL(ss.special, 0) special,
            ocpm.value_int printed_matter
            FROM orders o");
        $query->addJoin("LEFT JOIN deliveries d ON o.delivery_id = d.id");
        $query->addJoin("JOIN suppliers sup ON o.supplier_id = sup.id");
        $query->addJoin("LEFT JOIN shipping_services ss ON o.shipping_services_id = ss.id");
        $query->addJoin("LEFT JOIN orders_config ocpm ON o.id = ocpm.order_id AND ocpm.type = '" . OrdersConfig::TYPE_ORDER_PRINTED_MATTER . "'");
        self::applySearchFilters($query, $filters);
        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);
        $query->setOrderBy("o.id DESC");
        $query->setLimit("{$offset}, " . self::LIMIT_PER_PAGE);
        $records_raw = (new SafeMysql())->fetchAll($query->build(), $filters);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        self::handleDeliveries($records_raw);
        return ['records' => $records_raw, 'total' => (int)$total, 'current_page' => $current_page];
    }

    public static function getTotalCountReportCSV(array $filters): int
    {
        return (int)self::getAll($filters)['total'];
    }

    public static function getAllAsCSV(Warehouse $warehouse, array $filters = [])
    {
        $query = self::formatReportQuery(self::getSelectQueryForCSV(), $filters);
        $query->setGroupBy("o.id");
        $query->setOrderBy("o.id");
        $query->setLimit('10000');
        $csv = new FormatterCSV;
        $csv->addHeader(self::handleHeadlineCSV($warehouse->isVirtual()));
        $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        while (!empty($raw_result)) {
            self::handleDataToCSV($raw_result, $csv, $warehouse);
            $last_order_id = end($raw_result)['order_id'];
            $query->addWhere('o.id > ' . $last_order_id);
            $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        }
    }

    /** @throws \Exception */
    private static function applySearchFilters(QueryBuilder $query, array &$filters)
    {
        $warehouse = null;
        if (!empty($filters['warehouse_id'])) {
            $warehouse = Warehouses::getById($filters['warehouse_id']);
            $filters['warehouse_id'] = (int)$filters['warehouse_id'];
            $query->addWhere("o.warehouse_id = :warehouse_id");
        }
        if (!empty($filters['global_search']) && strlen(trim($filters['global_search'])) > 2) {
            $textArr = explode(' ', SafeMysql::escapeMatch($filters['global_search']));
            $textArrG = [];
            foreach ($textArr as $word) {
                if (empty($word) || strlen($word) < 3)
                    continue;
                $textArrG[] = '+' . urlencode($word);
            }
            $text_search = implode(' ', $textArrG);
            $order_ids = (new SafeMysql())->fetchArrayOneField("SELECT order_id FROM orders_search
                      WHERE MATCH(data) AGAINST(:text_search IN BOOLEAN MODE)", ['text_search' => $text_search]);
            if (!empty($order_ids)) {
                $query->addWhere("o.id IN (:order_ids)");
                $filters['order_ids'] = $order_ids;
            }
            else {
                //force empty result
                $query->addWhere("o.id = 0");
                return;
            }
        }
        if (!empty($filters['id'])) {
            $filters['id'] = (int)$filters['id'];
            $query->addWhere("o.id = :id");
        }
        $date_types = array_keys(self::dateTypes());
        $date_type_default = $date_types[0];
        $date_type = empty($filters['date_type']) ? $date_type_default : $filters['date_type'];
        $date_type = in_array($date_type, $date_types) ? $date_type : $date_type_default;
        $time_diff = !empty($warehouse) ? Timezones::getDiff($warehouse->getTimezone()) : 0;
        if (!empty($filters['date_from'])) {
            $date_from = $filters['date_from'];
            $date_from .= ' ' . (!empty($filters['time_from']) ? ($filters['time_from'] . ':00') : ' 00:00:00');
            if (!empty($time_diff))
                $date_from = Timezones::getDiff($warehouse->getTimezone(), $date_from, TRUE);
            $filters['date_from'] = $date_from;
            $query->addWhere("DATE_FORMAT(o.{$date_type}, '%Y-%m-%d %H:%i:%s') >= :date_from");
        }
        if (!empty($filters['date_to'])) {
            $date_to = $filters['date_to'];
            $date_to .= ' ' . (!empty($filters['time_to']) ? ($filters['time_to'] . ':00') : ' 23:59:59');
            if (!empty($time_diff))
                $date_to = Timezones::getDiff($warehouse->getTimezone(), $date_to, TRUE);
            $filters['date_to'] = $date_to;
            $query->addWhere("DATE_FORMAT(o.{$date_type}, '%Y-%m-%d %H:%i:%s') <= :date_to");
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] !== 'ret')
                $query->addWhere("o.status = :status");
            else {
                $query->addJoin("LEFT JOIN ASNs a ON a.order_id = o.id");
                $query->addWhere("o.status = " . Order::DESPATCHED_NUM . " AND a.order_id IS NOT NULL");
            }
        }
        if (!empty($filters['special'])) {
            $filters['special'] = (int)$filters['special'];
            $query->addWhere("ss.special = :special");
        }
        if (!empty($filters['supplier_id'])) {
            if (is_array($filters['supplier_id']))
                $query->addWhere("o.supplier_id IN (:supplier_id)");
            else
                $query->addWhere("o.supplier_id = :supplier_id");
        }
        if (!empty($filters['max_item_id'])) {
            $filters['max_item_id'] = (int)$filters['max_item_id'];
            $query->addWhere("o.id < :max_item_id");
        }
        if (!empty($filters['type_create']) && $filters['type_create'] != 'all')
            $query->addWhere("o.type_create = :type_create");
        if (isset($filters['shipping_services_id']) && $filters['shipping_services_id'] !== 'all') {
            $filters['shipping_services_id'] = (int)$filters['shipping_services_id'];
            $query->addWhere("o.shipping_services_id = :shipping_services_id");
        }
        if (!empty($filters['country_id']))
            $query->addWhere("d.country_id = :country_id");
        if (!empty($filters['ref'])) {
            $filters['ref'] = '%' .$filters['ref']. '%';
            $query->addWhere("o.ref LIKE :ref");
        }

    }

    public static function getData4Billing($date_despatched, $supplier_id, $warehouse_id)
    {
        $date_despatched = date('Y-m-d', $date_despatched);
        $sql = "SELECT o.id,
                       o.user_id,
                       o.delivery_id,
                       o.despatched_date,
                       o.ref,
                       o.notes,
                       o.billing_description_preparation,
                       o.billing_charge_preparation,
                       o.billing_description_shipping,
                       o.billing_charge_shipping,
                       o.billing_description_additional,
                       o.billing_charge_additional,
                       o.billing_charge_currency,
                       o.status,
                       o.packing_slip_print,
                       SUM(quantity) qty,
                       s.type_account
                FROM orders o
                    JOIN order_SKUs osk ON o.id = osk.order_id
                    JOIN suppliers s ON s.id = o.supplier_id
                WHERE o.status = :status AND
                      o.supplier_id = :supplier_id AND
                      o.warehouse_id = :warehouse_id AND
                      DATE_FORMAT(o.despatched_date, '%Y-%m-%d') = :date_despatched
                GROUP BY o.id
                ORDER BY o.despatched_date, o.id";
        return (new SafeMysql())->fetchAll($sql, [
            'status' => Order::DESPATCHED_NUM,
            'supplier_id' => (int)$supplier_id,
            'warehouse_id' => $warehouse_id,
            'date_despatched' => $date_despatched
        ]);
    }

    public static function getOrders4BillingVirtual($date_despatched, $warehouse_id)
    {
        $sql = "SELECT o.id, o.create_date, sw.shipping_account_number, s.name, ood.items, ood.delivery, s.default_description_of_goods, sw.address,
                       o.currency, o.type_create, s.product_price, o.shop_id, o.warehouse_id
                FROM orders o
                    JOIN orders_original_despatched ood ON o.id = ood.order_id
                    JOIN suppliers s ON o.supplier_id = s.id
                    LEFT JOIN suppliers_warehouses sw ON o.supplier_id = sw.supplier_id AND sw.warehouse_id = :warehouse_id
                WHERE o.status = :status AND o.warehouse_id = :warehouse_id AND DATE_FORMAT(o.despatched_date, '%Y-%m-%d') = :date_despatched";
        return (new SafeMysql())->fetchAll($sql, [
            'status' => Order::DESPATCHED_NUM,
            'warehouse_id' => (int)$warehouse_id,
            'date_despatched' => date('Y-m-d', $date_despatched)
        ]);
    }

    public static function getOrders4BillingReturns($order_id, $warehouse_id)
    {
        $sql = "SELECT o.id, o.create_date, sw.shipping_account_number, s.name, ood.delivery, s.default_description_of_goods,
                       o.currency, o.type_create, ss.code, o.despatched_date
                FROM orders o
                    JOIN orders_original_despatched ood ON o.id = ood.order_id
                    JOIN suppliers s ON o.supplier_id = s.id
                    LEFT JOIN shipping_services ss ON o.shipping_services_id = ss.id
                    LEFT JOIN suppliers_warehouses sw ON o.supplier_id = sw.supplier_id AND sw.warehouse_id = o.warehouse_id
                WHERE o.status = :status AND o.warehouse_id = :warehouse_id AND o.id = :order_id";
        return (new SafeMysql())->fetchOne($sql, [
            'status' => Order::DESPATCHED_NUM,
            'warehouse_id' => (int)$warehouse_id,
            'order_id' => (int)$order_id
        ]);
    }

    public static function getOrders4Billing($order_ids)
    {
        if (empty($order_ids))
            return [];
        $sql = "SELECT o.id,
                       o.user_id,
                       o.delivery_id,
                       o.despatched_date,
                       o.ref,
                       o.notes,
                       o.billing_description_preparation,
                       o.billing_charge_preparation,
                       o.billing_description_shipping,
                       o.billing_charge_shipping,
                       o.billing_description_additional,
                       o.billing_charge_additional,
                       o.billing_charge_currency,
                       o.status,
                       o.packing_slip_print,
                       SUM(quantity) qty,
                       s.type_account
                FROM orders o
                    JOIN order_SKUs osk ON o.id = osk.order_id
                    JOIN suppliers s ON s.id = o.supplier_id
                WHERE o.status = :status AND o.id IN (:order_ids)
                GROUP BY o.id
                ORDER BY o.despatched_date, o.id";
        return (new SafeMysql())->fetchAll($sql, ['status' => Order::DESPATCHED_NUM, 'order_ids' => $order_ids]);
    }

    public static function getReturns($order_id)
    {
        $sql = "SELECT a.id, a.ref, a.comment
                FROM ASNs a
                    JOIN orders o ON o.id = a.order_id AND o.id = :order_id";
        return (new SafeMysql())->fetchAll($sql, ['order_id' => (int)$order_id]);
    }

    public static function getMaxRefInSupplierChannel($supplierId, $shopId)
    {
        $sql = "SELECT ref last_order_id
                FROM orders
                WHERE id = (SELECT MAX(id)
                            FROM orders
                            WHERE shop_id = :shop_id AND supplier_id = :supplier_id)
        ";
        $result = (new SafeMysql())->fetchScalar($sql, ['shop_id' => (int)$shopId, 'supplier_id' => (int)$supplierId]);
        $cancel_pos = strpos($result, '-' . Order::CANCELLED . '_');
        if ($cancel_pos)
            $result = substr($result, 0, $cancel_pos);
        return $result;
    }

    public static function reinstateOrder(Order $order, int $user_id): string
    {
        $need_SKUs = OrderSKUs::getSKUsByOrder($order->getId());
        $exist_SKUs = SKULocations::getByOrder($order->getId());
        usort($exist_SKUs, function ($a, $b) {
            $w_a = in_array($a['type_id'], [Location::PF, Location::ST]);
            $w_b = in_array($b['type_id'], [Location::PF, Location::ST]);
            return $w_b <=> $w_a;
        });
        $order_id = (int)$order->getId();
        $next_status_num = OrdersConfig::getOrderStatusBeforeQuery($order->getId());
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        if (!OrdersConfig::getOrderStatusBeforeQuery($order->getId())) {
            $mysql->rollback();
            return "Order '{$order->getRef()}' reinstate .";
        }
        foreach ($exist_SKUs as $exist_SKU) {
            $SKU_id = (int)$exist_SKU['SKU_id'];
            $need_qty = (int)($need_SKUs[$SKU_id]['quantity'] ?? 0);
            $exist_qty = (int)$exist_SKU['quantity'];
            $order_qty = min($need_qty, $exist_qty);
            if (!empty($need_SKUs[$SKU_id]['quantity']))
                $need_SKUs[$SKU_id]['quantity'] -= $order_qty;
            if ($order_qty === $exist_qty)
                continue;
            $free_qty = $exist_qty - $order_qty;

            $SKU = SKUs::getById($SKU_id);
            $batch_id = empty($exist_SKU['batch_id']) ? null : (int)$exist_SKU['batch_id'];
            $location_id = (int)$exist_SKU['location_id'];
            $location = Locations::getById($location_id);
            $type = $exist_SKU['type'];
            $reason = $exist_SKU['reason'];
            $order_id_where = $exist_SKU['order_id'] ? " = :order_id" : ' IS NULL ';
            $batch_id_where = $batch_id ? " = :batch_id " : ' IS NULL ';
            //1 Создаем запись с нулевым количеством свободных товаров, если ее не было
            $sql = "INSERT INTO SKU_locations (SKU_id, location_id, quantity, order_id, type, reason, batch_id)
                    SELECT :SKU_id, :location_id, 0, null, :type, :reason, :batch_id 
                    WHERE NOT EXISTS(SELECT *
                        FROM SKU_locations 
                        WHERE SKU_id = :SKU_id
                            AND location_id = :location_id
                            AND order_id is NULL
                            AND type = :type
                            AND reason = :reason
                            AND batch_id $batch_id_where
                            LIMIT 1
                    )";
            $mysql->execute($sql, ['SKU_id' => $SKU_id, 'location_id' => $location_id, 'type' => 1, 'reason' => '2', 'batch_id' => $batch_id]);
            //2 Добавляем свободные товары
            $sql = "UPDATE SKU_locations f
                    SET f.quantity = f.quantity + :quantity
                    WHERE f.SKU_id = :SKU_id
                        AND f.location_id = :location_id
                        AND f.order_id IS NULL
                        AND f.type = :type
                        AND f.reason = :reason
                        AND f.batch_id $batch_id_where";
            $result = (bool)$mysql->execute($sql, ['SKU_id' => $SKU_id, 'location_id' => $location_id, 'type' => 1, 'reason' => '2', 'batch_id' => $batch_id, 'quantity' => $free_qty]);
            if (!$result) {
                $mysql->rollback();
                return "Error reinstate order #" . $order_id . " SKU #" . $SKU_id;
            }
            //3 Удаляем товары с заказа
            $sql = "UPDATE SKU_locations f
                    SET f.quantity = :new_quantity
                    WHERE f.SKU_id = :SKU_id
                        AND f.location_id = :location_id
                        AND f.order_id $order_id_where
                        AND f.type = :type
                        AND f.reason = :reason
                        AND f.batch_id $batch_id_where
                        AND f.quantity = :old_quantity";
            $result = (bool)$mysql->execute($sql, [
                'SKU_id' => $SKU_id,
                'location_id' => $location_id,
                'order_id' => $order_id,
                'type' => $type,
                'reason' => $reason,
                'batch_id' => $batch_id,
                'quantity' => $free_qty,
                'old_quantity' => $exist_qty,
                'new_quantity' => $order_qty,
            ]);
            if (!$result) {
                $mysql->rollback();
                return "Error reinstate quantity order #" . $order_id . " SKU #" . $SKU_id;
            }
            //1.4 Удаляем row если количество товаров ноль
            $sql = "DELETE FROM SKU_locations WHERE SKU_id = :SKU_id AND location_id = :location_id AND quantity = 0";
            $mysql->execute($sql, ['SKU_id' => $SKU_id, 'location_id' => $location_id, 'batch_id' => $batch_id]);
            $sql = "SELECT 1 FROM order_location_temporary WHERE order_id = :order_id AND location_id = :location_id AND SKU_id = :SKU_id AND quantity <> :quantity";
            if ($mysql->fetchScalar($sql, ['order_id' => $order_id, 'location_id' => $location_id, 'SKU_id' => $SKU_id, 'quantity' => $order_qty])) {
                $sql = "UPDATE order_location_temporary SET quantity = :quantity WHERE order_id = :order_id AND location_id = :location_id AND SKU_id = :SKU_id";
                $mysql->execute($sql, ['order_id' => $order_id, 'location_id' => $location_id, 'SKU_id' => $SKU_id, 'quantity' => $order_qty]);
                $sql = "DELETE FROM order_location_temporary WHERE order_id = :order_id AND location_id = :location_id AND SKU_id = :SKU_id AND quantity = 0";
                $mysql->execute($sql, ['order_id' => $order_id, 'location_id' => $location_id, 'SKU_id' => $SKU_id]);
            }
            if (in_array($location->getTypeId(), Location::PICK_AND_PACK_TYPES)) {
                $res = Movements::cancelSKUByOrder($SKU, $free_qty, $location_id, $batch_id, "Reinstate order ({$order->getRef()})", $user_id, $order->getWarehouseId());
                if (!$res[0]) {
                    $mysql->rollback();
                    return $res[1];
                }
            }
            SKUHistories::reinstateOrder($SKU_id, $free_qty, $location_id, $order_id, $user_id, $order->getWarehouseId());
            SKUHistories::updateStock($SKU_id, $order->getWarehouseId());
        }
        OrderHistories::reinstateOrder($order_id, $user_id);
        ShopInventoryQueues::addOrder($order_id);
        if (in_array($next_status_num, [Order::PICKING_NUM, Order::SINGLE_PICKING_NUM, Order::LARGE_PICKING_NUM, Order::LARGE_PICKING_NUM, Order::AWAITING_PACK_NUM]) && OrderSKUs::isCompleted($order_id)) {
            Orders::changeStatus($order->getId(), Order::AWAITING_PACK, $user_id, null, null, false);
        } else
            Orders::changeStatus($order->getId(), Order::getStateName($next_status_num), $user_id);

        $mysql->commit();
        return '';
    }

    public static function cancelOrder(Order $order, $user_id, $to_virtual = true)
    {
        if ($order->cancelled())
            return [false, 'Order has already been cancelled'];
        $status = str_pad($order->getStatus(), 4, '0');
        if ($status >= (Order::DESPATCHED_NUM . '00'))
            return [false, "Status is {$order->getStatusName()}"];
        $warehouse_id = $order->getWarehouseId();
        $warehouse_virtual = Warehouses::isVirtual($warehouse_id);
        if ($warehouse_virtual && $to_virtual && !$order->errorVirtual()) {
            try {
                $processor = Warehouses::getVirtualProcessor($warehouse_id, $order->getSupplierId());
                if (empty($processor))
                    return [false, 'Supplier is not found for warehouse'];
                if (!$processor->cancelOrder($order->getId()))
                    return [false, 'Order can not be cancelled (in virtual)'];
            } catch (Exception $e) {
                return [false, $e->getMessage()];
            }
        }
        $nums = 0;
        while (true) {
            $res = self::defendingOrder($order->getId(), function () use ($order, $user_id, $to_virtual, $warehouse_id) {
                $order_id = $order->getId();
                $num = 0;
                $mysql = new SafeMysql();
                while (true) {
                    $mysql->beginTransaction();
                    try {
                        $movements = SKULocations::cancelOrder($order_id);
                        foreach ($movements as $movement) {
                            $SKU_obj = SKUs::getById($movement['SKU_id']);
                            if (empty($SKU_obj)) {
                                $mysql->rollback();
                                return [false, "SKU #{$movement['SKU_id']} not found"];
                            }
                            $res = Movements::cancelSKUByOrder($SKU_obj, $movement['quantity'], $movement['location_id'], $movement['batch_id'], "Cancel order ({$order->getRef()})", $user_id, $warehouse_id);
                            if (!$res[0]) {
                                $mysql->rollback();
                                return $res;
                            }
                            SKUHistories::cancelOrder($SKU_obj->getId(), $movement['quantity'], $movement['location_id'], $order_id, $user_id, $warehouse_id);
                            SKUHistories::updateStock($SKU_obj->getId(), $warehouse_id);
                        }
                        OrderLocationTemporaries::cancelOrder($order_id);
                        OrderLocations::cancelOrder($order_id);
                        OrdersModel::changeStatus($order->getId(), Order::CANCELLED, $user_id);
                        $parts = explode('-recreated_', $order->getRef());
                        $order->setRef($parts[0] . '-' . Order::CANCELLED . '_' . time());
                        $order->save(['id', 'ref']);
                        OrderHistories::cancelOrder($order_id, $user_id);
                        $mysql->execute("DELETE FROM orders_packages_SKUs
                            WHERE orders_packages_id IN (SELECT op.id FROM orders_packages op WHERE op.order_id = :order_id)", ['order_id' => $order_id]);
                        $mysql->execute("DELETE FROM orders_packages_consumables
                            WHERE order_package_id IN (SELECT op.id FROM orders_packages op WHERE op.order_id = :order_id)", ['order_id' => $order_id]);
                        $mysql->execute("DELETE FROM orders_packages WHERE order_id = :order_id", ['order_id' => $order_id]);
                    } catch (Exception $e) {
                        $mysql->rollback();
                        if ($e instanceof DBException && $e->isDeadlock($num++))
                            continue;
                        Debug::sendDebugInfo('Cancel order confirmation', var_export([$e->getCode(), $e->getMessage()], 1));
                        return [false, 'Operation is not complete'];
                    }
                    $mysql->commit();
                    Queue::addJobToUpdateOrderSearch(['order_id' => $order_id]);
//                    Queue::addJobToSetInventoryLevel(['order_id' => $order_id]);
                    ShopInventoryQueues::addOrder($order_id);
                    return [true];
                }
            }, $nums >= Defends::NUMBER_OF_ATTEMPTS_TO_CANCEL);
            if ($res !== false)
                return $res;
            $nums++;
            if ($nums > Defends::NUMBER_OF_ATTEMPTS_TO_CANCEL)
                return [false, 'Order in processing'];
        }
    }

    public static function isFixed($SKU_id, $quantity, $location_id)
    {
        return (new SafeMysql())->fetchScalar("SELECT 1
                                  FROM movements
                                  WHERE SKU_id = :SKU_id AND
                                        quantity >= :quantity AND
                                        loc_from_id = :location_id AND
                                        type", [
            'SKU_id' => $SKU_id,
            'quantity' => $quantity,
            'location_id' => $location_id
        ]);
    }

    public static function fixedCancelOrder($warehouse_id)
    {
        $types = Location::PICK_AND_PACK_TYPES;
        $movements = (new SafeMysql())->fetchAll("SELECT SKU_locations.*
                                       FROM SKU_locations
                                           JOIN locations ON id = location_id AND warehouse = :warehouse_id AND type_id IN (:types)
                                       WHERE order_id IS NULL", [
            'warehouse_id' => $warehouse_id,
            'types' => $types
        ]);
        foreach ($movements as $movement) {
            $SKU_obj = SKUs::getById($movement['SKU_id']);
            if (empty($SKU_obj)) {
                echo "SKU #{$movement['SKU_id']} not found\n";
                continue;
            }
            if (!self::isFixed($SKU_obj->getId(), $movement['quantity'], $movement['location_id'])) {
                $mysql = new SafeMysql();
                $mysql->beginTransaction();
                $res = Movements::cancelSKUByOrder($SKU_obj, $movement['quantity'], $movement['location_id'], $movement['batch_id'], 'Fixed orders with cancelled status', 0, $warehouse_id);
                if (!$res[0]) {
                    $mysql->rollback();
                    echo "{$res[1]}\n";
                    continue;
                }
                SKUHistories::cancelOrder($SKU_obj->getId(), $movement['quantity'], $movement['location_id'], $movement['order_id'], 0, $warehouse_id);
                $mysql->commit();
            }
        }
    }

    public static function priority(Order $order, User $user)
    {
        self::setPriority($order, $user->getId());
    }

    public static function approve(Order $order, User $user)
    {
        self::changeStatus($order->getId(), Order::APPROVED, $user->getId());
    }

    public static function recreate(Order $order, User $user)
    {
        $parts = explode('-' . Order::CANCELLED . '_', $order->getRef());
//        $order->setRef($parts[0] . "-recreated_" . time());
        $order->setRef($parts[0]);
        $order->save(['id', 'ref']);
//        // https://trello.com/c/L9PU8mXA/598-all-orders-created-in-wms-for-destination-country-russian-federation-should-go-in-to-error-status
//        $delivery = Deliveries::getById($order->getDeliveryId());
//        if (strtolower($delivery->getCountryId()) === 'ru') {
//            Channels::sendMessageToTelegram("Order #{$order->getId()}", 'Order from Russia');
//            OrdersModel::changeStatus($order->getId(), Order::ERROR, $order->getUserId());
//            return;
//        }
//        // -------------------------------------------------------
        $failDocs = OrderDocs::checkAwaitingDocs($order->getId());
        OrderHistories::recreateOrder($order->getId(), $user->getId());
        if (!$failDocs) {
            self::changeStatus($order->getId(), Order::NEW, $user->getId());
            $qties = OrderSKUs::getSKUsQuantitiesByOrder($order->getId());
            self::creationOrder($order, $qties, $user->getId());
        } else
            self::changeStatus($order->getId(), Order::AWAITING_UPLOAD_DOCUMENTS, $user->getId());
    }

    public static function getOrders4Fixed($warehouse_id, $status, $empty_loc = '')
    {
        if ($empty_loc !== 'NOT')
            $empty_loc = '';
        $sql = "SELECT o.id
                FROM orders o
                    LEFT JOIN order_location ol ON o.id = ol.order_id
                WHERE o.status = :status AND ol.location_id IS $empty_loc NULL AND o.warehouse_id = :warehouse_id";
        return (new SafeMysql())->fetchArrayOneField($sql, ['status' => (int)$status, 'warehouse_id' => (int)$warehouse_id]);
    }

    public static function isBackorderDebug($order_id, $SKUs)
    {
        foreach ($SKUs as $SKU_id => $qty) {
            $q = (int)SKULocations::getReservedQuantity($order_id, $SKU_id);
            $q0 = (int)SKUPreffLocs::getFreePlace4SKU($SKU_id);
            echo "\t$SKU_id: $qty = $q + $q0\n";
        }
    }

    private static function shortShipHistory($order_id, $order_new_id, $SKU_id, $quantity, $user_id, $warehouse_id)
    {
        $move_id = Movements_::setShortShip($order_id, $order_new_id, $SKU_id, $quantity, $user_id);
        SKUHistories::changeSKU($order_id, $order_new_id, $SKU_id, $quantity, $move_id, $user_id, $warehouse_id);
        OrderHistories::addShortShipSKU($order_new_id, $order_id, $SKU_id, $quantity, $user_id);
    }

    public static function shortShipOrder(Order $order, $SKUs, User $user)
    {
        $res = [];
        foreach ($SKUs as $SKU)
            if (empty($res[$SKU['SKU_id']]))
                $res[$SKU['SKU_id']] = $SKU['quantity'];
            else
                $res[$SKU['SKU_id']] += $SKU['quantity'];
        $SKUs = $res;
        $SKUs_short = OrderSKUs::getSKUsByOrder($order->getId());
        foreach ($SKUs_short as $SKU_id => $SKU)
            if (!empty($SKUs[$SKU_id])) {
                if ($SKU['quantity'] > $SKUs[$SKU_id])
                    $SKUs_short[$SKU_id]['quantity'] -= $SKUs[$SKU_id];
                else
                    unset($SKUs_short[$SKU_id]);
            }
        if (empty($SKUs_short))
            return false;
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $order_new = new Order($order->toArray());
        $order_new->setId(null);
        $order_new->setTypeCreate(Sources::SOURCE_SHORT_SHIP);
        $order_new->setShopId(0);
        $order_new->setRef($order->getRef() . '-shortship_' . time());
        $fields_to_save = array_keys($order_new->toArray());
        unset($fields_to_save[array_search('id', $fields_to_save)]);
        $order_new->save($fields_to_save);
        OrderHistories::shortShipOrder($order->getId(), $order_new->getId(), $user->getId(), 'to');
        foreach ($SKUs as $SKU_id => $quantity) {
            $qty = OrderSKUs::getQuantity($order->getId(), $SKU_id);
            if ($quantity < $qty) {
                $mysql->execute("UPDATE order_SKUs
                                SET quantity = :quantity
                                WHERE order_id = :order_id AND SKU_id = :SKU_id", [
                    'quantity' => $quantity,
                    'order_id' => $order->getId(),
                    'SKU_id' => $SKU_id,
                ]);
                OrderHistories::shortShipSKU($order->getId(), $SKU_id, $qty, $quantity, $user->getId());
                $mysql->execute("INSERT INTO order_SKUs (order_id, SKU_id, quantity, price)
                                VALUES (:order_id, :SKU_id, :qty, :price)", [
                    'order_id' => $order_new->getId(),
                    'SKU_id' => $SKU_id,
                    'qty' => ($qty - $quantity),
                    'price' => $SKUs_short[$SKU_id]['price'],
                ]);
                self::shortShipHistory($order->getId(), $order_new->getId(), $SKU_id, $qty - $quantity, $user->getId(), $order->getWarehouseId());
                unset($SKUs_short[$SKU_id]);
            }
        }
        OrderHistories::shortShipOrder($order_new->getId(), $order->getId(), $user->getId(), 'from');
        foreach ($SKUs_short as $SKU) {
            $mysql->execute("UPDATE order_SKUs
                            SET order_id = :new_order_id
                            WHERE order_id = :old_order_id AND SKU_id = :SKU_id AND quantity = :quantity", [
                'new_order_id' => $order_new->getId(),
                'old_order_id' => $order->getId(),
                'SKU_id' => $SKU['SKU_id'],
                'quantity' => $SKU['quantity'],
            ]);
            self::shortShipHistory($order->getId(), $order_new->getId(), $SKU['SKU_id'], $SKU['quantity'], $user->getId(), $order->getWarehouseId());
        }
        OrdersModel::changeStatus($order->getId(), Order::ALLOCATED, $user->getId());
        $mysql->commit();
        return true;
    }

    public static function resolveAggregateParams(array $params, array $fieldsToResolve): array
    {
        $errors = Dashboard::validate($params);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                if (!empty($params[$error['field']]) && isset($fieldsToResolve[$error['field']])) {
                    $params[$error['field']] = $fieldsToResolve[$error['field']];
                }
            }
        }
        return $params;
    }

    public static function isAccess(Order $order, User $user, $supplier_ids)
    {
        if (empty($order) ||
            $order->getWarehouseId() != $user->getWarehouseId() ||
            $user->isRoleWorker())
            return false;
        if ($user->hasManagerAccess() ||
            ($user->isClientSide() && in_array($order->getSupplierId(), $supplier_ids)))
            return true;
        return false;
    }

    /**
     * @param $select
     * @param array $filters
     * @return QueryBuilder
     */
    protected static function formatReportQuery($select, array &$filters): QueryBuilder
    {
        $query = new QueryBuilder();
        $query->setSelectFrom($select);
        $query->addJoin("LEFT JOIN suppliers s ON o.supplier_id = s.id");
        if (!empty($filters['country_id']))
            $query->addJoin("LEFT JOIN deliveries d ON o.delivery_id = d.id");
        self::applySearchFilters($query, $filters);
        return $query;
    }

    /**
     * @throws Exception
     */
    public static function handleReportCSV($user_job_id, $max_item_id = 0): array
    {
        /**
         * @var FileJob $fileJob
         * @var UserJob $userJob
         * @var FormatterCSV $csv
         */
        try {
            list($userJob, $fileJob, $csv) = self::initReportCSV($user_job_id);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        $addition = json_decode($userJob->getAddition(), true);
        $filters = (!empty($max_item_id) ? ['max_item_id' => $max_item_id] : []) + ($addition['filters'] ?? []);
        $query = self::formatReportQuery(self::getSelectQueryForCSV(), $filters);
        $query->setGroupBy("o.id");
        $query->setOrderBy("o.id DESC");
        $query->setLimit(self::LIMIT_GENERATE_REPORT + 1);
        $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        $next = false;
        if (!empty($raw_result))
            $next = self::processStep($raw_result, $csv, $userJob);
        if (!$next || empty($raw_result)) {
            self::transferToCloud($fileJob);
            self::doneUserJob($userJob, $fileJob);
        }

        return ['success' => 'Handled part of orders report CSV for user job #' . $user_job_id];
    }

    protected static function getSelectQueryForCSV(): string
    {
        return "SELECT o.id as item_id, o.id as order_id, o.ref, o.ref2, o.create_date, o.despatch_date, o.despatched_date, o.status,
                       o.delivery_id,
                       s.name as supplier_name, o.is_hash
                FROM orders o";
    }

    public static function getDespatchedBatchesData($ids): array
    {
        if (empty($ids) || !is_array($ids))
            return [[], []];
        $res = (new SafeMysql())->fetchAll("
            SELECT o.id as order_id, ood.items, ood.delivery, o.is_hash
            FROM orders o LEFT JOIN orders_original_despatched ood ON ood.order_id = o.id WHERE o.id IN (:ids)", ['ids' => $ids]);
        $items = $deliveries = [];
        foreach ($res as $item) {
            if (!empty($item['is_hash']) && ArchivingOrderService::orderIsArchived($item['order_id'])) {
                try {
                    $archived_data = ArchivingOrderService::getOriginalDespatched($item['order_id']);
                } catch (Exception $exception) {
                    $archived_data = [];
                    Debug::sendDebugInfo('ArchivingOrder', 'Original Despatched: ' . $exception->getMessage());
                }

                $item['delivery'] = $archived_data['delivery'] ?? '';
                $item['items'] = $archived_data['items'] ?? '';
            }
            $deliveries[$item['order_id']] = json_decode($item['delivery'], true);
            if (empty($item['items']))
                continue;
            $SKUs = json_decode($item['items'], true);
            if (empty($SKUs))
                continue;
            foreach ($SKUs as $SKU)
                if (!empty($SKU['batch_id']))
                    $items[$item['order_id']][] = ['quantity' => $SKU['quantity'], 'batch_id' => $SKU['batch_id']];

        }
        return [$items, $deliveries];
    }

    public static function handleDataToCSV(array $raw_result, FormatterCSV $csv, $addition = null): void
    {
        $warehouse = $addition instanceof Warehouse ? $addition : null;

        $time_diff = 0;
        if (!empty($warehouse))
            $time_diff = Timezones::getDiff($warehouse->getTimezone());

        $orders = array_map(function ($item) {
            return ['id' => $item['order_id'], 'status' => $item['status'], 'delivery_id' => $item['delivery_id']];
        }, $raw_result);
        $desp_orders = array_filter($orders, function ($item) {
            return Order::isDespatched($item['status']);
        });
        $left_orders = array_filter($orders, function ($item) {
            return !Order::isDespatched($item['status']);
        });
        $desp_order_ids = Arr::column($desp_orders, 'id');
        $left_delivery_ids = array_unique(Arr::column($left_orders, 'delivery_id'));
        [$tracking_numbers, $warehouses] = ShippingsBackend::getTrackingNumbersByOrders($desp_order_ids);
        [$desp_items, $desp_deliveries] = self::getDespatchedBatchesData($desp_order_ids);
        $left_deliveries = DeliveryHistories::restoreDeliveryByIds($left_delivery_ids, date(WithDate::DATE_SQL_FORMAT));
        $orders_packages_ids = [];
        array_walk($tracking_numbers, function ($item) use (&$orders_packages_ids) {
            $orders_packages_ids = array_merge($orders_packages_ids, array_keys($item));
        });
        $order_ids = array_column($orders, 'id');
        $SKU_info_order = OrderSKUs::getSKUInfoByOrders($order_ids);        // $left_orders
        $serial_numbers = OrderSKUSerialNumbers::getByOrderIds($order_ids);
        $archived_order_ids = [];
        array_walk($raw_result, function ($item) use (&$archived_order_ids) {
            if (!empty($item['is_hash'])
                && ArchivingOrderService::orderIsArchived($item['order_id'])
                && in_array($item['status'], Order::$allowed_for_packing_slip_statutes))
                $archived_order_ids[] = $item['order_id'];
        });
        $SKU_info = OrderSKUs::getSKUInfoByOrdersPackages(array_filter($orders_packages_ids), $archived_order_ids);
        $batches = [];
        foreach ($desp_items as $order_items)
            foreach ($order_items as $items)
                $batches[$items['batch_id']] = 1;
        $batches_info = Batches::getBatchesByIds(array_keys($batches));
        foreach ($raw_result as $row) {
            $batches = [];
            if (!empty($desp_items[$row['order_id']])) {
                $batches_tmp = array_map(function ($item) use ($batches_info) {
                    if (empty($batches_info[$item['batch_id']]))
                        return ['', ''];
                    $batch = $batches_info[$item['batch_id']];
                    return [$batch['barcode'], sprintf("%s (%d units) %s", $batch['number'], $item['quantity'], date('jS F Y', strtotime($batch['expired'])))];
                }, $desp_items[$row['order_id']]);
                foreach ($batches_tmp as $batch_tmp) {
                    if (empty($batch_tmp[0]))
                        continue;
                    $batches[$batch_tmp[0]][] = $batch_tmp[1];
                }
                foreach ($batches as &$batch)
                    $batch = implode('; ', $batch);
                unset($batch);
            }
            $src_delivery = Order::isDespatched($row['status']) ? $desp_deliveries[$row['order_id']] : $left_deliveries[$row['delivery_id']];
            $data = array_merge(
                [
                    'ID' => $row['order_id'],
                    'Client' => $row['supplier_name'],
                    'Reference' => $row['ref'],
                    'Reference2' => $row['ref2'],
                    'Date created' => !empty($time_diff) ? Timezones::getDiff($warehouse->getTimezone(), $row['create_date']) : $row['create_date'],
                    'Date expected despatch' => $row['despatch_date'],
                    'Date despatched' => !empty($time_diff) && !empty($row['despatched_date'])
                        ? Timezones::getDiff($warehouse->getTimezone(), $row['despatched_date'])
                        : $row['despatched_date'],
                ],
                !empty($warehouse) && $warehouse->isVirtual() ? ['Shipped from' => $warehouses[$row['order_id']] ?? ''] : [],
                [
                    'Destination Country' => $src_delivery['country_id'],
                    'Destination State' => $src_delivery['county'],
                    'Destination City' => $src_delivery['town'],
                    'Destination Zip' => $src_delivery['postcode'],
                    'Destination Address line 1' => $src_delivery['address1'],
                    'Destination Address line 2' => $src_delivery['address2'],
                    'Destination Address line 3' => $src_delivery['address3'],
                    'Destination Contact name' => $src_delivery['contact'],
                    'Destination Contact phone' => $src_delivery['phone'],
                    'Destination Contact email' => $src_delivery['email'],
                    'Status' => Order::getStateName($row['status']),
                ]
            );
            if (!empty($tracking_numbers[$row['order_id']])) {
                $rows = [];
                foreach ($SKU_info as $orders_packages_id => $SKUs_items) {
                    if (empty($tracking_numbers[$row['order_id']][$orders_packages_id]))
                        continue;
                    $tracking_number = $tracking_numbers[$row['order_id']][$orders_packages_id];
                    foreach ($SKUs_items as $SKUs_item_id => $SKUs_item) {
                        if (isset($rows[$SKUs_item_id])) {
                            $rows[$SKUs_item_id]['Units'] += $SKUs_item['quantity'];
                            $rows[$SKUs_item_id]['Tracking Numbers'][] = $tracking_number;
                            continue;
                        }
                        $rows[$SKUs_item_id] = [
                            'Barcode' => $SKUs_item['barcode'],
                            'Customer Ref' => $SKUs_item['customer_ref'],
                            'Units' => $SKUs_item['quantity'],
                            'Batches' => empty($batches[$SKUs_item['barcode']]) ? '' : $batches[$SKUs_item['barcode']],
                            'Tracking Numbers' => [$tracking_number],
                            'Serial numbers' => implode(', ', $serial_numbers[$row['order_id']][$SKUs_item_id] ?? []),
                        ];
                    }
                }
                foreach ($rows as $row_track) {
                    $row_track['Tracking Numbers'] = implode(',', $row_track['Tracking Numbers']);
                    $csv->addRow($data + $row_track);
                }
            } else
                foreach ($SKU_info_order[$row['order_id']] ?? [] as $SKU_id => $SKU)
                    $csv->addRow($data + [
                            'Barcode' => $SKU['barcode'],
                            'Customer Ref' => $SKU['customer_ref'],
                            'Units' => $SKU['quantity'],
                            'Batches' => empty($batches[$SKU['barcode']]) ? '' : $batches[$SKU['barcode']],
                            'Tracking Numbers' => '',
                            'Serial numbers' => implode(', ', $serial_numbers[(int)$row['order_id']][(int)$SKU_id] ?? []),
                        ]);
        }
    }

    static function getParamsHeadline(UserJob $userJob)
    {
        $addition = json_decode($userJob->getAddition(), true);
        return Warehouses::isVirtual($addition['addition']['warehouse_id'] ?? null);

    }

    static function processCSV(UserJob $userJob, $raw_result, FormatterCSV $csv, $addition)
    {
        self::handleDataToCSV($raw_result, $csv, Warehouses::getById($addition['warehouse_id'] ?? null));
    }

    static function handleHeadlineCSV($warehouse_virtual): array
    {
        return array_merge(
            [
                'ID',
                'Client',
                'Reference',
                'Reference2',
                'Date created',
                'Date expected despatch',
                'Date despatched'
            ],
            $warehouse_virtual ? ['Shipped from'] : [],
            [
                'Destination Country',
                'Destination State',
                'Destination City',
                'Destination Zip',
                'Destination Address line 1',
                'Destination Address line 2',
                'Destination Address line 3',
                'Destination Contact name',
                'Destination Contact phone',
                'Destination Contact email',
                'Status',
                'Barcode',
                'Customer Ref',
                'Units',
                'Batches',
                'Tracking Numbers',
                'Serial numbers'
            ]
        );
    }

    public static function hashAnonymizeDespatched($order_id, Delivery $delivery)
    {
        (new SafeMysql())->execute("UPDATE orders_original_despatched
                SET delivery = :delivery
                WHERE order_id = :order_id", [
            'delivery' => json_encode($delivery->toArray()),
            'order_id' => $order_id,
        ]);
    }

    public static function isErrorVirtual(Order $order)
    {
        $connection_error = (new SafeMysql())->fetchScalar("SELECT 1 FROM order_history WHERE order_id = :order_id AND `comment` = 'Virtual: HTTP/1.1 401 Unauthorized'", ['order_id' => $order->getId()]);
        return $order->errorVirtual() && $connection_error;
    }

    public static function retryVirtual(Order $order, $user_id)
    {
        $processor = Warehouses::getVirtualProcessor($order->getWarehouseId(), $order->getSupplierId());
        if (empty($processor)) {
            Debug::sendDebugInfo('retryVirtualOrder', "Supplier is absent for warehouse (order #{$order->getId()})");
            if (!$order->errorVirtual())
                OrdersModel::changeStatus($order->getId(), Order::ERROR_VIRTUAL, $user_id, 'Virtual: Supplier is absent for warehouse');
        } else
            try {
                $result = $processor->createOrder($order->toArrayVirtual(), OrderSKUs::getSKUsByOrder($order->getId()));
                if ($result !== true)
                    throw new VirtualException($result);
                OrdersModel::changeStatus($order->getId(), Order::ALLOCATED, $user_id);
            } catch (Exception $e) {
                Debug::sendDebugInfo('retryVirtualOrder', "Order #{$order->getId()}: {$e->getMessage()}");
                if (!$order->errorVirtual())
                    OrdersModel::changeStatus($order->getId(), Order::ERROR_VIRTUAL, $user_id, $e->getMessage());
            }
    }

    public static function isSetScheduled(Order $order)
    {
        $supplier = Suppliers::getById($order->getSupplierId());
        if (empty($supplier)) {
            Debug::sendDebugInfo('isSetScheduled', "Order #{$order->getId()}: Supplier is not found", Debug::TEST);
            throw new \Exception("Order #{$order->getId()}: Supplier is not found");
        }
        if (!$supplier->getUtiliseDespatchDate())
            return false;
        $need_date = strtotime($order->getDespatchDate());
        if ($supplier->getDaysBeforeDespatch())
            $need_date -= $supplier->getDaysBeforeDespatch() * 60 * 60 * 24;
        return time() < $need_date;
    }

    public static function checkScheduled(Supplier $supplier)
    {
        if (empty($supplier))
            return;
        $orders = (new SafeMysql())->fetchAll("
            SELECT id, despatch_date, status
            FROM orders
            WHERE supplier_id = :supplier_id AND status IN (:statuses)", [
            'supplier_id' => $supplier->getId(),
            'statuses' => [Order::NEW_NUM, Order::SCHEDULED_NUM]
        ]);
        if (empty($orders))
            return;
        foreach ($orders as $order_data)
            if (!$supplier->getUtiliseDespatchDate()) {
                if (Order::isScheduled($order_data['status']))
                    Orders::changeStatus($order_data['id'], Order::NEW, -1, 'Status: NEW - Utilise despatch date is OFF');
            } else {
                $need_date = strtotime($order_data['despatch_date']);
                if ($supplier->getDaysBeforeDespatch())
                    $need_date -= $supplier->getDaysBeforeDespatch() * 60 * 60 * 24;
                if (time() < $need_date) {
                    if (Order::isNew($order_data['status']))
                        Orders::changeStatus($order_data['id'], Order::SCHEDULED, -1);
                } else {
                    if (Order::isScheduled($order_data['status']))
                        Orders::changeStatus($order_data['id'], Order::NEW, -1, 'Status: NEW - Utilise despatch date is OFF');
                }
            }
    }

    public static function getProcessingQueue($warehouse_id, array $filters)
    {
        $filters['warehouse_id'] = $warehouse_id;
        $filters['status_awaiting'] = Order::AWAITING_PACK_NUM;
        $where_supplier = '';
        if (!empty($filters['supplier_id']))
            $where_supplier = " AND sup.id = :supplier_id";
        $query = new QueryBuilder();
        $query->setSelectFrom("SELECT SQL_CALC_FOUND_ROWS o.*, o.id order_id, sup.name name_sup
            FROM orders o");
        $query->addJoin("JOIN suppliers sup ON o.supplier_id = sup.id $where_supplier");
        if (!empty($filters['status']))
            $query->addWhere("o.status = :status");
        else
            $query->addWhere("(o.status LIKE '32__' OR o.status = :status_awaiting)");
        $query->addWhere('o.warehouse_id = :warehouse_id');
        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);
        $query->setOrderBy("o.id DESC");
        $query->setLimit("{$offset}, " . self::LIMIT_PER_PAGE);
        $records_raw = (new SafeMysql())->fetchAll($query->build(), $filters);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        self::handleDeliveries($records_raw);
        return ['records' => $records_raw, 'total' => (int)$total, 'current_page' => $current_page];
    }

    public static function parseIds(string $ids)
    {
        preg_match_all('!(\d+)|(-{1})!', $ids, $numbers);
        $number_lst = [];
        $key = 0;
        $start = null;
        foreach ($numbers[0] as $number) {
            if ($number == '-') {
                if ($start === true)
                    $start = false;
            } else {
                if (is_null($start)) {
                    $number_lst[$key] = [$number];
                    $start = true;
                } else {
                    if ($start === true) {
                        $key++;
                        $number_lst[$key] = [$number];
                    } else {
                        $number_lst[$key][] = $number;
                        $key++;
                        $start = null;
                    }
                }
            }
        }
        $set = [];
        foreach ($number_lst as $interval) {
            if (count($interval) == 2) {
                if ($interval[0] <= $interval[1])
                    for ($i = (int)$interval[0]; $i <= (int)$interval[1]; $i++)
                        $set[] = $i;
            } else
                $set[] = (int)$interval[0];
        }
        sort($set);
        return array_unique($set);
    }

    /**
     * @return array{suppliers: array, users: array, supplier_id: int, user_id: int}
     */
    public static function getAvailableSuppliersAndUsersForOrdersCreation(
        Warehouse $warehouse,
        LogisticCompany $logistic_company,
        User $user,
        $selected_supplier_id,
        $selected_user_id
    ): array
    {
        $suppliers = $user->isRoleSupplierUser()
            ? Suppliers::getSuppliersByUser($warehouse->getId(), $user->getId(), Supplier::ACTIVE)
            : Suppliers::getByFilters($logistic_company->getId(), [
                'warehouse_id' => $warehouse->getId(),
                'active' => 1,
                'only_active_users' => true,
            ]);
        $users = [];
        if (empty($selected_supplier_id)) {
            if (!$user->isRoleSupplierUser()) {
                foreach ($suppliers as $id => $supplier) {
                    $users = Users::getUserBySupplierId($id, true);
                    if (!empty($users)) {
                        $supplier_id = $id;
                        break;
                    }
                }
                if (empty($users))
                    throw new \Exception('There is no supplier with users');
            } else
                $supplier_id = array_keys($suppliers)[0];
        } else {
            $supplier_id = $selected_supplier_id;
            if (!array_key_exists($supplier_id, $suppliers))
                throw new \Exception('Supplier is locked');
            if (!$user->isRoleSupplierUser()) {
                $users = Users::getUserBySupplierId($supplier_id, true);
                if (empty($users))
                    throw new \Exception('Supplier has no users');
            }
        }
        $user_id = $user->getId();
        if (!$user->isRoleSupplierUser()) {
            $user_id = (!empty($selected_user_id) ? $selected_user_id : array_keys($users)[0]);
            if (!array_key_exists($user_id, $users))
                $user_id = array_keys($users)[0];
        }

        return [
            'suppliers' => $suppliers,
            'users' => $users,
            'supplier_id' => $supplier_id,
            'user_id' => $user_id
        ];
    }

    public static function getOriginalDespatchedItems(int $order_id): ?array
    {
        $items = (new SafeMysql())->fetchScalar("SELECT items FROM orders_original_despatched WHERE order_id = :order_id", ['order_id' => $order_id]);
        if (!empty($items))
            return json_decode($items, true);
        return null;
    }

    public static function getPackagesDespatched(int $order_id): ?array
    {
        $packages = $SKU_ids = [];
        $packages_data = OrderPackages::getBoxes($order_id);

        if (empty($packages_data))
            return $packages;

        $SKUs_despatched = self::getOriginalDespatchedItems($order_id);
        if (empty($SKUs_despatched))
            return $packages;
        $SKU_id_batch_number = [];
        $batch_ids = array_filter(array_unique(array_column($SKUs_despatched, 'batch_id')));

        $batches = Batches::getByIds($batch_ids);
        foreach ($SKUs_despatched as $despatched_item) {
            $SKU_ids[(int)$despatched_item['SKU_id']] = (int)$despatched_item['SKU_id'];
            $batch = $batches[(int)$despatched_item['batch_id']] ?? null;
            $batch_number = $batch ? $batch->getNumber() : '';
            $batch_date = $batch ? date('Y-m-d', strtotime($batch->getExpired())) : '';
            if (isset($SKU_id_batch_number[(int)$despatched_item['SKU_id']][$batch_number])) //Этого кейса не должно быть никогда, но лучше перестраховаться
                $SKU_id_batch_number[(int)$despatched_item['SKU_id']][$batch_number]['quantity'] += $despatched_item['quantity'];
            else
                $SKU_id_batch_number[(int)$despatched_item['SKU_id']][$batch_number] = ['quantity' => (int)$despatched_item['quantity'], 'expired' => $batch_date];
        }
        $SKUs_info = SKUs::getInfoByIds($SKU_ids);
        $SKUs_packages = OrdersPackagesSKUs::getSKUsFromPackageByIds(array_column($packages_data, 'id'));
        foreach ($packages_data as $package) {
            $SKUs = [];
            foreach ($SKUs_packages[(int)$package['id']] ?? [] as $SKU_id => $quantity_in_package) {
                foreach ($SKU_id_batch_number[$SKU_id] as $batch_number => $batch_data) {
                    if (!$batch_data['quantity']) // Срабатывает если не осталось товаров с текущим batch
                        continue;
                    $quantity = min($quantity_in_package, $batch_data['quantity']); // Берем минимальное количество товаров между текущей упаковкой и товарами с текущим batch
                    $SKU_id_batch_number[$SKU_id][$batch_number]['quantity'] -= $quantity;
                    $quantity_in_package -= $quantity;
                    $SKUs[] = [
                        'SKU_ID' => $SKU_id,
                        'SKU_Barcode' => $SKUs_info[$SKU_id]['barcode'] ?? '',
                        'Unique_Customer_Ref' => $SKUs_info[$SKU_id]['unique_customer_ref'] ?? '',
                        'SKU_Quantity' => $quantity,
                        'Batch_Number' => $batch_number,
                        'Batch_Expiry' => $batch_data['expired'],
                    ];
                    if (!$quantity_in_package) // Срабатывает если наполнили текущую упаковку товаром
                        break;
                }
            }
            $packages[] = [
                'Tracking_Number' => $package['tracking_number'],
                'Weight' => $package['weight'],
                'Height' => $package['height'],
                'Width' => $package['width'],
                'Length' => $package['length'],
                'SKUs' => $SKUs
            ];
        }

        return $packages;
    }

    public static function getPackagesDespatchedVisible($order_id): array
    {
        return [];
    }
}