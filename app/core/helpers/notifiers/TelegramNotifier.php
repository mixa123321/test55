<?php

namespace app\core\helpers\notifiers;

use app\core\entities\LogisticCompany;
use app\core\Environment;
use app\core\entities\Notifier as NotifierEntity;
use app\core\models\Notifiers;

class TelegramNotifier extends Notifier
{
    public const PARSE_HTML = 'html';

    public const THREAD_ID_GENERAL = 0;
    public const THREAD_ID_TEST = 13;

    private $parse_mode;
    private $thread_id;

    public function __construct($group, $message, $parse_mode = '', $thread_id = self::THREAD_ID_GENERAL)
    {
        parent::__construct($group, $message);
        $this->parse_mode = $parse_mode;
        $this->thread_id = $thread_id;
    }

    private function getLocalName(): string
    {
        $local =  Environment::getLocalName();
        if (!empty($local)) {
            if ($this->parse_mode === self::PARSE_HTML)
                $local = "<pre>" . $local . "</pre>";
            $local .= "\n";
        }
        return $local;
    }

    public function send(bool $get_thread_id = true)
    {
        $message = $this->message;
        /** @var NotifierEntity[] $groups */
        $groups = self::getGroups() ?: Notifiers::getGroupsData(new LogisticCompany(['id' => SCRIPT_NOTIFIERS_DEFAULT]));
        if (empty($groups[$this->group]) || $groups[$this->group]->emptyTelegram())
            return false;

        $notifier = Environment::getDevTelegram() ?: $groups[$this->group];
        $token = $notifier->getToken();
        $chat_id = $notifier->getChatId();

        //TODO убрать с этого уровня выше. Здесь должен быть чистый thread_id
        if ($get_thread_id && $this->group === Notifiers::GROUP_WAREHOUSE) {
            $this->thread_id = defined('TELEGRAM_THREAD_ID_WAREHOUSE')
                ? TELEGRAM_THREAD_ID_WAREHOUSE
                : self::THREAD_ID_TEST;
        }

        $message = $this->getLocalName() . $message;
        $endpoint = 'https://api.telegram.org/bot' . $token . '/sendMessage';
        $ch = curl_init($endpoint);

        $data = [
            'chat_id'   => $chat_id,
            'text'      => $message,
        ];
        if ($this->thread_id) {
            $data['chat_id'] .= "_{$this->thread_id}";
            $data['message_thread_id'] = $this->thread_id;
        }
        if (!empty($this->parse_mode))
            $data['parse_mode'] = $this->parse_mode;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  =>  true,
            CURLOPT_POST            =>  true,
            CURLOPT_CONNECTTIMEOUT  =>  3,
            CURLOPT_TIMEOUT         =>  5,
            CURLOPT_HTTPHEADER      =>  ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS      =>  json_encode($data)
        ]);
        $result = curl_exec($ch);
        if (!(json_decode($result, TRUE)['ok'] ?? false))
            (new SlackNotifier($this->group, $message))->send();
        curl_close($ch);
        return !empty($result);
    }

    /* Todo delete after tested */
    public static function sendDirect(string $message, string $token, int $chat_id)
    {
        $endpoint = 'https://api.telegram.org/bot' . $token . '/sendMessage';
        $ch = curl_init($endpoint);
        $data = [
            'chat_id'   => $chat_id,
            'text'      => $message,
            'parse_mode' => 'html'
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  =>  true,
            CURLOPT_POST            =>  true,
            CURLOPT_CONNECTTIMEOUT  =>  3,
            CURLOPT_TIMEOUT         =>  5,
            CURLOPT_HTTPHEADER      =>  ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS      =>  json_encode($data)
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}