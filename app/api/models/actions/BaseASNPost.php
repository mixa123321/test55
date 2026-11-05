<?php

namespace app\api\models\actions;

use app\core\helpers\Validator;

abstract class BaseASNPost extends BaseAction
{
    public function getRequestFieldsConfig()
    {
        return [
            'ArrivalDate' => [
                'rules' => [Validator::TYPE_DATE => 1],
                'example' => '2019/07/20',
                'description' => 'Arrival date',
            ],
            'SKUs' => [
                'rules' => [Validator::TYPE_ARRAY => [1, 100], Validator::TYPE_OBJECT => 1],
            ],
        ];
    }
}
