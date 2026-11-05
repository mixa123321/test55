<?php

namespace app\core\db;


class DBException extends \Exception
{
    const ERROR_DEADLOCK = 1213;
    const ERROR_LOCK_WAIT = 1205;
    const ERROR_DUPLICATE_ENTRY = 1062;

    const NUM_ATTEMPTS = 10;

    private function getErrorCode(): ?int
    {
        $mes_part = explode(' | ', $this->message);
        if (count($mes_part) == 3) {
            $error_code = explode(': ', $mes_part[2]);
            if (count($error_code) == 2 && $error_code[0] == 'Error code')
                return (int)$error_code[1];
        }
        return null;
    }

    public function isDeadlock($num): bool
    {
        $error_code = $this->getErrorCode();
        if (empty($error_code))
            return false;
        if (($error_code == self::ERROR_DEADLOCK || $error_code == self::ERROR_LOCK_WAIT) && $num < self::NUM_ATTEMPTS)
            return true;
        return false;
    }

    public function isDuplicateEntry(): bool
    {
        $error_code = $this->getErrorCode();
        if (empty($error_code))
            return false;
        if ($error_code == self::ERROR_DUPLICATE_ENTRY)
            return true;
        return false;
    }

    public function getDuplicateEntry()
    {
        $error_code = $this->getErrorCode();
        if (empty($error_code) && $error_code !== self::ERROR_DUPLICATE_ENTRY)
            return null;
        preg_match("/Error message: Duplicate entry (['\"])[\S*]*(['\"]) for key /", $this->getMessage(), $matches);
        if (!empty($matches)) {
            preg_match("/(['\"])[\S*]*(['\"])/", $matches[0], $match_index);
            $index = str_replace(["'", '"'], '', $match_index[0]);
            return explode('-', $index);
        }
        return null;
    }
}
