<?php

namespace app\core\models;

/**
 * Model for handling order categories
 */
class OrderCategories
{
    const COMMERCIAL_GOODS = 'commercial_goods';
    const CLOTHING = 'clothing';
    const COMMERCIAL_SAMPLE = 'commercial_sample';
    const DANGEROUS_GOODS = 'dangerous_goods';
    const DOCUMENTS = 'documents';
    const GIFTS = 'gifts';
    const HUMANITARIAN_DONATIONS = 'humanitarian_donations';
    const INTERCOMPANY_TRANSFER = 'intercompany_transfer';
    const OTHER = 'other';
    const PERSONAL_ITEMS = 'personal_items';
    const REPAIR = 'repair';
    const RETURNED_GOODS = 'returned_goods';

    const DEFAULT = self::COMMERCIAL_GOODS;

    private static $categories = [
        self::COMMERCIAL_GOODS => 'Commercial Goods',
        self::CLOTHING => 'Clothing',
        self::COMMERCIAL_SAMPLE => 'Commercial Sample',
        self::DANGEROUS_GOODS => 'Dangerous Goods',
        self::DOCUMENTS => 'Documents',
        self::GIFTS => 'Gifts',
        self::HUMANITARIAN_DONATIONS => 'Humanitarian Donations',
        self::INTERCOMPANY_TRANSFER => 'Intercompany Transfer',
        self::OTHER => 'Other',
        self::PERSONAL_ITEMS => 'Personal Items',
        self::REPAIR => 'Repair',
        self::RETURNED_GOODS => 'Returned Goods',
    ];

    public static function getCategories(): array
    {
        return self::$categories;
    }

    public static function getCategoryName(string $category_key): string
    {
        return self::$categories[strtolower($category_key)] ?? self::DEFAULT;
    }

    public static function getCategory(string $category_key): string
    {
        $category_key = strtolower($category_key);
        return !empty(self::$categories[$category_key]) ? $category_key : self::DEFAULT;
    }
}