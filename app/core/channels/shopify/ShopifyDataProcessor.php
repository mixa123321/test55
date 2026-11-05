<?php

namespace app\core\channels\shopify;

use app\backend\models\Carriers;
use app\backend\models\Deliveries;
use app\backend\models\InventorySync;
use app\backend\models\OrderHistories;
use app\backend\models\Orders as OrdersModel;
use app\backend\models\SKUs as SKUsModel;
use app\core\channels\BaseChannelProcessor;
use app\core\entities\Carrier;
use app\core\entities\Delivery;
use app\core\entities\Order;
use app\core\entities\SKU;
use app\core\entities\Supplier;
use app\core\entities\SuppliersConfig;
use app\core\exceptions\ChannelException;
use app\core\helpers\Barcode128Rule;
use app\core\helpers\Debug;
use app\core\models\Bundles;
use app\core\models\Channels;
use app\core\models\Orders;
use app\core\models\OrdersOriginalRequest;
use app\core\models\OrdersSubstitutions;
use app\core\models\Shippings;
use app\core\models\ShippingServices;
use app\core\models\SKUChannelSuppliers;
use app\core\models\SKUs;
use app\core\models\TrackingsHistory;
use app\core\models\WarehouseCarriers;
use app\core\statical\Sources;
use app\core\statical\WithDate;
use app\core\models\OrderPackages;
use app\core\models\TrackingLinks;
use app\core\statical\Currencies;
use app\core\models\ShopWarehouses;

class ShopifyDataProcessor extends BaseChannelProcessor
{
    const FULFILLED = 'fulfilled';

    /** @var ShopifyConnector */
    protected $connector;

    /** @return ShopifyConnector */
    public function getConnector()
    {
        return $this->connector;
    }

    public function fetchByProductId($product_id)
    {
        $product_list = $this->connector->fetchByProductId([
            'product_id' => $product_id,
        ]);
        if (empty($product_list))
            return;
        $products_arr = json_decode($product_list, true);
        foreach ($products_arr['products'] as $product)
            $this->processSingleProduct($product);
    }

    public function processProducts()
    {
        $product_list = $this->getConnector()->fetchAllProducts([
            'shop_id' => $this->getUserChannel()->getShop()->getId(),
            'time_products' => $this->getUserChannel()->getShopWarehouse()->getTimeProducts(),
        ]);
        if (empty($product_list) || !is_array($product_list))
            return;
        $product_list = array_reverse($product_list);
        foreach ($product_list as $product)
            $this->processSingleProduct($product);
        $this->getUserChannel()->getShopWarehouse()->setTimeProducts(WithDate::startDay(time() - 86400 * 7));
        $this->getUserChannel()->getUserChannel()->saveShopWarehouse(['last_upload']);
    }

    public function getOrder($order_number)
    {
        return $this->getConnector()->fetchByOrderId($order_number);
    }

    public function processSingleProduct(array $product_data)
    {
        if (empty($product_data) || empty($product_data['variants']))
            return;
        $shopInfo = $this->getConnector()->getShopInfo();
        $supplier = $this->getSupplier();
        $bundle_SKUs = $this->getBundleSKUs($product_data['bundle_components'] ?? []);
        if ($bundle_SKUs === null)
            return;
        if ($bundle_SKUs) {
            $existing_SKU = SKUs::getByNameRefSupplier(substr($product_data['title'], 0, 255), $product_data['title'], $supplier->getId());
            if ($existing_SKU)
                return;
            $currency_id = Currencies::getId($shopInfo['currency']);
            $product = [
                'name' => $product_data['title'],
                'barcode' => $product_data['title'],
                'weight' => 0,
                'cost_value' => 0,
                'hs_code' => '',
                'country_id' => '',
                'cost_currency' => $shopInfo['currency'],
                'market_value' => 0,
                'market_currency' => $shopInfo['currency'],
                'photo_src' => '',
                'supplier_id' => $supplier->getId(),
                'state' => SKU::BUNDLE,
                'customer_ref' => $product_data['title'],
                'customer_ref2' => '',
            ];
            $res = SKUsModel::addSKU($product, [$product_data['title']], $this->getUserChannel()->getShopWarehouse()->getWarehouseId(), $this->getUserChannel()->getChannelSupplier()->getUserId());
            if (!$res[0]) {
                Channels::sendMessageToTelegram('Shopify (SKU_validation, bundle)', json_encode($res[1]));
                return;
            }
            Bundles::add($product_data['title'], $supplier->getId(), $bundle_SKUs, $this->getUserChannel()->getChannelSupplier()->getUserId(), $currency_id, round(array_sum(array_column($product_data['variants'], 'price')), 2));
            return;
        }
        foreach ($product_data['variants'] as $variant) {
            $SKU_id = SKUChannelSuppliers::getIdByPrefixExternalId($variant['id'], $variant['product_id'], $this->getUserChannel()->getShop()->getId());
            if ($SKU_id)
                continue;
            if (!$this->checkDelay($variant['created_at']))
                continue;
            $external_id = "{$variant['id']}-{$variant['inventory_item_id']}-{$variant['product_id']}";
            // ABF-475/CON-382 EU PID: the "shopify_barcode_field" choice (barcode vs sku as primary field) is
            // removed per the ticket ("remove option of setting barcode/sku as the primary field as both have
            // different purposes now") - barcode is now always sourced from the SKU, S-PID from Shopify's barcode field
            $SKU_state = SKU::ACTIVE_NUM;
            $barcode = $variant['sku'];
            $is_valid_barcode = Barcode128Rule::validate($barcode);
            if (!$is_valid_barcode) {
                $SKU_state = SKU::NOT_MAPPED_NUM;
                $barcode = $this->getConnector()->getUniqueBarcode(
                    $this->getUserChannel()->getShop()->getId(),
                    $variant['inventory_item_id']
                );
            }
            $product_name = $product_data['title'];
            if ("default title" !== strtolower($variant['title']))
                $product_name .= " " . $variant['title'];
            $customer_ref2 = strip_tags($product_data['body_html']);
            $inventory = $this->getConnector()->getInventoryItem($variant['inventory_item_id']);
            $product = [
                'name' => $product_name,
                'barcode' => $barcode,
                'weight' => !empty($variant['grams']) ? round($variant['grams'] / 1000, 3) : 0,
                'cost_value' => (float)($inventory['cost'] ?? ($variant['price'] ?? 0)),
                'hs_code' => $inventory['harmonized_system_code'] ?? '',
                'country_id' => $inventory['country_code_of_origin'] ?? '',
                'cost_currency' => $shopInfo['currency'],
                'market_value' => !empty($variant['price']) ? $variant['price'] : 0,
                'market_currency' => $shopInfo['currency'],
                'photo_src' => !empty($product_data['image']['src']) ? $product_data['image']['src'] : '',
                'supplier_id' => $supplier->getId(),
                'state' => $SKU_state,
                'customer_ref' => $variant['sku'],
                'customer_ref2' => substr($customer_ref2, 0, 65000),
                // ABF-475/CON-382 EU PID: NS-PID defaults to SKU (or the supplier's configured product metafield),
                // S-PID = Shopify's native barcode field
                'ns_pid' => $this->resolveNsPidFromMetafields($supplier->getId(), $product_data['metafields'] ?? [], $variant['sku'] ?? ''),
                's_pid' => $variant['barcode'] ?? '',
            ];
//            //Naturecan (20) has different names but same skus for the same products
//            $existing_SKU = $supplier->getId() == 20
//                ? SKUs::getByRefSupplier($variant['sku'], $supplier->getId())
//                : SKUs::getByNameRefSupplier(substr($product_name, 0, 255), $variant['sku'], $supplier->getId());
            $existing_SKU = SKUs::getByNameRefSupplier(substr($product_name, 0, 255), $variant['sku'], $supplier->getId());
            if (empty($existing_SKU)) {
                $SKU = SKUs::getByBarcodeAndSupplier($product['barcode'], $supplier->getId());
                if (!empty($SKU))
                    $SKU_id = $SKU->getId();
                else {
                    $res = SKUsModel::addSKU($product, [$product['barcode']], $this->getUserChannel()->getShopWarehouse()->getWarehouseId(), $this->getUserChannel()->getChannelSupplier()->getUserId());
                    if (!$res[0]) {
                        Channels::sendMessageToTelegram('Shopify (SKU_validation)', json_encode($res[1]));
                        continue;
                    }
                    $SKU_id = $res[1];
                }
            } else
                $SKU_id = $existing_SKU->getId();
            SKUChannelSuppliers::addConnectionSKU($SKU_id,
                $this->getUserChannel()->getShop()->getId(),
                $external_id,
                $this->getUserChannel()->getChannelSupplier()->getUserId(),
                $this->getUserChannel()->getShopWarehouse()->getWarehouseId());
        }
    }

