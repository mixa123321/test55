<?php

namespace app\core\channels;

use app\backend\models\ASNs;
use app\backend\models\ASNSKUs;
use app\backend\models\Bundles;
use app\backend\models\InventorySync;
use app\backend\models\OrderHistories;
use app\backend\models\Orders;
use app\backend\models\OrderSKUs;
use app\backend\models\Shippings;
use app\backend\models\SKULocations;
use app\backend\models\SupplierNotifications;
use app\backend\models\Suppliers;
use app\core\dbal\SafeMysql;
use app\core\entities\Order;
use app\core\entities\ShopInventoryQueue;
use app\core\entities\Supplier;
use app\core\entities\SuppliersConfig;
use app\core\ErrorHandler;
use app\core\exceptions\ChannelException;
use app\core\models\Channels;
use app\core\models\Shops;
use app\core\models\SKUChannelSuppliers;

/**
 * Processor child classes must implement all system related logic while getting data from Connector
 * Data flow scheme is: Integration -> Connector -> Processor
 */
abstract class BaseChannelProcessor
{
    /** @var int in minutes */
    public const PRODUCT_SAVE_DELAY = 10;
    /** @var int in minutes */
    public const ORDER_SAVE_DELAY = 10;

    private const NOTIFICATION_SKIP_ERROR = [
        403,
        404,
        422
    ];

    /** @var Shops */
    protected $user_channel;
    /** @var BaseChannelConnector */
    protected $connector;
    /** @var Supplier */
    protected $supplier;
    protected $supplier_settings = [];

    public function getSupplier()
    {
        return $this->supplier;
    }

    public function setSupplier(Supplier $supplier)
    {
        $this->supplier = $supplier;
        $this->supplier_settings = SuppliersConfig::readConfigsBySupplierId((int)$this->supplier->getId());
    }

    public function getConnector()
    {
        return $this->connector;
    }

    public function getSupplierSettings(): array
    {
        return $this->supplier_settings;
    }

    public function setConnector(BaseChannelConnector $connector)
    {
        $this->connector = $connector;
    }

    public function getUserChannel()
    {
        return $this->user_channel;
    }

    public function setUserChannel(Shops $user_channel)
    {
        $this->user_channel = $user_channel;
    }

    private function getType($params)
    {
        if (!empty($params['order_id']) && (int)$params['order_id'] > 0)
            return InventorySync::SYNC_ORDER;
        elseif (!empty($params['asn_id']) && (int)$params['asn_id'] > 0)
            return InventorySync::SYNC_ASN;
        elseif (!empty($params['SKU_id']) && (int)$params['SKU_id'] > 0)
            return InventorySync::SYNC_PUTAWAY;
        elseif (!empty($params['user_id'])) {
            if ((int)$params['user_id'] > 0)
                return InventorySync::SYNC_MANUAL;
            else
                return InventorySync::SYNC_DAILY;
        }
        return false;
    }

    abstract public function processOrders($params);
    abstract public function processProducts();
    abstract public function getOrder($order_number);
    /** @throws ChannelException */
    abstract public function getProductInfo($shop_id, $product_id, $addition_id);
    /** @throws ChannelException */
    abstract public function setInventory($inventory_id, $SKU_id, $quantity_available, $params, $source);
    abstract public function setInventoryManual($params);
    abstract public function setInventoryDaily($SKU_id, $external_id, $quantity, $type);
    /** @throws ChannelException */
    abstract public function setTrackInfo(array $params);

    /** @throws ChannelException */
    public function setInventoryQueue(ShopInventoryQueue $inventory)
    {
        $SKU_id = SKUChannelSuppliers::getByExternalShopId($inventory->getItemId(), $this->getUserChannel()->getShop()->getId());
        if (empty($SKU_id))
            return [
                'msg' => "#{$inventory->getItemId()}: SKU connection is absent for with shop ({$inventory->getShopWarehouseId()}",
                'code' => 0
            ];
        return $this->setInventory($inventory->getItemId(), $SKU_id, $inventory->getAvailable(), ['type' => InventorySync::SYNC_SKU], $inventory->getSource());
    }

    /** @throws ChannelException */
    public function getInventoryStockQuantity($channel_name, $inventory_id)
    {
        $data_inventory = InventorySync::getInventoryManual(['name' => $channel_name], $this->getUserChannel(), [$inventory_id]);
        return (int)($data_inventory[$inventory_id] ?? 0);
    }

