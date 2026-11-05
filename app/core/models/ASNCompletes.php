<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ASNComplete;

class ASNCompletes
{
    public static function getSKUs($ASN_id)
    {
        $sql = "SELECT barcode, SUM(quantity) as summa FROM ASN_complete WHERE ASN_id = :ASN_id GROUP BY barcode";
        return (new SafeMysql())->fetchAssocWithKey($sql, ['ASN_id' => $ASN_id], 'barcode');
    }

    public static function getComplete($ASN_id, $barcode, $loc_id = '')
    {
        $sql = "SELECT * FROM ASN_complete WHERE ASN_id = :ASN_id AND barcode = :barcode";
        $params = ['ASN_id' => $ASN_id, 'barcode' => $barcode];
        
        if (!empty($loc_id)) {
            $sql .= " AND location_id = :location_id";
            $params['location_id'] = $loc_id;
        }
        
        $res = (new SafeMysql())->fetchOne($sql, $params);
        if ($res)
            return new ASNComplete($res);
        return null;
    }

    public static function getSKUsLocations($ASN_id)
    {
        $sql = "SELECT s.id, s.name, ac.barcode, ac.quantity, s.photo, ac.location_id
                FROM ASN_complete ac
                    JOIN ASNs a ON a.id = ac.ASN_id
                    JOIN SKU_barcodes sb ON sb.barcode = ac.barcode
                    JOIN SKUs s ON s.id = sb.SKU_id AND s.supplier_id = a.supplier_id
                WHERE ac.ASN_id = :ASN_id AND ac.quantity <> 0 AND ac.location_id IS NOT NULL";
        return (new SafeMysql())->fetchAssocWithKey($sql, ['ASN_id' => $ASN_id], 'barcode');
    }

    public static function clear($asn)
    {
        (new SafeMysql())->execute("DELETE FROM ASN_complete WHERE ASN_id = :ASN_id", ['ASN_id' => (int)$asn]);
    }
}
