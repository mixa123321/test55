<?php

namespace app\core\entities;

use app\core\models\ChannelSuppliers;
use app\core\models\Shops;
use app\core\models\ShopWarehouses;

class UserChannel extends Entity
{
    private $id;
    private $supplier_id;
    private $user_id;
    private $channel_id;
    private $primary_shop_id;
    private $is_active;
    private $credentials;
    private $download_orders_flag;
    private $is_orders_approval_required;
    private $cache;
    private $shop_id;
    private $warehouse_id;
    private $location_id;
    private $last_upload;
    private $is_risk;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($supplier_id)
    {
        $this->supplier_id = (int)$supplier_id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = (int)$user_id;
    }

    public function getChannelId()
    {
        return $this->channel_id;
    }

    public function setChannelId($channel_id)
    {
        $this->channel_id = (int)$channel_id;
    }

    public function getPrimaryShopId()
    {
        return $this->primary_shop_id;
    }

    public function setPrimaryShopId($id)
    {
        $this->primary_shop_id = (int)$id;
    }

    public function getIsActive()
    {
        return $this->is_active;
    }

    public function setIsActive($is_active)
    {
        $this->is_active = (int)$is_active;
    }

    public function getCredentials()
    {
        return json_decode($this->credentials, true);
    }

    public function setCredentials($credentials)
    {
        if (is_object($credentials))
            $credentials = $credentials->toArray();
        if (is_array($credentials))
            $credentials = json_encode($credentials);
        $this->credentials = $credentials;
    }

    public function getDownloadOrdersFlag()
    {
        return $this->download_orders_flag;
    }

    public function setDownloadOrdersFlag($download_orders_flag)
    {
        $this->download_orders_flag = (int)$download_orders_flag;
    }

    public function getIsOrdersApprovalRequired()
    {
        return $this->is_orders_approval_required;
    }

    public function setIsOrdersApprovalRequired($is_orders_approval_required)
    {
        $this->is_orders_approval_required = (int)$is_orders_approval_required;
    }

    public function getCache()
    {
        return json_decode($this->cache, true);
    }

    public function setCache($cache)
    {
        $this->cache = is_array($cache) ?  json_encode($cache) : $cache;
    }

    public function getShopId()
    {
        return $this->shop_id;
    }

    public function setShopId($id)
    {
        $this->shop_id = (int)$id;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getLocationId()
    {
        return $this->location_id;
    }

    public function setLocationId($id)
    {
        $this->location_id = (int)$id;
    }

    public function getLastUpload()
    {
        return json_decode($this->last_upload, true);
    }

    public function setLastUpload($last_upload)
    {
        if (is_object($last_upload))
            $last_upload = $last_upload->toArray();
        if (is_array($last_upload))
            $last_upload = json_encode($last_upload);
        $this->last_upload = $last_upload;
    }

    public function getTimeProducts()
    {
        return $this->getLastUpload()['products'] ?? 0;
    }

    public function setTimeProducts($time_products)
    {
        $last_upload = $this->getLastUpload();
        $last_upload['products'] = $time_products;
        $this->setLastUpload($last_upload);
    }

    public function getTimeOrders()
    {
        return $this->getLastUpload()['orders'] ?? 0;
    }

    public function setTimeOrders($time_orders)
    {
        $last_upload = $this->getLastUpload();
        $last_upload['orders'] = $time_orders;
        $this->setLastUpload($last_upload);
    }

    public function getIsRisk()
    {
        return $this->is_risk;
    }

    public function setIsRisk($is_risk)
    {
        $this->is_risk = (int)$is_risk;
    }

    private function createShop()
    {
        $data = [
            'channel_supplier_id' => $this->getId(),
            'is_active' => $this->getIsActive(),
            'credentials' => $this->getCredentials(),
            'is_risk' => $this->getIsRisk(),
        ];
        $shop = new Shop($data);
        $shop->save(array_keys($data));
        return $shop->getId();
    }

    private function createShopWarehouse()
    {
        $data = [
            'shop_id' => $this->getShopId(),
            'warehouse_id' => $this->getWarehouseId(),
            'download_orders_flag' => $this->getDownloadOrdersFlag(),
            'is_orders_approval_required' => $this->getIsOrdersApprovalRequired(),
            'cache' => $this->getCache(),
            'location_id' => $this->getLocationId(),
            'last_upload' => $this->getLastUpload(),
        ];
        $shop_warehouse = new ShopWarehouse($data);
        $shop_warehouse->save(array_keys($data));
        return $shop_warehouse->getId();
    }

    public function saveShop($fields_to_save)
    {
        $shop = Shops::getById($this->getShopId());
        if (empty($shop))
            $this->setShopId($this->createShop());
        else {
            if (empty($fields_to_save))
                return;
            $fields_to_save = array_merge(['id'], $fields_to_save);
            $data = $this->toArray();
            $channel_supplier_id = $data['id'];
            $data['id'] = $data['shop_id'];
            foreach ($data as $data_field => $data_value)
                if (!in_array($data_field, $fields_to_save, true))
                    unset($data[$data_field]);
            $data['channel_supplier_id'] = $channel_supplier_id;
            $shop = new Shop($data);
            $shop->save($fields_to_save);
        }
    }

    public function saveShopWarehouse($fields_to_save)
    {
        $shop_warehouse = ShopWarehouses::getByShopWarehouse($this->getShopId(), $this->getWarehouseId());
        if (empty($shop_warehouse))
            $this->createShopWarehouse();
        else {
            if (empty($fields_to_save))
                return;
            $fields_to_save = array_merge(['id'], $fields_to_save);
            $data = $this->toArray();
            foreach ($data as $data_field => $data_value)
                if (!in_array($data_field, $fields_to_save, true))
                    unset($data[$data_field]);
            $data['id'] = $shop_warehouse->getId();
            $shop_warehouse = new ShopWarehouse($data);
            $shop_warehouse->save($fields_to_save);
        }
    }

    public function save()
    {
        $channel_supplier = ChannelSuppliers::getChannelSupplier($this->getChannelId(), $this->getSupplierId());
        if (empty($channel_supplier)) {
            $data = [
                'channel_id' => $this->getChannelId(),
                'supplier_id' => $this->getSupplierId(),
                'user_id' => $this->getUserId(),
                'primary_shop_id' => $this->getPrimaryShopId(),
            ];
            $channel_supplier = new ChannelSupplier($data);
            $channel_supplier->save(array_keys($data));
        }
        $this->setId($channel_supplier->getId());
        $this->saveShop(['is_active', 'credentials', 'is_risk']);
        $this->saveShopWarehouse(['shop_id', 'warehouse_id', 'download_orders_flag', 'is_orders_approval_required', 'cache', 'location_id', 'last_upload']);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'supplier_id' => $this->getSupplierId(),
            'user_id' => $this->getUserId(),
            'channel_id' => $this->getChannelId(),
            'primary_shop_id' => $this->getPrimaryShopId(),
            'is_active' => $this->getIsActive(),
            'credentials' => $this->getCredentials(),
            'download_orders_flag' => $this->getDownloadOrdersFlag(),
            'is_orders_approval_required' => $this->getIsOrdersApprovalRequired(),
            'cache' => $this->getCache(),
            'shop_id' => $this->getShopId(),
            'warehouse_id' => $this->getWarehouseId(),
            'location_id' => $this->getLocationId(),
            'last_upload' => $this->getLastUpload(),
            'is_risk' => $this->getIsRisk(),
       ];
    }
}