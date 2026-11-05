<?php

namespace app\core\statical;

class Sources
{
    const SOURCE_NONE = 'none';
    const SOURCE_WEB = 'web';
    const SOURCE_SHORT_SHIP = 'ShortShip';
    const SOURCE_UPLOAD = 'upload';
    const SOURCE_FLAT = 'flat';
    const SOURCE_API = 'api';
    const SOURCE_SHOPIFY = 'shopify';
    const SOURCE_WOOCOMMERCE = 'woocommerce';
    const SOURCE_MAGENTO = 'magento';

    private static $typeSources = [
        self::SOURCE_NONE => 'None',
        self::SOURCE_WEB => 'Web',
        self::SOURCE_SHORT_SHIP => 'Short Ship',
        self::SOURCE_UPLOAD => 'Upload',
        self::SOURCE_FLAT => 'Flat',
        self::SOURCE_API => 'API',
        self::SOURCE_SHOPIFY => 'Shopify',
        self::SOURCE_WOOCOMMERCE => 'WooCommerce',
        self::SOURCE_MAGENTO => 'Magento',
    ];

    public static $integrations = [
        self::SOURCE_SHOPIFY,
        self::SOURCE_WOOCOMMERCE,
        self::SOURCE_MAGENTO
    ];

    public static $not_show_requests = [
        self::SOURCE_WEB,
        self::SOURCE_SHORT_SHIP
    ];

    public static function getAll()
    {
        return self::$typeSources;
    }
}
