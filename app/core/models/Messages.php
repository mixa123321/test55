<?php

namespace app\core\models;

use app\core\entities\Message;
use app\core\dbal\SafeMysql;
use app\core\Environment;
use app\core\Router;
use app\core\statical\WithDate;
use app\core\helpers\Request;
use app\core\helpers\HTTP;
use app\core\helpers\notifiers\TelegramNotifier;
use app\core\helpers\notifiers\Notifier;

class Messages
{
    // 60 * 60 * 24 * 30 = 30 дней
    const TIME_LIVE = 2592000;
    const SLEEP_SECONDS = 4;

    const PARAMETER_KEY_ADD_DEBUG = 'add_debug';
    const PARAMETER_KEY_PARSE_MODE = 'parse_mode';
    const PARAMETER_KEY_FORMAT = 'format';
    const PARAMETER_KEY_TELEGRAM_THREAD = 'thread';

    const PARAMETER_VAL_FORMAT_WITH_TITLE = 'with_title';
    const PARAMETER_VAL_PARSE_MODE_HTML = TelegramNotifier::PARSE_HTML;

    const TEST_PARAMETERS = [Messages::PARAMETER_KEY_FORMAT => Messages::PARAMETER_VAL_FORMAT_WITH_TITLE];
    const DETAIL_PARAMETERS = [Messages::PARAMETER_KEY_ADD_DEBUG => true];

    const GROUP_WAREHOUSE = Notifiers::GROUP_WAREHOUSE;
    const GROUP_PROTECTION = Notifiers::GROUP_PROTECTION;
    const GROUP_ROBOT  = Notifiers::GROUP_ROBOT;
    const GROUP_INTEGRATION = Notifiers::GROUP_INTEGRATION;
    const GROUP_VIRTUAL = Notifiers::GROUP_VIRTUAL;

    const STATUS_NEW = 'new';
    const STATUS_SENT = 'sent';
    const STATUS_ERROR = 'error';

    const ALL_STATUS = [
        self::STATUS_NEW,
        self::STATUS_SENT,
        self::STATUS_ERROR,
    ];

    const ALL_TYPES = [
        self::GROUP_WAREHOUSE,
        self::GROUP_PROTECTION,
        self::GROUP_ROBOT,
        self::GROUP_INTEGRATION,
        self::GROUP_VIRTUAL,
    ];

    const TELEGRAM_TYPES = [
        self::GROUP_WAREHOUSE,
        self::GROUP_PROTECTION,
        self::GROUP_ROBOT,
        self::GROUP_INTEGRATION,
        self::GROUP_VIRTUAL,
    ];

    public static function addDebugInfo(string $subj, string $msg, string $type, array $parameters = [], bool $check_duplicate = false): void
    {
        $msg = self::formatMessageBeforeWrite($msg, $parameters[self::PARAMETER_KEY_ADD_DEBUG] ?? false);
        if (Environment::isDev())
            self::sendMessage($subj, $msg, $type, $parameters);
        else {
            $message = (new Message())->setTitle($subj)->setMessage($msg)->setType($type)->setStatus(self::STATUS_NEW)->setParameters(json_encode($parameters));
            if ($check_duplicate && self::existDuplicateMessage($message))
                return;
            self::add($message);
        }
    }

    public static function sendDebugInfo(): void
    {
        foreach (self::getNewMessagesByTypes(self::ALL_TYPES) as $item) {
            $parameters = $item['parameters'] ? json_decode($item['parameters'], true) : [];
            $status = self::sendMessage($item['title'], $item['message'], $item['type'], $parameters) ? self::STATUS_SENT : self::STATUS_ERROR;
            self::setStatus([$item['id']], $status);
            sleep(self::SLEEP_SECONDS);
        }
    }

    public static function delOld(): void
    {
        (new SafeMysql())->execute("DELETE FROM messages WHERE TIMESTAMPDIFF(SECOND, created_at, NOW()) > :time_life", ['time_life' => self::TIME_LIVE]);
    }

