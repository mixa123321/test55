<?php

namespace app\core\channels\magento;

use app\core\channels\BaseChannelConnector;
use app\core\exceptions\ChannelException;
use app\core\helpers\CURL;

class MagentoConnector extends BaseChannelConnector
{
    const INTEGRATION_NAME = 'Magento';

    const CONFIGURABLE = 'configurable';
    const SIMPLE = 'simple';
    const BUNDLE = 'bundle';

    /** @var MagentoCredentials */
    protected $credentials;

    /** @return MagentoCredentials */
    function getCredentials()
    {
        return $this->credentials;
    }

    function setCredentials($credentials)
    {
        $this->credentials = new MagentoCredentials($credentials);
    }

    public function __construct($credentials)
    {
        $this->setCredentials($credentials);
    }

    public function fetchAllProducts(array $params = [])
    {
        $search = ['type_id' => self::SIMPLE];
        if (!empty($params) && !empty($params['time_products']) && $params['time_products'] > 0)
            $search['created_at'] = [date('Y-m-d', $params['time_products']), 'gteq'];
        if (!empty($params) && !empty($params['ids']))
            $search['entity_id'] = [implode(',', $params['ids']), 'in'];
        $params = $this->createParams($search);
        $curl = $this->getCurl("products?$params", 'magento_all_products');
        $result = $curl->exec();
        if (empty($result) || $curl->getResultCode() != 200)
            return false;
        return json_decode($result, true)['items'];
    }

    public function getUniqueBarcode($shop_id, $external_id)
    {
        return "MG{$shop_id}SKU{$external_id}";
    }

    public function fetchAllOrders(array $params = [])
    {
        $search = ['status' => ['pending,processing', 'in']];
        if (!empty($params) && !empty($params['time_orders']) && $params['time_orders'] > 0)
            $search['created_at'] = [date('Y-m-d', $params['time_orders']), 'gteq'];
        $params = $this->createParams($search);
        $curl = $this->getCurl("orders?$params", 'magento_all_orders');
        $result = $curl->exec();
        if (empty($result) || $curl->getResultCode() != 200)
            return false;
        return json_decode($result, true)['items'];
    }

    private function createParams(array $params)
    {
        $filter_groups = [];
        foreach ($params as $field => $value)
            $filter_groups[] = ['filters' => [['field' => $field, 'value' => is_array($value) ? $value[0] : $value, 'conditionType' => is_array($value) ? $value[1] : 'eq']]];
        $filter = [
            'searchCriteria' => [
                'filterGroups' => $filter_groups
            ],
        ];
        return http_build_query($filter);
    }

    /** @throws ChannelException */
    public function fetchProduct($id)
    {
        $curl = $this->getCurl('products/?' . $this->createParams(['entity_id' => $id]), 'magento_get_product');
        $curl->setMethod('GET');
        $result = $curl->exec();
        if (empty($result) || $curl->getResultCode() != 200)
            throw new ChannelException($curl->getResultError());
        $result = json_decode($result, true);
        if (empty($result['items']))
            throw new ChannelException('Product is not found');
        return $result['items'][0];
    }

    public function fetchByOrderId($id)
    {
        $curl = $this->getCurl("orders/$id", 'magento_get_order');
        $curl->setMethod('GET');
        $result = $curl->exec();
        if (empty($result) || $curl->getResultCode() != 200)
            return false;
        return $result;
    }

    public function getCategories($id, $fields = null)
    {
        $curl = $this->getCurl("categories/$id" . (!empty($fields) ? "?fields=$fields" : ''), 'magento_get_categories');
        $curl->setMethod('GET');
        $result = $curl->exec();
        if (empty($result) || $curl->getResultCode() != 200)
            return false;
        return $result;
    }

    /**
     * @param $apiUri
     * @param $source
     * @return CURL
     */
    private function getCurl($apiUri, $source){
        $curl = new CURL("https://{$this->getCredentials()->getShopUrl()}/rest/V1/{$apiUri}", $source);
        $curl->addOption(CURLOPT_SSL_VERIFYHOST, 2);
        $curl->addOption(CURLOPT_SSL_VERIFYPEER, true);
        $this->setAuthorization($curl);
        return $curl;
    }

    private function setAuthorization(CURL $curl)
    {
        $curl->setBearerAuth($this->getCredentials()->getToken());
    }

    public function checkConnection()
    {
        $currency = $this->getBaseCurrency();
        $this->connection_errors = empty($currency) ? 'Connection not found' : '';
    }

    public function getBaseCurrency()
    {
        $curl = $this->getCurl('directory/currency', 'magento_base_currency');
        $result = $curl->exec();
        if (empty($result) || $curl->getResultCode() != 200)
            return false;
        $result = json_decode($result, true);
        return isset($result['base_currency_code']) ? $result['base_currency_code'] : '';
    }

    public function getStockItems($sku)
    {
        $sku = urlencode($sku);
        $curl = $this->getCurl("stockItems/$sku?fields=qty", 'magento_get_stock_item');
        $curl->setMethod('GET');
        $result = $curl->exec();
        if (empty($result) || $curl->getResultCode() != 200)
            throw new ChannelException($curl->getResultError());
        $qty = json_decode($result, true);
        if (!isset($qty['qty']))
            throw new ChannelException("Product $sku is not found");
        return $qty['qty'];
    }

    public function getAllInventory($ids = null)
    {
        $curl = $this->getCurl('products/?' . $this->createParams(['type_id' => self::SIMPLE]), 'magento_get_inventory');
        $curl->setMethod('GET');
        $result = $curl->exec();
        if (empty($result) || $curl->getResultCode() != 200)
            throw new ChannelException($curl->getResultError());
        $products = json_decode($result, true);
        if (empty($products['items']))
            throw new ChannelException('Products are not found');
        $res = [];
        foreach ($products['items'] as $item)
            if (is_null($ids) || in_array($item['id'], $ids))
                $res[$item['id']] = $this->getStockItems($item['sku']);
        return $res;
    }

    public function getInventory($inventories, $location_id = 0)
    {
        return $this->getAllInventory($inventories);
    }

    public function setInventory($product_id, $level, $sku)
    {
        $curl = $this->getCurl("products/{$sku}/stockItems/{$product_id}", 'magento_inventory');
        $curl->setMethod('PUT');
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setBody(json_encode(['stockItem' => ["qty" => (int)$level]]));
        $result = $curl->exec();
        if (empty($result) || $curl->getResultCode() != 200)
            throw new ChannelException($curl->getResultError(), $curl->getResultCode());
        return $result;
    }

    public function setTrackInfo($id, $items, $tracks)
    {
        $curl = $this->getCurl("order/$id/ship", 'magento_set_track_info');
        $curl->setMethod('POST');
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setBody(json_encode([
            'items' => $items,
            'tracks' => $tracks
        ]));
        $result = $curl->exec();
        if (empty($result) || $curl->getResultCode() != 200)
            throw new ChannelException($curl->getResultError(), $curl->getResultCode());
        return $result;
    }
}