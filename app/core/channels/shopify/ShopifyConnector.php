<?php

namespace app\core\channels\shopify;

use app\core\channels\BaseChannelConnector;
use app\core\exceptions\ChannelException;
use app\core\helpers\Arr;
use app\core\helpers\CURL;
use app\core\models\SKUChannelSuppliers;
use app\core\services\crypto\StoredSecret;
use app\core\services\simpleCache\db\SimpleDBCache;

class ShopifyConnector extends BaseChannelConnector
{
    const INTEGRATION_NAME  = 'Shopify';
    const VERSION           = '2025-01';

    const PAID_INTERVAL     = 45;
    const REPEAT_BY_TIMEOUT = 5;
    const MAX_COUNT         = 250;

    /** @var ShopifyCredentials */
    protected $credentials;

    protected $reset_oauth_token = false;

    /** @return ShopifyCredentials */
    function getCredentials()
    {
        return $this->credentials;
    }

    function setCredentials($credentials)
    {
        $this->credentials = new ShopifyCredentials($credentials);
    }

    public function __construct($credentials)
    {
        $this->setCredentials($credentials);
    }

    protected function getApiURL($action)
    {
        return "https://{$this->getCredentials()->getFullShopUrl()}/admin/api/" . self::VERSION . "/{$action}";
    }

    private function setBaseAuth(CURL $curl)
    {
        $curl->addOption(CURLOPT_USERPWD, $this->getCredentials()->getApiKey() . ':' . $this->getCredentials()->getApiSecret());
    }

    protected function setAuth(CURL $curl)
    {
        switch ($this->getCredentials()->getTypeApps()) {
            case ShopifyCredentials::PRIVATE_APPS:
                $this->setBaseAuth($curl);
                break;
            case ShopifyCredentials::CUSTOM_APPS:
                $curl->setHeader('X-Shopify-Access-Token', $this->getCredentials()->getAccessToken());
                break;
            case ShopifyCredentials::OAUTH_APPS:
                $access_token = $this->getAccessTokenFromOauthCached();
                if ($access_token === null)
                    throw new \Exception('Shopify oauth error');
                $curl->setHeader('X-Shopify-Access-Token', $access_token);
                break;
        }
    }

    protected function getAccessTokenFromOauthCached(): ?string
    {
        $prefix = SimpleDBCache::PREFIX_SHOPIFY_OAUTH;
        $url = $this->getCredentials()->getFullShopUrl();
        $client_id = $this->getCredentials()->getClientId();
        $client_secret = $this->getCredentials()->getClientSecret();
        $key = $prefix . hash('sha256', $url . '|' . $client_id);
        $cache = new SimpleDBCache();
        $token = $cache->get($key);
        if (is_string($token))
            $token = StoredSecret::decrypt($token);
        if (!$token || $this->reset_oauth_token) {
            $this->reset_oauth_token = false;
            $access_data = $this->getAccessDataFromOauth($url, $client_id, $client_secret);
            if ($access_data === null)
                throw new \Exception('Shopify oauth error');
            $expires_in = (int)($access_data['expires_in'] ?? 0);
            if ($expires_in <= 0) {
                throw new \Exception('Shopify oauth error: invalid expires_in');
            }
            $expires_in = $expires_in - floor($expires_in * 0.2);
            $cache->set($key, StoredSecret::encrypt($access_data['access_token']), (int)$expires_in);
            $token = $access_data['access_token'];
        }
        return $token;
    }

    protected function getAccessDataFromOauth(string $url, string $client_id, string $client_secret): ?array
    {
        $curl = new CURL("https://$url/admin/oauth/access_token", 'shopify_oauth_access_token');
        $curl->setMethod('POST');
        $curl->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $curl->setBody(['client_id' => $client_id, 'client_secret' => $client_secret, 'grant_type' => 'client_credentials']);
        $result = $curl->exec();
        if ($curl->getResultCode() === 200) {
            $result = json_decode($result, true);
            return ['access_token' => $result['access_token'], 'expires_in' => $result['expires_in']];
        } else
            Debug::sendDebugInfo('Shopify oauth error', "URL: " . $url . " HTTP Code: " . $curl->getResultCode() . " Message: " . $curl->getResultError(), Debug::TEST);
        return null;
    }