    private static function add(Message $message): void
    {
        if (!in_array($message->getType(), self::ALL_TYPES))
            throw new \Exception('Wrong type');
        if (!in_array($message->getStatus(), self::ALL_STATUS))
            throw new \Exception('Wrong status');
        (new SafeMysql())->execute("INSERT INTO messages SET title = :title, message = :message, type = :type, status = :status, parameters = :parameters", [
            'title' => $message->getTitle(),
            'message' => $message->getMessage(),
            'type' => $message->getType(),
            'status' => $message->getStatus(),
            'parameters' => $message->getParameters()
        ]);
    }

    private static function setStatus(array $ids, string $status): void
    {
        if (!$ids || !in_array($status, self::ALL_STATUS))
            return;
        $params = ['status' => $status];
        $placeholders = [];
        foreach ($ids as $key => $id) {
            $placeholders[] = ":id_$key";
            $params["id_$key"] = (int)$id;
        }
        (new SafeMysql())->execute("UPDATE messages SET status = :status WHERE id IN (" . implode(',', $placeholders) . ")", $params);
    }

    private static function existDuplicateMessage(Message $message)
    {
        $sql = "SELECT 1
                FROM messages
                WHERE title = :title AND message = :message AND type = :type AND status = :status
                LIMIT 1";
        return !empty((new SafeMysql())->fetchOne($sql, ['title' => $message->getTitle(), 'message' => $message->getMessage(), 'type' => $message->getType(), 'status' => self::STATUS_SENT]));
    }

    private static function getNewMessagesByTypes(array $types): array
    {
        if (empty($types))
            return [];
        if (array_diff($types, self::ALL_TYPES))
            throw new \Exception('Wrong type');
        $sql = "SELECT id, title, message, type, parameters
                FROM messages
                WHERE status = :status AND type IN (:types)
                ORDER BY created_at
                LIMIT 1000";
        return (new SafeMysql())->fetchAll($sql, ['types' => $types, 'status' => self::STATUS_NEW]);
    }

    public static function sendMessage(string $title, string $message, string $type, array $parameters): bool
    {
        if (in_array($type, self::TELEGRAM_TYPES)) {
            $message = self::formatMessageBeforeSent($title, $message, $parameters[self::PARAMETER_KEY_FORMAT] ?? '');
            $parse_mode = $parameters[self::PARAMETER_KEY_PARSE_MODE] ?? self::PARAMETER_VAL_PARSE_MODE_HTML;
            $thread_id = $parameters[self::PARAMETER_KEY_TELEGRAM_THREAD] ?? TelegramNotifier::THREAD_ID_GENERAL;
            return (new TelegramNotifier($type, $message, $parse_mode, $thread_id))->send(false);
        }
        return false;
    }

    private static function formatMessageBeforeWrite(string $msg, bool $add_debug): string
    {
        return $add_debug ? "Main message: {$msg}\r\n" . var_export(self::getGeneralDebugInfo(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)), true) : $msg;
    }

    private static function getGeneralDebugInfo(array $backtrace)
    {
        global $argv;
        $post = $_POST;
        $session = $_SESSION ?? [];
        unset($post['credit_card_data'], $post['password'], $post['api_key'], $session['permissions']);
        return [
            'time_start' => Router::getTimeStart(),
            'time_human' => date(WithDate::DATE_SQL_FORMAT),
            'ip' => Request::getIp(),
            'uri' => HTTP::getHost() . HTTP::getRequestUri(),
            'get' => $_GET,
            'post' => $post,
            'files' => $_FILES,
            'session' => $session ? $session : 'Session is empty',
            'headers' => Request::getRequestHeaders(),
            'argv' => $argv,
            'backtrace' => $backtrace,
        ];
    }

    private static function formatMessageBeforeSent(string $title, string $message, string $format): string
    {
        if ($format === self::PARAMETER_VAL_FORMAT_WITH_TITLE)
            return "$title\r\n<pre>$message</pre>";
        return $message;
    }
}