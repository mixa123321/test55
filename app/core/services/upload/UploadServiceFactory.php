<?php

namespace app\core\services\upload;

use app\core\interfaces\SFTPUpload;

class UploadServiceFactory
{
    protected static $uploadServices = [
        'ASNs' => ASNsUploadService::class,
        'SKUs' => SKUsUploadService::class,
        'Orders' => OrdersUploadService::class,
    ];

    public static function create(string $type): SFTPUpload
    {
        if (!array_key_exists($type, self::$uploadServices)) {
            throw new \InvalidArgumentException("Unknown upload type: {$type}");
        }

        $serviceClass = self::$uploadServices[$type];

        return new $serviceClass();
    }
}