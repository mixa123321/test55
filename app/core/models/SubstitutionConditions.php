<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\SubstitutionCondition;

class SubstitutionConditions
{
    public static function getById($substitution_id): ?SubstitutionCondition
    {
        $data = (new SafeMysql())->fetchOne("
            SELECT * 
            FROM substitution_conditions
            WHERE substitution_id = :substitution_id", ['substitution_id' => (int)$substitution_id]);
        if (empty($data))
            return null;
        return new SubstitutionCondition($data);
    }

    public static function delete($substitution_id)
    {
        $conditions = self::getById($substitution_id);
        if (empty($conditions))
            return;
        (new SafeMysql())->execute('DELETE FROM substitution_conditions WHERE substitution_id = :substitution_id', ['substitution_id' => (int)$substitution_id]);
    }

    public static function changeStatus(int $substitution_id): ?int {
        $conditions = self::getById($substitution_id);
        if (empty($conditions)) {
            return null;
        }
        $newStatus = (int)!$conditions->getStatus();
        (new SafeMysql())->execute('UPDATE substitution_conditions SET status = :status WHERE substitution_id = :substitution_id', [
            'status' => $newStatus,
            'substitution_id' => $substitution_id,
        ]);
        return $newStatus;
    }

    public static function checkQuantity(SubstitutionCondition $conditions, $quantity)
    {
        if (!$conditions->isQuantity())
            return true;
        if (!$conditions->getQuantityFrom() && !$conditions->getQuantityTo())
            return false;
        $in_interval = true;
        if ($conditions->getQuantityFrom())
            $in_interval = $quantity >= $conditions->getQuantityFrom();
        if ($conditions->getQuantityTo())
            $in_interval = ($quantity <= $conditions->getQuantityTo()) && $in_interval;
        return $in_interval;
    }

    public static function checkConditions($substitution_id, $quantity)
    {
        $conditions = self::getById($substitution_id);
        if (empty($conditions))
            return true;
        // quantity
        return self::checkQuantity($conditions, $quantity);
    }
}