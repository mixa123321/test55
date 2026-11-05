<?php

namespace app\backend\models;

use app\core\entities\SubstitutionCondition;
use app\core\entities\SubstitutionHistory;

class SubstitutionsHistories extends \app\core\models\SubstitutionsHistories
{
    private static $fields = ['substitution_id', 'event', 'addition', 'user_id'];

    private static function init($substitution_id, $user_id): SubstitutionHistory
    {
        return new SubstitutionHistory([
            'substitution_id' => (int)$substitution_id,
            'user_id' => (int)$user_id,
        ]);
    }

    private static function insert($substitution_id, $data, $event, $user_id)
    {
        $history = self::init($substitution_id, $user_id);
        $history->setEvent($event);
        $history->setAddition(json_encode($data));
        $history->save(self::$fields);
        return $history->getId();
    }

    public static function add($substitution_id, $SKUs, $user_id)
    {
        self::insert($substitution_id, ['SKUs' => $SKUs], SubstitutionHistory::EVENT_ADD, $user_id);
    }

    public static function update($substitution_id, $SKUs, $user_id)
    {
        self::insert($substitution_id, ['SKUs' => $SKUs], SubstitutionHistory::EVENT_UPDATE, $user_id);
    }

    public static function delete($substitution_id, $SKUs, $user_id)
    {
        self::insert($substitution_id, ['SKUs' => $SKUs], SubstitutionHistory::EVENT_DELETE, $user_id);
    }

    public static function addOrder($order_id, $substitution_id, $SKUs, $user_id)
    {
        return self::insert($substitution_id, ['order_id' => $order_id, 'SKUs' => $SKUs], SubstitutionHistory::EVENT_ORDER, $user_id);
    }

    public static function addConditions(SubstitutionCondition $conditions, $user_id)
    {
        $data = $conditions->toArray();
        array_shift($data);
        self::insert($conditions->getSubstitutionId(), $data, SubstitutionHistory::EVENT_ADD_COND, $user_id);
    }

    public static function updateConditions($conditions, $user_id)
    {
        $substitution_id = array_shift($conditions);
        self::insert($substitution_id, $conditions, SubstitutionHistory::EVENT_UPDATE_COND, $user_id);
    }
}
