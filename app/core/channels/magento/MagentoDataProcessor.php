<?php

namespace app\core\channels\magento;

use app\backend\models\Bundles;
use app\backend\models\Carriers;
use app\backend\models\Deliveries;
use app\backend\models\InventorySync;
use app\backend\models\OrderHistories;
use app\backend\models\Orders;
use app\backend\models\Shippings;
use app\core\channels\BaseChannelProcessor;
use app\core\entities\Delivery;
use app\core\entities\Order;
use app\core\entities\SKU;
use app\core\entities\Supplier;
use app\core\entities\SuppliersConfig;
use app\core\exceptions\ChannelException;
use app\core\helpers\Barcode128Rule;
use app\core\models\OrdersOriginalRequest;
use app\core\models\ShippingServices;
use app\core\models\SKUChannelSuppliers;
use app\backend\models\SKUs;
use app\core\models\WarehouseCarriers;
use app\core\models\Warehouses;
use app\core\statical\Sources;
use app\core\statical\WithDate;

class MagentoDataProcessor extends BaseChannelProcessor
{
    /** @var MagentoConnector */
    protected $connector;
    private $currency;

    /** @return MagentoConnector */
    public function getConnector()
    {
        return $this->connector;
    }

    /**
     * Processing all products info coming from the Connector.
     * This method is called via ChannelRobot
     */
    public function processProducts()
    {
        $product_list = $this->getConnector()->fetchAllProducts([
            'time_products' => $this->getUserChannel()->getShopWarehouse()->getTimeProducts(),
        ]);
        if (empty($product_list) || !is_array($product_list))
            return;
        $warehouse = Warehouses::getById($this->getUserChannel()->getShopWarehouse()->getWarehouseId());
        if (empty($warehouse) || empty($warehouse->getCurrencyId()))
            return;
        $currency = $this->getConnector()->getBaseCurrency();
        if (empty($currency))
            $currency = $warehouse->getCurrencyId();
        foreach ($product_list as $product)
            $this->processSingleProduct($product + ['currency_id' => $currency]);
        $this->getUserChannel()->getShopWarehouse()->setTimeProducts(WithDate::startDay(time() - 86400 * 7));
        $this->getUserChannel()->getUserChannel()->saveShopWarehouse(['last_upload']);
    }

    public function processSingleProduct(array $product_data)
    {
        if (empty($product_data))
            return;
        $shop = $this->getUserChannel()->getShop();
        $SKU_id = SKUChannelSuppliers::getByExternalShopId($product_data['id'], $shop->getId());
        if ($SKU_id)
            return;
        if (!$this->checkDelay($product_data['created_at']))
            return;
        $product = [];
        $is_valid_barcode = Barcode128Rule::validate($product_data['sku']);
        $product['barcode'] = !$is_valid_barcode ? $this->getConnector()->getUniqueBarcode($shop->getId(), $product_data['id']) : $product_data['sku'];
        $SKU = SKUs::getByBarcodeAndSupplier($product['barcode'], $this->getSupplier()->getId());
        if (empty($SKU)) {
            if (isset($product_data['media_gallery_entries']) && !empty($product_data['media_gallery_entries'][0]['file']))
                $photoSrc = "https://{$this->getConnector()->getCredentials()->getShopUrl()}/media/catalog/product{$product_data['media_gallery_entries'][0]['file']}";
            $product['cost_currency'] = $product['market_currency'] = $product_data['currency_id'];
            $product['supplier_id'] = $this->getSupplier()->getId();
            $product['name'] = addslashes($product_data['name']);
            $product['weight'] = $product_data['weight'] ?? 0;
            $product['cost_value'] = $product['market_value'] = $product_data['price'] ?? 0;
            $product['state'] = SKU::ACTIVE_NUM;
            $product['customer_ref'] = $product_data['sku'];
            $product['photo_src'] = addslashes($photoSrc ?? '');
            // ABF-475 EU PID: custom_attributes is already returned by the standard Magento REST product response
            $custom_attributes = $product_data['custom_attributes'] ?? [];
            $product['ns_pid'] = $this->resolveCustomAttribute($this->getSupplier()->getId(), $custom_attributes, SuppliersConfig::TYPE_MAGENTO_NS_PID_ATTRIBUTE, $product_data['sku'] ?? '');
            $product['s_pid'] = $this->resolveCustomAttribute($this->getSupplier()->getId(), $custom_attributes, SuppliersConfig::TYPE_MAGENTO_S_PID_ATTRIBUTE, '');
            $res = SKUs::addSKU($product, [$product['barcode']], $this->getUserChannel()->getShopWarehouse()->getWarehouseId(), $this->getUserChannel()->getChannelSupplier()->getUserId());
            if (!$res[0])
                return;
        }
        SKUChannelSuppliers::addConnectionSKU(empty($SKU) ? $res[1] : $SKU->getId(),
            $this->getUserChannel()->getShop()->getId(),
            $product_data['id'],
            $this->getUserChannel()->getChannelSupplier()->getUserId(),
            $this->getUserChannel()->getShopWarehouse()->getWarehouseId());
    }

