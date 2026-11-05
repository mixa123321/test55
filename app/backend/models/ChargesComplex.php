<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Charge;
use app\core\entities\ChargeComplex;

class ChargesComplex extends \app\core\models\ChargesComplex
{
    public static function insertBySupplier(int $sw_id, array $charge_ids = [])
    {
        if (empty($sw_id))
            return;
        $charge_types = array_column(Charges::getAll(), 'type', 'id');
        if (!$charge_ids)
            $charge_ids = array_keys($charge_types);
        $type_charges = [];
        foreach ($charge_ids as $charge_id) {
            if (empty($type_charges[$charge_types[$charge_id]]))
                $type_charges[$charge_types[$charge_id]] = [];
            $type_charges[$charge_types[$charge_id]][] = $charge_id;
        }
        $type_complex = [];
        foreach (ChargeComplex::TYPE_CHARGES as $type => $types_charge)
            foreach ($type_charges as $type_charge => $charge)
                if (in_array($type_charge, $types_charge)) {
                    if (empty($type_complex[$type]))
                        $type_complex[$type] = [];
                    $type_complex[$type] = array_merge($type_complex[$type], $charge);
                }
        foreach ($type_complex as $type => $charges) {
            switch ($type) {
                case ChargeComplex::TYPE_SIMPLE:
                    self::insertBySupplierSimple($sw_id, $charges);
                    break;
                case ChargeComplex::TYPE_PACKAGING:
                    self::insertBySupplierPackaging($sw_id, $charges);
                    break;
            }
        }
    }

    private static function getIndex(array $line)
    {
        if (!isset($line['id']) || !isset($line['cc_type']) || !isset($line['type_id']))
            return null;
        return "{$line['id']}_{$line['cc_type']}_{$line['type_id']}";
    }

    public static function getEmptyChargesComplex(int $sw_id)
    {
        if (empty($sw_id))
            return [];
        $data = Charges::getAll();
        $packagings = SupplierWarehouses::getPackaging($sw_id);
        $charges = [];
        foreach ($data as $datum) {
            $line = $datum + [
                'cc_id' => null,
                'cc_type' => (int)ChargeComplex::getTypeCharge($datum['type']),
                'type_id' => 0,
                'type_name' => '',
                'cc_status' => ChargeComplex::STATUS_LOCK
            ];
            switch ($line['cc_type']) {
                case ChargeComplex::TYPE_SIMPLE:
                    $charges[self::getIndex($line)] = $line;
                    break;
                case ChargeComplex::TYPE_PACKAGING:
                    if (!$packagings) {
                        $line['cc_status'] = ChargeComplex::STATUS_DISABLE;
                        $charges[self::getIndex($line)] = $line;
                        break;
                    }
                    foreach ($packagings as $packaging) {
                        $line['type_id'] = (int)$packaging['packaging_id'];
                        $line['type_name'] = $packaging['name'];
                        $line['group_id'] = $packaging['id'];
                        $line['group_code'] = $packaging['code'];
                        $line['is_default'] = $packaging['is_default'];
                        $charges[self::getIndex($line)] = $line;
                    }
                    break;
            }
        }
        return $charges;
    }

    public static function insertAbsentChargesComplex(int $sw_id, array $charges)
    {
        $exist_charges = $exist_charges_type_id = [];
        foreach ($charges as $charge)
            if ($charge['cc_type'] == ChargeComplex::TYPE_PACKAGING && $charge['status'] == Charge::STATUS_ACTIVE) {
                if (!in_array($charge['id'], $exist_charges))
                    $exist_charges[] = (int)$charge['id'];
                if (!in_array($charge['type_id'], $exist_charges_type_id))
                    $exist_charges_type_id[] = $charge['type_id'];
            }
        $data = self::getPackaging($sw_id);
        $inserts = [];
        foreach ($exist_charges as $charge_id)
            if (!isset($data[$charge_id]))
                $inserts[$charge_id] = [
                    'type_ids' => $exist_charges_type_id,
                    'status' => ChargeComplex::STATUS_ACTIVE,
                ];
            else {
                $arr_diff = array_diff($exist_charges_type_id, explode(',', $data[$charge_id]['type_ids']));
                if ($arr_diff)
                    $inserts[$charge_id] = [
                        'type_ids' => array_values($arr_diff),
                        'status' => !$data[$charge_id]['statuses'] ? ChargeComplex::STATUS_LOCK : ChargeComplex::STATUS_ACTIVE
                    ];
            }
        if ($inserts)
            self::insertAbsentPackaging($sw_id, $inserts);
    }

