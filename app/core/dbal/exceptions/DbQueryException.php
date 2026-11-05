<?php

declare(strict_types=1);

namespace app\core\dbal\exceptions;

class DbQueryException extends DbException
{
    /**
     * MySQL error codes, mirroring app\core\db\DBException's constants so both
     * exception types can be checked for the same deadlock conditions.
     */
    private const ERROR_DEADLOCK = 1213;
    private const ERROR_LOCK_WAIT = 1205;

    private const NUM_ATTEMPTS = 10;

    /**
     * The mysqli error code, when known. Passed in via the exception's built-in
     * $code (the constructor's second argument) by whoever throws this exception.
     */
    public function getErrorCode(): int
    {
        return $this->getCode();
    }

    public function isDeadlock(int $num): bool
    {
        $errorCode = $this->getErrorCode();
        if (!$errorCode) {
            return false;
        }
        return ($errorCode === self::ERROR_DEADLOCK || $errorCode === self::ERROR_LOCK_WAIT) && $num < self::NUM_ATTEMPTS;
    }
}
