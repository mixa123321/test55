<?php

namespace app\backend\models;

use app\core\entities\SFTPExport;
use app\core\helpers\Validator;

class SFTPExports extends \app\core\models\SFTPExports
{
    /**
     * @param $sftp SFTPExport
     * @return array
     */
    public static function validate($sftp)
    {
        $rules = [
            'supplier_id' => [
                'val' => $sftp->getSupplierId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'login' => [
                'val' => $sftp->getLogin(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [5, 32]]
            ],
            'password' => [
                'val' => $sftp->getPassword(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [10, 32]]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        if (empty($sftp->getId()) && !empty(SFTPExports::getBySupplierId($sftp->getSupplierId())))
            $validator->addError("sftp_export_not_unique", 'SFTP export is already exists');
        return $validator->getErrors();
    }
}