    public function getInfoByInventoryItemLocationIdsGraphQL(array $inventory_item_ids, array $location_ids)
    {
        $ids = $return_items = [];
        foreach ($inventory_item_ids as $inventory_item_id)
            $ids[] = "id:$inventory_item_id";
        $ids = implode(' OR ', $ids);
        foreach ($location_ids as $location_id) {
            $query = 'query inventoryItems { 
                      inventoryItems(first: 250, query: "' . $ids . '") { 
                        nodes { 
                          id inventoryLevel(locationId:"gid://shopify/Location/' . $location_id . '") { location { id } quantities(names: "available") { quantity updatedAt } }
                        } 
                      }
                  }';
            $items = $this->graphQLRequest($query, 'shopify_inventory_item_location');
            foreach ($items['data']['inventoryItems']['nodes'] as $item) {
                if (empty($item['inventoryLevel']))
                    continue;
                $return_items[] = [
                    'inventory_item_id' => $this->getShortId($item['id']),
                    'location_id' => $this->getShortId($item['inventoryLevel']['location']['id']),
                    'available' => $item['inventoryLevel']['quantities'][0]['quantity'],
                    'updated_at' => $item['inventoryLevel']['quantities'][0]['updatedAt'],
                ];
            }
        }
        return $return_items;
    }

    public function getInventory($inventories, $location_id)
    {
        $data = [];
        $parts = Arr::slices($inventories, self::MAX_COUNT);
        foreach ($parts as $part)
            $data = array_merge($data, $this->getInfoByInventoryItemLocationIdsGraphQL($part, [$location_id]));
        if (empty($data))
            return [];
        array_walk($data, function (&$inventory) {
            $inventory['available'] = (int)($inventory['available'] ?? 0);
        });
        return Arr::map($data, 'inventory_item_id', 'available');
    }

    public function setInventoryGraphQL($inventory_item_id, $adjustment, $location_id)
    {
        $query = "mutation { 
                    inventoryAdjustQuantities(input: { 
                      changes: [ { 
                        inventoryItemId: \"gid://shopify/InventoryItem/$inventory_item_id\", delta: $adjustment, locationId: \"gid://shopify/Location/$location_id\" 
                      } ], name: \"available\", reason: \"other\" 
                    }) { userErrors { field message code } }
                  }";
        $result = $this->graphQLRequest($query, 'shopify_inventory_item');
        if (!empty($result['data']['inventoryAdjustQuantities']['userErrors'])) {
            $codes = array_column($result['data']['inventoryAdjustQuantities']['userErrors'], 'code');
            if (in_array('INVALID_INVENTORY_ITEM', $codes) || in_array('ITEM_NOT_STOCKED_AT_LOCATION', $codes))
                return false;
            throw new ChannelException(json_encode($result['data']['inventoryAdjustQuantities']['userErrors']), 0);
        }
        return [];
    }

    public function setInventory($inventory_item_id, $adjustment, $location_id)
    {
        return $this->setInventoryGraphQL($inventory_item_id, $adjustment, $location_id);
    }

    // Checking

    //******** TODO Need to review
    //-----------------------------

    private $shopInfo = [];
    private $locationsCache = [];

    public function getUniqueBarcode($shop_id, $external_id)
    {
        return "SF{$shop_id}SKU{$external_id}";
    }

    public function fetchByProductId(array $params = [])
    {
        $items = $this->fetchAllProductsGraphQL(['ids' => [$params['product_id']], 'time_products' => 1]);
        return json_encode(['products' => $items]);
    }

    public function fetchByVariantIdGraphQL($variant_id)
    {
        $variant_id = 'gid://shopify/ProductVariant/' . $variant_id;
        $query = "query { productVariant(id:\"$variant_id\") { id sku inventoryItem { id } updatedAt createdAt } }";
        $data = $this->graphQLRequest($query, 'shopify_variant_by_id');
        return [
            'variant' => [
                'id' => $this->getShortId($data['data']['productVariant']['id'] ?? ''),
                'sku' => $data['data']['productVariant']['sku'] ?? '',
                'inventory_item_id' => $this->getShortId($data['data']['productVariant']['inventoryItem']['id'] ?? ''),
                'created_at' => $data['data']['productVariant']['createdAt'] ?? '',
                'updated_at' => $data['data']['productVariant']['updatedAt'] ?? '',
            ]
        ];
    }

    public function fetchAllProducts(array $params = [])
    {
        return $this->fetchAllProductsGraphQL($params);
    }

    public function fetchAllProductsGraphQL(array $params = [], bool $map = true)
    {
        $short_id_func = function (string $id) {
            return $this->getShortId($id);
        };
        $map_func = static function ($products) use ($short_id_func) {
            $mapped_data = [];
            foreach ($products as $key => $product) {
                $mapped_variants = [];
                $variants = $product['variants']['nodes'] ?? [];
                $product_id = $short_id_func($product['id']);
                foreach ($variants as $key_var => $variant) {
                    $unit = $variant['inventoryItem']['measurement']['weight']['unit'];
                    $val = $variant['inventoryItem']['measurement']['weight']['value'];
                    $grams = 0;
                    if ($unit === 'KILOGRAMS')
                        $grams = 1000 * $val;
                    $mapped_variants[$key_var] = [
                        'id' => $short_id_func($variant['id']),
                        'product_id' => $product_id,
                        'inventory_item_id' => $short_id_func($variant['inventoryItem']['id']),
                        'title' => $variant['title'],
                        'barcode' => $variant['barcode'],
                        'sku' => $variant['sku'],
                        'created_at' => $variant['createdAt'],
                        'price' => $variant['price'],
                        'grams' => $grams
                    ];
                }
                $mapped_data[$key] = [
                    'id' => $product_id,
                    'title' => $product['title'],
                    'body_html' => $product['descriptionHtml'],
                    'handle' => $product['handle'],
                    'created_at' => $product['createdAt'],
                    'image' => [
                        'src' => $product['featuredImage']['url'] ?? '',
                    ],
                    'variants' => $mapped_variants,
                    'bundle_components' => $product['bundleComponents']['nodes'] ?? [],
                    // ABF-475 EU PID: product metafields, for the optional NS-PID custom-field mapping
                    'metafields' => $product['metafields']['nodes'] ?? [],
                ];
            }
            return $mapped_data;
        };
        $query_func = static function($limit, $query, $cursor = '') {
            $after = $cursor ? ", after:\"$cursor\"" : "";
            return "query {
                      products(first: $limit, query: $query $after) {
                        nodes {
                            id
                            title
                            descriptionHtml
                            handle
                            createdAt
                            featuredImage {
                              url
                            }
                            variants(first: 250) {
                              nodes {
                                id
                                defaultCursor
                                title
                                barcode
                                sku
                                createdAt
                                price
                                inventoryItem {
                                  id
                                  measurement {
                                    weight {
                                      unit
                                      value
                                    }
                                  }
                                }
                              }
                            }
                            bundleComponents(first: 50) {
                                nodes {
                                  quantity
                                  componentProduct {
                                    id
                                  }
                                  componentVariants(first: 50) {
                                    nodes {
                                      id
                                    }
                                  }
                                }
                            }
                            metafields(first: 50) {
                              nodes {
                                key
                                namespace
                                value
                                definition {
                                  name
                                }
                              }
                            }
                        }
                        pageInfo {
                          hasNextPage
                          endCursor
                        }
                      }
                    }";
        };
        $time_products = (int)($params['time_products'] ?? 0);
        $start_product_id = 0;
        if (empty($time_products) || $time_products < 0) {
            if (empty($params['shop_id']))
                throw new ChannelException('On first launch of "Shopify get_products" parameter "shop_id" should exist.');
            $max_external_id = SKUChannelSuppliers::getMaxExternalId($params['shop_id']);
            $start_product_id = (int)(!empty($max_external_id[0]) ? explode('-', $max_external_id[0])[2] : $start_product_id);
        }
        if (!empty($params['ids'])) {
            $ids = array_map(function ($id) {
                return "(id:$id)";
            }, $params['ids']);
            $ids = implode(' OR ', $ids);
            $query = "\"$ids\"";
        } elseif (empty($time_products))
            $query = "\"id:>$start_product_id\"";
        else {
            $date = date('Y-m-dTH:i:s\Z', $time_products);
            $query = "\"updated_at:>'$date'\"";
        }
        $limit = 20;
        $products = [];
        do {
            $cursor = $items['data']['products']['pageInfo']['endCursor'] ?? '';
            $query_r = $query_func($limit, $query, $cursor);
            $items = $this->graphQLRequest($query_r, 'shopify_all_products');
            $has_next = $items['data']['products']['pageInfo']['hasNextPage'] ?? false;
            $products = array_merge($products, $items['data']['products']['nodes'] ?? []);
        } while($has_next);
        if ($map)
            return $map_func($products);
        return $products;
    }

    public function getShopInfo()
    {
        return $this->getShopInfoGraphQL();
    }

    public function getShopInfoGraphQL()
    {
        if (empty($this->shopInfo)) {
            $data = $this->graphQLRequest("query { shop { currencyCode } }", 'shopify_shop_info');
            $this->shopInfo = [
                'currency' => $data['data']['shop']['currencyCode'] ?? ''
            ];
        }
        return $this->shopInfo;
    }

    public function getInventoryItemGraphQL($id)
    {
        $query = "query inventoryItem { 
                            inventoryItem(id: \"gid://shopify/InventoryItem/$id\") { 
                                id
                                unitCost {
                                    amount
                                }
                                harmonizedSystemCode
                                countryCodeOfOrigin
                            } 
                        }";
        $data = $this->graphQLRequest($query, 'shopify_inventory_item');
        $result = $data['data']['inventoryItem'];
        if (empty($result))
            return [];
        return [
            'id' => $this->getShortId($result['id']),
            'cost' => $result['unitCost']['amount'] ?? null,
            'harmonized_system_code' => $result['harmonizedSystemCode'],
            'country_code_of_origin' => $result['countryCodeOfOrigin'],
        ];
    }

    public function getInventoryItem($id)
    {
        return $this->getInventoryItemGraphQL($id);
    }

    public function getInventoryItems($ids)
    {
        if (empty($ids) || !is_array($ids))
            return false;
        $result = [];
        foreach ($ids as $id)
            $result[$id] = $this->getInventoryItem($id);
        return $result;
    }

    public function fetchAllOrders(array $params = [])
    {
        return [];
    }

    public function fetchOrdersGraphQL(array $params = [], ?string $cursor = null)
    {
        $created_at = empty($params['created_at']) ? date('c', time() - 86400 * self::PAID_INTERVAL) : $params['created_at'];
        $query_params = [
            "(financial_status:paid)",
            "(fulfillment_status:unfulfilled)",
            "(created_at:>'$created_at')",
            "(status:open)",
        ];
        return $this->fetchOrdersQueryGraphQL($query_params, $cursor);
    }

    protected function fetchOrdersQueryGraphQL(array $query_params = [], ?string $cursor = null)
    {
        $query_params = implode(" AND ", $query_params);
        $after = $cursor ? ", after:\"$cursor\"" : "";
        $query = "query { 
                    orders(first: 50, query: \"$query_params\" $after) { 
                        nodes {
                          id
                          name
                          createdAt
                          tags
                          email
                          currencyCode
                          note
                          customAttributes {
                            key
                            value
                          }
                          refunds(first: 200) {
                            refundLineItems(first: 200) {
                              nodes {
                                id
                                lineItem {
                                  id
                                }
                              }
                            }
                          }
                          metafields(first: 200) {
                            nodes {
                              key
                              namespace
                              value
                            }
                          }
                          shippingLines(first: 200) {
                            nodes { 
                              code
                              title
                            }
                          }
                          lineItems(first: 200) {
                            nodes {
                              id
                              sku
                              quantity
                              name
                              title
                              product {
                                id
                                legacyResourceId
                              }
                              variant {
                                  id 
                                  legacyResourceId
                              }
                              requiresShipping
                              fulfillmentStatus
                              fulfillableQuantity
                              discountAllocations {
                                 allocatedAmountSet {
                                   shopMoney {
                                     amount
                                     currencyCode
                                   }
                                   presentmentMoney {
                                     amount
                                     currencyCode
                                   }
                                 }  
                              }
                              originalUnitPriceSet {
                                shopMoney {
                                  amount
                                  currencyCode
                                }
                              }
                            }    
                          }
                          shippingAddress {
                            name
                            company
                            phone
                            address1
                            address2
                            city
                            zip
                            province
                            countryCode
                          }
                          customer {
                            email
                            firstName
                            lastName
                            phone
                            defaultAddress {
                              firstName
                              lastName
                              company
                              phone
                              address1
                              address2
                              city
                              zip
                              province
                              countryCode
                            }
                          }
                          totalShippingPriceSet {
                            shopMoney {
                              amount
                              currencyCode
                            }
                          }
                          fulfillmentOrders(first: 200) {
                            nodes {
                              id
                            }
                          }
                        }
                        pageInfo {
                          hasNextPage
                          endCursor
                        }
                    } 
                  }";
        $items = $this->graphQLRequest($query, 'shopify_orders');
        $has_next = $items['data']['orders']['pageInfo']['hasNextPage'] ?? false;
        $orders = [];
        foreach ($items['data']['orders']['nodes'] as $key => $item) {
            $line_items = [];
            foreach ($item['lineItems']['nodes'] ?? [] as $line_item) {
                $discount_allocations = [];
                foreach ($line_item['discountAllocations'] as $discountAllocation)
                    $discount_allocations[] = ['amount' => $discountAllocation['allocatedAmountSet']['shopMoney']['amount'], 'currency' => $discountAllocation['allocatedAmountSet']['shopMoney']['currencyCode']];
                $line_items[] = [
                    'id' => $this->getShortId($line_item['id']),
                    'title' => $line_item['title'],
                    'sku' => $line_item['sku'],
                    'name' => $line_item['name'],
                    'quantity' => $line_item['quantity'],
                    'product_exists' => !empty($line_item['product']['id']),
                    'product_id' => !empty($line_item['product']['id']) ? (int)$line_item['product']['legacyResourceId'] : 0,
                    'variant_id' => !empty($line_item['variant']['id']) ? (int)$line_item['variant']['legacyResourceId'] : 0,
                    'requires_shipping' => $line_item['requiresShipping'],
                    'fulfillment_status' => $line_item['fulfillmentStatus'],
                    'fulfillable_quantity' => $line_item['fulfillableQuantity'],
                    'discount_allocations' => $discount_allocations,
                    'price' => $line_item['originalUnitPriceSet']['shopMoney']['amount'] ?? 0
                ];
            }
            $shipping_address = $customer = $default_address = [];
            if (!empty($item['shippingAddress']))
                $shipping_address = [
                    'name' => $item['shippingAddress']['name'],
                    'company' => $item['shippingAddress']['company'],
                    'phone' => $item['shippingAddress']['phone'],
                    'address1' => $item['shippingAddress']['address1'],
                    'address2' => $item['shippingAddress']['address2'],
                    'city' => $item['shippingAddress']['city'],
                    'zip' => $item['shippingAddress']['zip'],
                    'province' => $item['shippingAddress']['province'],
                    'country_code' => $item['shippingAddress']['countryCode'],
                ];
            if (!empty($item['customer']['defaultAddress']))
                $default_address = [
                    'first_name' => $item['customer']['defaultAddress']['firstName'],
                    'last_name' => $item['customer']['defaultAddress']['lastName'],
                    'company' => $item['customer']['defaultAddress']['company'],
                    'phone' => $item['customer']['defaultAddress']['phone'],
                    'address1' => $item['customer']['defaultAddress']['address1'],
                    'address2' => $item['customer']['defaultAddress']['address2'],
                    'city' => $item['customer']['defaultAddress']['city'],
                    'zip' => $item['customer']['defaultAddress']['zip'],
                    'province' => $item['customer']['defaultAddress']['province'],
                    'country_code' => $item['customer']['defaultAddress']['countryCode'],
                ];
            if (!empty($item['customer']))
                $customer = [
                    'email' => $item['customer']['email'],
                    'first_name' => $item['customer']['firstName'],
                    'last_name' => $item['customer']['lastName'],
                    'phone' => $item['customer']['phone'],
                    'default_address' => $default_address
                ];
            $fulfillment_orders_ids = [];
            foreach ($item['fulfillmentOrders']['nodes'] as $fulfillment_orders_id)
                $fulfillment_orders_ids[] = $fulfillment_orders_id['id'];

            $refunds = [];
            foreach ($item['refunds'] ?? [] as $refund) {
                $refund_line_items = ['refund_line_items' => []];
                foreach ($refund['refundLineItems']['nodes'] ?? [] as $node) {
                    $refund_line_items['refund_line_items'][] = ['line_item_id' => $this->getShortId($node['lineItem']['id'])];
                }
                $refunds[] = $refund_line_items;
            }

            $note_attributes = [];
            foreach ($item['customAttributes'] as $custom_attribute)
                $note_attributes[] = ['name' => $custom_attribute['key'], 'value' => $custom_attribute['value']];

            $orders[$key] = [
                'id' => $this->getShortId($item['id']),
                'name' => $item['name'],
                'created_at' => $item['createdAt'],
                'tags' => implode(', ', $item['tags']),
                'note' => $item['note'],
                'note_attributes' => $note_attributes,
                'email' => $item['email'],
                'currency' => $item['currencyCode'],
                'line_items' => $line_items,
                'shipping_address' => $shipping_address,
                'customer' => $customer,
                'refunds' => $refunds,
                'total_shipping_price_set' => [
                    'shop_money' => [
                        'amount' => $item['totalShippingPriceSet']['shopMoney']['amount'] ?? 0,
                        'currency_code' => $item['totalShippingPriceSet']['shopMoney']['currencyCode'] ?? ''
                    ],
                ],
                'shipping_lines' => $item['shippingLines']['nodes'] ?? [],
                'fulfillment_orders_ids' => $fulfillment_orders_ids,
                'metafields' => $item['metafields']['nodes']
            ];
        }

        return ['has_next' => $has_next, 'orders' => $orders, 'cursor' => $items['data']['orders']['pageInfo']['endCursor'] ?? ''];
    }

    public function fetchByOrderId($id)
    {
        $items = $this->fetchOrderGraphQL($id);
        return json_encode(['order' => $items['orders'][0] ?? []]);
    }

    public function fetchOrderGraphQL(int $id, ?string $cursor = null)
    {
        $query_params = ["(id:$id)",];
        return $this->fetchOrdersQueryGraphQL($query_params, $cursor);
    }

    public function checkConnection()
    {
        $this->setConnectionError('');
        $scopes = $this->getAccessList();
        $required_scopes = ['read_products', 'read_orders'];
        $errors = [];
        foreach ($required_scopes as $required_scope)
            if (empty($scopes[$required_scope]))
                $errors[] = $required_scope;
        if (!empty($errors))
            $this->setConnectionError("Required permissions are not set: " . implode(", ", $errors));
    }

    public function getInventoryByLocation($location_id)
    {
        $data = [];
        $url = $this->getApiURL("locations/$location_id/inventory_levels.json?limit=250");
        do {
            $curl = new CURL($url, 'shopify_inventory_by_location');
            $this->setAuth($curl);
            $result = $curl->exec();
            if (empty($result) || $curl->getResultCode() != 200)
                return $data;
            $decoded_res = json_decode($result, 1);
            $data = array_merge($data, $decoded_res['inventory_levels']);
            preg_match("/Link: <(.*)>; rel=\"next\"/iU", $curl->getResultHeaders(), $matches);
            if(empty($matches[1]))
                $url = '';
            else {
                if ($pos = strpos($matches[1], "rel=\"previous\", <"))
                    $url = substr($matches[1], strlen("rel=\"previous\", <") + $pos);
                else
                    $url = $matches[1];
            }
            sleep(1);
        } while(!empty($url));
        return $data;
    }

    public function getLocationsGraphQL()
    {
        if (!empty($this->locationsCache[$this->getCredentials()->getFullShopUrl()]))
            return $this->locationsCache[$this->getCredentials()->getFullShopUrl()];
        $query = "query { 
                    locations (first: 250, includeInactive: true, includeLegacy: true) { 
                      nodes { 
                        id 
                        name 
                      } 
                    }  
                  } ";
        $items = $this->graphQLRequest($query, 'shopify_location');
        $data = [];
        foreach ($items['data']['locations']['nodes'] ?? [] as $item) {
            $data[] = [
                'id' => $this->getShortId($item['id']),
                'name' => $item['name'],
            ];
        }
        $this->locationsCache[$this->getCredentials()->getFullShopUrl()] = $data;
        return $data;
    }

    public function getLocations()
    {
        return $this->getLocationsGraphQL();
    }

    public function setTrackInfoNewGraphQL(array $data)
    {
        $tracking_info_q = '';
        $tracking_info = $data['tracking_info'] ?? [];
        if ($tracking_info) {
            if ($number = $tracking_info['number'] ?? '')
                $number = "number:\"$number\"";
            if ($url = $tracking_info['url'] ?? '')
                $url = "url:\"$url\"";
            if ($company = $tracking_info['company'] ?? '')
                $company = "company:\"$company\"";
            $tracking_info_q = "trackingInfo: { $company $number $url }";
        }
        $items_p = [];
        $line_items_by_fulfillment_order = $data['line_items_by_fulfillment_order'] ?? [];
        foreach ($line_items_by_fulfillment_order as $key => $line_item) {
            $fulfillment_order_id = 'gid://shopify/FulfillmentOrder/' . $line_item['fulfillment_order_id'];
            $fulfillment_order_line_items = $line_item['fulfillment_order_line_items'] ?? [];
            $items_line_p = [];
            foreach ($fulfillment_order_line_items as $fulfillment_order_line_item) {
                $fulfillment_order_line_id = 'gid://shopify/FulfillmentOrderLineItem/' . $fulfillment_order_line_item['id'];
                $fulfillment_order_line_quantity = $fulfillment_order_line_item['quantity'];
                $items_line_p[] = "{id: \"$fulfillment_order_line_id\", quantity: $fulfillment_order_line_quantity}";
            }
            $items_p[] = "{fulfillmentOrderId: \"$fulfillment_order_id\", fulfillmentOrderLineItems: [" . implode(', ', $items_line_p) . "]}";
        }
        $line_items_q = "lineItemsByFulfillmentOrder: [" . implode(', ', $items_p) . "],";
        $notify_customer = $data['notify_customer'] ? "notifyCustomer: true," : "notifyCustomer: false,";
        $fulfillment = "{
                        $line_items_q
                        $notify_customer
                        $tracking_info_q
                      }";
        $message = '';
        $query = "mutation { 
                    fulfillmentCreate(fulfillment: $fulfillment, message: \"$message\") 
                    { fulfillment { id status } userErrors { field message } } }";
        $result = $this->graphQLRequest($query, 'shopify_set_track_info');
        return $result['data']['fulfillmentCreate'] ?? [];
    }

    public function setFulfillmentEventCreateGraphQL(string $id)
    {
        $query = "mutation fulfillmentEventCreate(\$fulfillmentEvent: FulfillmentEventInput!) { fulfillmentEventCreate(fulfillmentEvent: \$fulfillmentEvent) { fulfillmentEvent { id status message } userErrors { field message } } }";
        $variables = [
            'fulfillmentEvent' => [
                'fulfillmentId' => $id,
                'status' => 'CONFIRMED',
            ]
        ];
        $result = $this->graphQLRequest($query, 'shopify_set_track_info', $variables);
        return $result['data']['fulfillmentEventCreate'];
    }

    public function getOrderRisks($order_id)
    {
        return $this->fetchOrderRisksGraphQL($order_id);
    }

    public function fetchOrderRisksGraphQL(int $order_id)
    {
        $id = "gid://shopify/Order/$order_id";
        $query = "query { 
                    order (id: \"$id\") { 
                      risk {
                        recommendation
                        assessments {
                          riskLevel
                          facts { description }
                        }
                      }
                    }  
                  } ";
        $items = $this->graphQLRequest($query, 'shopify_order_risks');
        return $items['data']['order']['risk'] ?? [];
    }

    public function getAccessList()
    {
        return $this->getAccessListGraphQL();
    }

    public function getAccessListGraphQL()
    {
        $query = "query { app { title requestedAccessScopes { description handle } } }";
        $items = $this->graphQLRequest($query, 'shopify_access_list');
        $result = [];
        foreach ($items['data']['app']['requestedAccessScopes'] ?? [] as $item)
            $result[$item['handle']] = $item['handle'];
        return $result;
    }

    public function getFulfilmentOrdersGraphQL(int $order_id, bool $get_all_status = false): ?array
    {
        $query = "query { 
                    orders(first: 250, query: \"id:$order_id\") { 
                      nodes {
                        fulfillmentOrders(first: 250) {
                          nodes {
                            id
                          }
                        }
                      }
                    } 
                  }";
        $items = $this->graphQLRequest($query, 'fulfilment_orders_ids');
        $fulfillment_orders_ids = [];
        foreach ($items['data']['orders']['nodes'][0]['fulfillmentOrders']['nodes'] ?? [] as $fulfillment_orders_id)
            $fulfillment_orders_ids[] = $fulfillment_orders_id['id'];
        return $this->getFulfilmentOrdersByFulfillmentIdGraphQL($fulfillment_orders_ids, $get_all_status);
    }

    public function getFulfilmentOrdersByFulfillmentIdGraphQL(array $fulfillment_orders_ids, bool $get_all_status = false)
    {
        if (empty($fulfillment_orders_ids))
            return [];
        $return_items = [];
        foreach ($fulfillment_orders_ids as $fulfillment_orders_id) {
            $query = "query { 
                        fulfillmentOrder(id: \"$fulfillment_orders_id\") { 
                          id
                          status
                          assignedLocation {
                            location { id }
                          }
                          lineItems(first: 250) {
                            nodes {
                              id
                              remainingQuantity
                              variant {
                                 id
                              }
                              lineItem {
                                id
                                quantity
                              }
                            }
                          }
                          deliveryMethod {
                            methodType
                          }
                        } 
                      }";
            $item = $this->graphQLRequest($query, 'fulfilment_orders');
            if (!$get_all_status && !in_array(strtolower($item['data']['fulfillmentOrder']['status']), ['open', 'in_progress']))
                continue;
            $line_items = [];
            foreach ($item['data']['fulfillmentOrder']['lineItems']['nodes'] as $line_item) {
                $line_items[] = [
                    'id' => $this->getShortId($line_item['id']),
                    'fulfillable_quantity' => $line_item['remainingQuantity'],
                    'quantity' => $line_item['lineItem']['quantity'],
                    'variant_id' => $this->getShortId($line_item['variant']['id'] ?? 0),
                    'line_item_id' => $this->getShortId($line_item['lineItem']['id']),
                ];
            }
            $return_items[] = [
                'id' => $this->getShortId($item['data']['fulfillmentOrder']['id']),
                'assigned_location_id' => $this->getShortId($item['data']['fulfillmentOrder']['assignedLocation']['location']['id'] ?? ''),
                'status' => $item['data']['fulfillmentOrder']['status'],
                'line_items' => $line_items
            ];
        }
        return $return_items;
    }

    private function graphQLRequest(string $query, string $source, array $variables = []): ?array
    {
        $query = preg_replace('/\s+/', ' ', $query);
        $data = ['query' => $query];
        if ($variables)
            $data['variables'] = $variables;
        $body = json_encode($data);
        $this->clearLast();
        $curl = new CURL($this->getApiURL("graphql.json"), $source);
        $this->setAuth($curl);
        $curl->setMethod('POST');
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setBody($body);
        $curl->setRepeatByTimeout(5);
        $curl->setRepeatCodes([0, 429]);
        $result = $curl->exec();
        if ((int)$curl->getResultCode() === 401 && $this->getCredentials()->getTypeApps() === ShopifyCredentials::OAUTH_APPS) {
            $this->reset_oauth_token = true;
            $this->setAuth($curl);
            $result = $curl->exec();
        }
        $this->setLast($curl);
        if (empty($result) || $curl->getResultCode() != 200)
            return null;
        return json_decode($result, true);
    }

    public function getShortId(string $id): int
    {
        return (int)preg_replace('/.+\//', '', $id);
    }
}