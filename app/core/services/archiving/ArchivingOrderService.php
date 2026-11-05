<?php

namespace app\core\services\archiving;

use app\backend\models\Bundles;
use app\backend\models\Orders;
use app\backend\models\OrderHistories;
use app\backend\models\OrderSKUs;
use app\backend\models\Shippings;
use app\core\entities\Entity;
use app\core\entities\Order;
use app\core\helpers\Debug;
use app\core\helpers\File;
use app\core\helpers\formatters\FormatterZip;
use app\core\models\OrderPackageReturns;
use app\core\models\OrdersOriginalDespatched;
use app\core\models\OrdersOriginalRequest;
use app\core\models\OrdersSubstitutions;
use app\core\models\Packages;
use app\core\models\SKULocations;
use app\core\services\ViewOrderDataService;
use app\core\Storage;
use Exception;
use app\core\helpers\FileWrapper;

class ArchivingOrderService
{

    const STORAGE = ORDERS_ARCHIVE_STORAGE;
    const EXTRACT_STORAGE = ORDERS_STORAGE . 'extract';

    /** @var Order|null */
    private $order;

    /** @var FormatterZip */
    private $zip;

    const FORMAT = 'json';

    const VIEW_FILE = 'view_data';
    const BUNDLES_FILE = 'bundles';
    const ORIGINAL_DESPATCHED_FILE = 'orders_original_despatched';
    const SUBSTITUTIONS_FILE = 'orders_substitutions';
    const PACKAGES_FILE = 'packages';
    const SKUS_FILE = 'SKUs';


