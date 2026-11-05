<?php

namespace app\core\services;

use app\backend\models\Deliveries;
use app\backend\models\DeliveryHistories;
use app\backend\models\OrderHistories;
use app\backend\models\Orders;
use app\backend\models\Shippings as ShippingsBackend;
use app\core\dbal\SafeMysql;
use app\core\entities\Delivery;
use app\core\entities\Order;
use app\core\entities\Shipping;
use app\core\helpers\Debug;
use app\core\models\OrdersOriginalRequest;
use app\core\Queue;
use app\core\Storage;
use Exception;

class HashingOrderService
{

    /**
     * @var Order $order
     */
    private $order;

    public function __construct($id)
    {
        $order = Orders::getById((int)$id, false);
        if (empty($order))
            throw new Exception('Order #' . $id . ' not found');
        $this->order = $order;
        if ($order->isHashed())
            throw new Exception('Order #' . $id . ' already hashed');
    }

    public function hashAnonymize(): array
    {
        if ($this->order->isHashed())
            return [false, "Order #" . $this->order->getId() . " already hashed"];
        if (DeliveryHistories::isHash($this->order->getDeliveryId()))
            return [true, "Delivery was hashed early"];
        $delivery = Deliveries::getById($this->order->getDeliveryId());
        if (empty($delivery))
            $delivery = new Delivery();
        $this->hashingDelivery($delivery);
        $delivery->setId(0);
        $fields_to_save = array_keys($delivery->toArray());
        array_shift($fields_to_save);
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $delivery->save($fields_to_save);
        DeliveryHistories::hashDelivery($delivery->getId(), [
            'contact' => $delivery->getContact(),
            'name' => $delivery->getName(),
            'address1' => $delivery->getAddress1(),
            'address2' => $delivery->getAddress2(),
            'address3' => $delivery->getAddress3(),
            'phone' => $delivery->getPhone(),
            'email' => $delivery->getEmail(),
        ]);
        OrderHistories::hashOrder($this->order->getId());
        OrdersOriginalRequest::hashRequest($this->order->getId());
        if ($this->order->despatched())
            Orders::hashAnonymizeDespatched($this->order->getId(), $delivery);
        $this->hashShipping();
        $this->setHashToOrderHistory();
        $this->order->setDeliveryId($delivery->getId());
        $this->order->setIsHash(true);
        $this->order->save(['id', 'delivery_id', 'is_hash']);
        Queue::addJobToUpdateOrderSearch(['order_id' => $this->order->getId()]);
        $mysql->commit();
        return [true];
    }

    private function hashShipping()
    {
        $shippings = ShippingsBackend::getDataForHashing($this->order->getId());
        if (!empty($shippings))
            foreach ($shippings as $shipping) {
                switch ($shipping['status']) {
                    case Shipping::STATUS_SUCCESS:
                        $file_name = Storage::getFile(SHIPPING_LABELS_STORAGE, $shipping['tracking_number'] . '.zpl');
                        if (!empty($file_name))
                            unlink($file_name);
                        $shipping_obj = new Shipping();
                        $shipping_obj->setId($shipping['id']);
                        $shipping_obj->setUrl('');
                        $shipping_obj->save(['id', 'url']);
                        break;
                    case Shipping::STATUS_ERROR:
                        $shipping['tracking_number'] = explode('.', $shipping['tracking_number']);
                        array_pop($shipping['tracking_number']);
                        $shipping['tracking_number'] = implode('.', $shipping['tracking_number']);
                        break;
                    default:
                        Debug::sendDebugInfo('Shipping', "Unknown status for shipping (order #" . $this->order->getId() . ")", Debug::TEST);
                        continue 2;
                }
                $file_name_txt = Storage::getFile(SHIPPING_LABELS_STORAGE,  'logs' . DIRECTORY_SEPARATOR . $shipping['tracking_number'] . '.txt');
                if (!empty($file_name_txt))
                    unlink($file_name_txt);
                $file_name_xml = Storage::getFile(SHIPPING_LABELS_STORAGE,  'logs' . DIRECTORY_SEPARATOR . $shipping['tracking_number'] . '.xml');
                if (!empty($file_name_xml))
                    unlink($file_name_xml);
            }
    }

    private function setHashToOrderHistory()
    {
        (new SafeMysql())->execute("
            UPDATE order_history
            SET `comment` = 'Delivery address changed from: <span class=\"badge badge-danger\">hashed</span>'
            WHERE `comment` LIKE 'Delivery address changed from:%' AND order_id = :order_id", [
                'order_id' => $this->order->getId()
        ]);
    }

    public function hashingDelivery(Delivery $delivery)
    {
        $delivery->setContact(md5($delivery->getContact()));
        $delivery->setName(md5($delivery->getName()));
        $delivery->setAddress1(md5($delivery->getAddress1()));
        $delivery->setAddress2(md5($delivery->getAddress2()));
        $delivery->setAddress3(md5($delivery->getAddress3()));
        $delivery->setPhone(md5($delivery->getPhone()));
        $delivery->setEmail(md5($delivery->getEmail()));
    }

}