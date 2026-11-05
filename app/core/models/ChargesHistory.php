<?php

namespace app\core\models;

use app\core\entities\ChargeHistory;

class ChargesHistory
{
    public static function add(int $sw_id, int $user_id, string $event, array $changes = []): int
    {
        $history = [
            'supplier_warehouse_id' => $sw_id,
            'user_id' => $user_id,
            'event' => $event
        ];
        if ($changes)
            $history['changes'] = $changes;
        $charge_history = new ChargeHistory($history);
        $charge_history->save(array_keys($history));
        return $charge_history->getId();
    }
}