    /**
     * ABF-475 EU PID: resolves NS-PID from the supplier's configured Shopify product metafield.
     * Per the ticket ("requires providing the name of the attribute") this matches on the metafield's
     * display Name (what a supplier actually sees on the product page in Shopify), not its technical key -
     * those two can differ (e.g. Name "keyNSPID" vs key "keyns_pid"), confirmed against a real test store.
     * Falls back to matching by key for metafields without a definition (so a key still works if entered).
     * Empty setting = keep the SKU default; a configured name/key that isn't found on the product = null (empty).
     */
    private function resolveNsPidFromMetafields(int $supplier_id, array $metafields, string $default_ns_pid): string
    {
        $attribute = trim(SuppliersConfig::readConfigBySupplierId($supplier_id, SuppliersConfig::TYPE_SHOPIFY_NS_PID_ATTRIBUTE));
        if ($attribute === '')
            return $default_ns_pid;
        foreach ($metafields as $metafield)
            if (($metafield['definition']['name'] ?? null) === $attribute || ($metafield['key'] ?? null) === $attribute)
                return (string)($metafield['value'] ?? '');
        return '';
    }

    private function getBundleSKUs(array $bundle_components): ?array
    {
        $bundle_SKUs = [];
        foreach ($bundle_components as $bundle_component) {
            $product_id = $this->getConnector()->getShortId($bundle_component['componentProduct']['id']);
            $quantity = (int)$bundle_component['quantity'];
            foreach ($bundle_component['componentVariants']['nodes'] as $variant) {
                $variant_id = $this->getConnector()->getShortId($variant['id']);
                $SKU_id = SKUChannelSuppliers::getIdByPrefixExternalId($variant_id, $product_id, $this->getUserChannel()->getShop()->getId());
                if (empty($SKU_id))
                    return null;
                $SKU_obj = SKUs::getById($SKU_id);
                $bundle_SKUs[$SKU_id] = ['quantity' => $quantity, 'price' => $SKU_obj->getMarketValue(),];
            }
        }
        return $bundle_SKUs;
    }

    /** Processing all orders info coming from the Connector. This method is called via ChannelRobot */
    public function processOrders($params = [])
    {
        $cursor = null;
        do {
            $items = $this->connector->fetchOrdersGraphQL($params, $cursor);
            if (empty($items['orders']))
                return;
            $orders_list = $items['orders'];
            foreach ($orders_list as $order)
                $this->processSingleOrder($order);
            $cursor = $items['cursor'] ?? '';
        } while($items['has_next']);
    }

    public function processOrderIds($order_id)
    {
        $order_res = $this->connector->fetchByOrderId($order_id);
        if (empty($order_res))
            return null;
        $order_arr = json_decode($order_res, true);
        if (empty($order_arr['order']))
            return null;
        return $this->processSingleOrder($order_arr['order']);
    }

    public function processOrderId($order_id)
    {
        $order_res = $this->connector->fetchByOrderId($order_id);
        if (empty($order_res))
            return null;
        $order_arr = json_decode($order_res, true);
        if (empty($order_arr['order']))
            return null;
        return $this->processSingleOrder($order_arr['order']);
    }

