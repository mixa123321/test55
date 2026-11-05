<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Charge;
use app\core\helpers\status\ChargeStatus;
use app\core\helpers\status\ChargeType;

class Charges extends \app\core\models\Charges
{
    public static function getStandardChargesBilling(array $sw_ids, array $types = [])
    {
        if (empty($sw_ids))
            return [];
        $where = '';
        $params = ['sw_ids' => $sw_ids];
        if (!empty($types)) {
            $where = "WHERE c.type IN (:types)";
            $params['types'] = $types;
        }
        $data = (new SafeMysql())->fetchAll("
            SELECT DISTINCT c.id, c.name, c.code, c.type, cc.supplier_warehouse_id, cc.status
            FROM charges c 
                LEFT JOIN charges_complex cc ON c.id = cc.charge_id AND cc.supplier_warehouse_id IN (:sw_ids)
            $where", $params);
        $charges_tmp = array_column($data, 'type', 'id');
        $charges = [];
        foreach ($charges_tmp as $id => $type) {
            if (empty($charges[$type]))
                $charges[$type] = [];
            $charges[$type][$id] = 0;
        }
        $charges_billing = array_combine($sw_ids, array_fill(0, count($sw_ids), $charges));
        foreach ($data as $datum) {
            if (empty($datum['supplier_warehouse_id']))
                continue;
            $charges_billing[$datum['supplier_warehouse_id']][$datum['type']][$datum['id']] = (int)$datum['status'];
        }
        return $charges_billing;
    }

    public static function getRecurringChargesBilling(array $sw_ids)
    {
        if (empty($sw_ids))
            return [];
        $statistic = (new SafeMysql())->fetchOne("SELECT SUM(status) active, COUNT(*) total FROM charges WHERE type = 0");
        $charges_billing = array_combine($sw_ids, array_fill(0, count($sw_ids), [
            'using' => 0,
            'active' => (int)$statistic['active'],
            'total' => (int)$statistic['total']
        ]));
        $params = ['sw_ids' => $sw_ids];
        $data = (new SafeMysql())->fetchAll("
            SELECT cr.supplier_warehouse_id, SUM(cr.status) `using`  
            FROM charges_recurring cr
                JOIN charges c ON cr.charge_id = c.id AND c.status 
            WHERE cr.supplier_warehouse_id IN (:sw_ids) AND cr.status
            GROUP BY cr.supplier_warehouse_id", $params);
        foreach ($data as $datum)
            $charges_billing[$datum['supplier_warehouse_id']]['using'] = (int)$datum['using'];
        return $charges_billing;
    }

    public static function getChargesComplex(int $sw_id, array $types = [])
    {
        if (empty($sw_id))
            return [];
        $where = '';
        $params = ['sw_id' => $sw_id];
        if (!empty($types)) {
            $where = "WHERE c.type IN (:types)";
            $params['types'] = $types;
        }
        return (new SafeMysql())->fetchAll("
            SELECT DISTINCT c.*, IF(cc.charge_id IS NULL, 0, cc.status) is_usage
            FROM charges c 
                LEFT JOIN charges_complex cc ON c.id = cc.charge_id AND cc.supplier_warehouse_id = :sw_id
            $where", $params);
    }

    public static function getChargesPrepare(int $sw_id, array $types)
    {
        $data = self::getChargesComplex($sw_id, $types);
        if (!$data)
            return [];
        $charges = [];
        foreach ($data as $datum) {
            if (empty($charges[$datum['type']]))
                $charges[$datum['type']] = [
                    'type_name' => ChargeType::get($datum['type']),
                    'charges' => [],
                    'is_usage' => 1
                ];
            $charges[$datum['type']]['is_usage'] &= $datum['is_usage'];
            $charges[$datum['type']]['charges'][$datum['id']] = [
                'name' => $datum['name'],
                'code' => $datum['code'],
                'status' => $datum['status'],
                'status_name' => ChargeStatus::get($datum['status']),
            ];
        }
        return $charges;
    }

    public static function getChargesRecurring(int $sw_id)
    {
        if (empty($sw_id))
            return [];
        $params = ['sw_id' => $sw_id];
        $data = (new SafeMysql())->fetchAll("
            SELECT c.*, IF(cr.charge_id IS NULL, 0, cr.status) is_usage
            FROM charges c 
                LEFT JOIN charges_recurring cr ON c.id = cr.charge_id AND cr.supplier_warehouse_id = :sw_id
            WHERE c.type = 0", $params);
        if (!$data)
            return [];
        $charges = [];
        foreach ($data as $datum)
            $charges[$datum['id']] = [
                'name' => $datum['name'],
                'code' => $datum['code'],
                'status' => $datum['status'],
                'is_usage' => $datum['is_usage'],
                'status_name' => ChargeStatus::get($datum['status']),
            ];
        return $charges;
    }

    public static function changeLink(int $sw_id, array &$charges)
    {
        $link_charges = [];
        foreach ($charges as $charge)
            if (in_array($charge['type'], array_keys(Charge::TYPE_EQUAL)))
                $link_charges[] = [
                    'id' => $charge['id'],
                    'code' => $charge['code'],
                    'type' => $charge['type'],
                    'type_new' => Charge::TYPE_EQUAL[$charge['type']]
                ];
        if (!$link_charges)
            return;
        $request = [];
        $params = ['sw_id' => $sw_id];
        $index = 0;
        foreach ($link_charges as $link_charge) {
            $request[] = "(c.code = :code_$index AND c.type = :type_$index)";
            $params["code_$index"] = $link_charge['code'];
            $params["type_$index"] = $link_charge['type_new'];
            $index++;
        }
        $data_new_ids = (new SafeMysql())->fetchAll("
            SELECT cc.id, c.code, c.type
            FROM charges c
                JOIN charges_complex cc ON c.id = cc.charge_id AND cc.supplier_warehouse_id = :sw_id
            WHERE " . implode(' OR ', $request), $params);
        $data = [];
        foreach ($data_new_ids as $data_new_id)
            $data["{$data_new_id['code']}-{$data_new_id['type']}"] = $data_new_id['id'];
        $changes = [];
        foreach ($link_charges as $link_charge)
            $changes[$link_charge['id']] = $data["{$link_charge['code']}-{$link_charge['type_new']}"];
        foreach ($charges as &$charge)
            if (!empty($changes[$charge['id']]))
                $charge['cc_id'] = (int)$changes[$charge['id']];
        unset($charge);
    }
}