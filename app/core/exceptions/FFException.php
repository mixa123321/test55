<?php

namespace app\core\exceptions;

class FFException extends \Exception
{
    private $codes_message;
    private $http_code;

    public function __construct($http_code, $codes_messages, $previous = null)
    {
        $this->http_code = $http_code;
        $this->codes_message = ['errors' => $codes_messages];
        $message = reset($codes_messages);
        // First error message will use for description of exception.
        parent::__construct($message['message'], $http_code, $previous);
    }

    public function getCodesMessage()
    {
        return $this->codes_message;
    }

    public function getHttpCode()
    {
        return $this->http_code;
    }
}
