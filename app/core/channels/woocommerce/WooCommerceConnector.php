<?php

namespace app\core\channels\woocommerce;

use app\core\channels\BaseChannelConnector;
use app\core\exceptions\ChannelException;
use app\core\helpers\Arr;
use app\core\helpers\CURL;

class WooCommerceConnector extends BaseChannelConnector
{
    const INTEGRATION_NAME = 'WooCommerce';
    const LIMIT_PER_PAGE = 100;
    const PAID_INTERVAL = 14;

    private static $currency;
    private static $getStatuses = [
        'processing',
    ];
    private static $getAllStatuses = [
        'any',
    ];

    /** @var WooCommerceCredentials */
    protected $credentials;

    /** @return WooCommerceCredentials */
    public function getCredentials()
    {
        return $this->credentials;
    }

    public function setCredentials($credentials)
    {
        $this->credentials = new WooCommerceCredentials($credentials);
    }

    public function __construct($credentials)
    {
        $this->setCredentials($credentials);
    }

    public function getUniqueBarcode($shop_id, $external_id)
    {
        return "WC{$shop_id}SKU{$external_id}";
    }

    public function fetchAllProducts(array $params = [])
    {
        if (($nums = $this->getTotalProducts()) == -1)
            return false;
        $res = [];
        for ($i = 0; $i < (float)($nums / self::LIMIT_PER_PAGE); $i++) {
            $products = $this->getProductsFromPage($i + 1, self::LIMIT_PER_PAGE, $params['time_products'] ?? false);
            if (empty($products))
                break;
            foreach ($products as $product)
                $res = array_merge($res, $this->prepareSingleProduct($product));
        }
        return $res;
    }

    public function fetchSingleProduct($product_id)
    {
        return $this->prepareSingleProduct($this->getJSON("products/{$product_id}", [], 'single_product'));
    }

    public function getOriginalProduct($product_id, $variation_id)
    {
        if (empty($variation_id)) {
            $product = $this->getProductById($product_id);
            return (empty($product) || empty($product['type']) || $product['type'] != 'simple') ? null : $product;
        }
        $product = $this->getProductById($variation_id);
        return (empty($product) || empty($product['type']) || $product['type'] != 'variation' ||
                                   empty($product['parent_id']) || $product['parent_id'] != $product_id) ? null : $product;
    }

    public function fetchByOrderId($id)
    {
        $order_list = $this->fetchAllOrders(self::$getAllStatuses);
        if (empty($order_list))
            return [];
        $res = [];
        foreach ($order_list as $order)
            if ($order['order']['ref2'] == $id)
                $res[] = $order;
        return $res;
    }

    public function fetchAllOrders(array $last = [])
    {
        if (($nums = $this->getTotalOrders()) == -1)
            return false;
        if (empty($last))
            $last = self::$getStatuses;
        $res_orders = [];
        for ($i = 0; $i < (float)($nums / self::LIMIT_PER_PAGE); $i++) {
            $orders = $this->getOrdersFromPage($i + 1, self::LIMIT_PER_PAGE, $last);
            if (empty($orders))
                break;
            foreach ($orders as $order) {
                $tmp_res = [];
                $tmp_res['original_response'] = json_encode($order);
                $tmp_res['order'] = $this->getOrder($order);
                if (in_array($order['status'], ['processing', 'on-hold', 'refunded'])) {
                    $tmp_res['delivery'] = $this->getDelivery($order['shipping'], $order['billing']['phone'],
                        $order['billing']['email']);
                    $tmp_res['products'] = $this->getProducts($order['line_items']);
                }
                $res_orders[] = $tmp_res;
            }
        }
        return $res_orders;
    }

