<?php

namespace app\core\flat;

abstract class BaseFlatConnector
{
    protected $id;
    protected $credentials;
    protected $error_messages = [];

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    /** @return BaseFlatCredentials */
    public function getCredentials()
    {
        return $this->credentials;
    }

    abstract function setCredentials($credentials);

    public function getErrorMessages()
    {
        return $this->error_messages;
    }

    public function setErrorMessages($msg)
    {
        $this->error_messages[] = $msg;
    }

    public function isErrors()
    {
        return !empty($this->error_messages);
    }

    abstract public function uploadOrders($path);
    abstract public function sendReport($path, $csv_file, $type);
    abstract public function getFiles();
    abstract public function close();
}