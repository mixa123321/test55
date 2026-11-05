<?php

namespace app\backend\models;

use app\core\entities\Dimensioner;
use app\core\helpers\Validator;

class Dimensioners extends \app\core\models\Dimensioners
{
    public static function validate(Dimensioner $dimensioner)
    {
        $rules = [
            'name' => [
                'val' => $dimensioner->getName(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [2, 50]]
            ],
            'unit_id' => [
                'val' => $dimensioner->getUnitId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'branch_id' => [
                'val' => $dimensioner->getBranchId(),
                'rules' => [Validator::TYPE_INT => [0, PHP_INT_MAX]]
            ],
            'supplier_id' => [
                'val' => $dimensioner->getSupplierId(),
                'rules' => [Validator::TYPE_INT => [0, PHP_INT_MAX]]
            ],
            'warehouse_id' => [
                'val' => $dimensioner->getWarehouseId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'state' => [
                'val' => $dimensioner->getState(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT_SET => [Dimensioner::LOCKED, Dimensioner::ACTIVE]]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        $dims_exist = self::getByNameOrUnitId($dimensioner->getWarehouseId(), $dimensioner->getName(), $dimensioner->getUnitId());
        if (!empty($dims_exist))
            if (empty($dimensioner->getId()) ||
                (!empty($dimensioner->getId()) && (count($dims_exist) > 1 || $dims_exist[0]['id'] != $dimensioner->getId())))
                $validator->addError("not_unique", "This name or unit ID is already used", 'Name | UnitID');
        return $validator->getErrors();
    }
}
