<?php

namespace app\core;

use app\core\entities\Notifier;

class Environment
{
    private static $is_admin_panel = false;
    private static $is_api = false;
    private static $is_dev = false;
    private static $is_stage = false;
    private static $is_frontend = false;
    private static $isDocs = false;
    private static $is_production = false;
    private static $is_robot = false;

    private static $debug_mails;

    private static $local_name = null;
    private static $is_loggable = true;
    private static $db_console_reporting = false;
    private static $file_reporting = false;

    /** @var Notifier */
    private static $dev_telegram;

    public static function isAdminPanel()
    {
        return self::$is_admin_panel;
    }

    public static function isFrontend()
    {
        return self::$is_frontend;
    }

    public static function isDocs()
    {
        return self::$isDocs;
    }

    public static function isApi()
    {
        return self::$is_api;
    }

    public static function isDev()
    {
        return self::$is_dev;
    }

    public static function isStage()
    {
        return self::$is_stage;
    }

    public static function isProduction()
    {
        return self::$is_production;
    }

    public static function isRobot()
    {
        return self::$is_robot;
    }

    public static function isLoggable(): bool
    {
        return self::$is_loggable;
    }

    public static function isDbConsoleReporting(): bool
    {
        return self::$db_console_reporting;
    }

    public static function isFileReporting(): bool
    {
        return self::$file_reporting;
    }

    public static function setRobot()
    {
        self::$is_robot = true;
    }

    public static function setAdminPanel()
    {
        self::$is_admin_panel = true;
    }

    public static function setApi()
    {
        self::$is_api = true;
    }

    public static function setDev($settings = [])
    {
        self::$is_dev = true;

        self::$local_name = $settings['local_name'] ?? null;
        self::$is_loggable = $settings['is_loggable'] ?? true;
        self::$db_console_reporting = $settings['db_reporting'] ?? false;
        self::$file_reporting = $settings['file_reporting'] ?? false;

        self::$dev_telegram = $settings['telegram'] ?? null;

        ini_set('display_errors', 1);
        error_reporting(E_ALL^E_DEPRECATED);
    }

    public static function setStage(bool $is_loggable = true, bool $file_reporting = true)
    {
        self::$is_stage = true;

        self::$is_loggable = $is_loggable;
        self::$file_reporting = $file_reporting;

        // stage is publicly reachable: report everything, but never print PHP errors (paths, SQL) into responses;
        // they still go to the PHP log and through ErrorHandler/Debug reporting
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL^E_DEPRECATED);
    }

    public static function setFrontend()
    {
        self::$is_frontend = true;
    }

    public static function setDocs()
    {
        self::$isDocs = true;
    }

    public static function setProduction()
    {
        self::$is_production = true;
        ini_set('display_errors', 0);
    }

    public static function getDebugMails()
    {
        return self::$debug_mails;
    }

    public static function setDebugMails($debug_mails)
    {
        self::$debug_mails = $debug_mails;
    }

    public static function isHttps()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
        }
        return !empty($_SERVER['HTTPS']);
    }

    public static function getDevTelegram(): ?Notifier
    {
        if (!self::isDev() || empty(self::$dev_telegram) || empty(self::$dev_telegram['chat_id']) || empty(self::$dev_telegram['token']))
            return null;
        return new Notifier(['chat_id' => self::$dev_telegram['chat_id'], 'token' => self::$dev_telegram['token']]);
    }

    public static function getLocalName(): string
    {
        if (!self::isDev() || empty(self::$local_name))
            return '';
        return self::$local_name;
    }
}
