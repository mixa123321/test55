<?php

namespace app\core\helpers;

use app\core\Environment;
use app\core\helpers\notifiers\Notifier;
use app\core\helpers\notifiers\TelegramNotifier;
use app\core\helpers\notifiers\EmailNotifier;
use app\core\statical\WithDate;
use app\core\models\Messages;

class Debug
{
    const EMAIL = 'email';
    const TELEGRAM = 'telegram';
    const TEST = 'test';
    const ROBOT = 'robot';

    public static function myPrint()
    {
        if (!Environment::isDev())
            return;
        foreach (func_get_args() as $arg) {
            echo '<pre>';
            var_dump($arg);
            echo '</pre>';
        }
        die;
    }

    public static function myPrintWithoutDie()
    {
        if (!Environment::isDev())
            return;
        foreach (func_get_args() as $arg) {
            echo '<pre>';
            var_dump($arg);
            echo '</pre>';
        }
    }

    public static function putToWatchingFile($data)
    {
        if (empty($data))
            return;
        $file = 'reporting';
        $path = ROOT_PATH . "/storage/$file";
        if (filesize($path) > (1 << 20))
            file_put_contents($path, '');
        self::putToLogFile($file, $data);
    }

    public static function putToLogFile($logFileName, $data)
    {
        file_put_contents(ROOT_PATH . "/storage/{$logFileName}", var_export($data, 1), FILE_APPEND);
    }

    private static function getGeneralDebugInfo($subj, $msg)
    {
        global $argv;
        return [
            'time_human' => date(WithDate::DATE_SQL_FORMAT),
            'ip' => Request::getIp(),
            'uri' => HTTP::getHost() . HTTP::getRequestUri(),
            'get' => $_GET,
            'post' => Request::getVariable($_POST, null, [], ['credit_card_data', 'password', 'api_key']),
            'files' => $_FILES,
            'session' => empty($_SESSION) ? 'Session is empty' : Request::getVariable($_SESSION, null, [], ['credit_card_data', 'password', 'api_key', 'permissions']),
            'headers' => Request::getRequestHeaders(),
            'argv' => $argv,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
        ];
    }

    public static function sendDebugInfo($subj, $msg, $type = self::TELEGRAM, $thread_id = TelegramNotifier::THREAD_ID_GENERAL)
    {
        $generalInfo = self::getGeneralDebugInfo($subj, $msg);
        $thread_param = [Messages::PARAMETER_KEY_TELEGRAM_THREAD => defined('TELEGRAM_THREAD_ID_WAREHOUSE') ? TELEGRAM_THREAD_ID_WAREHOUSE : TelegramNotifier::THREAD_ID_TEST];
        switch ($type) {
            case self::TELEGRAM:
//                $provider = new TelegramNotifier(Notifier::GROUP_WAREHOUSE,
//                    "{$subj}\r\nMain message: {$msg}\r\n" . var_export($generalInfo, 1), '', $thread_id);
                Messages::addDebugInfo($subj, $msg, Messages::GROUP_WAREHOUSE, Messages::DETAIL_PARAMETERS + $thread_param);
                break;
            case self::TEST:
//                $provider = new TelegramNotifier(Notifier::GROUP_WAREHOUSE, "$subj\r\n<pre>$msg</pre>",
//                    TelegramNotifier::PARSE_HTML, $thread_id);
                Messages::addDebugInfo($subj, $msg, Messages::GROUP_WAREHOUSE, Messages::TEST_PARAMETERS + $thread_param);
                break;
            case self::ROBOT:
//                $provider = new TelegramNotifier(Notifier::GROUP_ROBOT, "$subj\r\n<pre>$msg</pre>",
//                                                TelegramNotifier::PARSE_HTML, $thread_id);
                Messages::addDebugInfo($subj, $msg, Messages::GROUP_ROBOT, Messages::TEST_PARAMETERS);
                break;
                break;
            case self::EMAIL:
                $provider = new EmailNotifier($subj, $msg."\nDebug info:\n " . var_export($generalInfo, 1));
                break;
            default:
                return;
        }
//        $provider->send();
    }

    public static function getBacktrace()
    {
        return debug_backtrace();
    }
}
