<?php

namespace app\core\exceptions;

use app\core\ErrorHandler;
use Throwable;

class VirtualException extends \Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct('Virtual: ' . $message, $code ?: ErrorHandler::VIRTUAL_INTEGRATION_ERROR, $previous);
    }
}