    public function processSingleOrder(array $request_arr, $new_ref = null)
    {
        $ref = $request_arr['id'];
        if (!is_null($new_ref))
            $ref .= '_copy';
        if (!$this->checkDelay($request_arr['created_at'], self::ORDER_SAVE_DELAY))
            return false;
        // Если есть настройка UTILISE SHOPIFY FULFILMENT LOCATIONS ($this->getSupplier()->getShopifyLocations()) и настройка SPLIT ORDERS ($supplier_settings[SuppliersConfig::TYPE_SHOPIFY_SPLIT_ORDER]),
        // то у нас один заказ с shopify может быть на 2 наших складах и на нашей стороне его нужно разделять на 2 заказа по складам
        $ref_with_warehouse = $ref . "-warehouse-" . (int)$this->getUserChannel()->getShopWarehouse()->getWarehouseId();
        if (OrdersModel::getByRefAndRef2($ref, $request_arr['name'], $this->getUserChannel()->getShop()->getId(), $ref_with_warehouse))
            return false;
        $supplier_settings = $this->getSupplierSettings();
        if ($this->getSupplier()->getShopifyLocations()) {
            $fulfilment_orders = $this->getFulfilmentOrderFromOurLoc((int)$request_arr['id']);
            if (!$fulfilment_orders)
                return false;
            if (($supplier_settings[SuppliersConfig::TYPE_SHOPIFY_SPLIT_ORDER] ?? false) && count(array_unique(array_column($fulfilment_orders, 'assigned_location_id'))) > 1)
                $ref = $ref_with_warehouse; //Если у заказа больше чем 1 локация, то к ref добавляем id склада
            $fulfilment_orders_in_our_location = $line_item_id_to_quantity = [];
            foreach ($fulfilment_orders as $fulfilment_order)
                if ((int)$this->getUserChannel()->getShopWarehouse()->getLocationId() === (int)$fulfilment_order['assigned_location_id'])
                    $fulfilment_orders_in_our_location[$fulfilment_order['id']] = $fulfilment_order;
            if (count($fulfilment_orders_in_our_location) === 0)
                return false;
            if (count($fulfilment_orders_in_our_location) > 1)
                Debug::sendDebugInfo("processSingleOrder", "fulfilment_orders_in_our_location count = " . count($fulfilment_orders_in_our_location) . " external_id " . $request_arr['id'], Debug::TEST);
            foreach ($fulfilment_orders_in_our_location as $fulfilment_order)
                foreach ($fulfilment_order['line_items'] as $line_item)
                    $line_item_id_to_quantity[$line_item['line_item_id']] = ($line_item_id_to_quantity[$line_item['line_item_id']] ?? 0) + $line_item['fulfillable_quantity'];
        }
        $products_ids = array_map(static function ($item) { return $item['product_id']; }, $request_arr['line_items']);
        $order_products = $this->getConnector()->fetchAllProducts([
            'shop_id' => $this->getUserChannel()->getShop()->getId(),
            'ids' => $products_ids,
        ]);
        foreach ($order_products as $order_product)
            if (!empty($order_product['variants']))
                foreach ($order_product['variants'] as $variant)
                    if (!$this->checkDelay($variant['created_at']))
                        return false;
        if (!empty($request_arr['shipping_address'])) {
            $delivery_data = [
                'contact' => $request_arr['shipping_address']['name'],
                'name' => $request_arr['shipping_address']['company'],
                'phone' => $request_arr['shipping_address']['phone'],
                'email' => $request_arr['customer']['email'] ?: '',
                'address1' => $request_arr['shipping_address']['address1'],
                'address2' => $request_arr['shipping_address']['address2'],
                'town' => $request_arr['shipping_address']['city'],
                'postcode' => $request_arr['shipping_address']['zip'],
                'county' => $request_arr['shipping_address']['province'],
                'country_id' => $request_arr['shipping_address']['country_code'] === 'GB' ? 'UK' : $request_arr['shipping_address']['country_code'],
            ];
        } elseif(!empty($request_arr['customer']['default_address'])) {
            $delivery_data = [
                'contact' => $request_arr['customer']['default_address']['first_name']
                    . " " . $request_arr['customer']['default_address']['last_name'],
                'name' => $request_arr['customer']['default_address']['company'],
                'phone' => $request_arr['customer']['default_address']['phone'],
                'email' => $request_arr['customer']['email'] ?: '',
                'address1' => $request_arr['customer']['default_address']['address1'],
                'address2' => $request_arr['customer']['default_address']['address2'],
                'town' => $request_arr['customer']['default_address']['city'],
                'postcode' => $request_arr['customer']['default_address']['zip'],
                'county' => $request_arr['customer']['default_address']['province'],
                'country_id' => $request_arr['customer']['default_address']['country_code'] === 'GB' ? 'UK' : $request_arr['customer']['default_address']['country_code'],
            ];
        } else {
            $delivery_data = [
                'contact' => $request_arr['customer']['first_name']
                    . " " . $request_arr['customer']['last_name'],
                'phone' => $request_arr['customer']['phone'],
                'email' => $request_arr['customer']['email'] ?: '',
            ];
        }
        if (empty($delivery_data['email']))
            $delivery_data['email'] = $request_arr['email'];
        $delivery = new Delivery($delivery_data);
        $delivery_id = Deliveries::handleDeliveryReturningId($delivery, $this->getUserChannel()->getUserChannel()->getUserId());
        $carriers = WarehouseCarriers::getAvailableCarrierList($this->getUserChannel()->getShopWarehouse()->getWarehouseId());
        if (empty($carriers))
            return false;
        $order_data = [
            'external_id' => $request_arr['id'],
            'ref' => $ref,
            'ref2' => $request_arr['name'],
            'create_date' => strtotime($request_arr['created_at']),
            'despatch_date' => strtotime($request_arr['created_at']),
            'delivery_id' => $delivery_id,
            'status' => Order::NEW_NUM,
            'user_id' => $this->getUserChannel()->getChannelSupplier()->getUserId(),
            'supplier_id' => $this->getUserChannel()->getChannelSupplier()->getSupplierId(),
            'shop_id' => $this->getUserChannel()->getShop()->getId(),
            'carrier_id' => $carriers[0]['id'],
            'warehouse_id' => $this->getUserChannel()->getShopWarehouse()->getWarehouseId(),
            'type_create' => Sources::SOURCE_SHOPIFY,
            'currency' => $request_arr['currency'],
//            'order_price' => $request_arr['subtotal_price'],
        ];
        if (!empty($request_arr['note'])) {
            $spec_reqs = $this->getSpecialReq($request_arr['note']);
            if (!empty($spec_reqs))
                foreach ($spec_reqs as $field => $spec_req)
                    $order_data[$field] = $spec_req;
        }
        // https://trello.com/c/fx0yPXbn/414-order-falling-into-error-when-a-product-is-already-fulfilled
        $products = [];
        foreach ($request_arr['line_items'] as $item) {
            if ($item['fulfillment_status'] == self::FULFILLED) {
                Channels::sendMessageToTelegram("Prepare SKU (order {$order_data['ref']})", "{$item['sku']} is fulfilled");
                continue;
            } elseif ($item['fulfillable_quantity'] == 0) {
                Debug::sendDebugInfo("Prepare SKU (order {$order_data['ref']})", "{$item['sku']} is empty fulfillable_quantity", Debug::TEST);
                continue;
            } elseif ($this->getSupplier()->getShopifyLocations()) {
                if (empty($line_item_id_to_quantity[$item['id']]))
                    continue;
                $quantity = $line_item_id_to_quantity[$item['id']];
            } else
                $quantity = $item['fulfillable_quantity'];

            $key = $item['variant_id'] . "_" . $item['product_id']; // Скорее всего для уникальности достаточно variant_id, но лучше перестраховаться
            $discount = array_sum(array_column($item['discount_allocations'] ?: [], 'amount'));
            //Убираем дубли SKU с учетом возможности разных сумм скидок и разных сумм товара
            if (isset($products[$key])) {
                //Получаем усредненную цену товара
                $products[$key]['price'] = ($products[$key]['price'] * $products[$key]['quantity'] + $item['price'] * $item['quantity']) / ($products[$key]['quantity'] + $item['quantity']);
                $products[$key]['quantity'] += $quantity;
            } else {
                $products[$key] = [
                    'title' => $item['title'], 'sku' => $item['sku'], 'quantity' => $quantity,
                    'variant_id' => $item['variant_id'], 'product_id' => $item['product_id'], 'price' => $item['price'], 'discount_allocations' =>  []
                ];
            }
            $products[$key]['discount_allocations'][] = ['amount' => $discount]; // Получаем общую сумму скидки за товар
        }
        if (empty($products)) {
            Channels::sendMessageToTelegram("Order {$order_data['ref']}", "All SKUs are fulfilled");
            return false;
        }
        // ----------------------------------------------------------------------------------------------
        $res = OrdersModel::createOrder($order_data, [], array_values($products), [$this, 'prepareSKUs'], $this);
        /** @var Order $order */
        $order = $res[2];
        if (!empty($order)) {
            $credentials = $this->getUserChannel()->getShop()->getCredentials();
            OrdersOriginalRequest::saveOriginalRequest($order->getId(), '', json_encode($request_arr));
            OrderHistories::addCommentOrder($order->getId(), $order->getUserId(), "Order downloaded from Shopify ({$credentials['shop_url']})");
            if ($order->error())
                return false;
            if (!$this->checkShippingMapping($order, $request_arr['shipping_lines'][0]['code']))
                return false;
        }
        if (!$res[0])
            return false;
        $unknown_skus = $res[3][1];
        if (!empty($unknown_skus)) {
            $comment = "Unrecognized SKUs found: ";
            foreach ($unknown_skus as $unknown_sku)
                $comment .= "{$unknown_sku['title']} ({$unknown_sku['sku']}); ";
            Channels::sendMessageToTelegram("Shopify (order $ref)", $comment);
            OrdersModel::changeStatus($order->getId(), Order::ERROR, $order->getUserId(), $comment);
            return false;
        }
        OrdersModel::creationOrder($order, $res[3][0], $this->getUserChannel()->getChannelSupplier()->getUserId());
        return true;
    }

