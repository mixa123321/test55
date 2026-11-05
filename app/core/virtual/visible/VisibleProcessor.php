<?php

namespace app\core\virtual\visible;

use app\backend\models\Orders;
use app\backend\models\SKUs;
use app\core\entities\Order;
use app\core\exceptions\VirtualException;
use app\core\helpers\notifiers\Notifier;
use app\core\virtual\BaseVirtualProcessor;
use app\core\virtual\VirtualNotification;

class VisibleProcessor extends BaseVirtualProcessor
{
    const ORDER_NEW                     = 'New';
    const ORDER_ALLOCATED               = 'Allocated';
    const ORDER_OPTIMIZED               = 'Optimized';
    const ORDER_BACKORDER               = 'Backorder';
    const ORDER_WAITING_FOR_ALLOCATION  = 'WaitingForAllocation';
    const ORDER_READY_TO_PICK           = 'ReadyToPick';
    const ORDER_PICKING                 = 'Picking';
    const ORDER_WAITING_FOR_PROCESSING  = 'WaitingForProcessing';
    const ORDER_READY_FOR_NOVO          = 'ReadyForNovo';
    const ORDER_SHIPPING_WMS_NOVO       = 'ShippingWMSNovo';
    const ORDER_READY_TO_PRINT          = 'ReadyToPrint';
    const ORDER_READY_TO_PACK           = 'ReadyToPack';
    const ORDER_PRINTED                 = 'Printed';
    const ORDER_READY_TO_SHIP           = 'ReadyToShip';
    const ORDER_PARTIAL_SHIPPED         = 'PartialShipped';
    const ORDER_COMPLETED               = 'Completed';
    const ORDER_SHIPPED                 = 'Shipped';
    const ORDER_CANCELLED               = 'Cancelled';

    public static $status_order_mapping = [
        self::ORDER_NEW                     => Order::ALLOCATED,
        self::ORDER_ALLOCATED               => Order::ALLOCATED,
        self::ORDER_OPTIMIZED               => Order::ALLOCATED,
        self::ORDER_BACKORDER               => Order::BACKORDER,
        self::ORDER_WAITING_FOR_ALLOCATION  => Order::WAITING_FOR_REPLEN,
        self::ORDER_READY_TO_PICK           => Order::AWAITING_PICK,
        self::ORDER_PICKING                 => Order::PICKING,
        self::ORDER_WAITING_FOR_PROCESSING  => Order::PROCESSING,
        self::ORDER_READY_FOR_NOVO          => Order::PROCESSING,
        self::ORDER_SHIPPING_WMS_NOVO       => Order::PROCESSING,
        self::ORDER_READY_TO_PRINT          => Order::PROCESSING,
        self::ORDER_READY_TO_PACK           => Order::AWAITING_PACK,
        self::ORDER_PRINTED                 => Order::PACKING,
        self::ORDER_READY_TO_SHIP           => Order::PACKING,
        self::ORDER_PARTIAL_SHIPPED         => Order::PACKING,
        self::ORDER_COMPLETED               => Order::PACKED,
        self::ORDER_SHIPPED                 => Order::DESPATCHED,
        self::ORDER_CANCELLED               => Order::CANCELLED,
    ];

    public static $status_order_can_cancelled = [
        self::ORDER_NEW,
        self::ORDER_BACKORDER,
        self::ORDER_READY_FOR_NOVO,
        self::ORDER_SHIPPING_WMS_NOVO,
    ];

    private static $mapping_SKU = [
        'ItemCode' => ['id', true],
        'Name' => ['name', false],
        'MfgItemCode' => ['customer_ref', false],
        'AlternateItemCode' => ['barcode', false],
        'Cost' => ['market_value', false],
        'CurrencyCode' => ['market_currency', false],
        'IsBarCoded' => true,
        'BarCodeNumber' => ['barcode', false],
        'CountryOfOrigin' => ['country_id', false],
    ];

    private function toVirtualSKU($product)
    {
        $res = [];
        foreach (self::$mapping_SKU as $key => $value) {
            if (!is_array($value)) {
                $res[$key] = $value;
                continue;
            }
            if (empty($product[$value[0]])) {
                if ($value[1])
                    return [false, 'Required field(s) not found'];
                else
                    continue;
            }
            $res[$key] = $product[$value[0]];
        }
        return $res;
    }

