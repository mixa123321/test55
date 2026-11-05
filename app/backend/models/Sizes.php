<?php

namespace app\backend\models;

use app\core\entities\Size;
use app\core\helpers\Validator;

class Sizes extends \app\core\models\Sizes
{
    public static function validate(Size $size, $authUser)
    {
        $rules = [
            'name' => [
                'val' => $size->getName(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [2, 50]]
            ],
            'width' => [
                'val' => $size->getWidth(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_DOUBLE => [0.01, 100000]]
            ],
            'height' => [
                'val' => $size->getHeight(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_DOUBLE => [0.01, 100000]]
            ],
            'depth' => [
                'val' => $size->getDepth(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_DOUBLE => [0.01, 100000]]
            ],
            'warehouse_id' => [
                'val' => $size->getWarehouseId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors)) {
            return $errors;
        }
        $size_by_name = Sizes::getByName($size->getWarehouseId(), $size->getName());
        if ($size_by_name && $size_by_name->getId() != $size->getId()) {
            $validator->addError("not_unique", "This name is already used.", 'name');
        }
        return $validator->getErrors();
    }

    public static function checkDiff($size, $old_size)
    {
        $diff_size = array_udiff_assoc($size->toArray(), $old_size->toArray(), function ($item1, $item2) {
            if (is_array($item1) && is_array($item2))
                return (int)!empty(array_diff_assoc($item1, $item2));
            if (is_array($item1) || is_array($item2))
                return 1;
            return (int)($item1 != $item2);
        });
        return $diff_size;
    }

    public static function createMinimumSizes($warehouse_id)
    {
        $id_unlimited = self::createSize($warehouse_id, [
            'name' => 'UNLIMITEDSIZE',
            'width' => 100000,
            'height' => 100000,
            'depth' => 100000,
        ]);
        $id_cart = self::createSize($warehouse_id, [
            'name' => 'CART_SIZE',
            'width' => 50,
            'height' => 92,
            'depth' => 29,
        ]);
        $id_OB = self::createSize($warehouse_id, [
            'name' => 'OB_SIZE',
            'width' => 36.7,
            'height' => 16.3,
            'depth' => 56.7,
        ]);
        return [$id_unlimited, $id_cart, $id_OB];
    }
}
