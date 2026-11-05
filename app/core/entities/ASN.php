<?php

namespace app\core\entities;

use app\backend\models\Suppliers;
use app\backend\models\Users;
use app\core\db\Mysql;
use app\core\statical\WithDate;
use app\core\statical\Services;

class ASN extends Entity
{
    const CONTAINER_20FT = '20ft_container';
    const CONTAINER_40FT = '40ft_container';
    const PALLET = 'pallet';
    const BOX = 'box';
    const MAILBAG = 'mailbag';
    const YOKE = 'yoke';
    const CAGE = 'cage';

    const CANCELLED         = 0;
    const NEW               = 1;
    const ARRIVED           = 2;
    const PUTAWAY           = 3;
    const COMPLETED         = 4;
    const NO_VERIFIED       = 5;
    const NON_COMPLIANT     = 6;
    const ACTION            = 7;
    const WAREHOUSE         = 8;

    const ARRIVAL_END_DAY = ' 23:59:59';

    private $id;
    private $supplier_id;
    private $unscheduled;
    private $arrival_date;
    private $user_id;
    private $status = self::NEW;
    private $arrived_date;
    private $ref;
    private $carrier;
    private $tracking_number;
    private $services;
    private $container;
    private $billing_description_pallet;
    private $billing_charge_pallet;
    private $billing_description_rework;
    private $billing_charge_rework;
    private $billing_charge_currency;
    private $completed_date;
    private $order_id;
    private $comment;
    private $warehouse_id;
    private $ship_to_location;
    private $actioned;

    public static $statusName = [
        self::CANCELLED => 'Cancelled',
        self::NEW => 'New',
        self::ARRIVED => 'Arrived',
        self::PUTAWAY => 'Putaway',
        self::COMPLETED => 'Completed',
        self::NO_VERIFIED => 'No verified',
        self::NON_COMPLIANT => 'Non-Compliant',
    ];

    public static $statusNameReturn = [
        self::CANCELLED => 'Cancelled',
        self::NEW => 'New',
        self::ACTION => 'Action Required',
        self::WAREHOUSE => 'Warehouse to Action',
        self::COMPLETED => 'Completed'
    ];

    const CONDITIONS = [
        'None',
        'As New',
        'Good - Rework',
        'Bad',
        'Unknown - See Comments'
    ];

    const REASONS = [
        'None',
        'Wrong Size',
        'Not as expected',
        'Wrong Item Sent',
        'Arrived too late',
        'See comments'
    ];

    const REFUNDS = [
        'None',
        'Refund',
        'Exchange',
        'See comments'
    ];

    const ACTIONS = [
        'None',
        'Return to stock',
        'Dispose',
        'Re-Ship (Manual Shipment)',
        'See comments'
    ];

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function setContainer($container)
    {
        $this->container = (string)$container;
    }

    public static function isCancelled($status)
    {
        return (int)$status === self::CANCELLED;
    }

    public function cancelled()
    {
        return self::isCancelled($this->getStatus());
    }

    public function new()
    {
        return $this->getStatus() === self::NEW;
    }

    public static function isArrived($status)
    {
        return (int)$status === self::ARRIVED;
    }

    public function arrived()
    {
        return self::isArrived($this->getStatus());
    }

    public static function isPutaway($status)
    {
        return (int)$status === self::PUTAWAY;
    }

    public function putaway()
    {
        return $this->getStatus() === self::PUTAWAY;
    }

    public static function isCompleted($status)
    {
        return (int)$status === self::COMPLETED;
    }

    public function completed()
    {
        return self::isCompleted($this->getStatus());
    }

    public function nonCompliant()
    {
        return $this->getStatus() === self::NON_COMPLIANT;
    }

    public static function isAction($status)
    {
        return (int)$status === self::ACTION;
    }

    public function action()
    {
        return self::isAction($this->getStatus());
    }

    public static function isWarehouse($status)
    {
        return (int)$status === self::WAREHOUSE;
    }

    public function warehouse()
    {
        return self::isWarehouse($this->getStatus());
    }

    public function getStatusName()
    {
        return self::getStateName($this->status);
    }

    public static function getStateName($status)
    {
        return self::$statusName[$status];
    }

    public static function getStateNameReturn($status)
    {
        return self::$statusNameReturn[$status];
    }

    public static function getStateNameAll(int $status): string
    {
        return (self::$statusName + self::$statusNameReturn)[$status];
    }

    public function getStatusNameAll(): string
    {
        return self::getStateNameAll($this->status);
    }

    public function setNextStatus()
    {
        switch ($this->getStatus()) {
            case self::NEW:
                return $this->setStatus(self::ACTION);
            case self::ACTION:
                return $this->setStatus(self::WAREHOUSE);
            case self::WAREHOUSE:
                return $this->setStatus(self::COMPLETED);
            default:
                return false;
        }
    }

