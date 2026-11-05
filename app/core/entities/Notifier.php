<?php

namespace app\core\entities;

class Notifier extends Entity
{
    private $chat_id;
    private $token;
    private $endpoint;

    public function getChatId()
    {
        return $this->chat_id;
    }

    public function setChatId($id)
    {
        $this->chat_id = (int)$id;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($name)
    {
        $this->token = (string)$name;
    }

    public function emptyTelegram()
    {
        return empty($this->getChatId()) || empty($this->getToken());
    }

    public function getEndpoint()
    {
        return $this->endpoint;
    }

    public function setEndpoint($name)
    {
        $this->endpoint = (string)$name;
    }

    public function toArray()
    {
        return [
            'chat_id' => $this->getChatId(),
            'token' => $this->getToken(),
            'endpoint' => $this->getEndpoint(),
        ];
    }
}