    /**
     * ABF-475 EU PID: resolves NS-PID/S-PID from the supplier's configured Magento custom attribute code.
     * Empty setting = keep the default; a configured code that isn't found on the product = null (empty).
     */
    private function resolveCustomAttribute(int $supplier_id, array $custom_attributes, string $config_type, string $default): string
    {
        $attribute_code = trim(SuppliersConfig::readConfigBySupplierId($supplier_id, $config_type));
        if ($attribute_code === '')
            return $default;
        foreach ($custom_attributes as $attribute)
            if (($attribute['attribute_code'] ?? null) === $attribute_code)
                return (string)($attribute['value'] ?? '');
        return '';
    }

    /**
     * Processing all orders info coming from the Connector.
     * This method is called via ChannelRobot
     */
    public function processOrders($params = [])
    {
        $order_list = $this->getConnector()->fetchAllOrders([
            'time_orders' => $this->getUserChannel()->getShopWarehouse()->getTimeOrders(),
        ]);
        if (empty($order_list) || !is_array($order_list))
            return false;
        $warehouse = Warehouses::getById($this->getUserChannel()->getShopWarehouse()->getWarehouseId());
        if (empty($warehouse) || empty($warehouse->getCurrencyId()))
            return false;
        $this->currency = $this->getConnector()->getBaseCurrency();
        if (empty($this->currency))
            $this->currency = $warehouse->getCurrencyId();
        foreach ($order_list as $order)
            $this->processSingleOrder($order);
        $this->getUserChannel()->getShopWarehouse()->setTimeOrders(WithDate::startDay(time() - 86400 * 7));
        $this->getUserChannel()->getUserChannel()->saveShopWarehouse(['last_upload']);
        return true;
    }

    public function processSingleOrder(array $order_arr)
    {
        if (empty($order_arr))
            return false;
        if (Orders::getByRefAndRef2($order_arr['entity_id'], $order_arr['increment_id'], $this->getUserChannel()->getShop()->getId()))
            return false;
        if (!$this->checkDelay($order_arr['created_at'], self::ORDER_SAVE_DELAY))
            return false;
        $products_ids = array_map(static function ($item) { return $item['product_id']; }, $order_arr['items']);
        $order_products = $this->getConnector()->fetchAllProducts([
            'ids' => $products_ids,
        ]);
        foreach ($order_products as $order_product)
            if (!$this->checkDelay($order_product['created_at']))
                return false;
        $carriers = WarehouseCarriers::getAvailableCarrierList($this->getUserChannel()->getShopWarehouse()->getWarehouseId());
        if (empty($carriers))
            return false;
        $delivery = $order_arr['extension_attributes']['shipping_assignments'][0]['shipping']['address'] ?? ($order_arr['billing_address'] ?? []);
        if (empty($delivery))
            return false;
        $delivery_data = [
            'contact' => ($delivery['firstname'] ?? '') . ' ' . ($delivery['lastname'] ?? ''),
            'phone' => $delivery['telephone'] ?? '',
            'town' => $delivery['city'] ?? '',
            'country_id' => empty($delivery['country_id']) ? '' : ($delivery['country_id'] === 'GB' ? 'UK' : $delivery['country_id']),
            'postcode' => $delivery['postcode'] ?? '',
            'email' => $delivery['email'] ?? '',
            'county' => $delivery['region'] ?? '',
            'address1' => $delivery['street'][0] ?? '',
            'address2' => $delivery['street'][1] ?? '',
            'address3' => $delivery['street'][2] ?? '',
        ];
        $delivery = new Delivery($delivery_data);
        $delivery_id = Deliveries::handleDeliveryReturningId($delivery, $this->getUserChannel()->getUserChannel()->getUserId());
        $order_data = [
            'delivery_id' => $delivery_id,
            'carrier_id' => $carriers[0]['id'],
            'create_date' => $order_arr['created_at'],
            'despatch_date' => $order_arr['updated_at'],
            'ref' => $order_arr['entity_id'],
            'external_id' => $order_arr['entity_id'],
            'ref2' => $order_arr['increment_id'],
            'user_id' => $this->getUserChannel()->getChannelSupplier()->getUserId(),
            'status' => Order::NEW_NUM,
            'supplier_id' => $this->getSupplier()->getId(),
            'type_create' => Sources::SOURCE_MAGENTO,
            'warehouse_id' => $this->getUserChannel()->getShopWarehouse()->getWarehouseId(),
            'shop_id' => $this->getUserChannel()->getShop()->getId(),
            'currency' => $order_arr['order_currency_code'],
        ];
        $res = Orders::createOrder($order_data, [], $order_arr['items'], [$this, 'prepareSKUs'], $this);
        /** @var Order $order */
        $order = $res[2];
        if (!empty($order)) {
            $credentials = $this->getUserChannel()->getShop()->getCredentials();
            OrdersOriginalRequest::saveOriginalRequest($order->getId(), '', json_encode($order_arr));
            OrderHistories::addCommentOrder($order->getId(), $order->getUserId(), "Order downloaded from Magento ({$credentials['shop_url']})");
            if ($order->error())
                return false;
            if (!$this->checkShippingMapping($order, $order_arr['shipping_description']))
                return false;
        }
        if (!$res[0])
            return false;
        $unknown_skus = $res[3][1];
        if (!empty($unknown_skus)) {
            $comment = "Unrecognized SKUs found: ";
            foreach ($unknown_skus as $unknown_sku)
                $comment .= "{$unknown_sku['name']} ({$unknown_sku['sku']}). ";
            Orders::changeStatus($order->getId(), Order::ERROR, $order->getUserId(), $comment);
            return false;
        }
        Orders::creationOrder($order, $res[3][0], $this->getUserChannel()->getChannelSupplier()->getUserId());
        return true;
    }

