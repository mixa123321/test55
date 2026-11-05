<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;

class MovementRequestHistories extends \app\core\models\MovementRequestHistories
{
    public static function getByRequestId(int $request_id)
    {
        return (new SafeMysql())->fetchAll("
            SELECT u.login, mrh.event, mrh.event_date, JSON_EXTRACT(mrh.additional_info, '$.quantity') quantity
            FROM movement_request_history mrh
                JOIN users u ON mrh.user_id = u.id 
            WHERE request_id = :request_id", ['request_id' => $request_id]);
    }
}