    private function getSpecialReq($note)
    {
        $tags = ['picking', 'packing', 'notes'];
        $new_str = $note;
        foreach ($tags as $tag)
            $new_str = str_replace($tag . ':', '@#$%^', $new_str);
        $values = explode('@#$%^', $new_str);
        $new_str = str_replace($values[0], '', $note);
        array_shift($values);
        foreach ($values as $value)
            $new_str = str_replace(':' . $value, '@#$%^', $new_str);
        $params = explode('@#$%^', $new_str);
        array_pop($params);
        $values = array_map(function ($value) { return trim($value); }, $values);
        $res = [];
        array_map(function ($param, $value) use (&$res) { $res[$param] = $value; }, $params, $values);
        return $res;
    }

    public function prepareSKUs(Order $order, $products, ShopifyDataProcessor $params)
    {
        $order_SKUs = $order_SKUs_bundle = [];
        $unknown_SKUs = [];
        $bundle = false;
        foreach ($products as $item) {
            $bundle_contents = Bundles::getBundleInfo([$item['title'], $item['sku']], $params->getUserChannel()->getChannelSupplier()->getSupplierId());
            $item_quantity = (int) $item['quantity'];
            $item_quantity = $item_quantity < 1 ? 1 : $item_quantity;
            if ($bundle_contents) {
                if (count(array_unique(array_column($bundle_contents, 'id'))) > 1)
                    Debug::sendDebugInfo("Bundles", "More than one bundle. Order " . $order->getId() . " Bundles " . json_encode([$item['title'], $item['sku']]), Debug::TEST);
                $bundle = true;
                $bundle_id = 0;
                if ($params->getSupplier()->getProductPrice()) {
                    if (!$bundle_contents[0]['cost_value']) {
                        Debug::sendDebugInfo("Bundles", "Bundle cost is empty. Order " . $order->getId() . " Bundles " . json_encode([$item['title'], $item['sku']]), Debug::TEST);
                    }
                    $price = $bundle_contents[0]['cost_value'];
                } else
                    $price = $this->getPrice($item, $item_quantity);
                $price_bundle = array_sum(array_map(function($el) { return $el['quantity']*$el['market_value']; }, $bundle_contents));
                $koeff = $price / $price_bundle;
                $order_SKUs_bundle_temp = [];
                foreach ($bundle_contents as $bundle_content) {
                    $bundle_id = $bundle_content['id'];
                    if (empty($order_SKUs_bundle_temp[$bundle_content['SKU_id']]))
                        $order_SKUs_bundle_temp[$bundle_content['SKU_id']]['qty'] = $bundle_content['quantity'] * $item_quantity;
                    else
                        $order_SKUs_bundle_temp[$bundle_content['SKU_id']]['qty'] += $bundle_content['quantity'] * $item_quantity;
                    $order_SKUs_bundle_temp[$bundle_content['SKU_id']]['price'] = $bundle_content['market_value'] * $koeff;
                }
                foreach ($order_SKUs_bundle_temp as $SKU_in_bundle => $SKU_in_bundle_data) {
                    //Добавляем SKU ко всем SKU
                    if (!isset($order_SKUs_bundle[$SKU_in_bundle])) {
                        $order_SKUs_bundle[$SKU_in_bundle] = $SKU_in_bundle_data;
                        continue;
                    }
                    //Если один и тот же SKU есть, то усредняем цену
                    $order_SKUs_bundle[$SKU_in_bundle]['price'] = ($SKU_in_bundle_data['qty'] * $SKU_in_bundle_data['price'] + $order_SKUs_bundle[$SKU_in_bundle]['qty'] * $order_SKUs_bundle[$SKU_in_bundle]['price']) / ($SKU_in_bundle_data['qty'] + $order_SKUs_bundle[$SKU_in_bundle]['qty']);
                    $order_SKUs_bundle[$SKU_in_bundle]['qty'] += $SKU_in_bundle_data['qty'];
                }
                Bundles::addToOrder($order->getId(), $bundle_id, $item_quantity, $order->getUserId());
                continue;
            }
            $SKU_id = SKUChannelSuppliers::getIdByPrefixExternalId($item['variant_id'], $item['product_id'], $params->getUserChannel()->getShop()->getId());
            if (!$SKU_id) {
                $this->fetchByProductId($item['product_id']);
                $SKU_id = SKUChannelSuppliers::getIdByPrefixExternalId($item['variant_id'], $item['product_id'], $params->getUserChannel()->getShop()->getId());
            }
            if ($SKU_id) {
                $SKU = SKUsModel::getById($SKU_id);
                // https://trello.com/c/Cso7v33f/456-sku-validation
                if ($SKU->getCustomerRef() != $item['sku'])
                    Channels::sendMessageToTelegram('SKU Mismatch',"<b>Order {$order->getRef()}</b>\nItem (Shopify): {$item['sku']}\nCustomer ref (WMS): {$SKU->getCustomerRef()}");
                // ----------------------------------------------------------------------------------------------
                if (empty($order_SKUs[$SKU_id]))
                    $order_SKUs[$SKU_id]['qty'] = $item['quantity'];
                else
                    $order_SKUs[$SKU_id]['qty'] += $item['quantity'];
                $order_SKUs[$SKU_id]['price'] = $item;
            } else {
                $unknown_SKUs[] = [
                    'sku' => $item['sku'],
                    'title' => $item['title'],
                    'shopify_id' => $item['variant_id'],
                ];
            }
        }

        foreach ($order_SKUs as $SKU_id => &$item)
            if (is_array($item['price']))
                $this->setPrice($params->getSupplier(), $SKU_id, $item, $unknown_SKUs);

        foreach ($order_SKUs_bundle as $SKU_in_bundle => $SKU_in_bundle_data) {
            //Добавляем SKU из bundle ко всем SKU
            if (!isset($order_SKUs[$SKU_in_bundle])) {
                $order_SKUs[$SKU_in_bundle] = $SKU_in_bundle_data;
                continue;
            }
            //Если один и тот же SKU есть в bundle и без него, то усредняем цену
            $order_SKUs[$SKU_in_bundle]['price'] = ($SKU_in_bundle_data['qty'] * $SKU_in_bundle_data['price'] + $order_SKUs[$SKU_in_bundle]['qty'] * $order_SKUs[$SKU_in_bundle]['price']) / ($SKU_in_bundle_data['qty'] + $order_SKUs[$SKU_in_bundle]['qty']);
            $order_SKUs[$SKU_in_bundle]['qty'] += $SKU_in_bundle_data['qty'];
        }

        return [true, [$order_SKUs, $unknown_SKUs], $bundle];
    }

    private function getPrice($item, $quantity)
    {
        $sum = 0;
        if (!empty($item['discount_allocations']))
            foreach ($item['discount_allocations'] as $discount)
                $sum += $discount['amount'];
        $price = $item['price']*$quantity - $sum;
        return abs($price) < 1E-3 ? 0 : ($price / $quantity);
    }

