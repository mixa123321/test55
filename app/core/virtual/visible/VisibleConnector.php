<?php

namespace app\core\virtual\visible;

use app\core\exceptions\VirtualException;
use app\core\helpers\CURL;
use app\core\models\Warehouses;
use app\core\virtual\BaseVirtualConnector;

class VisibleConnector extends BaseVirtualConnector
{
    const TIME_CONNECT                  = 60;    // sec

    const ERROR_UNAUTHORIZED            = -1;
    const ERROR_GET_TOKEN               = -10;
    const ERROR_ITEM_NOT_FOUND          = -20;
    const ERROR_ITEM_CREATE             = -21;
    const ERROR_ASN_NOT_FOUND           = -30;
    const ERROR_ASNS_NOT_FOUND          = -31;
    const ERROR_ASN_CREATE              = -32;
    const ERROR_ASN_UPDATE              = -33;
    const ERROR_ASN_CANCEL              = -34;
    const ERROR_ORDER_NOT_FOUND         = -40;
    const ERROR_ORDER_CREATE            = -41;
    const ERROR_ORDER_CANCEL            = -42;
    const ERROR_INVENTORY_NOT_FOUND     = -50;
    const ERROR_INVENTORY_SKU_NOT_FOUND = -51;

    /** @return VisibleCredentials */
    function getCredentials()
    {
        return $this->credentials;
    }

    function setCredentials($credentials)
    {
        $this->credentials = new VisibleCredentials($credentials);
    }

    protected function getApiURL($action)
    {
        return "{$this->getCredentials()->getHost()}/api/{$action}";
    }

    /** @throws VirtualException */
    protected function getToken()
    {
        $curl = new CURL($this->getApiURL('Security/Token'), 'visible_get_token');
        $curl->setMethod('POST');
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setBody(json_encode([
            'username' => $this->getCredentials()->getUserName(),
            'password' => $this->getCredentials()->getPass(),
            'grant_type' => ''
        ]));
        $result = $curl->exec();
        if (empty($result) || $curl->getResultCode() != 200)
            throw new VirtualException('Token is not found', self::ERROR_GET_TOKEN);
        $credentials = json_decode($result, true);
        if (empty($credentials['access_token']) || empty($credentials['.expires']))
            throw new VirtualException('Token is not found', self::ERROR_GET_TOKEN);
        $this->getCredentials()->setAccessToken($credentials['access_token']);
        $this->getCredentials()->setExpires($credentials['.expires']);
        if (!Warehouses::setVirtualCredentials($this->getId(), $this->getCredentials()))
            throw new VirtualException('Unexpected error', self::ERROR_GET_TOKEN);
    }

    /** @throws VirtualException */
    protected function getCurl($action, $source)
    {
        if ($this->getCredentials()->getExpires() < time() + self::TIME_CONNECT)
            $this->getToken();
        return new CURL($this->getApiURL($action), $source);
    }

    /** @throws VirtualException */
    protected function runBearer(CURL $curl, $error_code)
    {
        $curl->setBearerAuth($this->getCredentials()->getAccessToken());
        $result = $curl->exec();
        $this->response_detail = $curl->getResponseHeaders();
        if (empty($result) || $curl->getResultCode() != 200) {
            $message = $this->getLastResponse()[0];
            $message = $message[0] ?? json_encode($message);
            throw new VirtualException($message . ($curl->getResultCode() != 401 ? " ({$curl->getResultError()})" : ''), $error_code);
        }
        return json_decode($result, true);
    }

    /** @throws VirtualException */
    protected function getData($action, $source, $error_code)
    {
        $curl = $this->getCurl($action, $source);
        $curl->setMethod('GET');
        $curl->setHeader('Content-Type', 'application/json');
        return $this->runBearer($curl, $error_code);
    }

    /** @throws VirtualException */
    protected function postData($action, $source, $data, $error_code)
    {
        $curl = $this->getCurl($action, $source);
        $curl->setMethod('POST');
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setBody(json_encode($data));
        return $this->runBearer($curl, $error_code);
    }

    /** @throws VirtualException */
    protected function deleteData($action, $source, $error_code)
    {
        $curl = $this->getCurl($action, $source);
        $curl->setMethod('DELETE');
        return $this->runBearer($curl, $error_code);
    }

    public function getSKU($item_code)
    {
        try {
            $res = $this->getData("item/$item_code", 'visible_get_item', self::ERROR_ITEM_NOT_FOUND);
        } catch (VirtualException $e) {
            if ($e->getCode() == self::ERROR_ITEM_NOT_FOUND)
                return false;
            throw $e;
        }
        if (!$res['VisibleId'])
            return false;
        return $res;
    }

    public function addSKU($item)
    {
        return $this->postData('item','visible_add_item', $item,self::ERROR_ITEM_CREATE);
    }

    public function createASN($data)
    {
        return $this->postData('asn','visible_add_asn', $data,self::ERROR_ASN_CREATE);
    }

    public function updateASN($data)
    {
        return $this->postData('asn','visible_update_asn', $data,self::ERROR_ASN_UPDATE);
    }

    public function getASN($id)
    {
        try {
            $res = $this->getData("asn/$id", 'visible_get_asn', self::ERROR_ASN_NOT_FOUND);
        } catch (VirtualException $e) {
            if ($e->getCode() == self::ERROR_ASN_NOT_FOUND)
                return false;
            throw $e;
        }
        return $res;
    }

    public function cancelASN($id)
    {
        return $this->deleteData("asn/$id", 'visible_cancel_asn', self::ERROR_ASN_CANCEL);
    }

    public function getASNs()
    {
        return $this->getData("asn", 'visible_get_asns', self::ERROR_ASNS_NOT_FOUND);
    }

    public function getInventory()
    {
        try {
            $res = $this->getData("inventory", 'visible_get_inventory', self::ERROR_INVENTORY_NOT_FOUND);
        } catch (VirtualException $e) {
            if ($e->getCode() == self::ERROR_INVENTORY_NOT_FOUND)
                return false;
            throw $e;
        }
        return $res;
    }

    public function getInventorySKU($item_code)
    {
        try {
            $res = $this->getData("inventory/itemcode/$item_code", 'visible_get_inventory_SKU', self::ERROR_INVENTORY_SKU_NOT_FOUND);
        } catch (VirtualException $e) {
            if ($e->getCode() == self::ERROR_INVENTORY_SKU_NOT_FOUND)
                return false;
            throw $e;
        }
        return $res;
    }

    public function createOrder($data)
    {
        return $this->postData('order','visible_add_order', $data,self::ERROR_ORDER_CREATE);
    }

    public function getOrder($id)
    {
        try {
            $res = $this->getData("order/$id", 'visible_get_order', self::ERROR_ORDER_NOT_FOUND);
        } catch (VirtualException $e) {
            if ($e->getCode() == self::ERROR_ORDER_NOT_FOUND)
                return false;
            throw $e;
        }
        if (!$res['VisibleId'])
            return false;
        return $res;
    }

    public function cancelOrder($id)
    {
        return $this->deleteData("order/$id", 'visible_cancel_order', self::ERROR_ORDER_CANCEL);
    }
}
