<?php

namespace app\core\models;

use app\frontend\models\PrintLabel;
use app\core\helpers\File;
use app\core\entities\Order;
use app\core\helpers\FileWrapper;

class BulkLogic
{
    const FILE_PREFIX = 'LABEL_';
    const PRINT_ALL = 'all';

    public static function addPrintOrders(array &$queues)
    {
        $user_ids = [];
        foreach ($queues as $id => $queue) {
            $print_orders = [self::PRINT_ALL => self::PRINT_ALL];
            foreach ($queue['json_data']['order_ids'] ?? [] as $order_id)
                $print_orders[$order_id] = $order_id;
            $queues[$id]['print_orders'] = $print_orders;
            $queues[$id]['print_parameters'] = [];
            foreach (self::getPrintTypes($print_orders, true) as $print_type => $order_ids)
                foreach ($order_ids as $order_id => $ids)
                    foreach ($ids as $print_id => $hash)
                        $queues[$id]['print_parameters'][$order_id] = ['print_type' => $print_type, 'print_id' => $print_id, 'hash' => $hash];
            $user_id = (int)$queue['user_id'];
            $user_ids[$user_id] = $user_id;
        }
        $users = Users::getByIds($user_ids);
        foreach ($queues as $id => $queue)
            $queues[$id]['user_login'] = $users[(int)$queue['user_id']];
    }

    public static function addHash(array &$queues)
    {
        foreach ($queues as $id => $queue)
            $queues[$id]['hash'] = self::getHash($queue['warehouse_id'], $queue['user_id']);
    }

    public static function getHash(int $warehouse_id, int $user_id)
    {
        return md5($warehouse_id . $user_id);
    }

    public static function getPrintTypes(array $order_ids, bool $skip_empty_shipping = false): array
    {
        if (!$order_ids)
            return [];
        $print_type = [];
        foreach (Orders::getByIds($order_ids) as $order_id => $order_obj) {
            /** @var Order $order_obj */
            if (!$order_obj->printingDocumentsIssue() && !$order_obj->despatched() && !$order_obj->printingDocuments())
                continue;
            if ($order_obj->printingDocumentsIssue()) {
                $print_type['error_order_id'][$order_id][$order_id] = '';
            } else {
                $packages = Orders::getPackages($order_id);
                foreach ($packages as $package_id => $package) {
                    $type = PrintLabel::SHIPMENT_ID;
                    $shipping = Shippings::getById($package['shipment_id']);
                    if (!$shipping && $skip_empty_shipping)
                        continue;
                    if (!$shipping)
                        throw new \Exception('Empty shipping. Order #' . $order_id);
                    $hash = md5($shipping->getTrackingNumber());
                    $id = (int)$package['shipment_id'];
                    $print_type[$type][$order_id][$id] = $hash;
                }
            }
        }
        return $print_type;
    }

    public static function getZpl(array $print_type): array
    {
        $zpl = [];
        foreach ($print_type as $type => $order_ids)
            foreach ($order_ids as $order_id => $ids)
                foreach ($ids as $id => $hash) {
                    if ($path = self::getOneZpl($type, $id, $hash))
                        $zpl[] = $path;
                }
        return $zpl;
    }

    private static function getOneZpl(string $type, int $id, string $hash): string
    {
        $file_path = null;
        if ($type === PrintLabel::SHIPMENT_ID)
            $file_path = PrintLabel::getFileShippingLabel($id, $hash);
        elseif ($type === PrintLabel::ERROR_ORDER_ID)
            return '';
        if (!$file_path)
            throw new \Exception('Wrong print type');
        return $file_path;
    }

    public static function saveZpl(int $queue_id, string $zpl): void
    {
        $bulk_file = self::getZPLPath($queue_id);
        (new FileWrapper(FileWrapper::getContainerByPath($bulk_file)))->writeFileString($bulk_file, $zpl);
    }

    public static function getPdfPath(int $queue_id): string
    {
        $dir = SHIPPING_BULK_V2_PDF_LABELS_STORAGE;
        (new FileWrapper(FileWrapper::getContainerByPath($dir)))->handleDir($dir);
        return $dir . self::FILE_PREFIX . $queue_id . '.pdf';
    }

    public static function getZPLPath(int $queue_id): string
    {
        $dir = SHIPPING_BULK_V2_LABELS_STORAGE;
        File::handleDir($dir);
        return $dir . self::FILE_PREFIX . $queue_id . '.zpl';
    }
}