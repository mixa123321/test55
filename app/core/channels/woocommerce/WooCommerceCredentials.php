<?php

namespace app\core\channels\woocommerce;

use app\core\channels\BaseCredentials;

/** Incapsulated WooCommerce credentials */
class WooCommerceCredentials extends BaseCredentials
{
    protected $consumer_key;
    protected $consumer_secret;
    protected $time_orders;

    public function getConsumerKey()
    {
        return $this->consumer_key;
    }

    public function setConsumerKey($key)
    {
        $this->consumer_key = (string)$key;
    }

    public function getConsumerSecret()
    {
        return $this->consumer_secret;
    }

    public function setConsumerSecret($key)
    {
        $this->consumer_secret = (string)$key;
    }

    public function getTimeOrders()
    {
        return $this->time_orders;
    }

    public function setTimeOrders($key)
    {
        $this->time_orders = (string)$key;
    }
}