    private function setPrice(Supplier $supplier, $SKU_id, &$SKU_data, &$unknown_SKUs)
    {
        if ($supplier->getProductPrice()) {
            $SKU = SKUsModel::getById($SKU_id);
            if (empty($SKU)) {
                $unknown_SKUs[] = [
                    'sku' => $SKU_data['sku'],
                    'title' => $SKU_data['title'],
                    'shopify_id' => $SKU_data['variant_id'],
                ];
                unset($SKU_data);
            } else
                $SKU_data['price'] = $SKU->getMarketValue();
            return;
        }
        $SKU_data['price'] = $this->getPrice($SKU_data['price'], $SKU_data['qty']);
    }

    public function setInventory($inventory_id, $SKU_id, $quantity_available, $params, $source)
    {
        list(, $inventory_item_id, ) = explode('-', $inventory_id);
        $stock_quantity = $this->getInventoryStockQuantity(ShopifyConnector::INTEGRATION_NAME, $inventory_item_id);
        $quantity_adjust = $quantity_available - $stock_quantity;
        if ($quantity_adjust === 0)
            return false;
        $location_id = $this->getUserChannel()->getShopWarehouse()->getLocationId();
        if (!$location_id)
            return [
                'msg' => "#$SKU_id: Location is not selected for channel {$this->getUserChannel()->getShop()->getId()}",
                'code' => 0
            ];
        $error = null;
        $code = 0;
        try {
            $this->getConnector()->setInventory(
                $inventory_item_id,
                $quantity_adjust,
                $location_id
            );
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $code = $e->getCode();
        } finally {
            $this->storeInventoryLevel($SKU_id, $quantity_adjust, array_merge(empty($params['type']) ? $params : [], [
                'inventory_item_id' => $inventory_item_id,
                'location_id' => $location_id,
                'error' => substr($error,0,1) == '{' ? json_decode($error, true) : $error,
            ]), $params['type'] == InventorySync::SYNC_SKU ? $params['type'] : ($source ?? null));
        }
        return ['msg' => $error, 'code' => $code];
    }

    public function setInventoryManual($params)
    {
        if (empty($params) || empty($params['inventory_id']) || empty($params['SKU_id']))
            throw new ChannelException("Empty or wrong params");
        $error = null;
        $delta = (int)$params['available'] - (int)$params['available_shop'];
        try {
            $this->getConnector()->setInventory(
                (int)$params['inventory_id'],
                $delta,
                $this->getUserChannel()->getShopWarehouse()->getLocationId()
            );
        } catch (\Exception $e) {
            throw $e;
        }
        $this->storeInventoryLevel($params['SKU_id'], $delta, [
            'user_id' => $params['user_id'],
            'inventory_item_id' => $params['inventory_id'],
            'location_id' => $this->getUserChannel()->getShopWarehouse()->getLocationId(),
        ], InventorySync::SYNC_MANUAL);
    }

