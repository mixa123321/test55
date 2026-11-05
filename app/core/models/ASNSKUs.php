<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ASNSKU;

class ASNSKUs
{
    public static function getByASNId(int $id, $print = false)
    {
        $print_field = '';
        if ($print)
            $print_field = ', IF(IFNULL(s.width, 0)*IFNULL(s.height, 0)*IFNULL(s.depth, 0)*IFNULL(s.weight, 0)=0,"N","Y") dims, "N" PF';
        $sql = "SELECT ask.SKU_id id, s.name, ask.quantity, ask.batch_number,  b.expired,  s.photo$print_field 
                FROM ASN_SKUs ask
                    LEFT JOIN batches b ON b.number = ask.batch_number AND ask.SKU_id = b.SKU_id
                    LEFT JOIN SKUs s ON ask.SKU_id = s.id 
                WHERE ask.ASN_id = :id
                ORDER BY id";
        $result = $print
            ? (new SafeMysql())->fetchAssocWithKey($sql, ['id' => $id], 'id')
            : (new SafeMysql())->fetchAll($sql, ['id' => $id]);
        SKUBarcodes::addBarcodesToArray($result);
        return $result;
    }

    public static function getByRASNId($id)
    {
        $SKUs = (new SafeMysql())->fetchAll(
        "
            SELECT ask.*, s.name
            FROM ASN_SKUs ask
                LEFT JOIN SKUs s ON ask.SKU_id = s.id 
            WHERE ask.ASN_id = :id
            ORDER BY id
        ",
        ['id' => (int)$id]
    );
        SKUBarcodes::addBarcodesToArray($SKUs, 'SKU_id');
        $batches = [];
        foreach ($SKUs as $key => $SKU) {
            $ASN_SKUs = new ASNSKU($SKU);
            if ($ASN_SKUs->getBatchId())
                $batches[] = $ASN_SKUs->getBatchId();
            $SKUs[$key]['SKU'] = $ASN_SKUs;
            unset($SKUs[$key]['ASN_id'], $SKUs[$key]['accepted_quantity'], $SKUs[$key]['batch_number']);
        }
        if (!empty($batches))
            $batches = Batches::getBatchesByIds($batches);
        foreach ($SKUs as $key => $SKU)
            $SKUs[$key]['batch'] = $batches[$SKU['SKU']->getBatchId()] ?? null;
        return $SKUs;
    }

    public static function getManifestRASN($id)
    {
        $SKUs = (new SafeMysql())->fetchAll("
            SELECT ask.*, s.name,  s.photo, IF(IFNULL(s.width, 0)*IFNULL(s.height, 0)*IFNULL(s.depth, 0)*IFNULL(s.weight, 0)=0, \"N\", \"Y\") dims, \"N\" PF 
            FROM ASN_SKUs ask
                LEFT JOIN SKUs s ON ask.SKU_id = s.id 
            WHERE ask.ASN_id = :id
            ORDER BY id", ['id' => (int)$id]);
        SKUBarcodes::addBarcodesToArray($SKUs, 'SKU_id');
        $batches = [];
        foreach ($SKUs as $key => $SKU) {
            $ASN_SKUs = new ASNSKU($SKU);
            if ($ASN_SKUs->getBatchId())
                $batches[] = $ASN_SKUs->getBatchId();
            $SKUs[$key]['SKU'] = $ASN_SKUs;
            $SKUs[$key]['id'] = $SKU['SKU_id'];
            unset($SKUs[$key]['ASN_id'], $SKUs[$key]['SKU_id'], $SKUs[$key]['accepted_quantity']);
        }
        if (!empty($batches))
            $batches = Batches::getBatchesByIds($batches);
        foreach ($SKUs as $key => $SKU) {
            $SKUs[$key]['batch_number'] = $batches[$SKU['SKU']->getBatchId()]['number'] ?? null;
            $SKUs[$key]['expired'] = $batches[$SKU['SKU']->getBatchId()]['expired'] ?? null;
            unset($SKUs[$key]['SKU']);
        }
        $res = [];
        foreach ($SKUs as $SKU)
            $res[$SKU['id']] = $SKU;
        return $res;
    }

    public static function getAcceptedSKUsByAsn($asn_id)
    {
        return (new SafeMysql())->fetchAll("SELECT SKU_id, accepted_quantity as quantity
                    FROM ASN_SKUs WHERE ASN_id = :asn_id", ['asn_id' => (int)$asn_id]);
    }

    public static function isExist($ASN_id, $SKU_id)
    {
        $sql = "SELECT COUNT(*)
                FROM ASN_SKUs WHERE SKU_id = :SKU_id AND ASN_id = :ASN_id";
        return (bool)(new SafeMysql())->fetchArrayOneField($sql, ['SKU_id' => (int)$SKU_id, 'ASN_id' => (int)$ASN_id])[0];
    }

    public static function getByAsnIdAndSkuId($ASN_id, $SKU_id)
    {
        $sql = "SELECT ask.*, b.id, b.number batch_number, b.expired batch_expired
                FROM ASN_SKUs ask 
                    LEFT JOIN batches b ON ask.batch_number = b.number AND ask.SKU_id = b.SKU_id
                WHERE ask.SKU_id = :SKU_id AND ask.ASN_id = :ASN_id";
        return (new SafeMysql())->fetchOne($sql, ['ASN_id' => (int)$ASN_id, 'SKU_id' => (int)$SKU_id]);
    }

    public static function getQuantity($ASN_id, $SKU_id)
    {
        $sql = "SELECT s.name, quantity FROM ASN_SKUs ask, SKUs s WHERE SKU_id=s.id AND ASN_id = :ASN_id AND SKU_id = :SKU_id ORDER BY SKU_id";
        $res = (new SafeMysql())->fetchOne($sql, ['ASN_id' => (int)$ASN_id, 'SKU_id' => (int)$SKU_id]);

        return $res ?: ['name' => '', 'quantity' => 0];
    }

    public static function getQuantityByASN($ASN_id)
    {
        $sql = 'SELECT SUM(quantity) as summa FROM ASN_SKUs WHERE ASN_id = :ASN_id';
        $res = (new SafeMysql())->fetchOne($sql, ['ASN_id' => (int)$ASN_id]);
        return (int)($res['summa'] ?? 0);
    }
}