    /**
     * @throws Exception
     */
    public function __construct($id)
    {
        $order = Orders::getById((int)$id);
        if (empty($order))
            throw new Exception('Order #' . $id . ' not found');
        $this->order = $order;
        if (self::orderIsArchived($order->getId()))
            throw new Exception('Order #' . $id . ' already archived');
        $this->handleSKULocations();
        $zip = new FormatterZip(self::getZipPath($this->order->getId(), true));
        $error = $zip->getError();
        if (!empty($error))
            throw new Exception('Could not open zip for order #' . $id);
        $this->zip = $zip;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function closeZip()
    {
        unset($this->zip);
    }

    public function getExistPath(): ?string
    {
        return $this->zip->getPath();
    }

    public static function orderIsArchived($order_id): bool
    {
        $path = self::getZipPath($order_id);
        if (!$path)
            return false;
        return (new FileWrapper(FileWrapper::getContainerByPath($path)))->isFile($path);
    }

    private function handleSKULocations()
    {
        if (SKULocations::hasOrderSKUsINLocations($this->order->getId()))
            Debug::sendDebugInfo('ArchivingOrder', 'Order #' . $this->order->getId() . ' has items in locations', Debug::TEST);
    }

    public function markArchived()
    {
        OrderHistories::archivedOrder($this->order->getId());
    }

    private function handleJsonToZip($name, $content)
    {
        if (empty($content))
            return;
        array_walk_recursive($content, function(&$item) {
           if ($item instanceof Entity)
               $item = $item->toArray();
        });
        $this->zip->addFromString($name . '.' . self::FORMAT, json_encode($content));
    }

    /**
     * @throws Exception
     */
    public function handleView(): ArchivingOrderService
    {
        $view = new ViewOrderDataService($this->order);
        $this->handleJsonToZip(self::VIEW_FILE, $view->getMainData());
        return $this;
    }

    public function handleBundles(): ArchivingOrderService
    {
        if (!Bundles::hasOrderBundles($this->order->getId()))
            return $this;
        $this->handleJsonToZip(self::BUNDLES_FILE, Bundles::getOrderBundleSKUs($this->order->getId()));
        return $this;
    }

    public function handleOriginalDespatched(): ArchivingOrderService
    {
        $this->handleJsonToZip(self::ORIGINAL_DESPATCHED_FILE, OrdersOriginalDespatched::getByOrderId($this->order->getId()));
        return $this;
    }

    public function handleOriginalRequest(): ArchivingOrderService
    {
        $this->handleJsonToZip('orders_original_request', OrdersOriginalRequest::getOriginalRequest($this->order->getId()));
        return $this;
    }

    public function handlePackages(): ArchivingOrderService
    {
        $packages = Orders::getPackages($this->order->getId());
        if (empty($packages))
            return $this;
        $package_consumables = Packages::getOrderPackageConsumables($this->order->getId());
        $returns = OrderPackageReturns::getByOrderId($this->order->getId());
        $shipping = Shippings::getByOrderId($this->order->getId());

        $this->handleJsonToZip(self::PACKAGES_FILE, [
            'packages' => $packages,
            'package_consumables' => $package_consumables,
            'returns' => $returns,
            'shipping' => $shipping,
            'SKU_info' => $this->handleSKUInfo(array_keys($packages)),
            'tracking_numbers' => $this->handleTrackingNumbers()
        ]);
        return $this;
    }

    private function handleSKUInfo($orders_packages_ids): array
    {
        return OrderSKUs::getSKUInfoByOrdersPackages($orders_packages_ids);
    }

    private function handleTrackingNumbers(): array
    {
        $res = Shippings::getTrackingNumbersByOrders([$this->order->getId()]);
        if (empty($res[0]) && empty($res[1]))
            return [[], []];

        return [
            $res[0][$this->order->getId()] ?? [],
            $res[1][$this->order->getId()] ?? [],
        ];
    }

    public function handleSubstitutions(): ArchivingOrderService
    {
        $this->handleJsonToZip(self::SUBSTITUTIONS_FILE, OrdersSubstitutions::getOrderSubstitutionsSKUs($this->order->getId()));
        return $this;
    }

    public function handleSKUs(): ArchivingOrderService
    {
        $data = OrderSKUs::getSKUInfoByOrders([$this->order->getId()]);
        if (empty($data))
            return $this;
        $this->handleJsonToZip(self::SKUS_FILE, $data[$this->order->getId()]);
        return $this;
    }

    public static function getExtractPath(): string
    {
        return File::handleDir(self::EXTRACT_STORAGE);
    }

    /**
     * @throws Exception
     */
    private static function getDataFromArchive($order_id, $file)
    {
        $zip_path = self::getZipPath($order_id);
        if (!(new FileWrapper(FileWrapper::getContainerByPath($zip_path)))->isFile($zip_path)) {
            Debug::sendDebugInfo('Archive', 'Order #' . $order_id . ' ' . $file . ' zip not found');
            throw new Exception('Could not open archive');
        }
        $path = self::getExtractPath();
        try {
            $file_string = stream_get_contents((new FileWrapper(FileWrapper::getContainerByPath($zip_path)))->getStream($zip_path));
            $temp = tmpfile();
            fwrite($temp, $file_string);
            fseek($temp, 0);
            $tmp_file = stream_get_meta_data($temp)['uri'];
            FormatterZip::extract($tmp_file, $path, $file);
        } catch (Exception $exception) {
            Debug::sendDebugInfo('Archive', 'Order #' . $order_id . ', file: ' . $file . ' - ' . $exception->getMessage());
            return [];
        }
        $view_file = $path . DIRECTORY_SEPARATOR . $file;
        if (!is_file($view_file)) {
            Debug::sendDebugInfo('Archive', 'Order #' . $order_id . ' ' . $file . ' file not found');
            throw new Exception('Could not open archive');
        }
        $contents = file_get_contents($view_file);
        if (empty($contents) || empty($data = json_decode($contents, TRUE))) {
            Debug::sendDebugInfo('Archive', 'Order #' . $order_id . ' ' . $file . ' archive not exist content');
            throw new Exception('Could not open archive');
        }
        unlink($view_file);
        return $data;
    }

    /**
     * @throws Exception
     */
    public static function getViewData($order_id)
    {
        return self::getDataFromArchive($order_id, self::getViewDataFileName());
    }

    /**
     * @throws Exception
     */
    public static function getSKUs($order_id)
    {
        return self::getDataFromArchive($order_id, self::getSKUsFileName());
    }

    /**
     * @throws Exception
     */
    public static function getBundleSKUs($order_id)
    {
        return self::getDataFromArchive($order_id, self::getBundlesFileName());
    }

    /**
     * @throws Exception
     */
    public static function getOriginalDespatched($order_id)
    {
        return self::getDataFromArchive($order_id, self::getOriginalDespatchedFileName());
    }

    /**
     * @throws Exception
     */
    public static function getSubstitutionsSKUs($order_id)
    {
        return self::getDataFromArchive($order_id, self::getSubstitutionsFileName());
    }

    /**
     * @throws Exception
     */
    public static function getPackages($order_id)
    {
        return self::getDataFromArchive($order_id, self::getPackagesFileName());
    }

    public static function getZipPath($order_id, $create = false): ?string
    {
        if ($create) {
            $path = Storage::toExternal(ORDERS_ARCHIVE_STORAGE) . (int)($order_id / 10000);
            File::handleDir($path);
            return $path . DIRECTORY_SEPARATOR . $order_id . '.zip';
        } else {
            return Storage::getFile(ORDERS_ARCHIVE_STORAGE, (int)($order_id / 10000) . DIRECTORY_SEPARATOR . $order_id . '.zip');
        }
    }

    public static function getViewDataFileName(): string
    {
        return self::VIEW_FILE . '.' . self::FORMAT;
    }

    public static function getBundlesFileName(): string
    {
        return self::BUNDLES_FILE . '.' . self::FORMAT;
    }

    public static function getOriginalDespatchedFileName(): string
    {
        return self::ORIGINAL_DESPATCHED_FILE . '.' . self::FORMAT;
    }

    public static function getSubstitutionsFileName(): string
    {
        return self::SUBSTITUTIONS_FILE . '.' . self::FORMAT;
    }

    public static function getPackagesFileName(): string
    {
        return self::PACKAGES_FILE . '.' . self::FORMAT;
    }

    public static function getSKUsFileName(): string
    {
        return self::SKUS_FILE . '.' . self::FORMAT;
    }

}