<?php

namespace app\core\models\config;

class OrdersConfig extends \app\core\entities\config\OrdersConfig
{
    const TYPE_DESPATCHED_CARRIER_ID = 'despatched_carrier_id';
    const TYPE_DESPATCHED_SHIPPING_SERVICE_ID = 'despatched_shipping_service';
    const TYPE_ORDER_STATUS_BEFORE_QUERY = 'order_status_before_query';
    const TYPE_ORDER_PRINTED_MATTER = 'order_printed_matter';
    const TYPE_ORDER_EORI_NUMBER = 'order_eori_number';
    const TYPE_ORDER_VAT_NUMBER = 'order_vat_number';
    const TYPE_ORDER_IOSS_NUMBER = 'order_ioss_number';
    const TYPE_ORDER_TAX_ID = 'order_tax_id';
    const TYPE_EXCLUDE_ORDER_COMMENT = 'order_exclude_comment';
    const TYPE_EXCLUDE_ORDER_STATUS = 'order_exclude_status';

    const TYPES = [
        self::TYPE_DESPATCHED_CARRIER_ID => self::FIELD_VALUE_INT,
        self::TYPE_DESPATCHED_SHIPPING_SERVICE_ID => self::FIELD_VALUE_INT,
        self::TYPE_ORDER_STATUS_BEFORE_QUERY => self::FIELD_VALUE_INT,
        self::TYPE_ORDER_PRINTED_MATTER => self::FIELD_VALUE_INT,
        self::TYPE_ORDER_EORI_NUMBER => self::FIELD_VALUE_STRING,
        self::TYPE_ORDER_VAT_NUMBER => self::FIELD_VALUE_STRING,
        self::TYPE_ORDER_IOSS_NUMBER => self::FIELD_VALUE_STRING,
        self::TYPE_ORDER_TAX_ID => self::FIELD_VALUE_STRING,
        self::TYPE_EXCLUDE_ORDER_COMMENT => self::FIELD_VALUE_STRING,
        self::TYPE_EXCLUDE_ORDER_STATUS => self::FIELD_VALUE_INT,
    ];

    public static function despatchedCarrierId(int $order_id, int $value): OrdersConfig
    {
        return new OrdersConfig(self::getData($order_id, self::TYPE_DESPATCHED_CARRIER_ID, $value));
    }

    public static function getDespatchedCarrierId(int $order_id): int
    {
        return self::readConfigById($order_id, self::TYPE_DESPATCHED_CARRIER_ID);
    }

    public static function deleteDespatchedCarrierId(int $order_id)
    {
        self::removeConfigById($order_id, self::TYPE_DESPATCHED_CARRIER_ID);
    }

    public static function shippingServiceId(int $order_id, int $value): OrdersConfig
    {
        return new OrdersConfig(self::getData($order_id, self::TYPE_DESPATCHED_SHIPPING_SERVICE_ID, $value));
    }

    public static function getShippingServiceId(int $order_id): int
    {
        return self::readConfigById($order_id, self::TYPE_DESPATCHED_SHIPPING_SERVICE_ID);
    }

    public static function deleteShippingServiceId(int $order_id)
    {
        self::removeConfigById($order_id, self::TYPE_DESPATCHED_SHIPPING_SERVICE_ID);
    }

    public static function orderStatusBeforeQuery(int $order_id, int $value)
    {
        return new OrdersConfig(self::getData($order_id, self::TYPE_ORDER_STATUS_BEFORE_QUERY, $value));
    }

    public static function getOrderStatusBeforeQuery(int $order_id)
    {
        return self::readConfigById($order_id, self::TYPE_ORDER_STATUS_BEFORE_QUERY);
    }

    public static function deleteOrderStatusBeforeQuery(int $order_id)
    {
        self::removeConfigById($order_id, self::TYPE_ORDER_STATUS_BEFORE_QUERY);
    }

    public static function orderPrintedMatter(int $order_id, int $value)
    {
        return new OrdersConfig(self::getData($order_id, self::TYPE_ORDER_PRINTED_MATTER, $value));
    }

    public static function getOrderPrintedMatter(int $order_id)
    {
        return self::readConfigById($order_id, self::TYPE_ORDER_PRINTED_MATTER);
    }

    public static function deleteOrderPrintedMatter(int $order_id)
    {
        self::removeConfigById($order_id, self::TYPE_ORDER_PRINTED_MATTER);
    }

    public static function orderEoriNumber(int $order_id, string $value): OrdersConfig
    {
        return new OrdersConfig(self::getData($order_id, self::TYPE_ORDER_EORI_NUMBER, $value));
    }

    public static function getOrderEoriNumber(int $order_id): string
    {
        return self::readConfigById($order_id, self::TYPE_ORDER_EORI_NUMBER);
    }

    public static function deleteOrderEoriNumber(int $order_id)
    {
        self::removeConfigById($order_id, self::TYPE_ORDER_EORI_NUMBER);
    }

    public static function orderVatNumber(int $order_id, string $value): OrdersConfig
    {
        return new OrdersConfig(self::getData($order_id, self::TYPE_ORDER_VAT_NUMBER, $value));
    }

    public static function getOrderVatNumber(int $order_id): string
    {
        return self::readConfigById($order_id, self::TYPE_ORDER_VAT_NUMBER);
    }

    public static function deleteOrderVatNumber(int $order_id)
    {
        self::removeConfigById($order_id, self::TYPE_ORDER_VAT_NUMBER);
    }

    public static function orderIossNumber(int $order_id, string $value): OrdersConfig
    {
        return new OrdersConfig(self::getData($order_id, self::TYPE_ORDER_IOSS_NUMBER, $value));
    }

    public static function getOrderIossNumber(int $order_id): string
    {
        return self::readConfigById($order_id, self::TYPE_ORDER_IOSS_NUMBER);
    }

    public static function deleteOrderIossNumber(int $order_id)
    {
        self::removeConfigById($order_id, self::TYPE_ORDER_IOSS_NUMBER);
    }

    public static function orderTaxId(int $order_id, string $value): OrdersConfig
    {
        return new OrdersConfig(self::getData($order_id, self::TYPE_ORDER_TAX_ID, $value));
    }

    public static function getOrderTaxId(int $order_id): string
    {
        return self::readConfigById($order_id, self::TYPE_ORDER_TAX_ID);
    }

    public static function excludeOrderComment(int $order_id, string $value): OrdersConfig
    {
        return new OrdersConfig(self::getData($order_id, self::TYPE_EXCLUDE_ORDER_COMMENT, $value));
    }

    public static function getExcludeOrderComment(int $order_id): string
    {
        return self::readConfigById($order_id, self::TYPE_EXCLUDE_ORDER_COMMENT);
    }

    public static function deleteExcludeOrderComment(int $order_id)
    {
        self::removeConfigById($order_id, self::TYPE_EXCLUDE_ORDER_COMMENT);
    }

    public static function excludeOrderStatus(int $order_id, int $value): OrdersConfig
    {
        return new OrdersConfig(self::getData($order_id, self::TYPE_EXCLUDE_ORDER_STATUS, $value));
    }

    public static function getExcludeOrderStatus(int $order_id): int
    {
        return self::readConfigById($order_id, self::TYPE_EXCLUDE_ORDER_STATUS);
    }

    public static function deleteExcludeOrderStatus(int $order_id)
    {
        self::removeConfigById($order_id, self::TYPE_EXCLUDE_ORDER_STATUS);
    }
}