    private function setValidate(&$product)
    {
        if (!empty($product['name']) && strlen($product['name']) > 50)
            $product['name'] = substr($product['name'], 0, 47) . '...';
        if (!empty($product['country_id']) && $product['country_id'] == 'UK')
            $product['country_id'] = 'GB';
        if (!empty($product['customer_ref']) && strlen($product['customer_ref']) > 50)
            $product['customer_ref'] = substr($product['customer_ref'], 0, 47) . '...';
    }

    private function setValidateASN(&$ASN)
    {
        if (!empty($ASN['ref']) && strlen($ASN['ref']) > 50)
            $ASN['ref'] = substr($ASN['ref'], 0, 50);
    }

    public function getSKU($item_code)
    {
        return $this->getConnector()->getSKU($item_code);
    }

    /** @throws VirtualException */
    private function updateSKU($virtual_product, $changes)
    {
        foreach (self::$mapping_SKU as $param => $value) {
            if ($value === true)
                continue;
            if (empty($changes[$value[0]]))
                continue;
            $virtual_product[$param] = $changes[$value[0]][1];
        }
        $this->getConnector()->addSKU($virtual_product);
        return true;
    }

    public function addSKU($product, $update = false)
    {
        $this->setValidate($product);
        $virtual_product = $this->toVirtualSKU($product);
        if (empty($virtual_product))
            return false;
        $exist = $this->getSKU($virtual_product['ItemCode']);
        if ($exist) {
            if ($update !== false)
                return $this->updateSKU($exist, $update);
            return false;
        }
        $this->getConnector()->addSKU($virtual_product);
        return true;
    }

    public function getASN($id)
    {
        if (empty($id))
            return false;
        return $this->getConnector()->getASN(rawurlencode($id));
    }

    public function createASN($ASN, $SKUs)
    {
        if (empty($ASN['id']) || empty($ASN['arrival_date']) || empty($ASN['ref']) || empty($SKUs))
            return false;
        $this->setValidateASN($ASN);
        $data = [
            'OrderNumber' => $ASN['ref'],
            'OrderType' => 'Open',
            'OrderDate' => date(DATE_ATOM, $ASN['create_date']),
            'ShipmentReference' => $ASN['id'],
            'PurchaseOrderDetails' => [],
            'ShipToLocation' => $ASN['ship_to_location'],
        ];
        foreach ($SKUs as $SKU_id => $SKU) {
            $datum = [
                'ItemNumber' => $SKU_id,
                'QuantityOrdered' => $SKU['quantity'],
                'QuantityUoM' => 'ea',
                'ExpectedDate' => $ASN['arrival_date'],
            ];
            if (!empty($ASN['carrier']))
                $datum['CarrierName'] = $ASN['carrier'];
            if (!empty($SKU['batch_number'])) {
                $datum['LotNumber'] = $SKU['batch_number'];
                $datum['LotExpiration'] = $SKU['batch_expired'];
            }
            $data['PurchaseOrderDetails'][] = $datum;
        }
        $this->getConnector()->createASN($data);
        return true;
    }

    public function updateASN($ref, $create_date)
    {
        if (empty($ref) || empty($create_date))
            return false;
        $ASN_visible = $this->getASN($ref);
        $ASN_visible['OrderDate'] = $create_date;
        $this->getConnector()->updateASN($ASN_visible);
        return true;
    }

    public function updateASNSKUs($ref, $SKUs)
    {
        if (empty($ref) || empty($SKUs))
            throw new VirtualException('ASN is not found or SKUs are not found');
        $ASN_visible = $this->getASN($ref);
        foreach ($ASN_visible['PurchaseOrderDetails'] as $key => &$item) {
            $SKU_id = $item['ItemNumber'];
            if (!isset($SKUs[$SKU_id]))
                continue;
            $item['QuantityOrdered'] = (float)$SKUs[$SKU_id];
        } unset($item);
        $this->getConnector()->updateASN($ASN_visible);
        return true;
    }

    public function cancelASN($id)
    {
        if (empty($id))
            return false;
        $this->getConnector()->cancelASN($id);
        return true;
    }