    protected function getProductExternalLink($channel, $SKU_id, $shop_id, $product_id, $variant_id)
    {
        $SKU_id = (int)$SKU_id;
        $shop_id = (int)$shop_id;
        $product_id = (int)$product_id;
        $variant_id = (int)$variant_id;
        $external_id = SKUChannelSuppliers::getSqlExternalId($channel, $product_id, $variant_id);
        $SKU_connection_info = SKUChannelSuppliers::getConnection($shop_id, $SKU_id, $external_id);
        $external_id = $SKU_connection_info['external_id'];
        if (empty($external_id))
            return false;
        try {
            $product = $this->getProductInfo($shop_id, $product_id, $variant_id);
        } catch (\Exception $e) {
            return false;
        }
        return $product;
    }

    protected function storeInventoryLevel($SKU_id, $quantity, $params, $type = null)
    {
        if (empty($SKU_id))
            return;
        if (empty($type))
            if (($type = self::getType($params)) === false)
                return;
        $object_id = $params[strtolower($type) . '_id'] ?? 0;
        if (empty($this->user_channel->getShop()) || empty($this->user_channel->getShopWarehouse()))
            return;
        $shop_id = (int)$this->user_channel->getShop()->getId();
        $warehouse_id = (int)$this->user_channel->getShopWarehouse()->getWarehouseId();
        if (!$shop_id || !$warehouse_id)
            return;
        $user_id = !empty($params['user_id']) ? (int)$params['user_id'] : 0;
        $quantity = (int)$quantity;
        unset($params['order_id'], $params['asn_id'], $params['user_id']);
        $additional = json_encode($params);
        (new SafeMysql())->execute("INSERT INTO shop_inventory_logs (shop_id, warehouse_id, type, object_id, user_id, SKU_id, quantity, additional)
                        VALUES (:shop_id, :warehouse_id, :type, :object_id, :user_id, :SKU_id, :quantity, :additional)", [
            'shop_id' => $shop_id,
            'warehouse_id' => $warehouse_id,
            'type' => $type,
            'object_id' => $object_id,
            'user_id' => $user_id,
            'SKU_id' => $SKU_id,
            'quantity' => $quantity,
            'additional' => $additional,
        ]);
    }

    protected function checkDelay($created_at, int $delay = self::PRODUCT_SAVE_DELAY): bool
    {
        if (empty($created_at) || !is_string($created_at))
            return false;
        return (time() - strtotime($created_at))/60 > $delay;
    }

    protected function checkShippingMapping(Order $order, $channel_shipping_code)
    {
        // Shopify sends code: null for custom shipping lines; SafeMysql would bind it as NULL and the lookups never match
        $channel_shipping_code = (string)$channel_shipping_code;
        $shipping_mapping = Shippings::getChannelServiceMappingByWarehouseId($this->user_channel->getShopWarehouse()->getId(), $channel_shipping_code);
        $mapping_id = $shipping_mapping['id'] ?? 0;
        if (empty($shipping_mapping)) {
            $mapping_data = [
                'shop_warehouse_id' => $this->user_channel->getShopWarehouse()->getId(),
                'channel_shipping_code' => $channel_shipping_code,
                'shipping_services_id' => 0,
            ];
            $mapping_id = Shippings::getChannelServiceMappingByData($mapping_data);
            if (empty($mapping_id))
                $mapping_id = Shippings::addChannelServiceMapping($mapping_data);
        }
        if (empty($shipping_mapping) || !$shipping_mapping['shipping_service_id']) {
            OrderHistories::addCommentOrder($order->getId(), $order->getUserId(),"Shipping service '$channel_shipping_code' needs to be mapped");
            Orders::changeStatus($order->getId(), Order::AWAITING_CHANNEL_SERVICE_MAPPING, $order->getUserId());
            $order->setChannelsShippingServicesId($mapping_id);
            $order->save(['id', 'channels_shipping_services_id']);
            SupplierNotifications::addNotification($order->getSupplierId(), SupplierNotifications::SHIPPING_MAPPING, [
                'id' => $order->getId(),
                'ref' => $order->getRef(),
                'ref2' => $order->getRef2(),
                'required' => time(),
            ]);
            return false;
        }
        $order->setCarrierId($shipping_mapping['carrier_id']);
        $order->setShippingServicesId($shipping_mapping['shipping_service_id']);
        $order->setChannelsShippingServicesId($mapping_id);
        $order->setDDP($shipping_mapping['DDP']);
        $order->save(['id', 'carrier_id', 'channels_shipping_services_id', 'shipping_services_id', 'DDP']);
        return true;
    }

    /** @throws ChannelException */
    protected function setUserChanelById($channel, $id, $warehouse_id = null)
    {
        $channel_obj = Channels::getByName($channel);
        $params = [
            'shop_id' => $id,
            'channel_id' => $channel_obj['id'],
        ];
        if (!is_null($warehouse_id))
            $params['warehouse_id'] = $warehouse_id;
        /** @var Shops $shops */
        $shops = Channels::getUserChannel($params);
        if (empty($shops))
            throw new ChannelException("Shop #{$id} not found");
        if (!$shops->getShop()->getIsActive())
            throw new ChannelException('This channel is disabled');
        $this->setUserChannel($shops);
        $connectorName = Channels::getConnectorName($channel_obj);
        $this->setConnector(new $connectorName($shops->getShop()->getCredentials()));
        $this->setSupplier(Suppliers::getById($shops->getChannelSupplier()->getSupplierId()));
    }

    public function updateInventoryDaily($type, $wms_stock_raw, $stock)
    {
        $err_msg = [];
        foreach ($wms_stock_raw as $value) {
            if ($value['state'] != 1 || !isset($stock[$value['external_id']]))
                continue;
            $wms_stock_number = (int)$value['available'];
            if ((int)$stock[$value['external_id']] != $wms_stock_number) {
                if (Bundles::isBundleExists($this->getSupplier()->getId(), $value['customer_ref']))
                    continue;
                try{
                    $this->setInventoryDaily($value['id'], $value['external_id'], $wms_stock_number, $type);
                } catch (\Exception $e) {
                    $err_msg[] = "{$value['external_id']}: {$e->getMessage()}";
                }
            }
        }
        return $err_msg;
    }

    /**
     * @var array $params available keys: ['order_id', 'asn_id']
     * @throws ChannelException
     */
    public function setInventoryLevels($channel_name, $params)
    {
        if (empty($params['asn_id']) && empty($params['order_id']) && empty($params['SKU_id']))
            throw new ChannelException('asn_id/order_id is empty');
        if (!empty($params['asn_id'])) {
            $ASN = ASNs::getById($params['asn_id']);
            if (empty($ASN))
                throw new ChannelException('ASN not found');
            $SKUs = ASNSKUs::getAcceptedSKUsByAsn($params['asn_id']);
            $source = InventorySync::SYNC_ASN;
            $warehouse_id = $ASN->getWarehouseId();
        } elseif (!empty($params['order_id'])) {
            $order = Orders::getById($params['order_id']);
            if (empty($order))
                throw new ChannelException('Order not found');
            $SKUs = OrderSKUs::getSKUsByOrder($params['order_id']);
            $source = InventorySync::SYNC_ORDER;
            $warehouse_id = $order->getWarehouseId();
        } elseif (!empty($params['SKU_id'])) {
            $SKUs = [$params];
            $source = InventorySync::SYNC_SKU;
            $warehouse_id = $params['warehouse_id'];
        }
        if (empty($SKUs))
            throw new ChannelException('Nothing was reserved');
        $connectorName = Channels::getConnectorName(['name' => $channel_name]);
        $messages = [];
        $codes = [];
        foreach ($SKUs as $SKU_data) {
            $SKU_id = (int)$SKU_data['SKU_id'];
            $quantity_available = SKULocations::getUnreservedCount($SKU_id, $warehouse_id);
            $SKU_channels = SKUChannelSuppliers::getBySKU($SKU_id, $channel_name, $warehouse_id);
            if (empty($SKU_channels))
                continue;
            foreach ($SKU_channels as $channel) {
                $shop = Channels::getUserChannel([
                    'shop_id' => $channel['shop_id'],
                    'warehouse_id' => $warehouse_id,
                ]);
                if (empty($shop))
                    continue;
                try {
                    $this->setUserChannel($shop);
                    $this->setConnector(new $connectorName($this->getUserChannel()->getShop()->getCredentials()));
                    $res = $this->setInventory($channel['external_id'], $SKU_id, $quantity_available, $params, $source);
                    if ($res === false)
                        continue;
                    if (!empty($res['msg'])) {
                        $messages[] = "#$SKU_id: " . (empty($res['base64']) ? $res['msg'] : base64_decode($res['msg']));
                        $codes[$res['code']] = 1;
                    }
                } catch (\Exception $e) {
                    $messages[] = $e->getMessage();
                    $codes[$e->getCode()] = 1;
                }
            }
        }
        if (!empty($messages)) {
            $code = ErrorHandler::CI_ERROR;
            if (!empty($codes)) {
                $codes = array_diff(array_keys($codes), self::NOTIFICATION_SKIP_ERROR);
                if (empty($codes))
                    $code = ErrorHandler::CI_SKIP_ERROR;
            }
            throw new ChannelException(implode("\n", $messages), $code);
        }
    }
}