    private function prepareSingleProduct($product, $stock = false)
    {
        $res = [];
        $var_products = [];
        if ($product['type'] === 'variable') {
            $var_products[$product['id']] = [
                'name' => $product['name'],
                'nums' => count($product['variations'])
            ];
        } else
            $res[] = $this->getProduct($product, '', '', $stock);
        if (!empty($var_products))
            foreach ($var_products as $key => $var_product) {
                for ($j = 0; $j < (float)($var_product['nums'] / self::LIMIT_PER_PAGE); $j++) {
                    $variations = $this->getVariationProductsFromPage($key, $j + 1, self::LIMIT_PER_PAGE);
                    if (empty($variations))
                        break;
                    foreach ($variations as $variation)
                        $res[] = $this->getProduct($variation, $var_product['name'], $key, $stock);
                }
            }
        return $res;
    }

    private function getProduct($product, $name = '', $id = '', $stock = false)
    {
        $res = [
            'barcode' => $product['sku'],
            'customer_ref' => $product['sku'],
            // ABF-475 EU PID: NS-PID defaults to SKU, S-PID = WooCommerce's native Global Unique ID field
            // (core REST field since WooCommerce 8.4+; falls back to empty if the store doesn't have it)
            'ns_pid' => $product['sku'] ?? '',
            's_pid' => $product['global_unique_id'] ?? '',
            // ABF-475 EU PID: raw meta_data carried through for the optional NS-PID custom-field override,
            // resolved in WooCommerceDataProcessor and unset there before the SKU is saved
            'meta_data' => $product['meta_data'] ?? [],
            'cost_value' => (float)$product['price'],
            'market_value' => (float)(!empty($product['regular_price']) ? $product['regular_price'] : $product['price']),
            'width' => (float)$product['dimensions']['width'],
            'height' => (float)$product['dimensions']['height'],
            'depth' => (float)$product['dimensions']['length'],
            'weight' => (float)$product['weight'],
            'state' => 3, //not mapped
            'photo_src' => !empty($product['images'][0]) ? $product['images'][0]['src'] : '',
            'date_created' => $product['date_created_gmt'],
        ];
        if (!empty($name) && !empty($id)) {
            $attr = implode(', ', array_map(static function ($elem) {
                return "{$elem['name']} - {$elem['option']}";
            }, $product['attributes']));
            $res['name'] = "$name ($attr)";
            $res['photo_src'] = $product['image']['src'];
            $res['external_id'] = "{$id}_{$product['id']}";
        } else {
            $res['name'] = $product['name'];
            $res['photo_src'] = !empty($product['images'][0]) ? $product['images'][0]['src'] : '';
            $res['external_id'] = $product['id'];
        }
        if ($stock) {
            $res['available'] = $product['stock_quantity'];
            $res['stock_status'] = $product['stock_status'];
        }
        return $res;
    }

    private function getProductsFromPage($page, $per_page, $last_load, $ids = null)
    {
        $args = compact('page', 'per_page');
        if ($last_load !== false)
            $args['after'] = date("Y-m-d\TH:m:s", $last_load);
        if (!empty($ids))
            $args['include'] = implode(',', $ids);
        return $this->getJSON("products/", $args, 'product_from_page');
    }

    private function getVariationProductsFromPage($product_id, $page, $per_page)
    {
        $args = compact('page', 'per_page');
        return $this->getJSON("products/$product_id/variations/", $args, 'variation_products_from_page');
    }

    private function getDelivery($shipping, $phone, $email)
    {
        return [
            'name' => $shipping['company'],
            'contact' => "{$shipping['first_name']} {$shipping['last_name']}",
            'phone' => $phone,
            'email' => $email,
            'address1' => $shipping['address_1'],
            'address2' => $shipping['address_2'],
            'town' => $shipping['city'],
            'county' => $shipping['state'],
            'postcode' => $shipping['postcode'],
            'country_id' => $shipping['country'],
        ];
    }

