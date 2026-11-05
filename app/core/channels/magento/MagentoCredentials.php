<?php

namespace app\core\channels\magento;

use app\core\channels\BaseCredentials;

/** Incapsulated Magento credentials */
class MagentoCredentials extends BaseCredentials
{
    protected $token;

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = (string)$token;
    }
}