    public function setInventoryDaily($SKU_id, $inventory_item_id, $adjustment, $type)
    {
        $error = null;
        try {
            $this->getConnector()->setInventory(
                $inventory_item_id,
                $adjustment,
                $this->getUserChannel()->getShopWarehouse()->getLocationId(),
            );
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        $this->storeInventoryLevel($SKU_id, $adjustment, [
            'user_id' => -1,
            'inventory_item_id' => $inventory_item_id,
            'location_id' => $this->getUserChannel()->getShopWarehouse()->getLocationId(),
            'error' => substr($error,0,1) == '{' ? json_decode($error, true) : $error,
        ], $type);
    }

    /**
     * @param array $params
     * @return mixed
     * @throws ChannelException
     */
    public function setTrackInfo(array $params)
    {
        return $this->setTrackInfoNew($params);
        $order = Orders::getById($params['order_id']);
        //TODO если после заливки не будет ошибок, то заменить setTrackInfo на setTrackInfoNew
        if (in_array($order->getSupplierId(), [20, 33])) {
            return $this->setTrackInfoNew($params);
        }
        if (empty($order))
            throw new ChannelException("Not found order #{$params['order_id']}");
        $tracking_numbers = [];
        $tracking_urls = [];
        if ($order->getShippingServicesId()) {
            $shipping_service = ShippingServices::getById($order->getShippingServicesId());
            if (!$shipping_service->getIsUntracked()) {
                $shipments = Shippings::getByOrderId($order->getId());
                $tracking_numbers = array_column($shipments, 'tracking_number');
                $tracking_urls = array_column($shipments, 'url');
            }
        }
        if(!$this->getUserChannel() || $order->getShopId() != $this->getUserChannel()->getShop()->getId())
            $this->setUserChanelById(ShopifyConnector::INTEGRATION_NAME, $order->getShopId(), $order->getWarehouseId());
        $carrier = Carriers::getById($order->getCarrierId());
        if (!$this->getUserChannel()->getShopWarehouse()->getLocationId())
            throw new ChannelException('Not found LocationId');
        return $this->getConnector()->setTrackInfo($order->getExternalId(), $this->getUserChannel()->getShopWarehouse()->getLocationId(), $tracking_numbers, $tracking_urls, $carrier->getCode());
    }

    public function setTrackInfoNew(array $params): bool
    {
        $order = Orders::getById($params['order_id']);
        if (empty($order))
            $this->exceptionWithDebug("Not found order #{$params['order_id']}");
        if (!$this->getUserChannel() || $order->getShopId() != $this->getUserChannel()->getShop()->getId())
            $this->setUserChanelById(ShopifyConnector::INTEGRATION_NAME, $order->getShopId(), $order->getWarehouseId());
        if ($this->getSupplier()->getShopifyLocations())
            $fulfillment_orders = $this->getFulfilmentOrderFromOurLoc((int)$order->getExternalId());
        else
            $fulfillment_orders = $this->getConnector()->getFulfilmentOrdersGraphQL((int)$order->getExternalId());
        if ($this->getConnector()->getLastResultCode() === 403)
            throw new ChannelException('Shopify 403 Forbidden Order ' . $order->getId() . ' External ' . $order->getExternalId() . ' Supplier ' . $order->getSupplierId() . ' Error ' . $this->getConnector()->getLastResultError());
        elseif (!$fulfillment_orders)
            $this->exceptionWithDebug("Not found open Fulfillment Order\nOrder " . $order->getExternalId());
        //Нет shipping_services_id в заказе. Значит нет tracking информации и можем весь заказ одним запросом отправлять на fulfilment
        if (!$order->getShippingServicesId())
            return $this->sendTrackInfoCommon([$this->getSimpleDataForTrackInfo($fulfillment_orders)], $order, $fulfillment_orders);
        //Есть признак is_untracked в shipping_services. Значит нет tracking информации и можем весь заказ одним запросом отправлять на fulfilment
        if ((ShippingServices::getById($order->getShippingServicesId())->getIsUntracked()))
            return $this->sendTrackInfoCommon([$this->getSimpleDataForTrackInfo($fulfillment_orders)], $order, $fulfillment_orders);

        $all_variant_ids = $map_variant_id = $packages = $SKUs = $data_for_send = [];
        $all_quantity = 0;
        foreach ($fulfillment_orders as $fulfillment_order) {
            foreach ($fulfillment_order['line_items'] as $line_item) {
                $all_variant_ids[$line_item['variant_id']] = $line_item['variant_id'];
                $map_variant_id[$fulfillment_order['id']][$line_item['id']] = ['variant_id' => $line_item['variant_id'], 'quantity' => $line_item['fulfillable_quantity']];
                $all_quantity += $line_item['fulfillable_quantity'];
            }
        }

        $order_id = $order->getId();
        $carrier = Carriers::getById($order->getCarrierId());
        if (!$carrier)
            $this->exceptionWithDebug("Not found сarrier in order. Order id $order_id. Carrier id " . $order->getCarrierId());

        $orders_packages_to_tracking = $tracking_numbers = [];
        foreach (OrderPackages::getTrackingInfoByOrder([$order]) as $item) {
            if (isset($orders_packages_to_tracking[$item['id']]))
                $this->exceptionWithDebug("Duplicate tracking information. Order id $order_id. Order package id " . $item['id']);
            $orders_packages_to_tracking[$item['id']] = ['tracking_number' => $item['tracking_number'], 'url' => $item['url'], 'company' => $carrier->getCode()];
            $tracking_numbers[] = $item['tracking_number'];
        }

        $order_package_SKUs = OrderPackages::getSKUsAndQuantity($order);
        if (!$order_package_SKUs)
            $this->exceptionWithDebug("Not found SKUs in order`s package. Order id $order_id.");

        $order_substitutions = OrdersSubstitutions::getOrderSubstitutionsSKUs($order_id);
        $recovery_SKU_key = [];
        //Восстанавливаем подмененные SKU и их количество, чтобы отправить правильные SKU и их количество в shopify
        foreach ($order_substitutions as $SKU_id_original => $substitutions) {
            foreach ($substitutions as $substitution) {
                foreach ($order_package_SKUs as $key => &$order_package_SKU) {
                    if ($order_package_SKU['sku_id'] !== $substitution['SKU_id'] || isset($recovery_SKU_key[$key]))
                        continue;

                    $order_package_SKU['sku_id'] = $SKU_id_original;
                    $order_package_SKU['quantity'] = $order_package_SKU['quantity'] / ($substitution['quantity'] / $substitution['items']);
                    $recovery_SKU_key[$key] = $key;
                }
                unset($order_package_SKU);
            }
        }

        foreach ($order_package_SKUs as $package) {
            $packages[$package['id']][$package['sku_id']] = $package['quantity'];
            $SKUs[$package['sku_id']] = $package['sku_id'];
        }

        $bundles_data = Bundles::getBundlesByOrder($order);
        //Если в заказе нет bundle, то по более простой логике пробуем отправить посылку
        if (!$bundles_data) {
            $SKU_variant_id = SKUChannelSuppliers::getVariantIds($order, $SKUs, $all_variant_ids);
            if ($SKU_variant_id === null)
                return $this->sendOurTrackingForm($order, $fulfillment_orders, $carrier, $tracking_numbers, "Problem with variant_id. Order id $order_id. SKUs " . json_encode($SKUs) . ". Variant ids " . json_encode($all_variant_ids));
            if (empty($SKU_variant_id))
                return $this->sendOurTrackingForm($order, $fulfillment_orders, $carrier, $tracking_numbers, "Empty variant_id. Order id $order_id. SKUs " . json_encode($SKUs) . ". Variant ids " . json_encode($all_variant_ids));
            if ($error = $this->fillDataForTrackInfo($packages, $orders_packages_to_tracking, $SKU_variant_id, $order_id, $map_variant_id, $data_for_send))
                return $this->sendOurTrackingForm($order, $fulfillment_orders, $carrier, $tracking_numbers, $error);
            if (!$this->checkSendAllQuantity($map_variant_id))
                return $this->sendOurTrackingForm($order, $fulfillment_orders, $carrier, $tracking_numbers, "Failed to fill pack. Order id $order_id. Map variant id" . json_encode($map_variant_id));
            return $this->sendTrackInfoCommon($data_for_send, $order, $fulfillment_orders);
        }

        //Количество позиций в shopify больше чем количество упаковок. Можем попытаться передать все tracking_number в shopify на прямую
        if ($all_quantity >= count($packages)) {
            $bundles_inside = $bundles_quantity_order = [];
            foreach ($bundles_data as $bundle) {
                $bundles_inside[$bundle['name']][$bundle['SKU_id']] = $bundle['quantity_bundle'];
                $bundles_quantity_order[$bundle['name']] = $bundle['quantity_order'];
            }

            $packages_for_bundle = $packages;
            $bundles_in_package = [];
            foreach ($bundles_inside as $bundle_name => $bundle_data) {
                //Проверяем нужно ли еще запаковывать bundle с этим именем в коробку
                if (!$bundles_quantity_order[$bundle_name])
                    continue;
                foreach ($packages_for_bundle as $package_id => $package) {
                    //Проверяем что в этой упаковки есть все SKUs которые содержит bundle
                    if (count(array_intersect_key($bundle_data, $package)) !== count($bundle_data))
                        continue;
                    $count = null;
                    //Проверяем сколько bundle может поместиться в эту упаковку
                    foreach ($bundle_data as $SKU_id => $quantity_bundle) {
                        $count_in_package = intdiv($package[$SKU_id], $quantity_bundle);
                        $count = min($count ?? $count_in_package, $count_in_package, $bundles_quantity_order[$bundle_name]);
                    }
                    //Если больше нуля, значит какое-то количество bundle смогли поместить в коробку
                    if (!$count)
                        continue;
                    $bundles_in_package[$package_id][$bundle_name] = $count; //Запоминаем какое число bundles помесили в эту упаковку
                    $bundles_quantity_order[$bundle_name] -= $count; //Обновляем информацию о том какое число bundles еще нужно поместить в упаковку
                    foreach ($bundle_data as $SKU_id => $quantity_bundle)
                        $packages_for_bundle[$package_id][$SKU_id] -= $count * $quantity_bundle; //Обновляем информацию сколько еще осталось единиц в упаковке
                }
            }
            //Если все bundles разместили в коробки, значит можем передать все tracking_number в shopify на прямую
            if (!array_sum($bundles_quantity_order)) {
                $product_id_to_variant_id = SKUChannelSuppliers::getVariantIdsByProductId($order, $all_variant_ids);
                $bundle_name_to_variant_id = [];
                foreach ($product_id_to_variant_id as $product_id => $variant_id) {
                    //Без запроса в shopify не получается сделать маппинг
                    //Для этого в базе не хватает информации о связи bundle со SKU в котором прописан variant_id
                    $product_list = $this->connector->fetchByProductId(['product_id' => $product_id,]);
                    if (!$product_list)
                        return $this->sendOurTrackingForm($order, $fulfillment_orders, $carrier, $tracking_numbers, "Can't get product information. Product id $product_id. Order id $order_id.");

                    $products_arr = json_decode($product_list, true);
                    $bundle_name_to_variant_id[$products_arr['products'][0]['title']] = $variant_id;
                    foreach ($products_arr['products'][0]['variants'] as $item)
                        $bundle_name_to_variant_id[$item['sku']] = $variant_id;
                }
                $all_SKUs_without_bundles = []; //Все SKUs которые есть в заказе за пределами bundles
                foreach ($packages_for_bundle as $package)
                    $all_SKUs_without_bundles = array_unique(array_merge($all_SKUs_without_bundles, array_keys(array_filter($package))));

                $SKU_variant_id = SKUChannelSuppliers::getVariantIds($order, $all_SKUs_without_bundles, $all_variant_ids);
                //Добавляем данные по bundles в запрос.
                if ($error = $this->fillDataForTrackInfo($bundles_in_package, $orders_packages_to_tracking, $bundle_name_to_variant_id, $order_id, $map_variant_id, $data_for_send))
                    return $this->sendOurTrackingForm($order, $fulfillment_orders, $carrier, $tracking_numbers, $error);
                //Добавляем данные по SKU которые вне bundles в запрос.
                if ($error = $this->fillDataForTrackInfo($packages_for_bundle, $orders_packages_to_tracking, $SKU_variant_id, $order_id, $map_variant_id, $data_for_send))
                    return $this->sendOurTrackingForm($order, $fulfillment_orders, $carrier, $tracking_numbers, $error);
                if (!$this->checkSendAllQuantity($map_variant_id))
                    return $this->sendOurTrackingForm($order, $fulfillment_orders, $carrier, $tracking_numbers, "Failed to fill pack. Order id $order_id. Map variant id" . json_encode($map_variant_id));

                return $this->sendTrackInfoCommon($data_for_send, $order, $fulfillment_orders);
            }
        }
        return $this->sendOurTrackingForm($order, $fulfillment_orders, $carrier, $tracking_numbers);
    }

    private function exceptionWithDebug(string $message)
    {
        Debug::sendDebugInfo('Shopify', $message, Debug::TEST);
        throw new ChannelException($message);
    }

    private function sendTrackInfoCommon(array $data_for_send, Order $order, array $fulfillment_orders)
    {
        $fulfillment_orders_id_to_location = $fulfillment_orders_id_to_delivery_method = [];
        foreach ($fulfillment_orders as $fulfillment_order) {
            $fulfillment_orders_id_to_location[$fulfillment_order['id']] = $fulfillment_order['assigned_location_id'];
            $fulfillment_orders_id_to_delivery_method[$fulfillment_order['id']] = $fulfillment_order['delivery_method']['method_type'] ?? '';
        }

        //Если товары заказа находяться в разных локациях, то разделяем их на отдельные fulfilment order с одинаковой tracking информацией
        foreach ($data_for_send as $key => $item_for_send) {
            $lines_by_location = [];
            foreach ($item_for_send['line_items_by_fulfillment_order'] as $line) {
                $lines_by_location[$fulfillment_orders_id_to_location[$line['fulfillment_order_id']]][] = $line;
            }
            //Если все fulfilment order находятся в одной локации, то ничего делать не нужно
            if (count($lines_by_location) === 1) {
                continue;
            }
            Debug::sendDebugInfo('Shopify', 'Different locations in order ' . $order->getId(), Debug::TEST);
            foreach ($lines_by_location as $lines) {
                $item_for_send['line_items_by_fulfillment_order'] = $lines;
                $data_for_send[] = $item_for_send;
            }
            unset($data_for_send[$key]);
        }

        //Если товары заказа с разным delivery method, то разделяем их на отдельные fulfilment order с одинаковой tracking информацией
        foreach ($data_for_send as $key => $item_for_send) {
            $lines_by_delivery_method = [];
            foreach ($item_for_send['line_items_by_fulfillment_order'] as $line) {
                $lines_by_delivery_method[$fulfillment_orders_id_to_delivery_method[$line['fulfillment_order_id']]][] = $line;
            }
            //Если все fulfilment order находятся в одном delivery method, то ничего делать не нужно
            if (count($lines_by_delivery_method) === 1) {
                continue;
            }
            Debug::sendDebugInfo('Shopify', 'Different delivery method in order ' . $order->getId(), Debug::TEST);
            foreach ($lines_by_delivery_method as $lines) {
                $item_for_send['line_items_by_fulfillment_order'] = $lines;
                $data_for_send[] = $item_for_send;
            }
            unset($data_for_send[$key]);
        }

        return $this->sendTrackInfo(array_values($data_for_send), $order);
    }

    private function sendOurTrackingForm(Order $order, array $fulfillment_orders, Carrier $carrier, array $tracking_numbers, string $error = '')
    {
        $order_id = $order->getId();
        //Ситуация когда из-за bundles не можем на прямую передать tracking_info в shopify.
        Debug::sendDebugInfo('Shopify. Non standard tracking info', $error ?: "Order id $order_id.", Debug::TEST);

        $ids = TrackingsHistory::getByHash();
        if ($ids)
            foreach ($ids as $hash)
                if (TrackingLinks::deleteOldLink($hash))
                    TrackingsHistory::deleteRecord($hash);
        $link = TrackingLinks::createLink($order);
        if ($link === null) {
            Debug::sendDebugInfo('Shopify', "Not generated link. Order id $order_id.", Debug::TEST);
            throw new ChannelException("Not generated link. Order id $order_id.");
        }
        $tracking_info = ['number' => implode(',', $tracking_numbers) ?: $link, 'url' => BASE_PROTOCOL . '://' . BASE_DOMAIN . '/tracking?number=' . $link, 'company' => $carrier->getCode()];

        $fulfillment_orders_by_location = [];
        //Если товары заказа находяться в разных локациях, то разделяем их на отдельные fulfilment order с одинаковой tracking информацией
        foreach ($fulfillment_orders as $fulfillment_order) {
            $delivery_method_type = $fulfillment_order['delivery_method']['method_type'] ?? '';
            $fulfillment_orders_by_location[$delivery_method_type . '_' . $fulfillment_order['assigned_location_id']][] = $fulfillment_order;
        }

        if (count($fulfillment_orders_by_location) > 1) {
            Debug::sendDebugInfo('Shopify', 'Different locations or delivery method in order ' . $order->getId(), Debug::TEST);
        }

        $result = true;
        foreach ($fulfillment_orders_by_location as $fulfillment_orders_for_send) {
            $item_for_send = $this->getSimpleDataForTrackInfo($fulfillment_orders_for_send, ['tracking_info' => $tracking_info]);
            $result = $this->sendTrackInfo([$item_for_send], $order) && $result;
        }
        return $result;
    }

    private function fillDataForTrackInfo(array $packages, array $orders_packages_to_tracking, array $SKU_or_bundle_name_variant_id, int $order_id, array &$map_variant_id, array &$data_for_send): string
    {
        foreach ($packages as $package_id => $package) {
            if (!isset($orders_packages_to_tracking[$package_id]))
                return "Not found tracking information for package. Order id $order_id. Package id $package_id.";
            if (!isset($data_for_send[$package_id]))
                $data_for_send[$package_id] = ['tracking_info' => $orders_packages_to_tracking[$package_id], 'line_items_by_fulfillment_order' => []];
            foreach ($package as $sku_id_or_bundle_name => $quantity) {
                if (!$quantity)
                    continue;
                if (!isset($SKU_or_bundle_name_variant_id[$sku_id_or_bundle_name]))
                    return "Not found variant id for package. Order id $order_id. SKU/Bundle $sku_id_or_bundle_name. SKU/Bundle variant ids " . json_encode($SKU_or_bundle_name_variant_id);
                foreach ($map_variant_id as $fulfillment_order_id => $line_items) {
                    foreach ($line_items as $line_item_id => $item) {
                        if ((int)$item['variant_id'] !== (int)$SKU_or_bundle_name_variant_id[$sku_id_or_bundle_name] || !$item['quantity'])
                            continue;
                        $quantity_for_send = min($quantity, $item['quantity']);
                        $map_variant_id[$fulfillment_order_id][$line_item_id]['quantity'] -= $quantity_for_send; //Обновляем информацию сколько еще можно добавить позиций в этот line_item
                        $quantity -= $quantity_for_send; //Обновляем информацию сколько еще осталось единиц в упаковке
                        $data_for_send[$package_id]['line_items_by_fulfillment_order'][$fulfillment_order_id]['fulfillment_order_id'] = $fulfillment_order_id;
                        $data_for_send[$package_id]['line_items_by_fulfillment_order'][$fulfillment_order_id]['fulfillment_order_line_items'][] = ['id' => $line_item_id, 'quantity' => $quantity_for_send];
                    }
                }
                //Если на нашей стороне не смогли все единицы запаковать, значит что-то пошло не так
                if ($quantity)
                    return "Failed to pack. Order id $order_id. Package id $package_id. Map variant id " . json_encode($map_variant_id);
            }
        }
        return '';
    }

    private function getFulfilmentOrderFromOurLoc(int $external_id): array
    {
        $fulfillment_orders = [];
        foreach ($this->getConnector()->getFulfilmentOrdersGraphQL($external_id) ?? [] as $fulfilment_order) {
            if ((int)$this->getUserChannel()->getShopWarehouse()->getLocationId() === (int)$fulfilment_order['assigned_location_id'])
                $fulfillment_orders[] = $fulfilment_order;
        }
        return $fulfillment_orders;
    }

    private function checkSendAllQuantity(array $map_variant_id): bool
    {
        foreach ($map_variant_id as $line_items) {
            foreach ($line_items as $item) {
                //Если для shopify не смогли все единицы запаковать, значит что-то пошло не так
                if ($item['quantity']) {
                    return false;
                }
            }
        }
        return true;
    }

    private function getSimpleDataForTrackInfo(array $fulfillment_orders, array $item_for_send = []): array
    {
        $item_for_send['line_items_by_fulfillment_order'] = [];
        foreach ($fulfillment_orders as $fulfillment_order) {
            $item_for_send['line_items_by_fulfillment_order'][] = ['fulfillment_order_id' => $fulfillment_order['id']];
        }
        return $item_for_send;
    }

    private function sendTrackInfo(array $data_for_send, Order $order): bool
    {
        $shop_warehouse = ShopWarehouses::getByShopWarehouse($order->getShopId(), $order->getWarehouseId());
        foreach ($data_for_send as $item_for_send) {
            $item_for_send['line_items_by_fulfillment_order'] = array_values($item_for_send['line_items_by_fulfillment_order']);
            $item_for_send['notify_customer'] = $shop_warehouse->getNotify();
            $response = $this->getConnector()->setTrackInfoNewGraphQL($item_for_send);
            if (!in_array($this->getConnector()->getLastResultCode(), [200, 201, 202])) {
                Debug::sendDebugInfo('Shopify create fulfilment error ' . $this->getConnector()->getLastResultCode(), "Order {$order->getId()}\n {$this->getConnector()->getLastResultError()}", Debug::TEST);
                throw new ChannelException("Order {$order->getId()}\n {$this->getConnector()->getLastResultError()}", $this->getConnector()->getLastResultCode());
            }
            $id = $response['fulfillment']['id'] ?? '';
            if ($id) {
                $this->getConnector()->setFulfillmentEventCreateGraphQL($id);
                if (!in_array($this->getConnector()->getLastResultCode(), [200, 201, 202])) {
                    Debug::sendDebugInfo('Shopify create event fulfilment error ' . $this->getConnector()->getLastResultCode(), "Order {$order->getId()}\n {$this->getConnector()->getLastResultError()}", Debug::TEST);
                    throw new ChannelException("Order {$order->getId()}\n {$this->getConnector()->getLastResultError()}", $this->getConnector()->getLastResultCode());
                }
            } else {
                Debug::sendDebugInfo('Shopify create fulfilment error empty id', "Order {$order->getId()}\n " . json_encode($response), Debug::TEST);
                throw new ChannelException("Order {$order->getId()}\n " . json_encode($response), 0);
            }
        }
        return true;
    }

    public function getLocations()
    {
        if (empty($this->getUserChannel()->getShopWarehouse()->getCache())){
            $locations = $this->getConnector()->getLocations();
            if (is_array($locations) && !empty($locations)) {
                $this->getUserChannel()->getShopWarehouse()->setCache($locations);
                $this->getUserChannel()->getUserChannel()->saveShopWarehouse(['cache']);
            }
        }
        return $this->getUserChannel()->getShopWarehouse()->getCache();
    }

    public function getExternalLink($SKU_id, $shop_id, $product_id, $variant_id)
    {
        $product = $this->getProductExternalLink(ShopifyConnector::INTEGRATION_NAME, $SKU_id, $shop_id, $product_id, $variant_id);
        if ($product === false || empty($product['handle']))
            return false;
        return "https://" . $this->connector->getCredentials()->getFullShopUrl() . "/products/{$product['handle']}?variant=$variant_id";
    }

    /** @throws ChannelException */
    public function getProductInfo($shop_id, $product_id, $variant_id)
    {
        $this->setUserChanelById(ShopifyConnector::INTEGRATION_NAME, $shop_id);
        $product = $this->getConnector()->fetchByProductId(['product_id' => $product_id]);
        if (empty($product))
            throw new ChannelException('Product is not found');
        $product_arr = json_decode($product, true);
        if (empty($product_arr['products']))
            throw new ChannelException('Product is not found');
        $product = $product_arr['products'][0];
        if (!empty($variant_id)) {
            $handle = $product['handle'];
            $product_arr = $this->getConnector()->fetchByVariantIdGraphQL($variant_id);
            if (empty($product_arr['variant']))
                throw new ChannelException('Product is not found');
            $product = $product_arr['variant'];
            $product['handle'] = $handle;
        }
        return $product;
    }

    public function updateInventoryDaily($type, $wms_stock_raw, $stock)
    {
        $err_msg = [];
        foreach ($wms_stock_raw as $value) {
            if ($value['state'] != 1)
                continue;
            list(, $inventory_id, ) = explode('-', $value['external_id']);
            $wms_stock_number = (int)$value['available'];
            $stock_number = (int)($stock[$inventory_id] ?? 0);
            if (isset($stock[$inventory_id]) && $stock_number != $wms_stock_number) {
                if (Bundles::isBundleExists($this->getSupplier()->getId(), $value['customer_ref']))
                    continue;
                try{
                    $this->setInventoryDaily($value['id'], $inventory_id, $wms_stock_number - $stock_number, $type);
                } catch (\Exception $e) {
                    $err_msg[] = "$inventory_id: {$e->getMessage()}";
                }
            }
        }
        return $err_msg;
    }
}