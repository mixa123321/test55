<?php

namespace app\core;

use app\core\helpers\File;
use app\core\helpers\FileWrapper;

class Storage
{
    const STORAGES = [
        EXTERNAL_STORAGE,
        ARCHIVE_STORAGE,
        ORDERS_STORAGE,
        ORDERS_DOC_STORAGE,
        ORDERS_ARCHIVE_STORAGE,
        SKUS_STORAGE,
        CSV_STORAGE,
        REPORT_STORAGE,
        SUPPLIERS_STORAGE,
        CUSTOM_RETURN_DOC_STORAGE,
        SHIPPING_LABELS_STORAGE,
        SHIPPING_LABELS_LOG_STORAGE,
        RETURNS_LABELS_STORAGE,
    ];

    public static function init()
    {
        static $init = false;
        if (!$init) {
            if (!defined('EXTERNAL_STORAGE'))
                define('EXTERNAL_STORAGE', ROOT_STORAGE);

            //root
            define('ORDERS_STORAGE', ROOT_STORAGE . 'orders'. DIRECTORY_SEPARATOR);
            define('SKUS_STORAGE', ROOT_STORAGE . 'SKUs' . DIRECTORY_SEPARATOR);
            define('SUPPLIERS_STORAGE', ROOT_STORAGE . 'suppliers' . DIRECTORY_SEPARATOR);

            //external
            define('ARCHIVE_STORAGE', EXTERNAL_STORAGE . 'archive'. DIRECTORY_SEPARATOR);
            define('CSV_STORAGE', EXTERNAL_STORAGE . 'CSVs' . DIRECTORY_SEPARATOR);
            define('REPORT_STORAGE', EXTERNAL_STORAGE . 'report' . DIRECTORY_SEPARATOR);

            //addition
            define('ORDERS_DOC_STORAGE', ORDERS_STORAGE . 'docs' . DIRECTORY_SEPARATOR);
            define('ORDERS_ARCHIVE_STORAGE', ORDERS_STORAGE . 'archives' . DIRECTORY_SEPARATOR);
            define('CUSTOM_RETURN_DOC_STORAGE', SUPPLIERS_STORAGE . 'custom_returns' . DIRECTORY_SEPARATOR);
            define('SHIPPING_LABELS_STORAGE', ORDERS_STORAGE . 'labels' . DIRECTORY_SEPARATOR);
            define('SHIPPING_LABELS_LOG_STORAGE', SHIPPING_LABELS_STORAGE . 'logs' . DIRECTORY_SEPARATOR);
            define('RETURNS_LABELS_STORAGE', ORDERS_STORAGE . 'returns' . DIRECTORY_SEPARATOR);

            foreach (self::STORAGES as $STORAGE)
                File::handleDir($STORAGE);
        }
        $init = true;
    }

    public static function toRoot(string $storage): string
    {
        if (!in_array($storage, self::STORAGES))
            return $storage;
        return str_replace(EXTERNAL_STORAGE,ROOT_STORAGE, $storage);
    }

    public static function toExternal(string $storage): string
    {
        if (!in_array($storage, self::STORAGES))
            return $storage;
        return str_replace(ROOT_STORAGE, EXTERNAL_STORAGE, $storage);
    }

    public static function getFile($storage, $path): ?string
    {
        $alt_storage = null;
        if (in_array($storage, self::STORAGES)) {
            $alt_storage = preg_replace('#^' . preg_quote(EXTERNAL_STORAGE) . '#', ROOT_STORAGE, $storage);
            if ($storage == $alt_storage)
                $alt_storage = preg_replace('#^' . preg_quote(ROOT_STORAGE) . '#', EXTERNAL_STORAGE, $storage);
        }
        $alt_path = $alt_storage . DIRECTORY_SEPARATOR . $path;
        if (File::isPathWithin($alt_path, $alt_storage)
            && (new FileWrapper(FileWrapper::getContainerByPath($alt_path)))->isFile($alt_path))
            return $alt_path;
        $full_path = $storage . $path;
        if (File::isPathWithin($full_path, $storage)
            && (new FileWrapper(FileWrapper::getContainerByPath($full_path)))->isFile($full_path))
            return $full_path;
        return null;
    }
}