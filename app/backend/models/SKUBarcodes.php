<?php

namespace app\backend\models;

use app\core\entities\SKU;
use app\core\helpers\Validator;

class SKUBarcodes extends \app\core\models\SKUBarcodes
{
    public static function validate(SKU $SKU, $barcodes)
    {
        $validator = new Validator();
        if (empty($barcodes)) {
            $validator->addError('missing', 'Missing barcode(s)');
            return $validator->getErrors();
        }
        if (count($barcodes) !== count(array_unique($barcodes)))
            $validator->addError('not_unique', 'Barcodes are not unique');
        if ($not_unique_barcodes = self::getNotUniqueBarcodes($barcodes, (int)$SKU->getSupplierId(), (int)$SKU->getId())) {
            $show_barcodes = [];
            foreach ($not_unique_barcodes as $SKU_id => $barcode_lst)
                $show_barcodes[] = "#$SKU_id: " . implode(', ', $barcode_lst);
            $validator->addError('not_unique', "Barcodes (" . implode('; ', $show_barcodes) . ") are not unique for supplier");
        }
        $rules = [
            'barcode' => [
                'val' => '',
                'rules' => [
                    Validator::REQUIRED => true,
                    Validator::TYPE_STRING => [1, 255],
                    Validator::TYPE_BARCODE_128 => true
                ]
            ],
        ];
        $barcodes = array_unique($barcodes);
        foreach ($barcodes as $barcode) {
            $rules['barcode']['val'] = $barcode;
            $validator->setRules($rules);
            $validator->validate();
        }
        if (empty($SKU->getId()))
            return $validator->getErrors();
        $available_barcodes = self::getBySKUId($SKU->getId());
        foreach ($barcodes as $barcode) {
            if (in_array($barcode, $available_barcodes) && empty($SKU->getId()))
                $validator->addError('already', "Barcode '$barcode' is already in the WMS");
        }
        return $validator->getErrors();
    }
}