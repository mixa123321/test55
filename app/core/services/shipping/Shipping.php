<?php

namespace app\core\services\shipping;

use app\core\entities\Carrier;
use app\core\entities\LogisticCompany;
use app\core\entities\Shipping as ShippingEntity;
use app\core\models\Carriers;

abstract class Shipping
{
    const CLASS_SHIPPING_AB = 'ShippingAB';

    /** @var ?Carrier $main_carrier */
    private static $main_carrier = null;

    abstract public static function createShipment($order_info, $shipper, $consignee, $package, $user_id): ShippingEntity;
    abstract public static function cancelShipment($shipping_api_key, $tracking_number, $reference): ?string;
    abstract public static function trackShipment($shipping_api_key, $tracking_number, $reference): array;
    abstract public static function getMaxWeight(): ?float;
    abstract public static function getMinWeight();

    /** @throws */
    public static function getMainCarrier() : ?Carrier
    {
        return self::$main_carrier;
    }

    private static function createMethods(string $class_name) : array
    {
        $shipping_class = "\\app\\core\\services\\shipping\\$class_name";
        return [
            'getMaxWeight' => "$shipping_class::getMaxWeight",
            'getMinWeight' => "$shipping_class::getMinWeight",
            'createShipment' => "$shipping_class::createShipment",
            'cancelShipment' => "$shipping_class::cancelShipment",
            'trackShipment' => "$shipping_class::trackShipment",
            'requiresParcelCategory' => "$shipping_class::requiresParcelCategory",
            'getParcelCategoryList' => "$shipping_class::getParcelCategoryList",
        ];
    }

    /** @throws */
    public static function getShipping(LogisticCompany $lc, int $carrier_id) : array
    {
        /** @var Carrier $carrier */
        $carrier = Carriers::getById($carrier_id);
        if (empty($carrier))
            return [];
        $shipping_class = $carrier->getShippingClass() ?: ($carrier->getIsInternal() ? 'ShippingInternal' : $lc->getShippingClass());
        return [self::createMethods($shipping_class), $carrier, $shipping_class];
    }

    public static function createShippingMethods(LogisticCompany $lc) : array
    {
        self::$main_carrier = Carriers::getById($lc->getCarrierId());
        return self::createMethods($lc->getShippingClass());
    }

    public static function requiresParcelCategory() : bool
    {
        return false;
    }

    public static function getParcelCategoryList() : array
    {
        return [];
    }

    protected static function prepare($data)
    {
        foreach ($data as $key => $val)
            $data[$key] = is_array($val) ? self::prepare($val) : htmlspecialchars($val);
        return $data;
    }
}