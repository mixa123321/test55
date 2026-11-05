<?php

namespace app\core\channels;

use app\core\exceptions\ChannelException;
use app\core\helpers\CURL;

/**
 * Connector child classes must implement data transmission logic with the integration system (e.g. Shopify)
 * with minimum relation to the system logic
 * Data flow scheme is: Integration -> Connector -> Processor
 */
abstract class BaseChannelConnector
{
    protected $credentials;
    public $connection_errors = '';
    protected $last_result_code = null;
    protected $last_result_error = null;

    public function setConnectionError($msg = '')
    {
        if (empty($msg) || empty($this->connection_errors))
            $this->connection_errors = $msg;
        else
            $this->connection_errors .= "; $msg";
    }

    public function getLastResultCode(): ?int
    {
        return $this->last_result_code === null ? null : (int)$this->last_result_code;
    }

    public function getLastResultError(): ?string
    {
        return $this->last_result_error === null ? null : (string)$this->last_result_error;
    }

    /** @return BaseCredentials */
    public function getCredentials()
    {
        return $this->credentials;
    }

    abstract function setCredentials($credentials);

    abstract public function fetchAllOrders(array $param = []);
    abstract public function fetchAllProducts(array $param = []);
    abstract public function getUniqueBarcode($shop_id, $external_id);
    abstract public function checkConnection();
    /** @throws ChannelException */
    abstract public function getInventory($inventories, $location_id);
    /** @throws ChannelException */
    abstract public function setInventory($inventory_item_id, $adjustment, $location_id);

    protected function clearLast()
    {
        $this->last_result_code = $this->last_result_error = null;
    }

    protected function setLast(CURL $curl)
    {
        $this->last_result_error = $curl->getResultError();
        $this->last_result_code = $curl->getResultCode();
    }
}