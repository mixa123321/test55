<?php

namespace app\api\models;

class ErrorCodes
{
    const FIELD_REQUIRED = 'field_required';
    const FIELDS_NOT_UNIQUE = 'field_not_unique';
    const FIELD_INVALID = 'field_invalid';
    const FIELDS_FOUND = 'fields_found';
    const WEBHOOK_SUBSCRIBED = 'webhook_subscribed';
    const WEBHOOK_NOT_FOUND = 'webhook_not_found';
    const WEBHOOK_NOTHING = 'webhook_nothing';
    const UNKNOWN_ERROR = 'unknown_error';
    const CARRIER_ERROR = 'carrier_error';
    const CANCEL_NOT_AVAILABLE = 'cancel_not_available';
    const LABEL_ERROR = 'label_error';
    const BARCODE_RANGE_ENDED = 'barcode_range_ended';
    const ACCESS_DENIED = 'access_denied';
    const UNKNOWN_ENDPOINT = 'unknown_endpoint';
    const INTERNAL_ERROR = 'internal_server_error';
    const DATA_ERROR = 'data_error';

    public static $ALL = [
        self::FIELD_REQUIRED => 'Field is required',
        self::FIELDS_NOT_UNIQUE => 'Fields are not unique',
        self::FIELDS_FOUND => 'Fields are found',
        self::WEBHOOK_SUBSCRIBED => 'Webhook already subscribed',
        self::WEBHOOK_NOT_FOUND => 'Webhook is notfound',
        self::WEBHOOK_NOTHING => 'Webhook - nothing to change',
        self::FIELD_INVALID => 'Field has incorrect value',
        self::UNKNOWN_ERROR => 'Unknown error',
        self::CARRIER_ERROR => 'Carrier error',
        self::CANCEL_NOT_AVAILABLE => 'Cancel not available',
        self::LABEL_ERROR => 'Label generation failed',
        self::BARCODE_RANGE_ENDED => 'Carrier barcode range has ended',
        self::ACCESS_DENIED => 'Access denied',
        self::UNKNOWN_ENDPOINT => 'Unknown endpoint',
        self::INTERNAL_ERROR => 'Internal server error',
        self::DATA_ERROR => 'Data error',
    ];

    public static function getOldErrors($code)
    {
        switch ($code) {
            case self::FIELD_REQUIRED:
                return "EXT_COMMON__FIELD_REQUIRED";
            case self::FIELD_INVALID:
                return "EXT_COMMON__FIELD_INVALID";
            case self::ACCESS_DENIED:
                return "EXT_COMMON__OPERATION_NOT_ALLOWED";
            case self::CANCEL_NOT_AVAILABLE:
                return "EXT_RETURNS__CANCEL_NOT_AVAILABLE";
        }
        return $code;
    }
}
