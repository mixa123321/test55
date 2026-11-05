<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ChargeHistory;
use app\core\helpers\Arr;
use app\core\statical\WithDate;
use app\core\traits\ChargesBilling as TraitChargesBilling;

class ChargesRecurringBilling extends \app\core\models\ChargesRecurringBilling
{
    use TraitChargesBilling;

    const HEADER_SCV = ['Service Code', 'Quantity', 'Charge', 'Valid From', 'Valid To'];

    public static function getRecurringBilling(int $sw_id, string $rates_valid)
    {
        $charges = (new SafeMysql())->fetchAssocWithKey("
            SELECT cr.id, cr.charge_id, c.name, c.code, 0 cb_id, 0 quantity, 0 value, '" . ChargesBilling::BILLING_VALID_START . "' valid_from, null valid_to
            FROM charges_recurring cr 
                JOIN charges c ON cr.charge_id = c.id AND c.status
            WHERE cr.supplier_warehouse_id = :sw_id AND cr.status", ['sw_id' => $sw_id], 'charge_id');
        if (!$charges)
            return [];
        $ids = array_values(array_filter(array_unique(array_column($charges, 'id'))));
        $charges_billing = [];
        if ($ids) {
            $params = [
                'ids' => $ids,
                'rates_valid' => $rates_valid
            ];
            $charges_billing = (new SafeMysql())->fetchAssocWithKey("
                SELECT *
                FROM charges_recurring_billing
                WHERE valid_from <= :rates_valid AND (valid_to IS NULL OR (valid_to IS NOT NULL AND valid_to >= :rates_valid)) AND
                      charge_recurring_id IN (:ids)", $params, 'charge_recurring_id');
        }
        if (!$charges_billing)
            return $charges;
        foreach ($charges as &$charge) {
            if (empty($charges_billing[$charge['id']]))
                continue;
            $charge_billing = $charges_billing[$charge['id']];
            $charge['cb_id'] = $charge_billing['id'];
            $charge['quantity'] = $charge_billing['quantity'];
            $charge['value'] = $charge_billing['value'];
            $charge['valid_from'] = $charge_billing['valid_from'];
            $charge['valid_to'] = $charge_billing['valid_to'];
        } unset($charge);
        return $charges;
    }

    public static function createDataExport(int $sw_id, string $rates_valid)
    {
        $filename = SupplierWarehouses::getSageId($sw_id) . '_recurring_' . date('Ymd-His', strtotime($rates_valid)) . '.csv';
        $data = ChargesRecurringBilling::getRecurringBilling($sw_id, $rates_valid);
        $charges_billing = Arr::maps($data, 'id', ['code', 'quantity', 'value', 'valid_from', 'valid_to']);
        return [$filename, self::HEADER_SCV, $charges_billing];
    }

    public static function getChargesRecurring(array $recurring, string $rates_valid)
    {
        if (empty($recurring))
            return [];
        $params = [
            'recurring' => $recurring,
            'rates_valid' => $rates_valid,
        ];
        return (new SafeMysql())->fetchAssocWithKey("
            SELECT crb.*, cr.charge_id
            FROM charges_recurring_billing crb
                JOIN charges_recurring cr ON crb.charge_recurring_id = cr.id
            WHERE crb.valid_from <= :rates_valid AND (crb.valid_to IS NULL OR (crb.valid_to IS NOT NULL AND crb.valid_to >= :rates_valid)) AND
                  crb.charge_recurring_id IN (:recurring)", $params, 'charge_recurring_id');
    }

    public static function getChargeById(int $id, int $cr_id)
    {
        if (!$id)
            return [
                'quantity' => 0,
                'value' => 0,
                'valid_from' => ChargesBilling::BILLING_VALID_START,
                'valid_to' => '',
            ];
        return (new SafeMysql())->fetchOne("
            SELECT quantity, value, valid_from, valid_to
            FROM charges_recurring_billing
            WHERE id = :id AND charge_recurring_id = :cr_id", [
            'id' => $id,
            'cr_id' => $cr_id,
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
            if ($data[$index]['quantity'] == $range['quantity'] && $data[$index]['value'] == $range['value']) {
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
                    ['field' => 'quantity', 'old' => (int)$range['quantity']],
                    ['field' => 'charge', 'old' => (float)$range['value']],
                    ['field' => 'valid_from', 'old' => $range['valid_from']],
                    ['field' => 'valid_to', 'old' => $range['valid_to']]
                ];
                self::delete($range['id']);
                ChargesHistory::add($sw_id, $user_id, ChargeHistory::EVENT_DELETE_RECURRING, $changes);
            }
        if ($insert)
            foreach ($insert as $range) {
                $changes = [
                    ['field' => 'quantity', 'old' => (int)$range['quantity']],
                    ['field' => 'charge', 'new' => (float)$range['value']],
                    ['field' => 'valid_from', 'new' => $range['valid_from']],
                    ['field' => 'valid_to', 'new' => $range['valid_to']]
                ];
                self::add($range);
                ChargesHistory::add($sw_id, $user_id, ChargeHistory::EVENT_ADD_RECURRING, $changes);
            }
        if ($update)
            foreach ($update as $range) {
                $data = ChargesRecurringBilling::getChargeById($range['id'], $range['charge_recurring_id']);
                $changes = [];
                if ((int)$data['quantity'] != (int)$range['quantity'])
                    $changes[] = ['field' => 'quantity', 'old' => (int)$data['quantity'], 'new' => (int)$range['quantity']];
                if ((float)$data['value'] != (float)$range['value'])
                    $changes[] = ['field' => 'charge', 'old' => (float)$data['value'], 'new' => (float)$range['value']];
                if ($data['valid_from'] != $range['valid_from'])
                    $changes[] = ['field' => 'valid_from', 'old' => $data['valid_from'], 'new' => $range['valid_from']];
                if ($data['valid_to'] != $range['valid_to'])
                    $changes[] = ['field' => 'valid_to', 'old' => $data['valid_to'], 'new' => $range['valid_to']];
                self::update($range);
                ChargesHistory::add($sw_id, $user_id, ChargeHistory::EVENT_UPDATE_RECURRING, $changes);
            }
        self::reduction($id);
        $mysql->commit();
        return ['update' => true];
    }

    public static function deleteRange(int $id)
    {
        if (!$id)
            return false;
        $charge_recurring = self::getById($id);
        if (!$charge_recurring)
            return false;
        self::change($id, 0, 0);
        self::reduction($charge_recurring->getChargeRecurringId());
        return true;
    }
}