    private function validateOrder($order)
    {
        return !(empty($order['create_date']) || empty($order['id']) ||
            empty($order['delivery']) ||
            empty($order['delivery']['contact']) ||
            empty($order['delivery']['address1']) ||
            empty($order['delivery']['town']) ||
            empty($order['delivery']['country_id']));
    }

    private function validateOrderSKUs($SKUs)
    {
        if (empty($SKUs))
            return false;
        foreach ($SKUs as $SKU_id => $SKU)
            if (empty($SKU['quantity']))
                return false;
        return true;
    }

    public function getOrder($item_code)
    {
        return $this->getConnector()->getOrder($item_code);
    }

    public function createOrder($order, $SKUs)
    {
        if (!$this->validateOrder($order) || !$this->validateOrderSKUs($SKUs))
            throw new VirtualException('Missing/Incorrect Order Details (Create Date, Id, Contact Name, Address1, Town, Country) or SKUs are not found');
        $data = [
            'orderDate' => date(DATE_ATOM, $order['create_date']),
            'orderNumber' => $order['id'],
            'PONumber' => $order['ref2'],
            'shipName' => $order['delivery']['contact'],
            'shipCompany' => $order['delivery']['name'],
            'shipAddress1' => $order['delivery']['address1'],
            'shipAddress2' => $order['delivery']['address2'],
            'shipAddress3' => $order['delivery']['address3'],
            'shipCity' => $order['delivery']['town'],
            'shipState' => $order['delivery']['county'],
            'shipPostalCode' => $order['delivery']['postcode'],
            'shipCountry' => $order['delivery']['country_id'],
            'shipEmail' => trim($order['delivery']['email']),
            'shipPhone' => $order['delivery']['phone'],
            'currency' => $order['currency'],
            'shipCarrier' => $order['carrier'],
            'shipServiceType' => $order['shipping_service'],
            'orderItems' => [],
        ];
        // https://trello.com/c/mPLefvyY/552-standard-overnight-service-api-field-mapping-vscm
        if (strtolower($order['carrier']) === 'fedex' && strtolower($order['shipping_service']) === 'standardovernight')
            $data['DoNotOptimizeShipMethod'] = true;
        // ----------------------------------
        foreach ($SKUs as $SKU_id => $SKU) {
            $exist = $this->getSKU($SKU_id);
            if (!$exist) {
                $product = SKUs::getById($SKU_id)->toArray();
                $this->setValidate($product);
                $virtual_product = $this->toVirtualSKU($product);
                if (empty($virtual_product))
                    throw new VirtualException("SKU #$SKU_id is not correct");
                $this->getConnector()->addSKU($virtual_product);
            }
            if (Orders::isShopify($order['type_create']) && empty($SKU['price']))
                $SKU['price'] = MIN_PRICE_VISIBLE;
            $datum = [
                'itemCode' => $SKU_id,
                'quantity' => $SKU['quantity'],
                'salesPrice' => round($SKU['price'], 2),
            ];
            $data['orderItems'][] = $datum;
        }
        $exist = $this->getOrder($order['id']);
        if (!$exist || $exist['Status'] === 'Cancelled')
            $this->getConnector()->createOrder($data);
        return true;
    }

    /** @throws VirtualException */
    private function canCancelOrder($item_code)
    {
        $order = $this->getConnector()->getOrder($item_code);
        if (!$order)
            return false;
        if (empty(self::$status_order_mapping[$order['Status']])) {
            $msg = "Status '{$order['Status']}' is not mapped (order #$item_code)";
            VirtualNotification::sendErrorToTelegram('canCancelOrder', $msg, Notifier::GROUP_VIRTUAL);
            VirtualNotification::sendNotificationsToEmails('canCancelOrder', $msg);
            return false;
        }
        $order_obj = Orders::getById($item_code);
        $status = self::$status_order_mapping[$order['Status']];
        if (!empty($order_obj) && $order_obj->getStatusName() != $status)
            Orders::changeStatus($order_obj->getId(), $status, -1);
        return in_array($order['Status'], self::$status_order_can_cancelled);
    }

    public function cancelOrder($item_code)
    {
        if (!$this->canCancelOrder($item_code))
            return false;
        $this->getConnector()->cancelOrder($item_code);
        return true;
    }
}