<?php

namespace app\core\entities;

class TrackingHistory extends Entity
{
    const ACTION_MAIN = 'main';
    const ACTION_EXTERNAL = 'external';

    private $hash;
    private $ip;
    private $last_access;
    private $action;
    private $package_id;

    public function getHash()
    {
        return $this->hash;
    }

    public function setId(string $hash)
    {
        $this->hash = $hash;
    }

    public function getIP()
    {
        return (string)$this->ip;
    }

    public function setIP(string $ip)
    {
        $this->ip = $ip;
    }

    public function getLastAccess()
    {
        return $this->last_access;
    }

    public function setLastAccess(string $last_access)
    {
        $this->last_access = $last_access;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setAction(string $action)
    {
        $this->action = $action;
    }

    public function getPackageId()
    {
        return $this->package_id;
    }

    public function setPackageId(int $package_id)
    {
        $this->package_id = $package_id;
    }

    public function toArray()
    {
        return [
            'hash' => $this->getHash(),
            'IP' => $this->getIP(),
            'last_access' => $this->getLastAccess(),
            'action' => $this->getAction(),
            'package_id' => $this->getPackageId(),
        ];
    }
}