    public static function getStateStyleReturn($status)
    {
        switch ($status) {
            case ASN::CANCELLED:
                return 'secondary';
            case ASN::COMPLETED:
                return 'success';
            case ASN::ACTION:
                return 'warning';
            default:
                return 'primary';
        }
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($supplier_id)
    {
        $this->supplier_id = (int)$supplier_id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = (int)$user_id;
    }

    public function getUnscheduled()
    {
        return $this->unscheduled;
    }

    public function setUnscheduled($unscheduled)
    {
        $this->unscheduled = (int)$unscheduled;
    }

    public function getArrivalDate()
    {
        return is_null($this->arrival_date) ? null : date('Y-m-d', $this->arrival_date);
    }

    public function getArrivalDateInt()
    {
        return $this->arrival_date;
    }

    public function setArrivalDate($arrival_date)
    {
        $this->arrival_date = WithDate::dateToInt($arrival_date);
    }

    public function getBillingDescriptionPallet()
    {
        return $this->billing_description_pallet;
    }

    public function setBillingDescriptionPallet($txt)
    {
        $this->billing_description_pallet = (string)$txt;
    }

    public function getBillingChargePallet()
    {
        return $this->billing_charge_pallet;
    }

    public function setBillingChargePallet($sum)
    {
        $this->billing_charge_pallet = (float)$sum;
    }

    public function getBillingDescriptionRework()
    {
        return $this->billing_description_rework;
    }

    public function setBillingDescriptionRework($txt)
    {
        $this->billing_description_rework = (string)$txt;
    }

    public function getBillingChargeRework()
    {
        return $this->billing_charge_rework;
    }

    public function setBillingChargeRework($sum)
    {
        $this->billing_charge_rework = (float)$sum;
    }

    public function getBillingChargeCurrency()
    {
        return $this->billing_charge_currency;
    }

    public function setBillingChargeCurrency($txt)
    {
        $this->billing_charge_currency = (string)$txt;
    }

    public function getCompletedDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->completed_date);
    }

    public function getCompletedDateInt()
    {
        return $this->completed_date;
    }

    public function setCompletedDate($completed_date)
    {
        $this->completed_date = WithDate::dateToInt($completed_date);
    }

    public function getOrderId()
    {
        return $this->order_id;
    }

    public function setOrderId($id)
    {
        $this->order_id = (int)$id;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function setComment($txt)
    {
        $this->comment = (string)$txt;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getShipToLocation()
    {
        return $this->ship_to_location;
    }

    public function setShipToLocation($location)
    {
        $this->ship_to_location = (string)$location;
    }

    public function getActioned()
    {
        return $this->actioned;
    }

    public function setActioned($actioned)
    {
        $this->actioned = (int)$actioned;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        if (!empty($data['services']) && count($data['services']))
            $data['services'] = json_encode($data['services']);
        $id = Mysql::saveRow('ASNs', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'supplier_id' => $this->getSupplierId(),
            'unscheduled' => $this->getUnscheduled(),
            'arrival_date' => $this->getArrivalDate(),
            'user_id' => $this->getUserId(),
            'ref' => $this->getRef(),
            'status' => $this->getStatus(),
            'carrier' => $this->getCarrier(),
            'arrived_date' => $this->getArrivedDate(),
            'tracking_number' => $this->getTrackingNumber(),
            'services' => $this->getServices(),
            'container' => $this->getContainer(),
            'billing_description_pallet' => $this->getBillingDescriptionPallet(),
            'billing_charge_pallet' => $this->getBillingChargePallet(),
            'billing_description_rework' => $this->getBillingDescriptionRework(),
            'billing_charge_rework' => $this->getBillingChargeRework(),
            'billing_charge_currency' => $this->getBillingChargeCurrency(),
            'completed_date' => $this->getCompletedDate(),
            'order_id' => $this->getOrderId(),
            'comment' => $this->getComment(),
            'warehouse_id' => $this->getWarehouseId(),
            'ship_to_location' => $this->getShipToLocation(),
            'actioned' => $this->getActioned(),
        ];
    }

    public function toArrayView()
    {
        $supplier = Suppliers::getById($this->getSupplierId())->toArray();
        $supplier['user'] = Users::getById($supplier['user_id'])->getLogin();
        unset($supplier['id'], $supplier['type_account'], $supplier['day30'], $supplier['del'], $supplier['user_id']);
        $res = [];
        foreach ($this->getServices() as $key => $service_id)
            $res[] = Services::getService($key);
        return [
            'id' => $this->getId(),
            'supplier' => $supplier,
            'unscheduled' => $this->getUnscheduled(),
            'arrival_date' => $this->getArrivalDate(),
            'user' => Users::getById($this->getUserId())->getLogin(),
            'ref' => $this->getRef(),
            'status' => $this->getStatusNameAll(),
            'carrier' => $this->getCarrier(),
            'arrived_date' => empty($this->getArrivedDateInt())  ? '' : $this->getArrivedDate(),
            'tracking_number' => $this->getTrackingNumber(),
            'services' => $res,
            'order' => $this->getOrderId(),
            'comment' => $this->getComment(),
        ];
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = (int)$status;
    }

    public function getArrivedDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->arrived_date);
    }

    public function getArrivedDateInt()
    {
        return $this->arrived_date;
    }

    public function setArrivedDate($arrived_date)
    {
        $this->arrived_date = WithDate::dateToInt($arrived_date);
    }

    public function getRef()
    {
        return $this->ref;
    }

    public function setRef($ref)
    {
        $this->ref = (string)$ref;
    }

    public function getCarrier()
    {
        return $this->carrier;
    }

    public function setCarrier($carrier)
    {
        $this->carrier = (string)$carrier;
    }

    public function getTrackingNumber()
    {
        return $this->tracking_number;
    }

    public function setTrackingNumber($number)
    {
        $this->tracking_number = (string)$number;
    }

    public function getServices()
    {
        return is_array($this->services) ? $this->services : [];
    }

    public function setServices($services)
    {
        if (is_string($services))
            $services = json_decode($services, true);
        if (!empty($services) && is_array($services)) {
            $service_list = Services::getAll();
            foreach ($services as $key => $item)
                if (!isset($service_list[$item]) && $key)
                    unset($services[$key]);
        }
        $this->services = (array)$services;
    }
}