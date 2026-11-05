<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ChargeHistory;
use app\core\statical\WithDate;
use app\core\traits\ChargesBilling as TraitChargesBilling;

class ChargesBilling extends \app\core\models\ChargesBilling
{
    use TraitChargesBilling;

    const BILLING_VALID_START = '2025-01-01 00:00:00';
    const HEADER_SCV = ['Service Code', 'Charge', 'Valid From', 'Valid To'];
    const BILLING_IMPORT = BILLING_STORAGE . '_import' . DIRECTORY_SEPARATOR;

    public static function getChargesBilling(int $sw_id, string $rates_valid)
    {
        $charges = ChargesComplex::getEmptyChargesComplex($sw_id);
        if (!$charges)
            return [];
        ChargesComplex::fillChargesComplex($sw_id, $charges);
        Charges::changeLink($sw_id, $charges);
        $ids = array_values(array_filter(array_unique(array_column($charges, 'cc_id'))));
        $charges_billing = [];
        if ($ids) {
            $params = [
                'ids' => $ids,
                'rates_valid' => $rates_valid
            ];
            $charges_billing = (new SafeMysql())->fetchAssocWithKey("
                SELECT *
                FROM charges_billing
                WHERE valid_from <= :rates_valid AND (valid_to IS NULL OR (valid_to IS NOT NULL AND valid_to >= :rates_valid)) AND
                      charge_complex_id IN (:ids)", $params, 'charge_complex_id');
        }
        foreach ($charges as &$charge) {
            if (empty($charge['cc_id']) || empty($charges_billing[$charge['cc_id']])) {
                $charge += [
                    'cb_id' => null,
                    'value' => 0,
                    'valid_from' => self::BILLING_VALID_START,
                    'valid_to' => null
                ];
                continue;
            }
            $charge_billing = $charges_billing[$charge['cc_id']];
            $charge += ['cb_id' => $charge_billing['id']] + array_slice($charge_billing, 2);
        } unset($charge);
        return $charges;
    }

    public static function createDataExport(int $sw_id, string $rates_valid)
    {
        $filename = SupplierWarehouses::getSageId($sw_id) . '_' . date('Ymd-His', strtotime($rates_valid)) . '.csv';
        $header = array_merge(array_slice(self::HEADER_SCV, 0, 1), ['Packaging'], array_slice(self::HEADER_SCV, 1));
        $data = self::getChargesBilling($sw_id, $rates_valid);
        $charges_billing = array_map(function ($item) {
            return [
                'code' => $item['code'],
                'type_name' => $item['type_name'],
                'value' => $item['value'],
                'valid_from' => $item['valid_from'],
                'valid_to' => $item['valid_to']
            ];
        }, $data);
        return [$filename, $header, $charges_billing];
    }

    public static function getChargeByType(int $sw_id, string $date, int $type)
    {
        return (new SafeMysql())->fetchAll("
            SELECT cb.charge_complex_id, cb.value, cc.type cc_type, cc.type_id, c.code
            FROM charges_billing cb
                JOIN charges_complex cc ON cb.charge_complex_id = cc.id AND cc.supplier_warehouse_id = :sw_id AND cc.status
                JOIN charges c ON cc.charge_id = c.id AND c.type = :type AND c.status
            WHERE valid_from <= :date AND (valid_to IS NULL OR (valid_to IS NOT NULL AND valid_to >= :date))", [
            'sw_id' => $sw_id,
            'type' => $type,
            'date' => $date,
        ]);
    }

    public static function getChargeById(int $id, int $cc_id)
    {
        if (!$id)
            return [
                'value' => 0,
                'valid_from' => self::BILLING_VALID_START,
                'valid_to' => '',
            ];
        return (new SafeMysql())->fetchOne("
            SELECT value, valid_from, valid_to
            FROM charges_billing
            WHERE id = :id AND charge_complex_id = :cc_id", [
            'id' => $id,
            'cc_id' => $cc_id,
        ]);
    }

    private static function reductionData(array $data): array
    {
        $index = -1;
        $del_id = 0;
        $update = [];
        foreach ($data as $key => $range) {
            if ($index == -1) {
                $index = $key;
                continue;
            }
            if ($data[$index]['value'] == $range['value']) {
                $end_date = WithDate::dateToInt($data[$index]['valid_to']);
                $start_date = WithDate::dateToInt($range['valid_from']);
                if ($end_date + 1 == $start_date) {
                    $data[$index]['valid_to'] = $range['valid_to'];
                    $del_id = $data[$key]['id'];
                    $update = $data[$index];
                    unset($data[$key]);
                    break;
                }
            }
            $index = $key;
        }
        return [$del_id, $update, array_values($data)];
    }

    public static function updateRange(int $sw_id, int $id, int $user_id, array $delete, array $insert, array $update)
    {
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        if ($delete)
            foreach ($delete as $range) {
                $changes = [
                    ['field' => 'charge', 'old' => (float)$range['value']],
                    ['field' => 'valid_from', 'old' => $range['valid_from']],
                    ['field' => 'valid_to', 'old' => $range['valid_to']]
                ];
                self::delete($range['id']);
                ChargesHistory::add($sw_id, $user_id, ChargeHistory::EVENT_DELETE, $changes);
            }
        if ($insert)
            foreach ($insert as $range) {
                $changes = [
                    ['field' => 'charge', 'new' => (float)$range['value']],
                    ['field' => 'valid_from', 'new' => $range['valid_from']],
                    ['field' => 'valid_to', 'new' => $range['valid_to']]
                ];
                self::add($range);
                ChargesHistory::add($sw_id, $user_id, ChargeHistory::EVENT_ADD, $changes);
            }
        if ($update)
            foreach ($update as $range) {
                $data = ChargesBilling::getChargeById($range['id'], $range['charge_complex_id']);
                $changes = [];
                if ((float)$data['value'] != (float)$range['value'])
                    $changes[] = ['field' => 'charge', 'old' => (float)$data['value'], 'new' => (float)$range['value']];
                if ($data['valid_from'] != $range['valid_from'])
                    $changes[] = ['field' => 'valid_from', 'old' => $data['valid_from'], 'new' => $range['valid_from']];
                if ($data['valid_to'] != $range['valid_to'])
                    $changes[] = ['field' => 'valid_to', 'old' => $data['valid_to'], 'new' => $range['valid_to']];
                self::update($range);
                ChargesHistory::add($sw_id, $user_id, ChargeHistory::EVENT_UPDATE, $changes);
            }
        self::reduction($id);
        $mysql->commit();
        return ['update' => true];
    }

    public static function deleteRange(int $id)
    {
        if (!$id)
            return false;
        $charge_billing = self::getById($id);
        if (!$charge_billing)
            return false;
        self::change($id, 0);
        self::reduction($charge_billing->getChargeComplexId());
        return true;
    }
}