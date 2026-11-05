<?php

namespace app\core\traits;

use app\core\interfaces\RequestValidationInterface;

trait RequestValidation
{
    private $request_data = [];
    private $valid_request_data = [];
    private $errors = [];

    public function getValidationRequestErrors(): array
    {
        return $this->errors;
    }

    public function setRequestData(array $request_data): RequestValidationInterface
    {
        $this->request_data = $request_data;
        return $this;
    }

    public function getValidRequestData(): array
    {
        return $this->valid_request_data;
    }

    protected function noMandatoryMaxLen(array $values, int $max_len, string $field_name): string
    {
        if (isset($values[$field_name]) && mb_strlen($values[$field_name]) > $max_len) {
            return "$field_name should be max Length $max_len";
        }
        return '';
    }

    protected function mandatoryMaxLen(array $values, int $max_len, string $field_name): string
    {
        if (empty($values[$field_name])) {
            return "$field_name is required";
        } elseif (mb_strlen($values[$field_name]) > $max_len) {
            return "$field_name should be max Length $max_len";
        }
        return '';
    }

    protected function mandatoryMinMaxLen(array $values, int $min_len, int $max_len, string $field_name)
    {
        if (empty($values[$field_name])) {
            return "$field_name is required";
        }
        $len = mb_strlen($values[$field_name]);
        if ($len > $max_len) {
            return "$field_name should be max Length $max_len";
        } elseif ($len < $min_len) {
            return "$field_name should be min Length $max_len";
        }
        return '';
    }

    protected function mandatoryDate(array $values, string $dateFormat, string $field_name): string
    {
        if (empty($values[$field_name])) {
            return "$field_name is required";
        } elseif (\DateTime::createFromFormat($dateFormat, $values[$field_name]) === false) {
            return "$field_name wrong format";
        }
        return '';
    }

    protected function mandatoryArray(array $values, string $field_name): string
    {
        if (empty($values[$field_name])) {
            return "$field_name is required";
        } elseif (!is_array($values[$field_name])) {
            return "$field_name wrong format";
        }
        return '';
    }

    protected function mandatoryRegexp(array $values, string $regexp, string $field_name): string
    {
        if (empty($values[$field_name])) {
            return "$field_name is required";
        } elseif (!mb_ereg_match($regexp, $values[$field_name])) {
            return "$field_name wrong format";
        }
        return '';
    }

    protected function noMandatoryRegexp(array $values, string $regexp, string $field_name): string
    {
        if (isset($values[$field_name]) && !mb_ereg_match($regexp, $values[$field_name])) {
            return "$field_name wrong format";
        }
        return '';
    }
}