<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\helpers\Filter;

class ASNSKUs extends \app\core\models\ASNSKUs
{
    public static function getSKUs($ASN_id)
    {
        $sql = "SELECT SKU_id, quantity, accepted_quantity FROM ASN_SKUs WHERE ASN_id = :ASN_id";
        return (new SafeMysql())->fetchAssocWithKey($sql, ['ASN_id' => $ASN_id], 'SKU_id');
    }

    public static function getSKUsByBarcode($ASN_id, $barcode, $filter_type = Filter::TYPE__EQUALS)
    {
        $barcode = Filter::getFilterValue($barcode, $filter_type);
        $barcodes = SKUBarcodes::searchBarcode($barcode, $filter_type);
        if (empty($barcodes))
            return [];
        $sql = "SELECT SKU_id, quantity, accepted_quantity, s.name
                FROM ASN_SKUs
                JOIN SKUs s ON ASN_SKUs.SKU_id = s.id
                WHERE ASN_id = :ASN_id AND s.id IN (:barcode_ids)";
        $result = (new SafeMysql())->fetchAssocWithKey($sql, [
            'ASN_id' => $ASN_id,
            'barcode_ids' => array_keys($barcodes),
        ], 'SKU_id');
        foreach ($result as $SKU_id => &$SKU)
            $SKU['barcode'] = $barcodes[$SKU_id] ?? '';
        return $result;
    }
}