    private function getOrder($order)
    {
        return [
            'external_id' => $order['id'],
            'create_date' => $order['date_created_gmt'],
            'despatch_date' => $order['date_created_gmt'],
            'ref' => $order['order_key'],
            'ref2' => $order['number'],
            'status' => $order['status'], // processing, on-hold, cancelled, refunded
            'shipping_lines' => $order['shipping_lines'][0]['method_title'],
            'currency' => $order['currency'],
        ];
    }

    private function getProducts($items)
    {
        $res = [];
        foreach ($items as $item) {
            $product_id = $item['product_id'] . (!empty($item['variation_id']) ? "_{$item['variation_id']}" : '');
            if (!empty($product_id)) {
                if (empty($res[$product_id])) {
                    $res[$product_id] = [
                        'SKU_id' => $product_id,
                        'quantity' => $item['quantity'],
                        'name' => $item['name'],
                        'sku' => $item['sku'],
                        'price' => $item['price'],
                    ];
                } else
                    $res[$product_id]['quantity'] += $item['quantity'];
            }
        }
        return $res;
    }

    private function getOrdersFromPage($page, $per_page, $statuses)
    {
        $args = compact('page', 'per_page');
        $args['status'] = implode(',', $statuses);
        //get orders created during the last 7 days
        $args['after'] = date("Y-m-d\TH:m:s", time() - 86400 * self::PAID_INTERVAL);
        return $this->getJSON("orders/", $args, 'orders_from_page');
    }

    /**
     * @param $apiUri
     * @param array $args
     * @param string $source
     * @return CURL
     */
    private function getCurl($apiUri, $args = [], $source = 'undefined')
    {
        $apiUri .= '?' . http_build_query(array_merge([
                'consumer_key' => $this->getCredentials()->getConsumerKey(),
                'consumer_secret' => $this->getCredentials()->getConsumerSecret()
            ], $args));
        $curl = new CURL("https://{$this->getCredentials()->getShopUrl()}/wp-json/wc/v3/{$apiUri}", "woocommerce_$source");
        $curl->addOption(CURLOPT_HEADER, 0);
        return $curl;
    }

    private function getJSON($query, $args = [], $source = 'undefined')
    {
        $curl = $this->getCurl($query, $args, $source);
        $result = $curl->exec();
        if (empty($result)) {
            return null;
        }
        return json_decode($result, true);
    }

    public function checkConnection()
    {
        $check = $this->getJSON('', [], 'check_connection');
        $this->connection_errors = empty($check) ? 'Connection not found' : '';
    }

    public function getBaseCurrency()
    {
        if(!empty(self::$currency)) {
            $result = $this->getJSON('data/currencies/current', [], 'base_currency');
            self::$currency = isset($result['code']) ? $result['code'] : 'GBP';
        }
        return self::$currency;
    }

    public function getTotalProducts()
    {
        $totals = $this->getJSON('reports/products/totals', [], 'total_products');
        if (empty($totals)) {
            return -1;
        }
        $num = 0;
        foreach ($totals as $total) {
            $num += $total['total'];
        }
        return $num;
    }

    public function getTotalOrders()
    {
        $totals = $this->getJSON('reports/orders/totals', [], 'total_orders');
        if (empty($totals))
            return -1;
        $num = 0;
        foreach ($totals as $total)
            $num += $total['total'];
        return $num;
    }

    public function getInventory($inventories, $location_id = 0)
    {
        $data = $this->getAllInventory($inventories);
        if (empty($data))
            return [];
        return Arr::map($data, 'external_id', 'available');
    }

    public function setInventory($product_id, $level, $location_id = 0)
    {
        $external_info = explode('_', $product_id);
        if (!empty($external_info[1]))
            return $this->setInventoryVariation($external_info[0], $external_info[1], $level);
        $curl = $this->getCurl("products/{$product_id}", [], 'inventory');
        $curl->setMethod('PUT');
        $curl->setHeader('Content-Type', 'application/json; charset=UTF-8');
        $curl->setBody(json_encode(['stock_quantity' => $level]));
        $result = $curl->exec();
        sleep(1);
        if ($curl->getResultCode() != 200) {
            $error_res['code'] = $curl->getResultCode();
            $error_ret = $curl->getResultError();
            if (!empty($error_ret)) {
                $error_arr = json_decode($error_ret, true);
                if (!empty($error_arr['errors']))
                    $error_res['msg'] = $error_arr['errors'];
                else
                    $error_res['msg'] = $error_arr;
            }
            throw new ChannelException(json_encode($error_res), $error_res['code']);
        }
        if (empty($result))
            return false;
        return json_decode($result, true);
    }

