<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ChargeHistory;

class ChargesHistory extends \app\core\models\ChargesHistory
{
    const TYPE_HISTORY_CHARGES = [
        Billing::CHARGE_TAB => [
            ChargeHistory::EVENT_IMPORT,
            ChargeHistory::EVENT_CHANGE,
            ChargeHistory::EVENT_ADD,
            ChargeHistory::EVENT_DELETE,
            ChargeHistory::EVENT_UPDATE
        ],
        Billing::RECURRING_TAB => [
            ChargeHistory::EVENT_IMPORT_RECURRING,
            ChargeHistory::EVENT_CHANGE_RECURRING,
            ChargeHistory::EVENT_ADD_RECURRING,
            ChargeHistory::EVENT_DELETE_RECURRING,
            ChargeHistory::EVENT_UPDATE_RECURRING
        ],
    ];

    public static function getLog(int $sw_id, array $type_history)
    {
        if (!$type_history)
            return [];
        $params = [
            'sw_id' => $sw_id,
            'type_history' => $type_history,
        ];
        $data = (new SafeMysql())->fetchAll("
            SELECT ch.id, u.login, ch.event, ch.event_date, ch.changes
            FROM charges_history ch
                LEFT JOIN users u ON ch.user_id = u.id
            WHERE ch.supplier_warehouse_id = :sw_id AND ch.event IN (:type_history)", $params);
        foreach ($data as &$item)
            $item['changes'] = json_decode($item['changes'], true);
        unset($item);
        return $data;
    }
}
