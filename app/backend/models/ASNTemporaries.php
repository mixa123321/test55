<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\helpers\Validator;
use app\core\entities\ASNTemporary;
//use app\core\helpers\View;

class ASNTemporaries extends \app\core\models\ASNTemporaries
{
    public static function validate($temp)
    {
        $rules = [
            'ASN_id' => [
                'val' => $temp->getASNId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'SKU_id' => [
                'val' => $temp->getSKUId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'quantity' => [
                'val' => $temp->getQuantity(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors)) {
            return $errors;
        }
        return $validator->getErrors();
    }

    public static function addPutaway($asn, $sku, $quan)
    {
        $data = [
            'ASN_id' => $asn,
            'SKU_id' => $sku,
            'quantity' => $quan,
        ];
        $keys = array_keys($data);
        $temp = new ASNTemporary($data);
        $temp->save($keys);
    }

    public static function clear($asn)
    {
        (new SafeMysql())->execute("DELETE FROM ASN_temporary WHERE ASN_id = :ASN_id", [
            'ASN_id' => $asn,
        ]);
    }
}
