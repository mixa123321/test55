<?php

namespace app\core\exceptions;

use app\core\ErrorHandler;
use Throwable;

class FlatException extends \Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, ErrorHandler::FLAT_INTEGRATION_ERROR, $previous);
    }
}