    public function prepareSKUs(Order $order, $products, MagentoDataProcessor $params)
    {
        $configurables = [];
        $bundles = [];
        $bundle = false;
        foreach ($products as $key => $item) {
            if ($item['product_type'] === MagentoConnector::CONFIGURABLE) {
                $configurables[$item['sku']] = $item;
                unset($products[$key]);
            } elseif ($item['product_type'] === MagentoConnector::BUNDLE) {
                $bundles[$item['sku']] = $item;
                unset($products[$key]);
            }
        }
        foreach ($products as &$item)
            if (!empty($configurables[$item['sku']])) {
                $item['qty_ordered'] = $configurables[$item['sku']]['qty_ordered'];
                $item['price'] = $configurables[$item['sku']]['price'];
            }
        unset($item, $configurables);
        $order_SKUs = [];
        $unknown_SKUs = [];
        foreach ($products as $item) {
//            if ($item['product_type'] === MagentoConnector::BUNDLE)
            $bundle_contents = Bundles::getBundleInfo([$item['sku']], $params->getUserChannel()->getChannelSupplier()->getSupplierId());
            $item_quantity = (int) $item['qty_ordered'];
            $item_quantity = $item_quantity < 1 ? 1 : $item_quantity;
            if ($bundle_contents) {
                $bundle = true;
                $bundle_id = 0;
                $price = $this->getPrice($item, $item_quantity);
                $price_bundle = array_sum(array_map(function($el) { return $el['quantity']*$el['market_value']; }, $bundle_contents));
                $koeff = $price / $price_bundle;
                foreach ($bundle_contents as $bundle_content) {
                    $bundle_id = $bundle_content['id'];
                    if (empty($order_SKUs[$bundle_content['SKU_id']]))
                        $order_SKUs[$bundle_content['SKU_id']]['qty'] = $bundle_content['quantity'] * $item_quantity;
                    else
                        $order_SKUs[$bundle_content['SKU_id']]['qty'] += $bundle_content['quantity'] * $item_quantity;
                    $order_SKUs[$bundle_content['SKU_id']]['price'] = $bundle_content['market_value'] * $koeff;
                }
                Bundles::addToOrder($order->getId(), $bundle_id, $item_quantity, $order->getUserId());
                continue;
            }
            $SKU_id = SKUChannelSuppliers::getByExternalShopId($item['product_id'], $params->getUserChannel()->getShop()->getId());
            if (!$SKU_id) {
                $this->fetchByProductId($item['product_id']);
                $SKU_id = SKUChannelSuppliers::getByExternalShopId($item['product_id'], $params->getUserChannel()->getShop()->getId());
            }
            if ($SKU_id) {
                if (empty($order_SKUs[$SKU_id]))
                    $order_SKUs[$SKU_id]['qty'] = $item['qty_ordered'];
                else
                    $order_SKUs[$SKU_id]['qty'] += $item['qty_ordered'];
                $order_SKUs[$SKU_id]['price'] = $item;
            } else {
                $unknown_SKUs[] = [
                    'sku' => $item['sku'],
                    'name' => $item['name'],
                    'SKU_id' => $item['product_id'],
                ];
            }
        }
        foreach ($order_SKUs as $SKU_id => &$item)
            if (is_array($item['price']))
                $this->setPrice($params->getSupplier(), $SKU_id, $item, $unknown_SKUs);
        return [true, [$order_SKUs, $unknown_SKUs], $bundle];
    }