    public function setInventoryVariation($product_id, $variation_id, $level)
    {
        $curl = $this->getCurl("products/$product_id/variations/$variation_id", [], 'inventory_variation');
        $curl->setMethod('PUT');
        $curl->setHeader('Content-Type', 'application/json; charset=UTF-8');
        $curl->setBody(json_encode(['stock_quantity' => $level]));
        $result = $curl->exec();
        sleep(1);
        if ($curl->getResultCode() != 200) {
            $error_res['code'] = $curl->getResultCode();
            $error_ret = $curl->getResultError();
            if (!empty($error_ret)) {
                $error_arr = json_decode($error_ret, true);
                if (!empty($error_arr['errors']))
                    $error_res['msg'] = $error_arr['errors'];
                else
                    $error_res['msg'] = $error_arr;
            }
            throw new ChannelException(json_encode($error_res));
        }
        if (empty($result))
            return false;
        return json_decode($result, true);
    }

    public function getOrderStatus($order_id)
    {
        $curl = $this->getCurl("orders/{$order_id}", [], 'get_order_status');
        $curl->setMethod('GET');
        $result = $curl->exec();
        if (!in_array($curl->getResultCode(), [200, 201, 202]))
            throw new ChannelException("Returned code {$curl->getResultCode()}. Response:{$result}");
        $order_data = json_decode($result, true);
        if (empty($order_data) || empty($order_data['status']))
            throw new ChannelException("Status for order #$order_id not found");
        return $order_data['status'];
    }

    public function setOrderStatus($order_id)
    {
        $status = $this->getOrderStatus($order_id);
        if ($status == 'completed')
            return false;
        $curl = $this->getCurl("orders/{$order_id}", ['status' => 'completed'], 'set_track_info');
        $curl->setMethod('PUT');
        $result = $curl->exec();
        if (!in_array($curl->getResultCode(), [200, 201, 202])) {
            throw new ChannelException("Returned code {$curl->getResultCode()}. Response:{$result}");
        }
        return true;
    }

    public function getProductById($productId)
    {
        return $this->getJSON("products/$productId", [], 'get_product_by_id');
    }

    public function getProductVariationById($product_id, $variation_id)
    {
        return $this->getJSON("products/$product_id/variations/$variation_id", [], 'get_product_variation_by_id');
    }

    public function getProductsByIds($ids)
    {
        return $this->getJSON("products", ['include' => implode(',',$ids)], 'get_products_by_ids');
    }

    public function getProductVariantsByIds($product_id, $ids)
    {
        return $this->getJSON("products/$product_id/variations", ['include' => implode(',',$ids)], 'get_product_variations_by_ids');
    }

    public function getAllInventory($ids = null)
    {
        if (($nums = $this->getTotalProducts()) == -1)
            return false;
        if (!empty($ids)) {
            $ids_temp = $ids;
            $ids = [];
            foreach ($ids_temp as $id) {
                $tmp = explode('_', $id);
                if (!in_array($tmp[0], $ids))
                    $ids[] = $tmp[0];
            }
        }
        $res = [];
        $page = 0;
        while (true) {
            $products = $this->getProductsFromPage($page + 1, self::LIMIT_PER_PAGE, false, $ids);
            if (empty($products))
                break;
            foreach ($products as $product)
                $res = array_merge($res, $this->prepareSingleProduct($product, true));
            $page++;
        }
        return $res;
    }
}
