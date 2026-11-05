<?php

namespace app\core\helpers\notifiers;

use app\core\entities\Notifier as NotifierEntity;

class SlackNotifier extends Notifier
{
    private const HOST = 'https://hooks.slack.com/services';
    private const APP = 'T03F3TQ9VKN';

    public function send()
    {
        /** @var NotifierEntity[] $groups */
        $groups = self::getGroups();
        if (empty($groups[$this->group]) || empty($groups[$this->group]->getEndpoint()))
            return false;
        $ch = curl_init(self::HOST . DIRECTORY_SEPARATOR . self::APP . DIRECTORY_SEPARATOR . $groups[$this->group]->getEndpoint());
        $data = [
            'username' => 'Fulfilment',
            'channel' => '#'.$this->group,
            'text' => $this->message,
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($data)
        ]);
        curl_exec($ch);
        curl_close($ch);
        return !empty($result);
    }
}