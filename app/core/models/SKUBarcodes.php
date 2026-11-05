<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\helpers\Filter;

class SKUBarcodes
{
    public static function getBarcodesSQL(string $SKU_id, $separator = ', ')
    {
        $separator = addslashes($separator);
        return "SELECT GROUP_CONCAT(barcode SEPARATOR '$separator') FROM SKU_barcodes WHERE SKU_id = $SKU_id";
    }

    public static function getSKUIdsByBarcode(string $barcode, $filter_type = Filter::TYPE__EQUALS)
    {
        $operator = Filter::getFilterOperator($filter_type);
        $sql = "SELECT DISTINCT SKU_id FROM SKU_barcodes WHERE barcode $operator :barcode ORDER BY SKU_id";
        $items = (new SafeMysql())->fetchAll($sql, ['barcode' => Filter::getFilterValue($barcode, $filter_type)]);
        $result = [];
        foreach ($items as $item)
            $result[] = (int)$item['SKU_id'];
        return $result;
    }

    public static function getSKUIdsByBarcodes(array $barcodes): array
    {
        if (!$barcodes)
            return [];
        $sql = "SELECT SKU_id FROM SKU_barcodes WHERE barcode IN (:barcodes)";
        $items = (new SafeMysql())->fetchAll($sql, ['barcodes' => $barcodes]);
        $result = [];
        foreach ($items as $item)
            $result[(int)$item['SKU_id']] = (int)$item['SKU_id'];
        return $result;
    }

    public static function searchBarcode($barcode, $filter_type = Filter::TYPE__EQUALS)
    {
        $operator = Filter::getFilterOperator($filter_type);
        $sql = "SELECT DISTINCT SKU_id, barcode FROM SKU_barcodes WHERE barcode $operator :barcode ORDER BY SKU_id, id asc";
        $items = (new SafeMysql())->fetchAll($sql, ['barcode' => Filter::getFilterValue($barcode, $filter_type)]);
        $result = [];
        foreach ($items as $item)
            $result[(int)$item['SKU_id']] = $item['barcode'];
        return $result;
    }

    public static function getBarcodesBySKUids(array $SKU_ids): array
    {
        if (!$SKU_ids)
            return [];
        $sql = "SELECT SKU_id, barcode FROM SKU_barcodes WHERE SKU_id IN (:SKU_ids) ORDER BY id asc";
        $items = (new SafeMysql())->fetchAll($sql, ['SKU_ids' => array_map('intval', $SKU_ids)]);
        $result = [];
        foreach ($items as $item)
            $result[(int)$item['SKU_id']][] = $item['barcode'];
        return $result;
    }

    public static function getBarcodesBySKUIdString(int $SKU_id): string
    {
        return implode(', ', self::getBarcodesBySKUId($SKU_id));
    }

    public static function getOneBySKUId(int $SKU_id)
    {
        return self::getBySKUId($SKU_id)[0] ?? null;
    }

    public static function getBarcodesBySKUId(int $SKU_id): array
    {
        $sql = "SELECT barcode FROM SKU_barcodes WHERE SKU_id = :SKU_id ORDER BY id asc";
        $items = (new SafeMysql())->fetchAll($sql, ['SKU_id' => $SKU_id]);
        $result = [];
        foreach ($items as $item)
            $result[] = $item['barcode'];
        return $result;
    }

    public static function addBarcodesToArray(array &$records_raw, string $key = 'id', string $new_key = 'barcodes', string $transform_barcode = 'barcode')
    {
        $ids = array_column($records_raw, $key);
        $barcodes = self::getBarcodesBySKUids($ids);
        foreach ($records_raw as &$record) {
            $record[$new_key] = $barcodes[$record[$key]] ?? [];
            if ($transform_barcode)
                $record[$transform_barcode] = implode(', ', $record[$new_key]);
        }
        unset($record);
    }

    public static function getNotUniqueBarcodes(array $barcodes, int $supplier_id, ?int $SKU_id = null): array
    {
        $result = [];
        foreach (self::getNotUniqueBarcodesClear($barcodes, $supplier_id, $SKU_id) as $item)
            $result[(int)$item['SKU_id']][] = $item['barcode'];
        return $result;
    }

    public static function getNotUniqueOnlyBarcodes(array $barcodes, int $supplier_id)
    {
        $result = [];
        foreach (self::getNotUniqueBarcodesClear($barcodes, $supplier_id) as $item)
            $result[$item['barcode']] = $item['barcode'];
        return $result;
    }

    private static function getNotUniqueBarcodesClear(array $barcodes, int $supplier_id, ?int $SKU_id = null): array
    {
        if (!$barcodes)
            return [];
        $barcodes = array_unique($barcodes);
        $params = ['barcodes' => $barcodes, 'supplier_id' => $supplier_id];
        $where_SKU_id = '';
        if ($SKU_id) {
            $where_SKU_id = "AND s.id <> :SKU_id";
            $params['SKU_id'] = $SKU_id;
        }
        $sql = "
        SELECT sb.SKU_id, sb.barcode
        FROM SKU_barcodes sb
            JOIN SKUs s ON sb.SKU_id = s.id AND s.supplier_id = :supplier_id
        WHERE sb.barcode IN (:barcodes) $where_SKU_id ORDER BY SKU_id, barcode";
        return (new SafeMysql())->fetchAll($sql, $params);
    }

    public static function getPrimaryBySKUIdSQL(string $SKU_id): string
    {
        return "SELECT barcode FROM SKU_barcodes WHERE SKU_id = $SKU_id ORDER BY id ASC LIMIT 1";
    }

    public static function getBySKUId(int $SKU_id): array
    {
        return (new SafeMysql())->fetchArrayOneField(
            "SELECT barcode FROM SKU_barcodes WHERE SKU_id = :SKU_id ORDER BY id asc",
            ['SKU_id' => $SKU_id]);
    }

    public static function add(int $SKU_id, array $barcodes): void
    {
        if (!$barcodes)
            return;
        $params = $values = [];
        foreach ($barcodes as $key => $barcode) {
            $values[] = "(:SKU_id_$key, :barcode_$key)";
            $params["SKU_id_$key"] = $SKU_id;
            $params["barcode_$key"] = $barcode;
        }
        (new SafeMysql())->execute("INSERT IGNORE INTO SKU_barcodes (SKU_id, barcode) VALUES " . implode(", ", $values), $params);
    }

    public static function delete(int $SKU_id): void
    {
        (new SafeMysql())->execute("DELETE FROM SKU_barcodes WHERE SKU_id = :SKU_id", ['SKU_id' => $SKU_id]);
    }
}