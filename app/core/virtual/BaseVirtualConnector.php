<?php

namespace app\core\virtual;

use app\core\exceptions\VirtualException;
use app\core\models\Warehouses;
use app\core\statical\Currencies;

abstract class BaseVirtualConnector
{
    protected $id;
    protected $credentials;
    protected $currency;
    protected $response_detail = null;

    /** @throws VirtualException */
    public function __construct($id, $credentials)
    {
        if (empty($credentials))
            throw new VirtualException("Warehouse isn't set up");
        $this->setId($id);
        $this->setCredentials($credentials);
        $warehouse = Warehouses::getById($id);
        $this->setCurrency(Currencies::getCurrency($warehouse->getCurrencyId()));
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    /** @return BaseVirtualCredentials */
    public function getCredentials()
    {
        return $this->credentials;
    }

    public function getLastResponse()
    {
        return $this->response_detail;
    }

    abstract function setCredentials($credentials);
    /** SKUs */
    /** @throws VirtualException */
    abstract function getSKU($SKU_id);
    /** @throws VirtualException */
    abstract function addSKU($SKU_data);
    /** ASNs */
    /** @throws VirtualException */
    abstract function createASN($ASN_data);
    /** @throws VirtualException */
    abstract function updateASN($ASN_data);
    /** @throws VirtualException */
    abstract function getASN($ASN_id);
    /** @throws VirtualException */
    abstract function cancelASN($ASN_id);
    /** @throws VirtualException */
    abstract function getASNs();
    /** Orders */
    /** @throws VirtualException */
    abstract function createOrder($order_data);
    /** @throws VirtualException */
    abstract function getOrder($order_id);
    /** @throws VirtualException */
    abstract function cancelOrder($order_id);
    /** Inventory */
    /** @throws VirtualException */
    abstract function getInventory();

    public function getCurrency()
    {
        return $this->currency;
    }

    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    public static function getClassName($class)
    {
        return "\\app\\core\\virtual\\" . strtolower($class) . "\\{$class}Connector";
    }
}