    private function getPrice($item, $quantity)
    {
        return $item['price'];
    }

    private function setPrice(Supplier $supplier, $SKU_id, &$SKU_data, &$unknown_SKUs)
    {
        if ($supplier->getProductPrice()) {
            $SKU = SKUs::getById($SKU_id);
            if (empty($SKU)) {
                $unknown_SKUs[] = [
                    'sku' => $SKU_data['sku'],
                    'name' => $SKU_data['name'],
                    'SKU_id' => $SKU_data['SKU_id'],
                ];
                unset($SKU_data);
            } else
                $SKU_data['price'] = $SKU->getMarketValue();
            return;
        }
        $SKU_data['price'] = $this->getPrice($SKU_data['price'], $SKU_data['qty']);
    }

    public function fetchByProductId($product_id)
    {
        try {
            $product = $this->getConnector()->fetchProduct($product_id);
            if (empty($product))
                return;
            $this->processSingleProduct($product + ['currency_id' => $this->currency]);
        } catch (\Exception $e) { }
    }

    public function setInventory($inventory_id, $SKU_id, $quantity_available, $params, $source)
    {
        $stock_quantity = $this->getInventoryStockQuantity(MagentoConnector::INTEGRATION_NAME, $inventory_id);
        if ($quantity_available - $stock_quantity === 0)
            return false;
        $error = null;
        try {
            $SKU = SKUs::getById($SKU_id);
            if (empty($SKU))
                throw new ChannelException('SKU is not found');
            $this->getConnector()->setInventory(
                $inventory_id,
                $quantity_available,
                $SKU->getCustomerRef()
            );
        } catch (\Exception $e) {
            $error = $this->getError($e);
        } finally {
            $this->storeInventoryLevel($SKU_id, $quantity_available, array_merge(empty($params['type']) ? $params : [], [
                'external_id' => $inventory_id,
                'error' => $error,
            ]), $params['type'] == InventorySync::SYNC_SKU ? $params['type'] : ($source ?? null));
        }
        return $error;
    }

    private function getError(\Exception $error)
    {
        $err = json_decode($error->getMessage(), true);
        $tmp = $err['message'];
        $num = 1;
        foreach ($err['parameters'] as $parameter) {
            $tmp = str_replace('%' . $num, $parameter, $tmp);
            $num++;
        }
        $tmp = base64_encode($tmp);
        return [
            'code' => $error->getCode(),
            'msg' => $tmp,
            'base64' => true,
        ];
    }

    public function getOrder($order_number)
    {
        return $this->getConnector()->fetchByOrderId($order_number);
    }

    public function getProductInfo($shop_id, $product_id, $variant_id)
    {
        $this->setUserChanelById(MagentoConnector::INTEGRATION_NAME, $shop_id);
        $product = $this->getConnector()->fetchProduct($product_id);
        if (empty($product))
            throw new ChannelException('Product is not found');
        if ($product['type_id'] == MagentoConnector::CONFIGURABLE) {
            if (empty($variant_id))
                throw new ChannelException('Product has variants');
            if (empty($product['extension_attributes']) || empty($product['extension_attributes']['configurable_product_links']))
                throw new ChannelException('Product is not recognized');
            if (!in_array($variant_id, $product['extension_attributes']['configurable_product_links']))
                throw new ChannelException('Data is not correct');
            $product = $this->getConnector()->fetchProduct($variant_id);
            if (empty($product) || $product['type_id'] != MagentoConnector::SIMPLE)
                throw new ChannelException('Product is not found or is not recognized');
            return $product;
        }
        if ($product['type_id'] == MagentoConnector::SIMPLE) {
            if (!empty($variant_id))
                throw new ChannelException('Product has not variants');
            return $product;
        }
        throw new ChannelException('Product is not recognized');
    }

