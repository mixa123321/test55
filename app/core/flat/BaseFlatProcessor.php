<?php

namespace app\core\flat;

use app\core\models\Warehouses;
use app\core\statical\Currencies;

abstract class BaseFlatProcessor
{
    /** @var BaseFlatConnector $connector*/
    protected $connector;
    protected $warehouse_id;
    protected $supplier_id;
    protected $currency;

    public function getConnector()
    {
        return $this->connector;
    }

    public function setConnector(BaseFlatConnector $connector)
    {
        $this->connector = $connector;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
        $warehouse = Warehouses::getById($this->warehouse_id);
        $this->currency = Currencies::getCurrency($warehouse->getCurrencyId());
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($id)
    {
        $this->supplier_id = (int)$id;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function getId()
    {
        return $this->getConnector()->getId();
    }

    public function getErrorMessages()
    {
        return $this->getConnector()->getErrorMessages();
    }

    public function setErrorMessages($msg)
    {
        $this->getConnector()->setErrorMessages($msg);
    }

    abstract public function processOrders();
    abstract public function processReport($report_date, $order_ids = []);
    abstract public function processReportManual($orders);
    abstract public function processCheck();
}