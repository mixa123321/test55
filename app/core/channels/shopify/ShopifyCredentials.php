<?php

namespace app\core\channels\shopify;

use app\core\channels\BaseCredentials;

class ShopifyCredentials extends BaseCredentials
{
    const SUFFIX        = '.myshopify.com';
    const PRIVATE_APPS  = 1;
    const CUSTOM_APPS   = 2;
    const OAUTH_APPS   = 3;

    protected $type_apps;
    protected $api_key;
    protected $api_secret;
    protected $access_token;
    protected $client_id;
    protected $client_secret;

    public function getFullShopUrl()
    {
        return $this->getShopUrl() . self::SUFFIX;
    }

    public function getTypeApps()
    {
        return $this->type_apps;
    }

    public function setTypeApps($type_apps)
    {
        $this->type_apps = (int)$type_apps;
    }

    public function getApiKey()
    {
        return $this->api_key;
    }

    public function setApiKey($api_key)
    {
        $this->api_key = $api_key;
    }

    public function getApiSecret()
    {
        return $this->api_secret;
    }

    public function setApiSecret($api_secret)
    {
        $this->api_secret = $api_secret;
    }

    public function getAccessToken()
    {
        return $this->access_token;
    }

    public function setAccessToken($access_token)
    {
        $this->access_token = $access_token;
    }

    public function getClientId()
    {
        return $this->client_id;
    }

    public function setClientId($client_id)
    {
        $this->client_id = $client_id;
    }

    public function getClientSecret()
    {
        return $this->client_secret;
    }

    public function setClientSecret($client_secret)
    {
        $this->client_secret = $client_secret;
    }
}