    private function getPath($attributes)
    {
        if (empty($attributes))
            return false;
        $url_key = $url_path = '';
        foreach ($attributes as $attribute) {
            if ($attribute['attribute_code'] == 'url_key')
                $url_key = $attribute['value'];
            if ($attribute['attribute_code'] == 'category_ids') {
                $categories = [];
                foreach ($attribute['value'] as $category_id) {
                    $category = $this->connector->getCategories($category_id, 'id,parent_id');
                    if (empty($category))
                        return false;
                    $categories[] = json_decode($category, true);
                }
                $id = 0;
                while (!empty($categories))
                    foreach ($categories as $key => $category)
                        if (!in_array($category['parent_id'], $attribute['value']) || ($id == $category['parent_id'])) {
                            $id = $category['id'];
                            unset($categories[$key]);
                        }
                $category = $this->connector->getCategories($id);
                if (empty($category))
                    return false;
                $category = json_decode($category, true);
                foreach ($category['custom_attributes'] as $category_attribute)
                    if ($category_attribute['attribute_code'] == 'url_path')
                        $url_path = $category_attribute['value'];
            }
        }
        if (empty($url_key) || empty($url_path))
            return false;
        return "$url_path/$url_key.html";
    }

    public function getExternalLink($SKU_id, $shop_id, $product_id, $variant_id)
    {
        $product = $this->getProductExternalLink(MagentoConnector::INTEGRATION_NAME, $SKU_id, $shop_id, $product_id, $variant_id);
        if ($product === false || empty($product['custom_attributes']))
            return false;
        $path = $this->getPath($product['custom_attributes']);
        return "https://" . $this->connector->getCredentials()->getShopUrl() . "/$path";
    }

    public function setInventoryManual($params)
    {
        if (empty($params) || empty($params['inventory_id']) || empty($params['SKU_id']))
            throw new ChannelException("Empty or wrong params");
        try {
            $SKU = SKUs::getById($params['SKU_id']);
            if (empty($SKU))
                throw new ChannelException("SKU is not found");
            $this->getConnector()->setInventory(
                $params['inventory_id'],
                $params['available'],
                $SKU->getCustomerRef()
            );
        } catch (\Exception $e) {
            throw $e;
        }
        $this->storeInventoryLevel($params['SKU_id'], $params['available'], [
            'user_id' => $params['user_id'],
            'external_id' => $params['inventory_id'],
        ], InventorySync::SYNC_MANUAL);
    }

    public function setInventoryDaily($SKU_id, $external_id, $quantity, $type)
    {
        $error = null;
        try {
            $SKU = SKUs::getById($SKU_id);
            if (empty($SKU))
                throw new ChannelException("SKU is not found");
            $this->getConnector()->setInventory(
                $external_id,
                $quantity,
                $SKU->getCustomerRef()
            );
        } catch (\Exception $e) {
            $error = $this->getError($e);
        }
        $this->storeInventoryLevel($SKU_id, $quantity, [
            'user_id' => -1,
            'external_id' => $external_id,
            'error' => $error,
        ], $type);
    }

    /**
     * @param array $params
     * @return mixed
     * @throws ChannelException
     */
    public function setTrackInfo(array $params)
    {
        if (empty($params['order_id']))
            throw new ChannelException("Parameter 'order_id' is not found");
        $order = Orders::getById($params['order_id']);
        if (empty($order))
            throw new ChannelException("Not found order #{$params['order_id']}");
        if (!$this->getUserChannel() || $order->getShopId() != $this->getUserChannel()->getShop()->getId())
            $this->setUserChanelById(MagentoConnector::INTEGRATION_NAME, $order->getShopId(), $order->getWarehouseId());
        $carrier = Carriers::getById($order->getCarrierId());
        if (empty($carrier))
            throw new ChannelException('Carrier is not found');
        if (!$order->getShippingServicesId())
            return;
        $shipping_service = ShippingServices::getById($order->getShippingServicesId());
        if ($shipping_service->getIsUntracked())
            return;
        $original = OrdersOriginalRequest::getOriginalRequest($order->getId());
        if (empty($original) || empty($original['response']))
            throw new ChannelException('Original order is not found');
        $original = json_decode($original['response'], true)['items'];
        $products = [];
        foreach ($original as $product_data) {
            if ($product_data['product_type'] != MagentoConnector::SIMPLE)
                continue;
            $products[] = [
                'order_item_id' => $product_data['item_id'],
                'qty' => $product_data['qty_ordered'],
            ];
        }
        $shipments = Shippings::getByOrderId($order->getId());
        $tracking_info = [];
        array_map(function($item) use(&$tracking_info, $carrier) {
            $tracking_info[] = [
                'carrier_code' => $carrier->getCode(),
                'title' => $item['reference'],
                'track_number' => $item['tracking_number'],
            ];
        }, $shipments);
        return $this->getConnector()->setTrackInfo($order->getExternalId(), $products, $tracking_info);
    }
}
