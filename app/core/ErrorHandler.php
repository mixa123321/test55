<?php

namespace app\core;

use app\backend\models\ProtectionHistories;
use app\core\helpers\Debug;
use app\core\helpers\HTTP;
use app\core\models\Channels;

class ErrorHandler
{
    const FLAT_INTEGRATION_ERROR        = -1000;
    const VIRTUAL_INTEGRATION_ERROR     = -2000;
    // CHANNEL_INTEGRATION => CI
    const CI_ERROR                      = -3000;
    const CI_SKIP_ERROR                 = -3001;
    const CI_SHOP_INVENTORY_ERROR       = -3002;

    private static $available_hosts = [
        BASE_DOMAIN,
        'admin.' . BASE_DOMAIN,
        'api.' . BASE_DOMAIN,
    ];

    public static function handleErrors($e)
    {
        if ($e->getCode() == self::FLAT_INTEGRATION_ERROR) {
            Channels::sendMessageToTelegram('Flat Integration', $e->getMessage());
            return;
        }
        if (Environment::isDev()) {
            echo "<pre>";
            echo $e->getMessage(), " in file ", $e->getFile(), " at line ", $e->getLine();
            echo "<br/><br/>";
            echo $e->getTraceAsString();
            echo "</pre>";
        } else {
            if (!in_array(HTTP::getHost(), self::$available_hosts))
                ProtectionHistories::addEvent();
            $place = " in file ". $e->getFile(). " at line ". $e->getLine();
            $msg = "Code {$e->getCode()}\n{$e->getMessage()}\n\n{$place}\n\n{$e->getTraceAsString()}";
            if ($e->getCode() != 302) {
                Debug::sendDebugInfo("Received exception", $msg);
                Debug::sendDebugInfo("Received exception", "Code {$e->getCode()}\n{$e->getMessage()}");
            }
            Router::internalError();
        }
    }

    private static $phpFileUploadErrors = [
        0 => 'There is no error, the file uploaded with success',
        1 => 'The uploaded file exceeds the upload max filesize on server',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension stopped the file upload.',
    ];

    public static function errorFileUpload($num)
    {
        return !empty(self::$phpFileUploadErrors[$num]) ? self::$phpFileUploadErrors[$num] : '';
    }
}