    public static function fillChargesComplex(int $sw_id, array &$charges)
    {
        self::insertAbsentChargesComplex($sw_id, $charges);
        $charges_complex = self::getAll($sw_id);
        $active_packaging_ids = [];
        $lock_packaging_ids = array_column(array_filter($charges, function ($item) {
            return $item['cc_status'] == ChargeComplex::STATUS_DISABLE;
        }), 'id');
        foreach ($charges_complex as $charge_complex) {
            $charge_complex['status'] = (int)$charge_complex['status'];
            switch ((int)$charge_complex['type']) {
                case ChargeComplex::TYPE_SIMPLE:
                    $index = self::getIndex([
                        'id' => $charge_complex['charge_id'],
                        'cc_type' => $charge_complex['type'],
                        'type_id' => $charge_complex['type_id'],
                    ]);
                    if (!empty($charges[$index])) {
                        $charges[$index]['cc_id'] = (int)$charge_complex['id'];
                        $charges[$index]['cc_status'] = $charge_complex['status'];
                    }
                    break;
                case ChargeComplex::TYPE_PACKAGING:
                    if ($charge_complex['status']) {
                        $pos = array_search($charge_complex['charge_id'], $lock_packaging_ids);
                        if ($pos !== false)
                            unset($lock_packaging_ids[$pos]);
                        $active_packaging_ids[$charge_complex['charge_id']][$charge_complex['type_id']] = $charge_complex['id'];
                    }
                    break;
            }
        }
        // ChargeComplex::TYPE_PACKAGING
        foreach ($active_packaging_ids as $charge_id => $type_ids) {
            $search = "{$charge_id}_1_";
            $index_0 = "{$search}0";
            $indexes = [];
            foreach ($charges as $index => $charge)
                if (strpos($index, $search) === 0)
                    $indexes[] = $index;
            if (in_array($index_0, $indexes))
                continue;
            foreach ($indexes as $index) {
                $type_id = substr($index, strlen($search));
                if (!empty($type_ids[$type_id]))
                    $charges[$index]['cc_id'] = (int)$type_ids[$type_id];
                $charges[$index]['cc_status'] = ChargeComplex::STATUS_ACTIVE;
            }
        }
        foreach ($lock_packaging_ids as $charge_id)
            foreach ($charges as $index => $charge)
                if (strpos($index, "{$charge_id}_1_") === 0 && $charge['cc_status'] === ChargeComplex::STATUS_DISABLE)
                    $charges[$index]['cc_status'] = ChargeComplex::STATUS_LOCK;
        $charges = array_filter($charges, function ($charge) {
            return $charge['cc_status'] !== ChargeComplex::STATUS_LOCK;
        });
    }

    public static function getByCode(int $sw_id, string $code, $packaging = false)
    {
        $charge = Charges::getByCode($code);
        if (!$charge)
            return null;
        $type = ChargeComplex::getTypeCharge($charge['type']);
        if ($type == ChargeComplex::TYPE_PACKAGING) {
            if ($packaging === false)
                return null;
            foreach (SupplierWarehouses::getPackaging($sw_id) as $packaging_data)
                if ($packaging_data['packaging_id'] == $packaging || $packaging_data['name'] == $packaging) {
                    $packaging = $packaging_data['packaging_id'];
                    break;
                }
            if ($packaging === false)
                return null;
        }
        $packaging = (int)$packaging;
        if ($packaging)
            self::deleteEmptyPackaging($sw_id, [$charge['id']]);
        $mysql = new SafeMysql();
        $mysql->execute("
            INSERT IGNORE INTO charges_complex
            SET charge_id = :charge_id, supplier_warehouse_id = :sw_id, type = :type, type_id = :packaging", [
            'charge_id' => $charge['id'],
            'sw_id' => $sw_id,
            'type' => $type,
            'packaging' => $packaging,
        ]);
        return $mysql->fetchScalar("
            SELECT id FROM charges_complex
            WHERE charge_id = :charge_id AND supplier_warehouse_id = :sw_id AND type = :type AND type_id = :packaging", [
            'charge_id' => $charge['id'],
            'sw_id' => $sw_id,
            'type' => $type,
            'packaging' => $packaging,
        ]);
    }

    public static function getCodeById(int $id): string
    {
        if (!$id)
            return '';
        return (string)(new SafeMysql())->fetchScalar("
            SELECT CONCAT(ch.code, IF(c.id IS NULL, '', CONCAT(' (', c.name, ')')))
            FROM charges_complex cc
                JOIN charges ch ON cc.charge_id = ch.id
                LEFT JOIN consumables c ON cc.type_id = c.id
            WHERE cc.id = :id", [
            'id' => $id,
        ]);
    }
}