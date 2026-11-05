<?php

namespace app\core\entities;

use app\core\db\Mysql;

class ShopWarehouse extends Entity
{
    private $id;
    private $shop_id;
    private $warehouse_id;
    private $download_orders_flag;
    private $is_orders_approval_required;
    private $location_id;
    private $is_active;
    private $is_daily;
    private $cache;
    private $last_upload;
    private $is_retain_me;
    private $notify;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
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

    public function getLocationId()
    {
        return $this->location_id;
    }

    public function setLocationId($id)
    {
        $this->location_id = (string)$id;
    }

    public function getIsActive()
    {
        return $this->is_active;
    }

    public function setIsActive($is_active)
    {
        $this->is_active = (int)$is_active;
    }

    public function getIsDaily()
    {
        return $this->is_daily;
    }

    public function setIsDaily($is_daily)
    {
        $this->is_daily = (int)$is_daily;
    }

    public function getCache()
    {
        return json_decode($this->cache, true);
    }

    public function setCache($cache)
    {
        $cache = is_array($cache) ?  json_encode($cache) : $cache;
        $this->cache = $cache;
    }

    public function getLastUpload()
    {
        return empty($this->last_upload) ? [] : json_decode($this->last_upload, true);
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

    public function getIsRetainMe()
    {
        return $this->is_retain_me;
    }

    public function setIsRetainMe($is_retain_me)
    {
        $this->is_retain_me = (int)$is_retain_me;
    }

    public function getNotify()
    {
        return $this->notify;
    }

    public function setNotify($notify)
    {
        $this->notify = (int)$notify;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save)) {
            return;
        }
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        if (isset($data['last_upload']) && is_array($data['last_upload']))
            $data['last_upload'] = json_encode($data['last_upload']);
        if (isset($data['cache']) && is_array($data['cache']))
            $data['cache'] = json_encode($data['cache']);
        $id = Mysql::saveRow('shop_warehouses', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'shop_id' => $this->getShopId(),
            'warehouse_id' => $this->getWarehouseId(),
            'download_orders_flag' => $this->getDownloadOrdersFlag(),
            'is_orders_approval_required' => $this->getIsOrdersApprovalRequired(),
            'location_id' => $this->getLocationId(),
            'is_active' => $this->getIsActive(),
            'is_daily' => $this->getIsDaily(),
            'cache' => $this->getCache(),
            'last_upload' => $this->getLastUpload(),
            'is_retain_me' => $this->getIsRetainMe(),
            'notify' => $this->getNotify(),
        ];
    }
}