<?php

namespace app\core\channels\woocommerce;

use app\backend\models\Bundles;
use app\backend\models\Deliveries;
use app\backend\models\InventorySync;
use app\backend\models\OrderHistories;
use app\backend\models\Orders;
use app\backend\models\SKUs;
use app\core\channels\BaseChannelProcessor;
use app\core\entities\Delivery;
use app\core\entities\Order;
use app\core\entities\Supplier;
use app\core\entities\SuppliersConfig;
use app\core\exceptions\ChannelException;
use app\core\helpers\Barcode128Rule;
use app\core\models\Orders as OrdersModel;
use app\core\models\OrdersOriginalRequest;
use app\core\models\SKUChannelSuppliers;
use app\core\models\SKUs as SKUsModel;
use app\core\models\WarehouseCarriers;
use app\core\statical\Sources;
use app\core\statical\WithDate;

class WooCommerceDataProcessor extends BaseChannelProcessor
{
    /** @var WooCommerceConnector */
    protected $connector;

    /** @return WooCommerceConnector */
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
        if (empty($product_list))
            return;
        foreach ($product_list as $product)
            $this->saveSingleProduct($product);
        $this->getUserChannel()->getShopWarehouse()->setTimeProducts(WithDate::startDay(time() - 60 * 60 * 24));
        $this->getUserChannel()->getUserChannel()->saveShopWarehouse(['last_upload']);
    }

    public function processSingleProduct($product_id)
    {
        $product_ids = explode('_', $product_id);
        if (count($product_ids) == 2)
            $product_id = $product_ids[0];
        $product_variants = $this->connector->fetchSingleProduct($product_id);
        if (empty($product_variants))
            return;
        foreach ($product_variants as $product)
            $this->saveSingleProduct($product);
    }

    public function prepareExternalIds($external_ids)
    {
        $var_res = [];
        foreach ($external_ids as $key => $external_id) {
            $tmp = explode('_', $external_id);
            if (count($tmp) != 1) {
                $var_res[$tmp[0]][] = $tmp[1];
                unset($external_ids[$key]);
            }
        }
        return [$external_ids, $var_res];
    }

    private function saveSingleProduct($product)
    {
        if (!$this->checkDelay($product['date_created']))
            return;
        $external_id = $product['external_id'];
        if (SKUChannelSuppliers::getByExternalShopId($external_id, $this->getUserChannel()->getShop()->getId()))
            return;
        unset($product['external_id']);
        $is_valid_barcode = Barcode128Rule::validate($product['barcode']);
        if (!$is_valid_barcode) {
            $external_info = explode('_', $external_id);
            $product['barcode'] = $this->connector->getUniqueBarcode(
                $this->getUserChannel()->getShop()->getId(),
                $external_info[1] ?? $external_info[0]
            );
        }
        $SKU = SKUsModel::getByBarcodeAndSupplier($product['barcode'], $this->getUserChannel()->getChannelSupplier()->getSupplierId());
        if (empty($SKU)) {
            $supplier_id = $this->getUserChannel()->getChannelSupplier()->getSupplierId();
            $product['cost_currency'] = $product['market_currency'] = $this->connector->getBaseCurrency();
            $product['supplier_id'] = $supplier_id;
            $product['ns_pid'] = $this->resolveNsPidFromMetaData($supplier_id, $product['meta_data'] ?? [], $product['ns_pid'] ?? '');
            unset($product['meta_data']);
            $res = SKUs::addSKU($product, [$product['barcode']], $this->getUserChannel()->getShopWarehouse()->getWarehouseId(), $this->getUserChannel()->getChannelSupplier()->getUserId());
            if (!$res[0])
                return;
        }
        SKUChannelSuppliers::addConnectionSKU(empty($SKU) ? $res[1] : $SKU->getId(),
            $this->getUserChannel()->getShop()->getId(),
            $external_id,
            $this->getUserChannel()->getChannelSupplier()->getUserId(),
            $this->getUserChannel()->getShopWarehouse()->getWarehouseId());
    }

    /**
     * ABF-475 EU PID: resolves NS-PID from the supplier's configured WooCommerce custom field name.
     * Empty setting = keep the SKU default; a configured name that isn't found on the product = null (empty).
     */
    private function resolveNsPidFromMetaData(int $supplier_id, array $meta_data, string $default_ns_pid): string
    {
        $attribute = trim(SuppliersConfig::readConfigBySupplierId($supplier_id, SuppliersConfig::TYPE_WOOCOMMERCE_NS_PID_ATTRIBUTE));
        if ($attribute === '')
            return $default_ns_pid;
        foreach ($meta_data as $meta)
            if (($meta['key'] ?? null) === $attribute)
                return (string)($meta['value'] ?? '');
        return '';
    }

    /**
     * Processing all orders info coming from the Connector.
     * This method is called via ChannelRobot
     */
    public function processOrders($params = [])
    {
        $orders_list = $this->connector->fetchAllOrders();
        if (empty($orders_list))
            return false;
        foreach ($orders_list as $orderWC)
            $this->processSingleOrder($orderWC, $this->getUserChannel()->getShop()->getCredentials()['shop_url']);
        return true;
    }

    public function getOrder($order_number)
    {
        $order = $this->getConnector()->fetchByOrderId($order_number);
        if (!empty($order))
            return $order[0]['original_response'];
        return null;
    }

    private function separateProducts($products_data)
    {
        $products = [];
        $variations = [];
        array_map(function($item) use (&$products, &$variations) {
            $product_ids = explode('_', $item['SKU_id']);
            if (count($product_ids) == 1)
                $products[] = $item['SKU_id'];
            else
                $variations[$product_ids[0]][] = $product_ids[1];
        }, $products_data);
        return [$products, $variations];
    }

    private function isProducts($products_data)
    {
        list($product_ids, $variation_ids) = $this->separateProducts($products_data);
        if (!empty($product_ids)) {
            $products = $this->getConnector()->getProductsByIds($product_ids);
            foreach ($products as $product)
                if (!$this->checkDelay($product['date_created']))
                    return false;
        }
        if (!empty($variation_ids))
            foreach($variation_ids as $product_id => $variation_id) {
                $variations = $this->getConnector()->getProductVariantsByIds($product_id, $variation_id);
                foreach ($variations as $variation)
                    if (!$this->checkDelay($variation['date_created']))
                        return false;
            }
        return true;
    }

    public function processSingleOrder($order_data, $shop_name)
    {
        if (!$this->checkDelay($order_data['order']['create_date'], self::ORDER_SAVE_DELAY))
            return;
        if (!$this->isProducts($order_data['products']))
            return;
        $order_by_refs = OrdersModel::getByRefAndRef2($order_data['order']['ref'], $order_data['order']['ref2'], $this->getUserChannel()->getShop()->getId());
        if ($order_by_refs)
            return;
        $delivery = new Delivery($order_data['delivery']);
        $delivery_id = Deliveries::handleDeliveryReturningId($delivery, $this->getUserChannel()->getUserChannel()->getUserId());
        $channel_shipping_code = $order_data['order']['shipping_lines'];
        unset($order_data['order']['shipping_lines']);
        $order_data['order']['delivery_id'] = $delivery_id;
        $order_data['order']['status'] = Order::NEW_NUM;                               // new
        $order_data['order']['user_id'] = $this->getUserChannel()->getChannelSupplier()->getUserId();
        $order_data['order']['shop_id'] = $this->getUserChannel()->getShop()->getId();
        $order_data['order']['warehouse_id'] = $this->getUserChannel()->getShopWarehouse()->getWarehouseId();
        $order_data['order']['supplier_id'] = $this->getUserChannel()->getChannelSupplier()->getSupplierId();
        $order_data['order']['type_create'] = Sources::SOURCE_WOOCOMMERCE;
        $carriers = WarehouseCarriers::getAvailableCarrierList($this->getUserChannel()->getShopWarehouse()->getWarehouseId());
        if (empty($carriers))
            return;
        $order_data['order']['carrier_id'] = $carriers[0]['id'];
        $res = Orders::createOrder($order_data['order'], [], $order_data['products'], [$this, 'prepareSKUs'], $this);
        /** @var Order $order */
        $order = $res[2];
        if (!empty($order)) {
            OrdersOriginalRequest::saveOriginalRequest($order->getId(), '', $order_data['original_response']);
            OrderHistories::addCommentOrder($order->getId(), $order->getUserId(),"Order downloaded from WooCommerce ($shop_name)");
            if ($order->error())
                return;
            if (!$this->checkShippingMapping($order, $channel_shipping_code))
                return;
        }
        if (!$res[0])
            return;
        $unknown_skus = $res[3][1];
        if (!empty($unknown_skus)) {
            $comment = "Unrecognized SKUs found: ";
            foreach ($unknown_skus as $unknown_sku)
                $comment .= "{$unknown_sku['name']} ({$unknown_sku['sku']}) #{$unknown_sku['SKU_id']}. ";
            OrdersModel::changeStatus($order->getId(), Order::ERROR, $order->getUserId(), $comment);
            return;
        }
        Orders::creationOrder($order, $res[3][0], $this->getUserChannel()->getChannelSupplier()->getUserId());
    }

    public function prepareSKUs(Order $order, $quantities, WooCommerceDataProcessor $params)
    {
        $order_SKUs = [];
        $unknown_SKUs = [];
        $bundle = false;
        foreach ($quantities as $product) {
            $bundle_contents = Bundles::getBundleInfo([$product['name'], $product['sku']], $params->getUserChannel()->getChannelSupplier()->getSupplierId());
            $item_quantity = (int) $product['quantity'];
            $item_quantity = $item_quantity < 1 ? 1 : $item_quantity;
            if ($bundle_contents) {
                $bundle = true;
                $bundle_id = 0;
                $price = $product['price'];
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
            $SKU_id = SKUChannelSuppliers::getByExternalShopId($product['SKU_id'], $params->getUserChannel()->getShop()->getId());
            if (!$SKU_id) {
                $this->processSingleProduct($product['SKU_id']);
                $SKU_id = SKUChannelSuppliers::getByExternalShopId($product['SKU_id'], $params->getUserChannel()->getShop()->getId());
            }
            if ($SKU_id) {
                if (empty($order_SKUs[$SKU_id]))
                    $order_SKUs[$SKU_id]['qty'] = $product['quantity'];
                else
                    $order_SKUs[$SKU_id]['qty'] += $product['quantity'];
                $order_SKUs[$SKU_id]['price'] = $product;
            } else {
                $unknown_SKUs[] = [
                    'sku' => $product['sku'],
                    'name' => $product['name'],
                    'SKU_id' => $product['SKU_id'],
                ];
            }
        }
        foreach ($order_SKUs as $SKU_id => &$product)
            if (is_array($product['price']))
                $this->setPrice($params->getSupplier(), $SKU_id, $product, $unknown_SKUs);
        return [true, [$order_SKUs, $unknown_SKUs], $bundle];
    }

    private function setPrice(Supplier $supplier, $SKU_id, &$SKU_data, &$unknown_SKUs)
    {
        if ($supplier->getProductPrice()) {
            $SKU = SKUsModel::getById($SKU_id);
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
        $SKU_data['price'] = $SKU_data['price']['price'];
    }

    public function setInventoryDaily($SKU_id, $external_id, $quantity, $type)
    {
        $error = null;
        try {
            $this->getConnector()->setInventory(
                $external_id,
                $quantity
            );
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        $this->storeInventoryLevel($SKU_id, $quantity, [
            'user_id' => -1,
            'external_id' => $external_id,
            'error' => substr($error,0,1) == '{' ? json_decode($error, true) : $error,
        ], $type);
    }

    public function setInventoryManual($params)
    {
        $error = null;
        try {
            $this->getConnector()->setInventory(
                $params['inventory_id'],
                $params['available']
            );
        } catch (\Exception $e) {
            throw $e;
        }
        $this->storeInventoryLevel($params['SKU_id'], $params['available'], [
            'user_id' => $params['user_id'],
            'external_id' => $params['inventory_id'],
        ], InventorySync::SYNC_MANUAL);
    }

    /** @throws ChannelException */
    public function getInventoryStockQuantity($channel_name, $inventory_id)
    {
        $external_info = explode('_', $inventory_id);
        $shop_inventory_id = count($external_info) == 2 ? $external_info[0] : $inventory_id;
        $data_inventory = InventorySync::getInventoryManual(['name' => $channel_name], $this->getUserChannel(), [$shop_inventory_id]);
        return (int)($data_inventory[$inventory_id] ?? 0);
    }

    public function setInventory($inventory_id, $SKU_id, $quantity_available, $params, $source)
    {
        $stock_quantity = $this->getInventoryStockQuantity(WooCommerceConnector::INTEGRATION_NAME, $inventory_id);
        if ($quantity_available - $stock_quantity === 0)
            return false;
        $error = null;
        $code = 0;
        try {
            $this->getConnector()->setInventory(
                $inventory_id,
                $quantity_available
            );
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $code = $e->getCode();
        } finally {
            $this->storeInventoryLevel($SKU_id, $quantity_available, array_merge(empty($params['type']) ? $params : [], [
                'external_id' => $inventory_id,
                'error' => substr($error,0,1) == '{' ? json_decode($error, true) : $error,
            ]), $params['type'] == InventorySync::SYNC_SKU ? $params['type'] : ($source ?? null));
        }
        return ['msg' => $error, 'code' => $code];
    }

    public function setTrackInfo(array $params)
    {
        $order = Orders::getById($params['order_id']);
        if (empty($order))
            throw new ChannelException("Not found order #{$params['order_id']}");
        if(!$this->getUserChannel() || $order->getShopId() !=  $this->getUserChannel()->getShop()->getId())
            $this->setUserChanelById(WooCommerceConnector::INTEGRATION_NAME, $order->getShopId());
        return $this->connector->setOrderStatus($order->getExternalId());
    }

    public function getExternalLink($SKU_id, $shop_id, $product_id, $variation_id)
    {
        $product = $this->getProductExternalLink(WooCommerceConnector::INTEGRATION_NAME, $SKU_id, $shop_id, $product_id, $variation_id);
        if ($product === false)
            return false;
        return (empty($product) || empty($product['permalink'])) ? false : $product['permalink'];
    }

    public function getProductInfo($shop_id, $product_id, $variation_id)
    {
        $this->setUserChanelById(WooCommerceConnector::INTEGRATION_NAME, $shop_id);
        $product = $this->getConnector()->getOriginalProduct($product_id, $variation_id);
        if (empty($product))
            throw new ChannelException('Product is not found');
        return $product;
    }
}