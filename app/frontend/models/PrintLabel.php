<?php

namespace app\frontend\models;

use app\core\helpers\File;
use app\backend\models\Shippings;
use app\backend\models\Returns;
use app\core\models\Queues as QueuesModal;
use app\core\models\BulkLogic;
use app\core\entities\Queue;
use app\core\helpers\FileWrapper;

class PrintLabel
{
    const SHIPMENT_ID = 'shipment_id';
    const ERROR_ORDER_ID = 'error_order_id';
    const TYPE_PDF = 'pdf';
    const TYPE_ZPL = 'zpl';
    const ALL_TYPES = [
//        self::TYPE_PDF => self::TYPE_PDF,
        self::TYPE_ZPL => self::TYPE_ZPL,
    ];

    public static function printShippingLabel($shipment_id, $hash, $viewMode)
    {
        $label_full_path = self::getFileShippingLabel($shipment_id, $hash);
        if (!$label_full_path)
            return false;
        if (!empty($viewMode)) {
            if ($viewMode === self::TYPE_PDF)
                self::viewLabel($label_full_path);
            elseif ($viewMode === self::TYPE_ZPL)
                File::outputFile($label_full_path, OUTPUT_ATTACHMENT, 'application/zpl');
            else
                self::viewLabel($label_full_path);
        }
        File::outputFile($label_full_path, OUTPUT_ATTACHMENT, 'application/zpl');
    }

    public static function getFileShippingLabel($shipment_id, $hash)
    {
        if (!$shipment_id)
            return false;
        $shipping_obj = Shippings::getById($shipment_id);
        if (!$shipping_obj)
            return false;
        if(empty($hash) || md5($shipping_obj->getTrackingNumber()) !== $hash)
            return false;
        $label_full_path = Shippings::getShippingLabelFullPath($shipping_obj->getTrackingNumber() . '.zpl');
        if (!(new FileWrapper(FileWrapper::getContainerByPath($label_full_path)))->isFile($label_full_path))
            return false;
        return $label_full_path;
    }

    public static function printReturnLabel($return_id, $hash, $viewMode)
    {
        $label_full_path = self::getFileReturnLabel($return_id, $hash);
        if (!(new FileWrapper(FileWrapper::getContainerByPath($label_full_path)))->isFile($label_full_path))
            return false;
        if (!empty($viewMode)) {
            if ($viewMode === self::TYPE_PDF)
                self::viewLabel($label_full_path);
            elseif ($viewMode === self::TYPE_ZPL)
                File::outputFile($label_full_path, OUTPUT_ATTACHMENT, 'application/zpl');
            else
                self::viewLabel($label_full_path);
        }
        File::outputFile($label_full_path, OUTPUT_ATTACHMENT, 'application/zpl');
    }

    public static function getFileReturnLabel($return_id, $hash)
    {
        if (!$return_id)
            return false;
        $returns_obj = Returns::getById($return_id);
        if (!$returns_obj)
            return false;
        if(empty($hash) || md5($returns_obj->getTrackingNumber()) !== $hash)
            return false;
        return Returns::getReturnLabelFullPath($returns_obj->getReturnCarrierId(), $returns_obj->getTrackingNumber() . '.zpl');
    }

    public static function printBulk(int $queue_id, string $hash, string $viewMode): bool
    {
        $queues = QueuesModal::formatBulkQueueData(QueuesModal::getByIdsType([$queue_id], Queue::TYPE_BULK_PRINT));
        if (empty($queues[$queue_id]))
            return false;
        $queue = $queues[$queue_id];
        if (BulkLogic::getHash($queue['warehouse_id'] ?? '', $queue['user_id'] ?? '') !== $hash)
            return false;
        $file = '';
        if ($viewMode === self::TYPE_ZPL)
            $file = BulkLogic::getZPLPath($queue_id);
        elseif ($viewMode === PrintLabel::TYPE_PDF)
            $file = BulkLogic::getPdfPath($queue_id);
        if (!$file)
            return false;
        return self::showPdfZplFile($file, $viewMode);
    }

    private static function showPdfZplFile(string $file, string $viewMode): bool {
        if ($viewMode === self::TYPE_PDF) {
            self::showPdfHeader(basename($file));
            echo stream_get_contents((new FileWrapper(FileWrapper::getContainerByPath($file)))->getStream($file));
            return true;
        } elseif ($viewMode === self::TYPE_ZPL) {
            // labels embed recipient data; without an explicit type the response defaulted to text/html
            header('Content-Type: text/plain; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            echo stream_get_contents((new FileWrapper(FileWrapper::getContainerByPath($file)))->getStream($file));
            return true;
        }
        return false;
    }

    private static function showPdfHeader(string $filename)
    {
        header("Content-Type: application/pdf");
        header("Content-Disposition: inline; filename='$filename'");
    }

    private static function viewLabel($label_path)
    {
        $basename = str_replace("zpl", "pdf", basename($label_path));
        $converted_pdf = File::getZplAsPdfNew($label_path);
        header("Content-Type: application/pdf");
        header("Content-Disposition: inline; filename=\"{$basename}\"");
        echo $converted_pdf;
        exit;
    }
}
