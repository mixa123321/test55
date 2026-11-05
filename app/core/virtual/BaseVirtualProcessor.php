<?php

namespace app\core\virtual;

use app\core\exceptions\VirtualException;

abstract class BaseVirtualProcessor
{
    /** @var BaseVirtualConnector $connector*/
    protected $connector;

    protected $supplier_id;

    /** @throws VirtualException */
    public function __construct($connector, $supplier_id)
    {
        if (empty($connector))
            throw new VirtualException("Warehouse isn't set up");
        $this->setConnector($connector);
        $this->setSupplierId($supplier_id);
    }

    /** @return BaseVirtualConnector */
    public function getConnector()
    {
        return $this->connector;
    }

    public function setConnector(BaseVirtualConnector $connector)
    {
        $this->connector = $connector;
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($id)
    {
        $this->supplier_id = (int)$id;
    }

    /** SKUs */
    /** @throws VirtualException */
    abstract public function getSKU($id);
    /** @throws VirtualException */
    abstract public function addSKU($SKU, $update);
    /** ASNs */
    /** @throws VirtualException */
    abstract public function getASN($id);
    /** @throws VirtualException */
    abstract public function createASN($ASN, $SKUs);
    /** @throws VirtualException */
    abstract public function updateASN($ref, $create_date);
    /** @throws VirtualException */
    abstract public function updateASNSKUs($ref, $SKUs);
    /** @throws VirtualException */
    abstract public function cancelASN($id);
    /** Orders */
    /** @throws VirtualException */
    abstract public function getOrder($id);
    /** @throws VirtualException */
    abstract public function createOrder($order, $SKUs);
    /** @throws VirtualException */
    abstract public function cancelOrder($id);

    public static function getClassName($class)
    {
        return "\\app\\core\\virtual\\" . strtolower($class) . "\\{$class}Processor";
    }
}