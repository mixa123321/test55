<?php

namespace app\backend\models;

use app\core\entities\SubstitutionCondition;
use app\core\helpers\Validator;

class SubstitutionConditions extends \app\core\models\SubstitutionConditions
{
    public static function validate(SubstitutionCondition $condition)
    {
        $rules = [
            'substitution_id' => [
                'val' => $condition->getSubstitutionId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        if ($condition->isQuantity() && !$condition->getQuantityFrom() && !$condition->getQuantityTo())
            $validator->addError('empty_from_and_to', 'From and To cannot be empty');
        if ($condition->getQuantityFrom() && $condition->getQuantityTo() && $condition->getQuantityFrom() > $condition->getQuantityTo())
            $validator->addError('from_more_to', 'From cannot be more than To');
        return $validator->getErrors();
    }
}