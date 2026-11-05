<?php

namespace app\api\models\actions;

use app\core\helpers\Validator;

abstract class BaseSKUPost extends BaseAction
{
    public function getRequestFieldsConfig()
    {
        return [
            'Name' => [
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 100]],
                'example' => 'My SKU',
                'description' => 'Name of SKU',
            ],
            'Barcode' => [
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 25], Validator::TYPE_BARCODE_128 => []],
                'example' => 'BARCODEEX',
                'description' => 'Barcode of SKU',
            ],
            'GS1' => [
                'rules' => [Validator::TYPE_INT => [0, 1]],
                'example' => '0',
                'description' => 'GS1 support',
            ],
            'CostValue' => [
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_DOUBLE => [0, PHP_FLOAT_MAX]],
                'example' => '1.00',
                'description' => 'Cost of SKU',
            ],
            'CostCurrency' => [
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 5]],
                'example' => 'GBP',
                'description' => 'Cost currency of SKU',
            ],
            'MarketValue' => [
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_DOUBLE => [0, PHP_FLOAT_MAX]],
                'example' => '1.00',
                'description' => 'Market cost of SKU',
            ],
            'MarketCurrency' => [
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 5]],
                'example' => 'GBP',
                'description' => 'Market cost currency of SKU',
            ],
            'Width' => [
                'rules' => [Validator::TYPE_DOUBLE => [0, PHP_FLOAT_MAX]],
                'example' => '1.00',
                'description' => 'Width of SKU (cm)',
            ],
            'Height' => [
                'rules' => [Validator::TYPE_DOUBLE => [0, PHP_FLOAT_MAX]],
                'example' => '1.00',
                'description' => 'Height of SKU (cm)',
            ],
            'Length' => [
                'rules' => [Validator::TYPE_DOUBLE => [0, PHP_FLOAT_MAX]],
                'example' => '1.00',
                'description' => 'Length of SKU (cm)',
            ],
            'Depth' => [
                'rules' => [Validator::TYPE_DOUBLE => [0, PHP_FLOAT_MAX]],
                'example' => '1.00',
                'description' => 'Length of SKU (cm) - old version',
            ],
            'Weight' => [
                'rules' => [Validator::TYPE_DOUBLE => [0, PHP_FLOAT_MAX]],
                'example' => '1.00',
                'description' => 'Weight of SKU (kg)',
            ],
            'HSCode' => [
                'rules' => [Validator::TYPE_STRING => [0, 20]],
                'example' => '000000',
                'description' => 'HS code of SKU',
            ],
            'Season' => [
                'rules' => [Validator::TYPE_STRING => [0, 100]],
                'example' => 'Winter',
                'description' => 'Season',
            ],
            'CustomerReference' => [
                'rules' => [Validator::TYPE_STRING => [0, 255]],
                'example' => 'My reference',
                'description' => 'Customer reference of SKU',
            ],
            'CustomerReference2' => [
                'rules' => [Validator::TYPE_STRING => [0, 255]],
                'example' => 'My reference 2',
                'description' => 'Customer reference 2 of SKU',
            ],
            'Photo' => [
                'rules' => [Validator::TYPE_STRING => [0, 255]],
                'example' => 'localhost/image.jpg',
                'description' => 'Image of SKU',
            ],
        ];
    }
}
