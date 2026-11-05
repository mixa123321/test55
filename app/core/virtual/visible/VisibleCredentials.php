<?php

namespace app\core\virtual\visible;

use app\core\virtual\BaseVirtualCredentials;

class VisibleCredentials extends BaseVirtualCredentials
{
    protected $access_token;
    protected $expires;
    protected $api_key_name;
    protected $api_key;

    public function getAccessToken()
    {
        return $this->access_token;
    }

    public function setAccessToken($token)
    {
        $this->access_token = (string)$token;
    }

    public function getExpires()
    {
        return $this->expires;
    }

    public function setExpires($date_expires)
    {
        $this->expires = is_string($date_expires) ? strtotime($date_expires) : (int)$date_expires;
    }

    public function getAPIKeyName()
    {
        return $this->api_key_name;
    }

    public function setAPIKeyName($api_key_name)
    {
        $this->api_key_name = $api_key_name;
    }

    public function getAPIKey()
    {
        return $this->api_key;
    }

    public function setAPIKey($api_key)
    {
        $this->api_key = $api_key;
    }
}