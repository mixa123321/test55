<?php

namespace app\core\services\shipping;

use app\core\entities\Shipping;
use app\core\models\ShippingServices;
use app\core\statical\Countries;
use app\core\helpers\File;
use app\core\helpers\FileWrapper;

class ShippingInternal extends \app\core\services\shipping\Shipping
{
    const MAX_WEIGHT            = 0;
    const MIN_WEIGHT            = 0.1;

    const LABEL_WIDTH_IMAGE     = 712;
    const PERCENTAGE_MONOCHROME = 0.7;

    public static function createShipment($order_info, $shipper, $consignee, $package, $user_id): Shipping
    {
        list($label, $reference) = self::createLabel($order_info, $shipper, $consignee, $package);
        $shipment = new Shipping();
        $shipment->setStatus(Shipping::STATUS_ACCEPT);
        $shipment->setReference($reference);
        $shipment->setCreateDate(time()) ;
        $shipment->setUserId($user_id);
        $shipment->setCarrierId($order_info['carrier_id']);
        $shipment->save(['id', 'status', 'reference', 'create_date', 'user_id', 'carrier_id']);
        (new FileWrapper(FileWrapper::getContainerByPath(SHIPPING_LABELS_STORAGE)))->writeFileString(SHIPPING_LABELS_STORAGE . $reference . '.zpl', $label);
        $shipment->setTrackingNumber($reference);
        $shipment->setStatus(Shipping::STATUS_SUCCESS);
        $shipment->save(['id', 'tracking_number', 'status']);
        return $shipment;
    }

    public static function cancelShipment($shipping_api_key, $tracking_number, $reference): ?string
    {
        return null;
    }

    public static function trackShipment($shipping_api_key, $tracking_number, $reference): array
    {
        return [];
    }

    public static function getMaxWeight(): ?float
    {
        return self::MAX_WEIGHT;
    }

    public static function getMinWeight()
    {
        return self::MIN_WEIGHT;
    }

    private static function getAddress(array $address, int $x, int $y, int $step = 20)
    {
        $line  = "^FO$x,$y^FD{$address['contact']}^FS" . PHP_EOL;
        $y += $step;
        $line .= "^FO$x,$y^FD{$address['name']}^FS" . PHP_EOL;
        $y += $step;
        $line .= "^FO$x,$y^FD{$address['address1']}^FS" . PHP_EOL;
        $y += $step;
        if (!empty($address['address2'])) {
            $line .= "^FO$x,$y^FD{$address['address2']}^FS" . PHP_EOL;
            $y += $step;
        }
        if (!empty($address['address3'])) {
            $line .= "^FO$x,$y^FD{$address['address3']}^FS" . PHP_EOL;
            $y += $step;
        }
        $line .= "^FO$x,$y^FD{$address['town']}^FS" . PHP_EOL;
        $y += $step;
        $line .= "^FO$x,$y^FD{$address['county']} {$address['postcode']}^FS" . PHP_EOL;
        $y += $step;
        $line .= "^FO$x,$y^FD" . Countries::getName($address['country_id']) . '^FS' . PHP_EOL;
        return $line;
    }

    private static function getLabel(?string $image, array $shipper, array $consignee, array $order, string $service)
    {
        $label = '^XA' . PHP_EOL . PHP_EOL . '^CI28' . PHP_EOL . '^LL1218' . PHP_EOL . PHP_EOL;
        if ($image)
            $label .= '^FX Indicia image' . PHP_EOL .
                '^FO50,50^GFA,' . base64_decode($image) . '^FS' . PHP_EOL . PHP_EOL;
        $label .= '^FX Sender address' . PHP_EOL .
            '^CFA,20' . PHP_EOL .
            '^FO100,240^FDReturn address^FS' . PHP_EOL .
            self::getAddress($shipper, 100, 260) . PHP_EOL . PHP_EOL;
        $label .= '^FX Recipient address' . PHP_EOL .
            '^CFA,30' . PHP_EOL .
            self::getAddress($consignee, 100, 460, 37) . PHP_EOL . PHP_EOL;
        $label .= '^FX Barcode for Parcel ID' . PHP_EOL .
            '^BY2,3,60' . PHP_EOL .
            "^FO100,780^BC^FD{$order['id']}^FS" . PHP_EOL . PHP_EOL;
        $label .= '^FX Shipper name' . PHP_EOL .
            '^CF0,30' . PHP_EOL .
            "^FO100,880^FD{$shipper['name']}^FS" . PHP_EOL .
            "^FO100,920^FD{$order['ref']}^FS" . PHP_EOL .
            "^FO100,960^FD$service^FS" . PHP_EOL .
            '^BY2,3,120' . PHP_EOL .
            (!empty($order['ref2']) ? ("^FO100,1000^BC^FD{$order['ref2']}^FS" . PHP_EOL . PHP_EOL) : '') .
            '^XZ' . PHP_EOL;
        return $label;
    }

    private static function createLabel($order_info, $shipper, $consignee, $package)
    {
        $shipper = self::prepare($shipper);
        $consignee = self::prepare($consignee);
        if (!empty($package['order_reference']))
            $order_info['ref'] = htmlspecialchars($package['order_reference']);
        $service = ShippingServices::getById((int)$order_info['shipping_services_id']);
        $label = self::getLabel($service->getIndiciaImage(), $shipper, $consignee, $order_info, "{$order_info['cname']} - {$service->getName()}");
        return [$label, File::prepareFileName($order_info['ref'])];
    }
}
