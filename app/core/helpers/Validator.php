<?php

namespace app\core\helpers;

use app\api\models\ErrorCodes;
use app\core\statical\WithDate;

class Validator
{
    const REQUIRED = 999;

    //Base types
    const TYPE_INT = 1;
    const TYPE_DOUBLE = 2;
    const TYPE_STRING = 3;
    const TYPE_ARRAY = 4;
    const TYPE_OBJECT = 5;
    const TYPE_BOOL = 6;
    const TYPE_INT_SET = 7;

    //Custom types
//    const TYPE_COUNTRY_CODE = 10;
//    const TYPE_EMAIL = 11;
//    const TYPE_COUNTRY_STATE = 12;
    const TYPE_DATE = 13;
    const TYPE_TIME = 14;
    const TYPE_BARCODE_128 = 128;

    private $rules;
    private $errors;

    public function __construct(array $rules = [])
    {
        $this->setRules($rules);
        $this->errors = [];
    }

    public function setRules(array $rules)
    {
        $this->rules = $rules;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function flushErrors()
    {
        $this->errors = [];
    }

    public function addError($code, $message, $field = '')
    {
        $this->errors[] = ['code' => $code, 'message' => $message, 'field' => $field];
    }

    public function validate()
    {
        foreach ($this->rules as $field_name => $specific_rules)
            foreach ($specific_rules['rules'] as $rule_type => $rule_value)
                if (isset($specific_rules['val']) && is_array($specific_rules['val']) && $rule_type !== self::TYPE_ARRAY)
                    foreach ($specific_rules['val'] as $field_value)
                        $this->validateByRule($rule_type, $rule_value, $field_name, $field_value);
                else
                    $this->validateByRule($rule_type, $rule_value, $field_name, isset($specific_rules['val']) ? $specific_rules['val'] : null);
        return $this->errors;
    }

    private function validateByRule($rule_type, $rule_value, $field_name, $field_value)
    {
        if ($rule_type == self::REQUIRED)
            if (is_null($field_value) || $field_value === "")
                $this->errors[] = [
                    "field" => $field_name,
                    "code" => ErrorCodes::FIELD_REQUIRED,
                    "message" => "$field_name is required"
                ];
        if (is_null($field_value))
            return;
        $error = [];
        switch ($rule_type) {
            case self::TYPE_STRING:
                if (!is_string($field_value)) {
                    $error = ["field" => $field_name, "message" => "$field_name must be a string"];
                    break;
                }
                list($min_len, $max_len) = $rule_value;
                $len = strlen($field_value);
                if ($len < $min_len || $len > $max_len) {
                    $error = [
                        "field" => $field_name,
                        "message" => "$field_name string minimum length is {$min_len}, maximum length is {$max_len}"
                    ];
                }
                break;
            case self::TYPE_INT:
                if (!is_int($field_value)) {
                    $error = [
                        "field" => $field_name,
                        "message" => "$field_name value '{$field_value}' is incorrect. $field_name must be an integer"
                    ];
                    break;
                }
                list($min_val, $max_val) = $rule_value;
                if ($field_value < $min_val || $field_value > $max_val) {
                    $error = [
                        "field" => $field_name,
                        "message" => "$field_name value '{$field_value}' is incorrect. $field_name must be between {$min_val} and {$max_val}"
                    ];
                }
                break;
            case self::TYPE_BOOL:
                $intval = (int)$field_value;
                if (!is_bool($field_value) && $intval !== 0 && $intval !== 1) {
                    $error = [
                        "field" => $field_name,
                        "message" => "$field_name must be boolean (1 or 0, true or false)"
                    ];
                }
                break;
            case self::TYPE_DOUBLE:
                if (!is_numeric($field_value)) {
                    $error = [
                        "field" => $field_name,
                        "message" => "$field_name value '{$field_value}' is incorrect. $field_name must be a double(numeric)"
                    ];
                    break;
                }
                if (empty($rule_value) || !is_array($rule_value)) {
                    break;
                }
                list($min_val, $max_val) = $rule_value;
                if ($field_value < $min_val || $field_value > $max_val) {
                    $error = [
                        "field" => $field_name,
                        "message" => "$field_name value '{$field_value}' is incorrect. $field_name must be between {$min_val} and {$max_val}"
                    ];
                }
                break;
            case self::TYPE_ARRAY:
                if (!is_array($field_value)) {
                    $error = ["field" => $field_name, "message" => "$field_name must be an array"];
                }
                if (is_array($rule_value) && !empty($rule_value) && count($rule_value) === 2) {
                    list($min_size, $max_size) = $rule_value;
                    $real_size = count($field_value);
                    if ($real_size < $min_size || $real_size > $max_size) {
                        $error = [
                            "field" => $field_name,
                            "message" => "$field_name array size must between $min_size and $max_size"
                        ];
                    }
                }
                break;
//            case self::TYPE_OBJECT:
//                if (!is_object($field_value)) {
//                    $error = ["field" => $field_name, "message" => "$field_name must be an object"];
//                }
//                break;
            case self::TYPE_INT_SET:
                if (!is_int($field_value)) {
                    $error = [
                        "field" => $field_name,
                        "message" => "$field_name value '{$field_value}' is incorrect. $field_name must be an integer"
                    ];
                    break;
                }
                if (!in_array($field_value, $rule_value))
                    $error = [
                        "field" => $field_name,
                        "message" => "$field_name value '{$field_value}' is incorrect. $field_name must be in (" . implode(',', $rule_value) . ")"
                    ];
                break;
//            case self::TYPE_COUNTRY_CODE:
//                if (strlen($field_value) != 2) {
//                    $error = ["field" => $field_name, "message" => "$field_name length must be 2 characters"];
//                    break;
//                }
//                if (!Countries::getName($field_value)) {
//                    $error = ["field" => $field_name, "message" => "$field_name must be valid ISO 3166-1 alpha-2 code"];
//                }
//                break;
//            case self::TYPE_COUNTRY_STATE:
//                if (!is_string($field_value)) {
//                    $error = ["field" => $field_name, "message" => "$field_name must be a string"];
//                    break;
//                }
//                $states = \app\core\statical\States::getAll();
//                $country_code = strtoupper($rule_value); //US, UK, UA, RU, ...
//                if (strlen($country_code) > 1 && isset($states[$country_code])) {
//                    $state_code = strtolower($field_value); //NY, TX, ...
//                    if (!isset($states[$country_code][$state_code])) {
//                        $error = [
//                            "field" => $field_name,
//                            "message" => "$field_name: $field_value not is state of $country_code"
//                        ];
//                    }
//                }
//                break;
//            case self::TYPE_EMAIL:
//                if (!preg_match("/^[^\s@]+@[^\s@]+\.[^\s@]+$/Ui", $field_value)) {
//                    $error = ["field" => $field_name, "message" => "$field_name has incorrect email format"];
//                }
//                break;
            case self::TYPE_DATE:
                if (!WithDate::dateToInt($field_value))
                    $error = ['field' => $field_name, 'message' => 'Date format is not correct'];
                break;
            case self::TYPE_TIME:
                if (!TimeRule::validate($field_value))
                    $error = ['field' => $field_name, 'message' => 'Time is not correct'];
                break;
            case self::TYPE_BARCODE_128:
                $is_valid_barcode = Barcode128Rule::validate($field_value);
                if (!$is_valid_barcode) {
                    $error = [
                        'field' => $field_name,
                        'message' => 'barcode is not matching type Code128'
                    ];
                }
                break;
        }
        if (!empty($error)) {
            $error["code"] = isset($error["code"]) ? $error["code"] : ErrorCodes::FIELD_INVALID;
            $this->errors[] = $error;
        }
    }
}
