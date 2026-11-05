<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Charge;

class InvoiceBillingCharges extends \app\core\models\InvoiceBillingCharges
{
    const CHARGE_GROUP = 'GROUP';

    private static function createGroups(array $data)
    {
        if (!$data)
            return [];
        $groups = [];
        foreach ($data as $item)
            switch ($item['type']) {
                case Charge::TYPE_PACKAGING:
                case Charge::TYPE_CULLING_PACKAGING:
                case Charge::TYPE_BUNDLING_PACKAGING:
                    $group_id = (int)$item['group_id'];
                    if (!$group_id)
                        break;
                    if (empty($groups[$item['type']]))
                        $groups[$item['type']] = [];
                    if (empty($groups[$item['type']][$group_id]))
                        $groups[$item['type']][$group_id] = [
                            'code' => $item['code'],
                            'type_ids' => []
                        ];
                    $groups[$item['type']][$group_id]['type_ids'][(int)$item['type_id']] = $item['is_default'];
                    break;
            }
        return $groups;
    }

    private static function changeDataWithGroups(array &$data, array $groups)
    {
        // create data groups
        $data_groups = [];
        foreach ($groups as $type => $group)
            foreach ($group as $group_id => $info)
                $data_groups["{$type}_$group_id"] = [
                    'id' => self::CHARGE_GROUP,
                    'type' => $type,
                    'type_id' => $group_id,
                    'name' => $info['code'],
                    'quantity' => 0,
                    'value' => 0,
                    'price' => 0
                ];
        foreach ($data_groups as &$data_group) {
            $pos = array_search(1, $groups[$data_group['type']][$data_group['type_id']]['type_ids']);
            if ($pos === false)
                $pos = array_search(0, $groups[$data_group['type']][$data_group['type_id']]['type_ids']);
            $data_group['value'] = $pos;
        } unset($data_group);
        // fill data groups
        foreach ($data as $key => $item)
            switch ($item['type']) {
                case Charge::TYPE_PACKAGING:
                case Charge::TYPE_CULLING_PACKAGING:
                case Charge::TYPE_BUNDLING_PACKAGING:
                    if (isset($groups[$item['type']][$item['group_id']]['type_ids'][$item['type_id']])) {
                        $data_groups["{$item['type']}_{$item['group_id']}"]['quantity'] += $item['quantity'];
                        if ($item['type_id'] == $data_groups["{$item['type']}_{$item['group_id']}"]['value'])
                            $data_groups["{$item['type']}_{$item['group_id']}"]['value'] = $item['value'];
                        unset($data[$key]);
                    } else
                        unset($data[$key]['group_id'], $data[$key]['code'], $data[$key]['is_default']);

                    break;
            }
        foreach ($data_groups as &$data_group)
            $data_group['price'] = $data_group['quantity']*$data_group['value'];
        $data = array_values(array_merge($data, $data_groups));
    }

    public static function getCharges(int $invoice_id, string $symbol)
    {
        $data = (new SafeMysql())->fetchAll("SELECT ibc.id, ibc.type, ibc.type_id, IF(ibc.type = 0, ch.name, IF(ibc.type_id = 0, '', c.name)) name,
                   cg.id group_id, cg.code, cgp.is_default,
                   ibc.quantity, ROUND(ibc.value, 4) value, ROUND(ibc.quantity*ROUND(ibc.value, 4), 4) price
            FROM invoice_billing_charges ibc
                LEFT JOIN consumables c ON ibc.type_id = c.id
                LEFT JOIN charges ch ON ibc.type_id = ch.id
                LEFT JOIN charge_groups_packagings cgp ON ibc.type_id = cgp.packaging_id
                LEFT JOIN charge_groups cg ON cgp.charge_group_id = cg.id AND cg.status
            WHERE ibc.invoice_billing_supplier_id = :invoice_id
            ORDER BY ibc.type = 0, ibc.type", [
            'invoice_id' => $invoice_id,
        ]);
        if (!$data)
            return [];
        $groups = self::createGroups($data);
        if ($groups)
            self::changeDataWithGroups($data, $groups);
        foreach ($data as &$item) {
            if ($item['type']) {
                $name = Charge::TYPE_FULL_NAMES[$item['type']] ?? '';
                if ($item['id'] === self::CHARGE_GROUP)
                    $name .= " ({$item['id']})";
                $item['name'] = $name . ($item['type_id'] ? ": \"{$item['name']}\"" : '');
            }
            switch ($item['type']) {
                case Charge::TYPE_PICK:
                case Charge::TYPE_CULLING_PICK:
                case Charge::TYPE_BUNDLING_PICK:
                    $item['details'] = ChargeOrderPicks::getDetails($item['id'], $item['type'], $symbol);
                    break;
                case Charge::TYPE_BOOKING_IN:
                    $item['details'] = ChargeASNBookings::getDetails($item['id'], $symbol);
                    break;
            }
            unset($item['id']);
        } unset($item);
        $res = [];
        foreach ($data as $item) {
            if (empty($res[$item['type']]))
                $res[$item['type']] = [];
            $res[$item['type']][] = $item;
        }
        return $res;
    }
}