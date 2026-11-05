<?php

namespace app\core\exceptions;

use app\core\ErrorHandler;
use Throwable;

class ChannelException extends \Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code ?: ErrorHandler::CI_ERROR, $previous);
    }
}
